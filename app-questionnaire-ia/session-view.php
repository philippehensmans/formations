<?php
/**
 * Vue globale de session - Questionnaire IA
 * Affiche toutes les reponses de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isFormateur()) {
    header('Location: formateur.php');
    exit;
}

$appKey = 'app-questionnaire-ia';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse a cette session.");
}

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: formateur.php');
    exit;
}

// Initialiser les questions par defaut si necessaire
initDefaultQuestions($sessionId);

$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les questions
$questions = getQuestions($sessionId);

// Recuperer les reponses
$sharedFilter = $showAll ? "" : "AND r.is_shared = 1";
$stmt = $db->prepare("SELECT r.*, q.label as question_label, q.type as question_type, q.options as question_options, q.ordre
                      FROM reponses r
                      JOIN questions q ON r.question_id = q.id
                      WHERE r.session_id = ? $sharedFilter
                      ORDER BY r.user_id, q.ordre");
$stmt->execute([$sessionId]);
$allReponses = $stmt->fetchAll();

// Enrichir avec infos utilisateur
foreach ($allReponses as &$r) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$r['user_id']]);
    $userInfo = $userStmt->fetch();
    $r['user_prenom'] = $userInfo['prenom'] ?? '';
    $r['user_nom'] = $userInfo['nom'] ?? '';
    $r['user_organisation'] = $userInfo['organisation'] ?? '';
}

// Grouper par participant
$byParticipant = [];
foreach ($allReponses as $r) {
    $uid = $r['user_id'];
    if (!isset($byParticipant[$uid])) {
        $byParticipant[$uid] = [
            'user' => ['prenom' => $r['user_prenom'], 'nom' => $r['user_nom'], 'organisation' => $r['user_organisation']],
            'reponses' => [],
            'is_shared' => $r['is_shared']
        ];
    }
    $byParticipant[$uid]['reponses'][$r['question_id']] = $r;
}

// Stats pour les questions radio
$radioStats = [];
foreach ($questions as $q) {
    if ($q['type'] === 'radio') {
        $options = json_decode($q['options'], true) ?: [];
        $radioStats[$q['id']] = ['options' => $options, 'counts' => array_fill_keys($options, 0), 'total' => 0];
    }
}
foreach ($allReponses as $r) {
    if (isset($radioStats[$r['question_id']]) && !empty($r['contenu'])) {
        if (isset($radioStats[$r['question_id']]['counts'][$r['contenu']])) {
            $radioStats[$r['question_id']]['counts'][$r['contenu']]++;
        }
        $radioStats[$r['question_id']]['total']++;
    }
}

$participantsCount = count($byParticipant);

// Compter les reponses
$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as total FROM reponses WHERE session_id = ?");
$stmt->execute([$sessionId]);
$totalRespondents = $stmt->fetch()['total'];
$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as shared FROM reponses WHERE session_id = ? AND is_shared = 1");
$stmt->execute([$sessionId]);
$sharedRespondents = $stmt->fetch()['shared'];
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionnaire IA - Vue Session - <?= h($session['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>📋</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } body { background: white !important; } }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-50 to-blue-100 min-h-screen">
    <header class="bg-gradient-to-r from-sky-600 to-blue-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">📋 Questionnaire IA</h1>
                    <p class="text-sky-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">Partages seulement (<?= $sharedRespondents ?>)</a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">Tous les participants (<?= $totalRespondents ?>)</a>
                    <?php endif; ?>
                    <?php if (isSuperAdmin()): ?>
                    <button onclick="generateAISummary()" id="aiSummaryBtn" class="bg-amber-500 hover:bg-amber-400 px-3 py-1 rounded text-sm flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        Synthese IA
                    </button>
                    <?php endif; ?>
                    <a href="questions-edit.php?id=<?= $sessionId ?>" class="bg-sky-500 hover:bg-sky-400 px-3 py-1 rounded text-sm">Modifier questions</a>
                    <?= renderLanguageSelector('bg-sky-500 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <button onclick="window.print()" class="bg-sky-500 hover:bg-sky-400 px-3 py-1 rounded text-sm">Imprimer</button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-sky-500 hover:bg-sky-400 px-3 py-1 rounded text-sm">Retour</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Sujet -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <h2 class="text-lg font-bold text-gray-800 mb-2">Contexte de la formation</h2>
                    <?php if (!empty($session['sujet'])): ?>
                    <p class="text-gray-700" id="sujetDisplay"><?= nl2br(h($session['sujet'])) ?></p>
                    <?php else: ?>
                    <p class="text-gray-400 italic" id="sujetDisplay">Aucun sujet defini</p>
                    <?php endif; ?>
                </div>
                <button onclick="openSujetModal()" class="no-print ml-4 px-3 py-1 bg-sky-100 text-sky-700 hover:bg-sky-200 rounded text-sm">Modifier</button>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-sky-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= count($questions) ?></div>
                <div class="text-gray-500 text-sm">Questions</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?= count($allReponses) ?></div>
                <div class="text-gray-500 text-sm">Reponses</div>
            </div>
        </div>

        <!-- Stats des questions radio (barres) -->
        <?php foreach ($questions as $q):
            if ($q['type'] !== 'radio' || !isset($radioStats[$q['id']])) continue;
            $rStat = $radioStats[$q['id']];
            if ($rStat['total'] === 0) continue;
        ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h3 class="font-bold text-gray-800 mb-4"><?= h($q['label']) ?></h3>
            <div class="space-y-3">
                <?php foreach ($rStat['options'] as $opt):
                    $count = $rStat['counts'][$opt] ?? 0;
                    $pct = round(($count / $rStat['total']) * 100);
                ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-700"><?= h($opt) ?></span>
                        <span class="text-gray-500 font-medium"><?= $count ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-5">
                        <div class="bg-sky-500 h-5 rounded-full transition-all duration-500 flex items-center justify-end pr-2"
                             style="width: <?= max($pct, 2) ?>%">
                            <?php if ($pct > 10): ?>
                            <span class="text-white text-xs font-bold"><?= $pct ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-2"><?= $rStat['total'] ?> repondants</p>
        </div>
        <?php endforeach; ?>

        <!-- Affichage -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 no-print">
            <div class="flex gap-4 items-center">
                <div class="font-medium text-gray-700">Affichage:</div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="participant" checked onchange="setDisplayMode('participant')">
                    <span class="text-sm">Par participant</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="question" onchange="setDisplayMode('question')">
                    <span class="text-sm">Par question</span>
                </label>
            </div>
        </div>

        <!-- Vue par participant -->
        <div id="participantView" class="space-y-6">
            <?php foreach ($byParticipant as $uid => $data): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-sky-500 to-blue-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['user']['organisation'])): ?>
                            <span class="text-sky-200 text-sm ml-2">(<?= h($data['user']['organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <span class="bg-white/30 px-2 py-1 rounded text-sm"><?= count($data['reponses']) ?>/<?= count($questions) ?></span>
                    </div>
                </div>
                <div class="p-4 space-y-4">
                    <?php foreach ($questions as $q):
                        $r = $data['reponses'][$q['id']] ?? null;
                    ?>
                    <div class="border-b border-gray-100 pb-3 last:border-0">
                        <div class="text-sm font-medium text-gray-500 mb-1"><?= h($q['label']) ?></div>
                        <?php if ($r && !empty($r['contenu'])): ?>
                        <?php if ($q['type'] === 'radio'): ?>
                        <span class="inline-block px-3 py-1 bg-sky-100 text-sky-700 rounded-lg text-sm font-medium"><?= h($r['contenu']) ?></span>
                        <?php else: ?>
                        <p class="text-gray-800 text-sm"><?= nl2br(h($r['contenu'])) ?></p>
                        <?php endif; ?>
                        <?php else: ?>
                        <p class="text-gray-300 text-sm italic">Pas de reponse</p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($byParticipant)): ?>
            <div class="text-center py-12 text-gray-400">
                <p class="text-4xl mb-3">📭</p>
                <p class="text-lg">Aucune reponse partagee pour le moment</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue par question -->
        <div id="questionView" class="hidden space-y-6">
            <?php foreach ($questions as $q): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-sky-50 border-b-2 border-sky-200 p-4">
                    <h3 class="font-bold text-sky-800"><?= h($q['label']) ?></h3>
                    <span class="text-xs text-sky-500"><?= ucfirst($q['type']) ?><?= $q['obligatoire'] ? ' - Obligatoire' : '' ?></span>
                </div>
                <div class="p-4 space-y-3">
                    <?php
                    $hasAnswers = false;
                    foreach ($byParticipant as $uid => $data):
                        $r = $data['reponses'][$q['id']] ?? null;
                        if (!$r || empty($r['contenu'])) continue;
                        $hasAnswers = true;
                    ?>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-600 whitespace-nowrap"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                        <span class="text-gray-300">|</span>
                        <?php if ($q['type'] === 'radio'): ?>
                        <span class="px-3 py-0.5 bg-sky-100 text-sky-700 rounded text-sm"><?= h($r['contenu']) ?></span>
                        <?php else: ?>
                        <p class="text-gray-700 text-sm"><?= nl2br(h($r['contenu'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$hasAnswers): ?>
                    <p class="text-gray-300 text-center py-4 text-sm">Aucune reponse</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Modal synthese IA -->
    <?php if (isSuperAdmin()): ?>
    <div id="aiModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 no-print">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-6 border-b">
                <div class="flex items-center gap-3">
                    <div class="bg-amber-100 p-2 rounded-lg">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Synthese IA - Questionnaire</h3>
                </div>
                <button onclick="closeAIModal()" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="aiContent" class="p-6 overflow-y-auto flex-1"></div>
            <div class="p-4 border-t flex justify-end gap-3">
                <button onclick="closeAIModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Fermer</button>
                <button onclick="printAISummary()" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Imprimer
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal sujet -->
    <div id="sujetModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 no-print">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Modifier le contexte</h3>
                <button onclick="closeSujetModal()" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <textarea id="sujetInput" rows="4"
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-transparent mb-4"
                      placeholder="Contexte de la formation..."><?= h($session['sujet'] ?? '') ?></textarea>
            <div class="flex justify-end gap-3">
                <button onclick="closeSujetModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Annuler</button>
                <button onclick="saveSujet()" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg">Enregistrer</button>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>

    <script>
        const sessionId = <?= $sessionId ?>;

        function setDisplayMode(mode) {
            document.getElementById('participantView').classList.toggle('hidden', mode !== 'participant');
            document.getElementById('questionView').classList.toggle('hidden', mode !== 'question');
        }

        function openSujetModal() { document.getElementById('sujetModal').classList.remove('hidden'); document.getElementById('sujetInput').focus(); }
        function closeSujetModal() { document.getElementById('sujetModal').classList.add('hidden'); }

        async function saveSujet() {
            const sujet = document.getElementById('sujetInput').value.trim();
            try {
                const response = await fetch('api/update_session.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_id: sessionId, sujet: sujet })
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('sujetDisplay').innerHTML = sujet ? sujet.replace(/\n/g, '<br>') : '<span class="text-gray-400 italic">Aucun sujet defini</span>';
                    closeSujetModal();
                } else { alert('Erreur: ' + (result.error || 'Erreur inconnue')); }
            } catch (e) { alert('Erreur de connexion'); }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { closeSujetModal(); closeAIModal(); }
        });

        <?php if (isSuperAdmin()): ?>
        let aiGenerating = false;

        async function generateAISummary() {
            if (aiGenerating) return;
            aiGenerating = true;
            const btn = document.getElementById('aiSummaryBtn');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Generation...';
            btn.disabled = true;

            document.getElementById('aiModal').classList.remove('hidden');
            document.getElementById('aiContent').innerHTML = `
                <div class="flex flex-col items-center justify-center py-16">
                    <svg class="w-12 h-12 text-amber-500 animate-spin mb-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <p class="text-gray-600 font-medium">Analyse des reponses en cours...</p>
                    <p class="text-gray-400 text-sm mt-2">Claude analyse les fiches de tous les participants</p>
                </div>`;

            try {
                const response = await fetch('api/ai-summary.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_id: sessionId })
                });
                const data = await response.json();
                if (!response.ok || data.error) throw new Error(data.error || 'Erreur lors de la generation');
                document.getElementById('aiContent').innerHTML = data.summary;
            } catch (e) {
                document.getElementById('aiContent').innerHTML = `
                    <div class="flex flex-col items-center justify-center py-16">
                        <svg class="w-12 h-12 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        <p class="text-red-600 font-medium">Erreur</p>
                        <p class="text-gray-500 text-sm mt-2">${e.message}</p>
                    </div>`;
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                aiGenerating = false;
            }
        }

        function closeAIModal() { document.getElementById('aiModal').classList.add('hidden'); }

        function printAISummary() {
            const content = document.getElementById('aiContent').innerHTML;
            const w = window.open('', '_blank');
            w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Synthese IA - <?= h($session['nom']) ?></title>
                <script src="https://cdn.tailwindcss.com"><\/script>
                <style>body{font-family:system-ui,sans-serif;padding:2rem;}</style></head>
                <body><h1 class="text-2xl font-bold mb-2">Synthese IA - Questionnaire IA</h1>
                <p class="text-gray-500 mb-6"><?= h($session['nom']) ?> | Session <?= $session['code'] ?> | ${new Date().toLocaleDateString('fr-FR')}</p>
                ${content}</body></html>`);
            w.document.close();
            setTimeout(() => { w.print(); }, 500);
        }
        <?php endif; ?>
    </script>
</body>
</html>
