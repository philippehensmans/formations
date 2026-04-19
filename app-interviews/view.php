<?php
require_once __DIR__ . '/config.php';
if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-interviews';
$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id
    FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) die('Participant non trouvé');
if (!canAccessSession($appKey, $participant['session_id'])) die('Accès refusé');

$stmt = $db->prepare("SELECT * FROM fiches WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$fiche = $stmt->fetch() ?: [];
$isSubmitted = ($fiche['is_submitted'] ?? 0) == 1;

try {
    $stmt = $db->prepare("SELECT * FROM lignes_reponse WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$participant['user_id'], $participant['session_id']]);
    $lignes = $stmt->fetch() ?: [];
} catch (Exception $e) { $lignes = []; }
$qrData = json_decode($lignes['qr_data'] ?? '[]', true) ?: [];
$elementsData = json_decode($lignes['elements_data'] ?? '[]', true) ?: [];
$lignesSubmitted = ($lignes['is_submitted'] ?? 0) == 1;
$hasLignes = !empty(array_filter($qrData, fn($r) => trim($r['question'] ?? '').trim($r['reponse'] ?? '') !== ''))
          || !empty(array_filter($elementsData, fn($r) => trim($r['situation'] ?? '').trim($r['formulation'] ?? '') !== ''));

try {
    $stmt = $db->prepare("SELECT * FROM communiques WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$participant['user_id'], $participant['session_id']]);
    $cp = $stmt->fetch() ?: [];
} catch (Exception $e) { $cp = []; }
$cpSubmitted = ($cp['is_submitted'] ?? 0) == 1;
$hasCp = !empty(array_filter([$cp['titre'] ?? '', $cp['chapeau'] ?? '', $cp['paragraphe1'] ?? ''], fn($v) => trim($v) !== ''));

function field($v) { return !empty(trim($v ?? '')) ? h($v) : '<em class="text-gray-400">Non renseigné</em>'; }
function badge($submitted, $has) {
    if ($submitted) return '<span class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs">Soumis</span>';
    if ($has)       return '<span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-xs">Brouillon</span>';
    return '<span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-xs">Vide</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche — <?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-rose-600 to-pink-600 text-white p-3 shadow-lg sticky top-0 z-50 no-print">
        <div class="max-w-4xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></span>
                <span class="text-rose-200 text-sm ml-2"><?= h($participant['session_nom']) ?> (<?= h($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="session-view.php?id=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto p-4">
        <div class="bg-gradient-to-r from-rose-600 to-pink-600 text-white rounded-xl p-6 mb-6 shadow">
            <h1 class="text-xl font-bold">Dossier de préparation à l'interview</h1>
            <p class="opacity-80 text-sm mt-1"><?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></p>
        </div>

        <!-- ============ FICHE INTERVIEWÉ·E ============ -->
        <div class="bg-gradient-to-r from-rose-500 to-pink-500 text-white rounded-xl p-5 mb-4 shadow flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold">📋 Fiche interviewé·e</h2>
                <p class="text-sm opacity-90 mt-0.5">Messages clés, anecdote et points sensibles</p>
            </div>
            <?= badge($isSubmitted, !empty(trim($fiche['sujet'] ?? '')) || !empty(trim($fiche['message1'] ?? ''))) ?>
        </div>

        <!-- Sujet -->
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h2 class="font-bold text-gray-800 mb-2 text-sm uppercase tracking-wide text-rose-700">Sujet / contexte</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= field($fiche['sujet'] ?? '') ?></p>
        </div>

        <!-- Messages clés -->
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h2 class="font-bold text-gray-800 mb-3 text-sm uppercase tracking-wide text-rose-700">Messages clés</h2>
            <div class="space-y-3">
                <?php foreach ([1, 2, 3] as $i): $val = $fiche['message' . $i] ?? ''; ?>
                <div class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-7 h-7 rounded-full bg-rose-100 text-rose-700 font-bold text-sm flex items-center justify-center"><?= $i ?></span>
                    <p class="text-gray-700 pt-0.5 whitespace-pre-wrap"><?= field($val) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Anecdote -->
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h2 class="font-bold text-gray-800 mb-2 text-sm uppercase tracking-wide text-rose-700">Anecdote / exemple concret</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= field($fiche['anecdote'] ?? '') ?></p>
        </div>

        <!-- À éviter -->
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h2 class="font-bold text-gray-800 mb-2 text-sm uppercase tracking-wide text-red-600">Ce qu'il ne faut PAS dire</h2>
            <p class="text-gray-700 whitespace-pre-wrap bg-red-50 p-3 rounded"><?= field($fiche['a_eviter'] ?? '') ?></p>
        </div>

        <!-- ============ LIGNES DE RÉPONSE ============ -->
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white rounded-xl p-5 mt-8 mb-4 shadow flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold">💬 Lignes de réponse</h2>
                <p class="text-sm opacity-90 mt-0.5">Q&R probables et éléments de langage</p>
            </div>
            <?= badge($lignesSubmitted, $hasLignes) ?>
        </div>

        <?php if ($hasLignes): ?>
        <?php $validQr = array_filter($qrData, fn($r) => trim($r['question'] ?? '').trim($r['reponse'] ?? '') !== ''); ?>
        <?php if (!empty($validQr)): ?>
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h3 class="font-bold text-gray-800 mb-3 text-sm uppercase tracking-wide text-indigo-700">Questions probables & réponses</h3>
            <div class="space-y-4">
                <?php foreach ($validQr as $i => $r): ?>
                <div class="border-l-4 border-indigo-300 pl-3">
                    <div class="font-semibold text-gray-800 text-sm mb-1">❓ <?= h($r['question'] ?: '—') ?></div>
                    <div class="text-gray-700 text-sm whitespace-pre-wrap pl-4"><?= h($r['reponse'] ?: '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php $validEl = array_filter($elementsData, fn($r) => trim($r['situation'] ?? '').trim($r['formulation'] ?? '') !== ''); ?>
        <?php if (!empty($validEl)): ?>
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h3 class="font-bold text-gray-800 mb-3 text-sm uppercase tracking-wide text-amber-700">Éléments de langage</h3>
            <div class="space-y-4">
                <?php foreach ($validEl as $r): ?>
                <div class="border-l-4 border-amber-300 pl-3">
                    <div class="font-semibold text-gray-800 text-sm mb-1">⚠️ <?= h($r['situation'] ?: '—') ?></div>
                    <div class="text-gray-700 text-sm whitespace-pre-wrap pl-4 bg-amber-50 p-2 rounded"><?= h($r['formulation'] ?: '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow p-5 mb-4 text-center text-gray-400 italic text-sm">Aucune ligne de réponse renseignée.</div>
        <?php endif; ?>

        <!-- ============ COMMUNIQUÉ DE PRESSE ============ -->
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl p-5 mt-8 mb-4 shadow flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold">📰 Communiqué de presse</h2>
                <p class="text-sm opacity-90 mt-0.5">Rédaction en pyramide inversée</p>
            </div>
            <div class="flex items-center gap-2">
                <?php if (isFormateur() && (!empty(trim($fiche['sujet'] ?? '')) || !empty(trim($fiche['message1'] ?? '')))): ?>
                <button id="aiGenBtn" onclick="generateCP()" class="no-print bg-white text-purple-700 hover:bg-purple-50 px-3 py-1.5 rounded text-sm font-medium">
                    ✨ Générer avec l'IA
                </button>
                <?php endif; ?>
                <?= badge($cpSubmitted, $hasCp) ?>
            </div>
        </div>

        <?php if (isFormateur()): ?>
        <!-- AI result (hidden by default) -->
        <div id="aiResult" class="hidden mb-6">
            <div class="bg-gradient-to-r from-violet-100 to-purple-100 border border-purple-300 rounded-xl p-4 mb-2 flex items-center justify-between flex-wrap gap-2 no-print">
                <div class="text-sm">
                    <span class="font-semibold text-purple-800">✨ Communiqué rédigé par l'IA</span>
                    <span class="text-purple-600 ml-2">— brouillon de travail, vérifiez avant diffusion</span>
                </div>
                <button onclick="document.getElementById('aiResult').classList.add('hidden')" class="text-purple-700 hover:text-purple-900 text-sm">Fermer</button>
            </div>
            <div class="bg-white rounded-xl shadow p-8 border-2 border-purple-200" style="font-family: Georgia, serif;">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Communiqué de presse — proposition IA</div>
                <h1 id="aiTitre" class="text-2xl font-bold text-gray-900 mb-4"></h1>
                <p id="aiChapeau" class="text-base font-medium text-gray-800 mb-4 pb-4 border-b whitespace-pre-wrap"></p>
                <div id="aiCorps" class="space-y-3 text-gray-700 text-sm"></div>
                <blockquote id="aiCitation" class="mt-6 pl-4 border-l-4 border-purple-300 italic text-gray-700 hidden"></blockquote>
                <div id="aiRaw" class="hidden mt-4 bg-amber-50 border border-amber-200 rounded p-3 text-xs text-amber-900 font-mono whitespace-pre-wrap"></div>
            </div>
        </div>

        <div id="aiError" class="hidden mb-6 bg-red-50 border border-red-300 rounded-xl p-4 text-red-800 text-sm no-print"></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow p-8 mb-4" style="font-family: Georgia, serif;">
            <?php if (!empty($cp)): ?>
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-4">Communiqué de presse</div>
            <div class="mb-5">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Titre</div>
                <div class="text-xl font-bold text-gray-900"><?= field($cp['titre'] ?? '') ?></div>
            </div>
            <div class="mb-5 pb-4 border-b">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Chapeau</div>
                <div class="text-gray-800 whitespace-pre-wrap"><?= field($cp['chapeau'] ?? '') ?></div>
            </div>
            <?php foreach ([1,2,3] as $i): ?>
            <div class="mb-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Paragraphe <?= $i ?></div>
                <div class="text-gray-700 text-sm whitespace-pre-wrap"><?= field($cp['paragraphe' . $i] ?? '') ?></div>
            </div>
            <?php endforeach; ?>
            <div class="mt-4 pt-4 border-t">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Citation</div>
                <?php if (!empty(trim($cp['citation'] ?? ''))): ?>
                <blockquote class="pl-4 border-l-4 border-purple-300 italic text-gray-700">
                    « <?= h($cp['citation']) ?> »
                    <?php if (!empty(trim($cp['citation_source'] ?? ''))): ?>
                    <div class="text-xs not-italic text-gray-500 mt-1">— <?= h($cp['citation_source']) ?></div>
                    <?php endif; ?>
                </blockquote>
                <?php else: ?><em class="text-gray-400">Non renseigné</em><?php endif; ?>
            </div>
            <?php $contactParts = array_filter([$cp['contact_nom'] ?? '', $cp['contact_titre'] ?? '', $cp['contact_email'] ?? '', $cp['contact_tel'] ?? ''], fn($v) => trim($v) !== ''); ?>
            <div class="mt-4 pt-4 border-t">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Contact presse</div>
                <?php if ($contactParts): ?>
                <div class="text-gray-700 text-sm"><?= h(implode(' · ', $contactParts)) ?></div>
                <?php else: ?><em class="text-gray-400">Non renseigné</em><?php endif; ?>
            </div>
            <?php else: ?>
            <div class="text-center text-gray-400 italic text-sm py-4">Le participant n'a pas encore rempli cet onglet.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isFormateur()): ?>
    <script>
    async function generateCP() {
        const btn = document.getElementById('aiGenBtn');
        const err = document.getElementById('aiError');
        const result = document.getElementById('aiResult');
        err.classList.add('hidden');
        const oldLabel = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Génération en cours...';
        try {
            const r = await fetch('api/generate-press-release.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({participant_id: <?= (int)$participantId ?>}),
            });
            const json = await r.json();
            if (!json.success) {
                err.textContent = json.error || 'Erreur inconnue';
                err.classList.remove('hidden');
                return;
            }
            if (json.parsed && json.communique) {
                const c = json.communique;
                document.getElementById('aiTitre').textContent = c.titre || '';
                document.getElementById('aiChapeau').textContent = c.chapeau || '';
                const corps = document.getElementById('aiCorps');
                corps.innerHTML = '';
                [c.paragraphe1, c.paragraphe2, c.paragraphe3].forEach(p => {
                    if ((p || '').trim()) { const el = document.createElement('p'); el.textContent = p; el.className = 'whitespace-pre-wrap'; corps.appendChild(el); }
                });
                const quote = document.getElementById('aiCitation');
                document.getElementById('aiRaw').classList.add('hidden');
                if ((c.citation || '').trim()) {
                    quote.classList.remove('hidden');
                    quote.innerHTML = '« ' + escapeHtml(c.citation) + ' »' + (c.citation_source ? '<div class="text-xs not-italic text-gray-500 mt-1">— ' + escapeHtml(c.citation_source) + '</div>' : '');
                } else quote.classList.add('hidden');
            } else {
                // Fallback: afficher le texte brut
                document.getElementById('aiTitre').textContent = 'Réponse IA';
                document.getElementById('aiChapeau').textContent = '';
                document.getElementById('aiCorps').innerHTML = '';
                document.getElementById('aiCitation').classList.add('hidden');
                const raw = document.getElementById('aiRaw');
                raw.textContent = json.raw || '';
                raw.classList.remove('hidden');
            }
            result.classList.remove('hidden');
            result.scrollIntoView({behavior: 'smooth'});
        } catch (e) {
            err.textContent = 'Erreur réseau : ' + e.message;
            err.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldLabel;
        }
    }
    function escapeHtml(s) { return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    </script>
    <?php endif; ?>
</body>
</html>
