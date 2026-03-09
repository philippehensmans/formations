<?php
/**
 * Vue globale de session - Prompt Jeunes
 * Affiche les exercices de prompting de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-prompt-jeunes';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse a cette session.");
}

// Recuperer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: formateur.php');
    exit;
}

// Option pour voir tous ou seulement les partages
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les travaux de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM travaux WHERE session_id = ? ORDER BY user_id, exercice_num ASC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM travaux WHERE session_id = ? AND is_shared = 1 ORDER BY user_id, exercice_num ASC");
    $stmt->execute([$sessionId]);
}
$allTravaux = $stmt->fetchAll();

// Enrichir avec les infos utilisateur et grouper par participant
$travauxByParticipant = [];
foreach ($allTravaux as &$t) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$t['user_id']]);
    $userInfo = $userStmt->fetch();
    $t['user_prenom'] = $userInfo['prenom'] ?? '';
    $t['user_nom'] = $userInfo['nom'] ?? '';
    $t['user_organisation'] = $userInfo['organisation'] ?? '';

    $uid = $t['user_id'];
    if (!isset($travauxByParticipant[$uid])) {
        $travauxByParticipant[$uid] = [
            'user' => [
                'prenom' => $t['user_prenom'],
                'nom' => $t['user_nom'],
                'organisation' => $t['user_organisation']
            ],
            'travaux' => []
        ];
    }
    $travauxByParticipant[$uid]['travaux'][] = $t;
}
unset($t);

// Statistiques
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM travaux WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM travaux WHERE session_id = ?");
$stmt->execute([$sessionId]);
$participantsCount = $stmt->fetch()['count'];

// Nombre d'exercices distincts
$stmt = $db->prepare("SELECT COUNT(DISTINCT exercice_num) as count FROM travaux WHERE session_id = ?");
$stmt->execute([$sessionId]);
$exercicesCount = $stmt->fetch()['count'];

// Moyenne de completion
$stmt = $db->prepare("SELECT AVG(completion_percent) as avg_completion FROM travaux WHERE session_id = ?");
$stmt->execute([$sessionId]);
$avgCompletion = round($stmt->fetch()['avg_completion'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prompt Jeunes - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        .travail-card { transition: all 0.3s ease; }
        .travail-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gradient-to-br from-rose-50 to-pink-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-rose-600 to-pink-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">🤖 Prompt Jeunes</h1>
                    <p class="text-rose-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Partages seulement (<?= $totalShared ?>)
                    </a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">
                        Voir tous (<?= $totalAll ?>)
                    </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="bg-rose-500 hover:bg-rose-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-rose-500 hover:bg-rose-400 px-3 py-1 rounded text-sm">
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($showAll): ?>
        <div class="bg-orange-100 border border-orange-300 rounded-xl p-4 mb-6">
            <p class="text-orange-800 text-sm">
                <strong>Mode: Tous les travaux</strong> - Vous voyez tous les travaux (<?= $totalAll ?>), y compris ceux non partages.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-rose-600"><?= count($allTravaux) ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Travaux (tous)' : 'Travaux partages' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-pink-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $exercicesCount ?></div>
                <div class="text-gray-500 text-sm">Exercices</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
        </div>

        <!-- Travaux par participant -->
        <div class="space-y-8">
            <?php foreach ($travauxByParticipant as $userId => $data): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- En-tete participant -->
                <div class="bg-gradient-to-r from-rose-500 to-pink-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['user']['organisation'])): ?>
                            <span class="text-rose-200 text-sm ml-2">(<?= h($data['user']['organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <span class="bg-white/30 px-3 py-1 rounded text-sm"><?= count($data['travaux']) ?> exercice(s)</span>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <?php foreach ($data['travaux'] as $travail): ?>
                    <div class="travail-card border rounded-lg overflow-hidden <?= (!$travail['is_shared'] && $showAll) ? 'opacity-75 border-orange-300' : 'border-rose-200' ?>">
                        <div class="bg-rose-50 px-4 py-2 flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <span class="bg-rose-500 text-white text-xs font-bold px-2 py-0.5 rounded">
                                    Exercice <?= (int)$travail['exercice_num'] ?>
                                </span>
                                <?php if (!empty($travail['organisation_nom'])): ?>
                                <span class="text-gray-600 text-sm"><?= h($travail['organisation_nom']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if (!$travail['is_shared'] && $showAll): ?>
                                <span class="bg-orange-200 text-orange-800 text-xs px-2 py-0.5 rounded">Non partage</span>
                                <?php endif; ?>
                                <span class="text-xs text-gray-500"><?= (int)($travail['completion_percent'] ?? 0) ?>%</span>
                            </div>
                        </div>

                        <div class="p-4 space-y-3">
                            <!-- Prompt initial -->
                            <?php if (!empty($travail['prompt_initial'])): ?>
                            <div>
                                <div class="text-xs font-semibold text-rose-500 uppercase mb-1">Prompt initial</div>
                                <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-700 font-mono whitespace-pre-wrap"><?= h($travail['prompt_initial']) ?></div>
                            </div>
                            <?php endif; ?>

                            <!-- Resultat initial -->
                            <?php if (!empty($travail['resultat_initial'])): ?>
                            <div>
                                <div class="text-xs font-semibold text-pink-500 uppercase mb-1">Resultat</div>
                                <div class="bg-pink-50 rounded-lg p-3 text-sm text-gray-700"><?= nl2br(h($travail['resultat_initial'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($allTravaux)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <div class="text-6xl mb-4">🤖</div>
                <p class="text-gray-500 text-lg">Aucun travail <?= $showAll ? '' : 'partage' ?> pour cette session.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
