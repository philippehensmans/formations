<?php
/**
 * Vue globale de session - Gestion Agile
 * Affiche tous les projets agiles de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-agile';
$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) { header('Location: formateur.php'); exit; }

$db = getDB();
$sharedDb = getSharedDB();

if (!canAccessSession($appKey, $sessionId)) { die("Acces refuse."); }

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) { header('Location: formateur.php'); exit; }

$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les projets de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM projects WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$projects = $stmt->fetchAll();

// Compter le total (partages vs tous)
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM projects WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

// Enrichir avec les infos utilisateur
$participantsData = [];
$totalCards = 0;
$totalStories = 0;
$totalRetroItems = 0;

foreach ($projects as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $cards = json_decode($a['cards'] ?? '[]', true) ?: [];
    $userStories = json_decode($a['user_stories'] ?? '[]', true) ?: [];
    $retrospective = json_decode($a['retrospective'] ?? '{}', true) ?: [];

    $a['parsed_cards'] = $cards;
    $a['parsed_stories'] = $userStories;
    $a['parsed_retro'] = $retrospective;

    $cardCount = is_array($cards) ? count($cards) : 0;
    $storyCount = is_array($userStories) ? count($userStories) : 0;

    // Retro items can be structured as arrays of items per category
    $retroCount = 0;
    if (is_array($retrospective)) {
        foreach ($retrospective as $key => $val) {
            if (is_array($val)) {
                $retroCount += count(array_filter($val, fn($v) => is_string($v) ? !empty(trim($v)) : !empty($v)));
            }
        }
    }

    $a['card_count'] = $cardCount;
    $a['story_count'] = $storyCount;
    $a['retro_count'] = $retroCount;

    $totalCards += $cardCount;
    $totalStories += $storyCount;
    $totalRetroItems += $retroCount;

    $participantsData[] = $a;
}

$participantsCount = count($projects);

// Kanban column labels
$kanbanCols = [
    'backlog' => ['label' => 'Backlog', 'color' => 'gray'],
    'todo' => ['label' => 'A faire', 'color' => 'blue'],
    'in_progress' => ['label' => 'En cours', 'color' => 'yellow'],
    'review' => ['label' => 'Revue', 'color' => 'purple'],
    'done' => ['label' => 'Termine', 'color' => 'green']
];

// Retro categories
$retroCats = [
    'bien' => ['label' => 'Ce qui a bien marche', 'color' => 'green'],
    'ameliorer' => ['label' => 'A ameliorer', 'color' => 'amber'],
    'actions' => ['label' => 'Actions a prendre', 'color' => 'blue']
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Agile - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-violet-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-indigo-600 to-violet-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Gestion Agile</h1>
                    <p class="text-indigo-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
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
                    <button onclick="window.print()" class="bg-indigo-500 hover:bg-indigo-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-indigo-500 hover:bg-indigo-400 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Tous les projets</strong> - Vous voyez tous les projets (<?= $totalAll ?>), y compris ceux non partages.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques globales -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-violet-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Partages</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-blue-500">
                <div class="text-3xl font-bold text-blue-600"><?= $totalCards ?></div>
                <div class="text-gray-500 text-sm">Cartes Kanban</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-green-500">
                <div class="text-3xl font-bold text-green-600"><?= $totalStories ?></div>
                <div class="text-gray-500 text-sm">User Stories</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-amber-500">
                <div class="text-3xl font-bold text-amber-600"><?= $totalRetroItems ?></div>
                <div class="text-gray-500 text-sm">Items Retro</div>
            </div>
        </div>

        <!-- Projets par participant -->
        <?php if (empty($participantsData)): ?>
        <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
            Aucun participant dans cette session.
        </div>
        <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($participantsData as $a):
                $cards = $a['parsed_cards'];
                $stories = $a['parsed_stories'];
                $retro = $a['parsed_retro'];
            ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Participant header -->
                <div class="bg-gradient-to-r from-indigo-500 to-violet-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($a['user_prenom']) ?> <?= h($a['user_nom']) ?></span>
                            <?php if (!empty($a['user_organisation'])): ?>
                            <span class="text-indigo-200 text-sm ml-2">(<?= h($a['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($a['project_name'])): ?>
                            <span class="block text-indigo-100 text-sm mt-1">Projet: <?= h($a['project_name']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($a['team_name'])): ?>
                            <span class="block text-indigo-100 text-sm">Equipe: <?= h($a['team_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2 flex-wrap justify-end">
                            <?php if (!empty($a['sprint'])): ?>
                            <span class="bg-indigo-400/30 px-2 py-1 rounded text-sm">Sprint <?= h($a['sprint']) ?></span>
                            <?php endif; ?>
                            <span class="bg-blue-400/30 px-2 py-1 rounded text-sm"><?= $a['card_count'] ?> cartes</span>
                            <span class="bg-green-400/30 px-2 py-1 rounded text-sm"><?= $a['story_count'] ?> stories</span>
                            <span class="bg-amber-400/30 px-2 py-1 rounded text-sm"><?= $a['retro_count'] ?> retro</span>
                            <span class="px-2 py-1 rounded text-sm <?= $a['is_shared'] ? 'bg-green-400/50' : 'bg-yellow-500/50' ?>"><?= $a['is_shared'] ? 'Partage' : 'Brouillon' ?></span>
                        </div>
                    </div>
                </div>

                <div class="p-4 space-y-4">
                    <!-- Kanban cards summary -->
                    <?php if (!empty($cards)): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 mb-3">Cartes Kanban</h4>
                        <?php
                        // Group cards by status/column
                        $cardsByCol = [];
                        foreach ($cards as $card) {
                            $status = $card['status'] ?? $card['column'] ?? $card['colonne'] ?? 'backlog';
                            $cardsByCol[$status][] = $card;
                        }
                        ?>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            <?php foreach ($kanbanCols as $colKey => $col):
                                $colCards = $cardsByCol[$colKey] ?? [];
                            ?>
                            <div class="bg-<?= $col['color'] ?>-50 rounded-lg border border-<?= $col['color'] ?>-200 p-3">
                                <div class="font-medium text-<?= $col['color'] ?>-700 text-sm mb-2"><?= $col['label'] ?> (<?= count($colCards) ?>)</div>
                                <?php if (!empty($colCards)): ?>
                                <ul class="space-y-1">
                                    <?php foreach (array_slice($colCards, 0, 5) as $card): ?>
                                    <li class="text-xs text-gray-600 bg-white rounded px-2 py-1 shadow-sm"><?= h($card['title'] ?? $card['titre'] ?? $card['name'] ?? '...') ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($colCards) > 5): ?>
                                    <li class="text-xs text-gray-400 italic">+<?= count($colCards) - 5 ?> autres</li>
                                    <?php endif; ?>
                                </ul>
                                <?php else: ?>
                                <p class="text-xs text-gray-400 italic">Vide</p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- User Stories summary -->
                    <?php if (!empty($stories)): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 mb-3">User Stories (<?= count($stories) ?>)</h4>
                        <div class="grid md:grid-cols-2 gap-2">
                            <?php foreach (array_slice($stories, 0, 6) as $story): ?>
                            <div class="bg-green-50 rounded-lg border border-green-200 p-3">
                                <p class="text-sm text-gray-700">
                                    <?php if (is_array($story)): ?>
                                    <?= h($story['title'] ?? $story['titre'] ?? $story['description'] ?? json_encode($story)) ?>
                                    <?php else: ?>
                                    <?= h($story) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($stories) > 6): ?>
                            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3 flex items-center justify-center">
                                <span class="text-sm text-gray-400 italic">+<?= count($stories) - 6 ?> autres stories</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Retrospective summary -->
                    <?php if (!empty($retro) && $a['retro_count'] > 0): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 mb-3">Retrospective</h4>
                        <div class="grid md:grid-cols-3 gap-3">
                            <?php foreach ($retroCats as $catKey => $cat):
                                $items = $retro[$catKey] ?? [];
                                if (!is_array($items)) $items = [];
                                $items = array_filter($items, fn($v) => is_string($v) ? !empty(trim($v)) : !empty($v));
                            ?>
                            <div class="bg-<?= $cat['color'] ?>-50 rounded-lg border border-<?= $cat['color'] ?>-200 p-3">
                                <div class="font-medium text-<?= $cat['color'] ?>-700 text-sm mb-2"><?= $cat['label'] ?></div>
                                <?php if (!empty($items)): ?>
                                <ul class="space-y-1">
                                    <?php foreach ($items as $item): ?>
                                    <li class="text-xs text-gray-600">
                                        <span class="text-<?= $cat['color'] ?>-500">&#x2022;</span>
                                        <?= h(is_array($item) ? ($item['text'] ?? json_encode($item)) : $item) ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                                <p class="text-xs text-gray-400 italic">Aucun element</p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($cards) && empty($stories) && $a['retro_count'] == 0): ?>
                    <p class="text-gray-400 italic text-center py-4">Aucune donnee dans ce projet</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
