<?php
/**
 * Vue globale de session - Analyse PESTEL
 * Affiche toutes les analyses PESTEL de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-pestel';
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

// Recuperer les analyses de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM analyses WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM analyses WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$analyses = $stmt->fetchAll();

// Compter le total (partages vs tous)
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM analyses WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$categories = [
    'politique' => ['label' => 'Politique', 'color' => 'red', 'letter' => 'P'],
    'economique' => ['label' => 'Economique', 'color' => 'blue', 'letter' => 'E'],
    'socioculturel' => ['label' => 'Socioculturel', 'color' => 'purple', 'letter' => 'S'],
    'technologique' => ['label' => 'Technologique', 'color' => 'cyan', 'letter' => 'T'],
    'environnemental' => ['label' => 'Environnemental', 'color' => 'green', 'letter' => 'E'],
    'legal' => ['label' => 'Legal', 'color' => 'amber', 'letter' => 'L']
];

// Enrichir avec les infos utilisateur et collecter les stats
$participantsData = [];
$categoryTotals = [];
foreach ($categories as $key => $cat) {
    $categoryTotals[$key] = 0;
}

foreach ($analyses as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $pestelData = json_decode($a['pestel_data'] ?? '{}', true) ?: [];
    $a['parsed_pestel'] = $pestelData;

    foreach ($categories as $key => $cat) {
        $items = array_filter($pestelData[$key] ?? [], fn($v) => !empty(trim($v)));
        $categoryTotals[$key] += count($items);
    }

    $participantsData[] = $a;
}

$participantsCount = count($analyses);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse PESTEL - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-orange-50 to-amber-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-orange-500 to-amber-600 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Analyse PESTEL</h1>
                    <p class="text-orange-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Partages seulement (<?= $totalShared ?>)
                    </a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-400 hover:bg-orange-300 px-3 py-1 rounded text-sm">
                        Voir toutes (<?= $totalAll ?>)
                    </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="bg-orange-400 hover:bg-orange-300 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-orange-400 hover:bg-orange-300 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Toutes les analyses</strong> - Vous voyez toutes les analyses (<?= $totalAll ?>), y compris celles non partagees.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques globales -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-orange-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-amber-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Partages</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-orange-500"><?= array_sum($categoryTotals) ?></div>
                <div class="text-gray-500 text-sm">Elements total</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-amber-500"><?= $participantsCount > 0 ? round(array_sum($categoryTotals) / $participantsCount, 1) : 0 ?></div>
                <div class="text-gray-500 text-sm">Moyenne / participant</div>
            </div>
        </div>

        <!-- Repartition par categorie -->
        <div class="bg-white rounded-xl shadow p-6 mb-8">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Repartition par categorie PESTEL</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php foreach ($categories as $key => $cat): ?>
                <div class="flex flex-col items-center p-4 bg-<?= $cat['color'] ?>-50 rounded-xl border-2 border-<?= $cat['color'] ?>-200">
                    <span class="bg-<?= $cat['color'] ?>-200 text-<?= $cat['color'] ?>-800 w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold mb-2"><?= $cat['letter'] ?></span>
                    <div class="font-bold text-<?= $cat['color'] ?>-700"><?= $categoryTotals[$key] ?></div>
                    <div class="text-xs text-gray-500 text-center"><?= $cat['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Analyses par participant -->
        <?php if (empty($participantsData)): ?>
        <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
            Aucun participant dans cette session.
        </div>
        <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($participantsData as $a):
                $pestel = $a['parsed_pestel'];
            ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Participant header -->
                <div class="bg-gradient-to-r from-orange-500 to-amber-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($a['user_prenom']) ?> <?= h($a['user_nom']) ?></span>
                            <?php if (!empty($a['user_organisation'])): ?>
                            <span class="text-orange-200 text-sm ml-2">(<?= h($a['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($a['titre_projet'])): ?>
                            <span class="block text-orange-100 text-sm mt-1">Projet: <?= h($a['titre_projet']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <span class="px-2 py-1 rounded text-sm <?= $a['is_shared'] ? 'bg-green-400/50' : 'bg-yellow-500/50' ?>"><?= $a['is_shared'] ? 'Partage' : 'Brouillon' ?></span>
                        </div>
                    </div>
                </div>

                <!-- PESTEL 6 sections as colored pills/tags -->
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($categories as $key => $cat):
                            $items = array_filter($pestel[$key] ?? [], fn($v) => !empty(trim($v)));
                        ?>
                        <div class="bg-<?= $cat['color'] ?>-50 rounded-lg border-2 border-<?= $cat['color'] ?>-200 p-4">
                            <h4 class="font-bold text-<?= $cat['color'] ?>-700 mb-3 flex items-center gap-2">
                                <span class="bg-<?= $cat['color'] ?>-200 text-<?= $cat['color'] ?>-800 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold"><?= $cat['letter'] ?></span>
                                <?= $cat['label'] ?> (<?= count($items) ?>)
                            </h4>
                            <?php if (empty($items)): ?>
                            <p class="text-gray-400 italic text-sm">Aucun element</p>
                            <?php else: ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($items as $item): ?>
                                <span class="inline-block bg-<?= $cat['color'] ?>-100 text-<?= $cat['color'] ?>-800 text-sm px-3 py-1 rounded-full border border-<?= $cat['color'] ?>-300"><?= h($item) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
