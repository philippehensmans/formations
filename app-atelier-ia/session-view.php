<?php
/**
 * Vue globale de session - Atelier IA
 * Affiche tous les ateliers de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-atelier-ia';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse.");
}

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: formateur.php');
    exit;
}

$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les ateliers de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM ateliers WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM ateliers WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$allAteliers = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allAteliers as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';
    $a['post_its_arr'] = json_decode($a['post_its'] ?? '[]', true) ?: [];
    $a['themes_arr'] = json_decode($a['themes'] ?? '[]', true) ?: [];
}
unset($a);

// Statistiques
$totalAteliers = count($allAteliers);

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM ateliers WHERE session_id = ?");
$stmt->execute([$sessionId]);
$totalParticipants = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM ateliers WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$avgCompletion = $totalAteliers > 0 ? round(array_sum(array_column($allAteliers, 'completion_percent')) / $totalAteliers) : 0;

$postItColors = ['bg-yellow-200 text-yellow-800', 'bg-pink-200 text-pink-800', 'bg-green-200 text-green-800', 'bg-blue-200 text-blue-800', 'bg-orange-200 text-orange-800', 'bg-teal-200 text-teal-800'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atelier IA - Vue Session - <?= h($session['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>🤖</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 to-fuchsia-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-600 to-fuchsia-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">🤖 Atelier IA</h1>
                    <p class="text-purple-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
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
                    <button onclick="window.print()" class="bg-purple-500 hover:bg-purple-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-purple-500 hover:bg-purple-400 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Tous les ateliers</strong> - Vous voyez tous les ateliers (<?= $totalAll ?>), y compris ceux non partages.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $totalParticipants ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-fuchsia-600"><?= $totalAteliers ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Ateliers (tous)' : 'Ateliers partages' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Ateliers partages</div>
            </div>
        </div>

        <!-- Ateliers des participants -->
        <div class="space-y-6">
            <?php if (empty($allAteliers)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <p class="text-gray-400 text-lg">Aucun atelier pour le moment.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($allAteliers as $a): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden <?= (!$a['is_shared'] && $showAll) ? 'opacity-75 border-2 border-orange-200' : '' ?>">
                <div class="bg-gradient-to-r from-purple-500 to-fuchsia-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($a['user_prenom']) ?> <?= h($a['user_nom']) ?></span>
                            <?php if (!empty($a['user_organisation'])): ?>
                            <span class="text-purple-200 text-sm ml-2">(<?= h($a['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!$a['is_shared'] && $showAll): ?>
                            <span class="bg-orange-400 text-white text-xs px-2 py-0.5 rounded ml-2">Non partage</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-purple-200">Association: <?= h($a['association_nom'] ?: 'Non definie') ?></span>
                        </div>
                    </div>
                    <!-- Barre de completion -->
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-purple-200 mb-1">
                            <span>Progression</span>
                            <span><?= (int)$a['completion_percent'] ?>%</span>
                        </div>
                        <div class="w-full bg-white/20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full transition-all" style="width: <?= (int)$a['completion_percent'] ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <!-- Mission de l'association -->
                    <?php if (!empty($a['association_mission'])): ?>
                    <div class="mb-4">
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-purple-500">🎯</span> Mission de l'association
                        </h4>
                        <p class="text-sm text-gray-700 bg-purple-50 rounded-lg p-3 border border-purple-100"><?= h($a['association_mission']) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Post-its -->
                    <?php if (!empty($a['post_its_arr'])): ?>
                    <div class="mb-4">
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-purple-500">📌</span> Post-its (<?= count($a['post_its_arr']) ?>)
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($a['post_its_arr'] as $i => $postIt): ?>
                            <span class="<?= $postItColors[$i % count($postItColors)] ?> px-3 py-1.5 rounded-lg text-sm font-medium shadow-sm">
                                <?= h(is_array($postIt) ? ($postIt['text'] ?? $postIt['content'] ?? json_encode($postIt)) : $postIt) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Themes -->
                    <?php if (!empty($a['themes_arr'])): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-fuchsia-500">🏷️</span> Themes (<?= count($a['themes_arr']) ?>)
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($a['themes_arr'] as $theme): ?>
                            <span class="bg-fuchsia-100 text-fuchsia-700 px-3 py-1 rounded-full text-sm border border-fuchsia-200">
                                <?= h(is_array($theme) ? ($theme['name'] ?? $theme['label'] ?? json_encode($theme)) : $theme) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($a['association_mission']) && empty($a['post_its_arr']) && empty($a['themes_arr'])): ?>
                    <p class="text-gray-400 text-sm italic">Aucun contenu encore.</p>
                    <?php endif; ?>

                    <div class="mt-3 text-xs text-gray-400 text-right">
                        Mis a jour: <?= date('d/m/Y H:i', strtotime($a['updated_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
