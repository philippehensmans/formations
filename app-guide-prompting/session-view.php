<?php
/**
 * Vue globale de session - Guide de Prompting
 * Affiche tous les guides de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-guide-prompting';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse.");
}

// Recuperer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: formateur.php');
    exit;
}

// Option pour voir tous les guides ou seulement les partages
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les guides de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM guides WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM guides WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$allGuides = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allGuides as &$g) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$g['user_id']]);
    $userInfo = $userStmt->fetch();
    $g['user_prenom'] = $userInfo['prenom'] ?? '';
    $g['user_nom'] = $userInfo['nom'] ?? '';
    $g['user_organisation'] = $userInfo['organisation'] ?? '';
    $g['tasks_arr'] = json_decode($g['tasks'] ?? '[]', true) ?: [];
    $g['experimentations_arr'] = json_decode($g['experimentations'] ?? '[]', true) ?: [];
}
unset($g);

// Statistiques
$totalGuides = count($allGuides);

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM guides WHERE session_id = ?");
$stmt->execute([$sessionId]);
$totalParticipants = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM guides WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$avgCompletion = $totalGuides > 0 ? round(array_sum(array_column($allGuides, 'completion_percent')) / $totalGuides) : 0;

$stepLabels = [
    1 => 'Identification',
    2 => 'Selection',
    3 => 'Experimentation',
    4 => 'Synthese',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide Prompting - Vue Session - <?= h($session['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>📝</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-violet-50 to-purple-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">📝 Guide de Prompting</h1>
                    <p class="text-violet-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
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
                    <button onclick="window.print()" class="bg-violet-500 hover:bg-violet-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-violet-500 hover:bg-violet-400 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Tous les guides</strong> - Vous voyez tous les guides (<?= $totalAll ?>), y compris ceux non partages.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-violet-600"><?= $totalParticipants ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $totalGuides ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Guides (tous)' : 'Guides partages' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Guides partages</div>
            </div>
        </div>

        <!-- Guides des participants -->
        <div class="space-y-6">
            <?php if (empty($allGuides)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <p class="text-gray-400 text-lg">Aucun guide pour le moment.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($allGuides as $g): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden <?= (!$g['is_shared'] && $showAll) ? 'opacity-75 border-2 border-orange-200' : '' ?>">
                <div class="bg-gradient-to-r from-violet-500 to-purple-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($g['user_prenom']) ?> <?= h($g['user_nom']) ?></span>
                            <?php if (!empty($g['user_organisation'])): ?>
                            <span class="text-violet-200 text-sm ml-2">(<?= h($g['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!$g['is_shared'] && $showAll): ?>
                            <span class="bg-orange-400 text-white text-xs px-2 py-0.5 rounded ml-2">Non partage</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-violet-200">Organisation: <?= h($g['organisation_nom'] ?: 'Non definie') ?></span>
                            <span class="bg-white/20 px-2 py-0.5 rounded text-xs">Etape <?= (int)$g['current_step'] ?><?= isset($stepLabels[(int)$g['current_step']]) ? ' - ' . $stepLabels[(int)$g['current_step']] : '' ?></span>
                        </div>
                    </div>
                    <!-- Barre de completion -->
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-violet-200 mb-1">
                            <span>Progression</span>
                            <span><?= (int)$g['completion_percent'] ?>%</span>
                        </div>
                        <div class="w-full bg-white/20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full transition-all" style="width: <?= (int)$g['completion_percent'] ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <!-- Taches identifiees -->
                    <?php if (!empty($g['tasks_arr'])): ?>
                    <div class="mb-4">
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-violet-500">📋</span> Taches identifiees (<?= count($g['tasks_arr']) ?>)
                        </h4>
                        <div class="grid md:grid-cols-2 gap-2">
                            <?php foreach ($g['tasks_arr'] as $task): ?>
                            <div class="bg-violet-50 rounded-lg p-3 border border-violet-100">
                                <p class="text-sm text-gray-700"><?= h(is_array($task) ? ($task['description'] ?? $task['name'] ?? json_encode($task)) : $task) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Experimentations -->
                    <?php if (!empty($g['experimentations_arr'])): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-purple-500">🧪</span> Experimentations (<?= count($g['experimentations_arr']) ?>)
                        </h4>
                        <div class="grid md:grid-cols-2 gap-2">
                            <?php foreach ($g['experimentations_arr'] as $exp): ?>
                            <div class="bg-purple-50 rounded-lg p-3 border border-purple-100">
                                <p class="text-sm text-gray-700"><?= h(is_array($exp) ? ($exp['description'] ?? $exp['name'] ?? json_encode($exp)) : $exp) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($g['tasks_arr']) && empty($g['experimentations_arr'])): ?>
                    <p class="text-gray-400 text-sm italic">Aucun contenu encore.</p>
                    <?php endif; ?>

                    <div class="mt-3 text-xs text-gray-400 text-right">
                        Mis a jour: <?= date('d/m/Y H:i', strtotime($g['updated_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
