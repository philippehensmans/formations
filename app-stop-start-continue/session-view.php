<?php
/**
 * Vue globale de session - Stop Start Continue
 * Affiche toutes les reponses de tous les participants avec filtrage par categorie
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-stop-start-continue';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Categories Stop, Start, Continue
$categories = [
    'stop' => [
        'nom' => 'Stop',
        'description' => 'Ce qu\'il faut arreter de faire',
        'icon' => '🛑',
        'bg' => 'bg-red-50',
        'border' => 'border-red-300',
        'text' => 'text-red-700',
        'header_bg' => 'bg-red-500'
    ],
    'start' => [
        'nom' => 'Start',
        'description' => 'Ce qu\'il faut commencer a faire',
        'icon' => '🚀',
        'bg' => 'bg-green-50',
        'border' => 'border-green-300',
        'text' => 'text-green-700',
        'header_bg' => 'bg-green-500'
    ],
    'continue' => [
        'nom' => 'Continue',
        'description' => 'Ce qu\'il faut continuer a faire',
        'icon' => '✅',
        'bg' => 'bg-blue-50',
        'border' => 'border-blue-300',
        'text' => 'text-blue-700',
        'header_bg' => 'bg-blue-500'
    ]
];

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

// Option pour voir toutes les reponses ou seulement les partagees
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les retrospectives de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM retrospectives WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM retrospectives WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$retrospectives = $stmt->fetchAll();

// Enrichir avec les infos utilisateur et extraire tous les items
$allItems = [
    'stop' => [],
    'start' => [],
    'continue' => []
];

$participantsData = [];

foreach ($retrospectives as &$r) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$r['user_id']]);
    $userInfo = $userStmt->fetch();
    $r['user_prenom'] = $userInfo['prenom'] ?? '';
    $r['user_nom'] = $userInfo['nom'] ?? '';
    $r['user_organisation'] = $userInfo['organisation'] ?? '';

    // Decoder les items JSON
    $stopItems = json_decode($r['stop_items'], true) ?: [];
    $startItems = json_decode($r['start_items'], true) ?: [];
    $continueItems = json_decode($r['continue_items'], true) ?: [];

    // Ajouter les items aux listes globales
    foreach ($stopItems as $item) {
        if (!empty(trim($item))) {
            $allItems['stop'][] = [
                'content' => $item,
                'user_id' => $r['user_id'],
                'user_prenom' => $r['user_prenom'],
                'user_nom' => $r['user_nom'],
                'user_organisation' => $r['user_organisation'],
                'projet_nom' => $r['projet_nom'],
                'is_shared' => $r['is_shared'],
                'updated_at' => $r['updated_at']
            ];
        }
    }

    foreach ($startItems as $item) {
        if (!empty(trim($item))) {
            $allItems['start'][] = [
                'content' => $item,
                'user_id' => $r['user_id'],
                'user_prenom' => $r['user_prenom'],
                'user_nom' => $r['user_nom'],
                'user_organisation' => $r['user_organisation'],
                'projet_nom' => $r['projet_nom'],
                'is_shared' => $r['is_shared'],
                'updated_at' => $r['updated_at']
            ];
        }
    }

    foreach ($continueItems as $item) {
        if (!empty(trim($item))) {
            $allItems['continue'][] = [
                'content' => $item,
                'user_id' => $r['user_id'],
                'user_prenom' => $r['user_prenom'],
                'user_nom' => $r['user_nom'],
                'user_organisation' => $r['user_organisation'],
                'projet_nom' => $r['projet_nom'],
                'is_shared' => $r['is_shared'],
                'updated_at' => $r['updated_at']
            ];
        }
    }

    // Stocker les donnees par participant
    if (!isset($participantsData[$r['user_id']])) {
        $participantsData[$r['user_id']] = [
            'user' => [
                'prenom' => $r['user_prenom'],
                'nom' => $r['user_nom'],
                'organisation' => $r['user_organisation']
            ],
            'projet_nom' => $r['projet_nom'],
            'stop' => [],
            'start' => [],
            'continue' => []
        ];
    }
    $participantsData[$r['user_id']]['stop'] = array_merge($participantsData[$r['user_id']]['stop'], $stopItems);
    $participantsData[$r['user_id']]['start'] = array_merge($participantsData[$r['user_id']]['start'], $startItems);
    $participantsData[$r['user_id']]['continue'] = array_merge($participantsData[$r['user_id']]['continue'], $continueItems);
}

// Statistiques
$totalItems = count($allItems['stop']) + count($allItems['start']) + count($allItems['continue']);
$participantsCount = count($retrospectives);

// Compter le total (partages vs tous)
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM retrospectives WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stop Start Continue - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .category-filter { transition: all 0.2s ease; }
        .category-filter:hover { transform: scale(1.05); }
        .category-filter.active { ring: 4px; transform: scale(1.1); }
        .item-card { transition: all 0.3s ease; }
        .item-card:hover { transform: translateY(-2px); }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-pink-50 to-rose-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-pink-500 to-rose-600 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Stop Start Continue</h1>
                    <p class="text-pink-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Partages seulement (<?= $totalShared ?>)
                    </a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">
                        Voir toutes (<?= $totalAll ?>)
                    </a>
                    <?php endif; ?>
                    <?= renderLanguageSelector('bg-pink-400 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <button onclick="window.print()" class="bg-pink-400 hover:bg-pink-300 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-pink-400 hover:bg-pink-300 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Toutes les retrospectives</strong> - Vous voyez toutes les retrospectives (<?= $totalAll ?>), y compris celles non partagees.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques globales -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-pink-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-gray-600"><?= $totalItems ?></div>
                <div class="text-gray-500 text-sm">Items total</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-red-500">
                <div class="text-3xl font-bold text-red-600"><?= count($allItems['stop']) ?></div>
                <div class="text-gray-500 text-sm">Stop</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-green-500">
                <div class="text-3xl font-bold text-green-600"><?= count($allItems['start']) ?></div>
                <div class="text-gray-500 text-sm">Start</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-blue-500">
                <div class="text-3xl font-bold text-blue-600"><?= count($allItems['continue']) ?></div>
                <div class="text-gray-500 text-sm">Continue</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 no-print">
            <div class="flex flex-wrap gap-4 items-center">
                <div class="font-medium text-gray-700">Filtrer par categorie:</div>
                <button onclick="filterByCategory('all')" id="filter-all"
                        class="category-filter px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium active ring-pink-500 ring-4">
                    Tous (<?= $totalItems ?>)
                </button>
                <?php foreach ($categories as $key => $cat): ?>
                <button onclick="filterByCategory('<?= $key ?>')" id="filter-<?= $key ?>"
                        class="category-filter px-4 py-2 <?= $cat['bg'] ?> <?= $cat['text'] ?> hover:opacity-80 rounded-lg text-sm font-medium border <?= $cat['border'] ?>">
                    <?= $cat['icon'] ?> <?= $cat['nom'] ?> (<?= count($allItems[$key]) ?>)
                </button>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex gap-4 items-center">
                <div class="font-medium text-gray-700">Affichage:</div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="grid" checked onchange="setDisplayMode('grid')">
                    <span class="text-sm">Par categorie</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="participant" onchange="setDisplayMode('participant')">
                    <span class="text-sm">Par participant</span>
                </label>
            </div>
        </div>

        <!-- Vue Grille (par categorie) -->
        <div id="gridView" class="grid md:grid-cols-3 gap-6">
            <?php foreach ($categories as $key => $cat): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden category-section" data-category="<?= $key ?>">
                <div class="<?= $cat['header_bg'] ?> text-white p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl"><?= $cat['icon'] ?></span>
                            <div>
                                <div class="font-bold text-xl"><?= $cat['nom'] ?></div>
                                <div class="text-sm opacity-80"><?= $cat['description'] ?></div>
                            </div>
                        </div>
                        <span class="bg-white/30 px-3 py-1 rounded-full text-lg font-bold"><?= count($allItems[$key]) ?></span>
                    </div>
                </div>
                <div class="p-4 max-h-[600px] overflow-y-auto">
                    <?php if (empty($allItems[$key])): ?>
                    <p class="text-gray-400 text-center py-8 text-sm">Aucun item</p>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($allItems[$key] as $item): ?>
                        <div class="item-card p-3 <?= $cat['bg'] ?> rounded-lg border <?= $cat['border'] ?>">
                            <p class="text-gray-700 text-sm mb-2"><?= nl2br(h($item['content'])) ?></p>
                            <div class="flex justify-between items-center text-xs text-gray-500">
                                <span class="font-medium"><?= h($item['user_prenom']) ?> <?= h($item['user_nom']) ?></span>
                                <?php if (!empty($item['projet_nom'])): ?>
                                <span class="italic"><?= h($item['projet_nom']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Vue Par Participant -->
        <div id="participantView" class="hidden space-y-6">
            <?php foreach ($participantsData as $userId => $data): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden participant-section">
                <div class="bg-gradient-to-r from-pink-500 to-rose-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['user']['organisation'])): ?>
                            <span class="text-pink-200 text-sm ml-2">(<?= h($data['user']['organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($data['projet_nom'])): ?>
                            <span class="block text-pink-100 text-sm mt-1">Projet: <?= h($data['projet_nom']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <span class="bg-red-400/50 px-2 py-1 rounded text-sm"><?= count(array_filter($data['stop'])) ?> Stop</span>
                            <span class="bg-green-400/50 px-2 py-1 rounded text-sm"><?= count(array_filter($data['start'])) ?> Start</span>
                            <span class="bg-blue-400/50 px-2 py-1 rounded text-sm"><?= count(array_filter($data['continue'])) ?> Continue</span>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <div class="grid md:grid-cols-3 gap-4">
                        <?php foreach ($categories as $catKey => $cat): ?>
                        <div class="category-item" data-category="<?= $catKey ?>">
                            <h4 class="font-bold <?= $cat['text'] ?> mb-2 flex items-center gap-2">
                                <span><?= $cat['icon'] ?></span>
                                <?= $cat['nom'] ?>
                            </h4>
                            <div class="space-y-2">
                                <?php
                                $items = array_filter($data[$catKey]);
                                if (empty($items)): ?>
                                <p class="text-gray-400 text-sm italic">Aucun</p>
                                <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                <div class="p-2 <?= $cat['bg'] ?> rounded border <?= $cat['border'] ?> text-sm">
                                    <?= nl2br(h($item)) ?>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucune retrospective trouvee pour cette session.
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?= renderLanguageScript() ?>

    <script>
        let currentFilter = 'all';
        let currentDisplay = 'grid';

        function filterByCategory(category) {
            currentFilter = category;

            // Update filter buttons
            document.querySelectorAll('.category-filter').forEach(btn => {
                btn.classList.remove('active', 'ring-4', 'ring-pink-500');
            });
            document.getElementById('filter-' + category).classList.add('active', 'ring-4', 'ring-pink-500');

            applyFilters();
        }

        function setDisplayMode(mode) {
            currentDisplay = mode;

            // Hide all views
            document.getElementById('gridView').classList.add('hidden');
            document.getElementById('participantView').classList.add('hidden');

            // Show selected view
            document.getElementById(mode + 'View').classList.remove('hidden');

            applyFilters();
        }

        function applyFilters() {
            if (currentDisplay === 'grid') {
                // Grid view: show/hide sections
                document.querySelectorAll('.category-section').forEach(section => {
                    if (currentFilter === 'all' || section.dataset.category === currentFilter) {
                        section.style.display = '';
                    } else {
                        section.style.display = 'none';
                    }
                });
            } else if (currentDisplay === 'participant') {
                // Participant view: show/hide category items within each participant
                document.querySelectorAll('.category-item').forEach(item => {
                    if (currentFilter === 'all' || item.dataset.category === currentFilter) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
        }
    </script>
</body>
</html>
