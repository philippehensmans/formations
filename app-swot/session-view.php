<?php
/**
 * Vue globale de session - Analyse SWOT
 * Affiche toutes les analyses SWOT de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-swot';

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

// Option pour voir toutes les analyses ou seulement les partagees
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

// Enrichir avec les infos utilisateur
$participantsData = [];
$totalForces = 0;
$totalFaiblesses = 0;
$totalOpportunites = 0;
$totalMenaces = 0;

foreach ($analyses as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $swotData = json_decode($a['swot_data'] ?? '{}', true) ?: [];
    $a['parsed_swot'] = $swotData;

    $forces = array_filter($swotData['forces'] ?? [], fn($v) => !empty(trim($v)));
    $faiblesses = array_filter($swotData['faiblesses'] ?? [], fn($v) => !empty(trim($v)));
    $opportunites = array_filter($swotData['opportunites'] ?? [], fn($v) => !empty(trim($v)));
    $menaces = array_filter($swotData['menaces'] ?? [], fn($v) => !empty(trim($v)));

    $totalForces += count($forces);
    $totalFaiblesses += count($faiblesses);
    $totalOpportunites += count($opportunites);
    $totalMenaces += count($menaces);

    $participantsData[] = $a;
}

$participantsCount = count($analyses);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse SWOT - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-emerald-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Analyse SWOT</h1>
                    <p class="text-green-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
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
                    <button onclick="window.print()" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
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
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Partages</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-green-500">
                <div class="text-3xl font-bold text-green-600"><?= $totalForces ?></div>
                <div class="text-gray-500 text-sm">Forces</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-red-500">
                <div class="text-3xl font-bold text-red-600"><?= $totalFaiblesses ?></div>
                <div class="text-gray-500 text-sm">Faiblesses</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-blue-500">
                <div class="text-3xl font-bold text-blue-600"><?= $totalOpportunites ?></div>
                <div class="text-gray-500 text-sm">Opportunites</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-amber-500">
                <div class="text-3xl font-bold text-amber-600"><?= $totalMenaces ?></div>
                <div class="text-gray-500 text-sm">Menaces</div>
            </div>
        </div>

        <!-- Analyses par participant -->
        <?php if (empty($participantsData)): ?>
        <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
            Aucune analyse trouvee pour cette session.
        </div>
        <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($participantsData as $a):
                $swot = $a['parsed_swot'];
                $forces = array_filter($swot['forces'] ?? [], fn($v) => !empty(trim($v)));
                $faiblesses = array_filter($swot['faiblesses'] ?? [], fn($v) => !empty(trim($v)));
                $opportunites = array_filter($swot['opportunites'] ?? [], fn($v) => !empty(trim($v)));
                $menaces = array_filter($swot['menaces'] ?? [], fn($v) => !empty(trim($v)));
            ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Participant header -->
                <div class="bg-gradient-to-r from-green-500 to-emerald-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($a['user_prenom']) ?> <?= h($a['user_nom']) ?></span>
                            <?php if (!empty($a['user_organisation'])): ?>
                            <span class="text-green-200 text-sm ml-2">(<?= h($a['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($a['titre_projet'])): ?>
                            <span class="block text-green-100 text-sm mt-1">Projet: <?= h($a['titre_projet']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <span class="bg-white/20 px-2 py-1 rounded text-sm"><?= count($forces) + count($faiblesses) + count($opportunites) + count($menaces) ?> elements</span>
                            <span class="px-2 py-1 rounded text-sm <?= $a['is_shared'] ? 'bg-green-400/50' : 'bg-yellow-500/50' ?>"><?= $a['is_shared'] ? 'Partage' : 'Brouillon' ?></span>
                        </div>
                    </div>
                </div>

                <!-- SWOT 2x2 grid -->
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Forces (S) -->
                        <div class="bg-green-50 rounded-lg border-2 border-green-200 p-4">
                            <h4 class="font-bold text-green-700 mb-3 flex items-center gap-2">
                                <span class="bg-green-200 text-green-800 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">S</span>
                                Forces (<?= count($forces) ?>)
                            </h4>
                            <?php if (empty($forces)): ?>
                            <p class="text-gray-400 italic text-sm">Aucun element</p>
                            <?php else: ?>
                            <ul class="space-y-1">
                                <?php foreach ($forces as $item): ?>
                                <li class="text-sm text-gray-700 flex items-start gap-2">
                                    <span class="text-green-500 mt-0.5">+</span>
                                    <span><?= h($item) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>

                        <!-- Faiblesses (W) -->
                        <div class="bg-red-50 rounded-lg border-2 border-red-200 p-4">
                            <h4 class="font-bold text-red-700 mb-3 flex items-center gap-2">
                                <span class="bg-red-200 text-red-800 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">W</span>
                                Faiblesses (<?= count($faiblesses) ?>)
                            </h4>
                            <?php if (empty($faiblesses)): ?>
                            <p class="text-gray-400 italic text-sm">Aucun element</p>
                            <?php else: ?>
                            <ul class="space-y-1">
                                <?php foreach ($faiblesses as $item): ?>
                                <li class="text-sm text-gray-700 flex items-start gap-2">
                                    <span class="text-red-500 mt-0.5">-</span>
                                    <span><?= h($item) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>

                        <!-- Opportunites (O) -->
                        <div class="bg-blue-50 rounded-lg border-2 border-blue-200 p-4">
                            <h4 class="font-bold text-blue-700 mb-3 flex items-center gap-2">
                                <span class="bg-blue-200 text-blue-800 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">O</span>
                                Opportunites (<?= count($opportunites) ?>)
                            </h4>
                            <?php if (empty($opportunites)): ?>
                            <p class="text-gray-400 italic text-sm">Aucun element</p>
                            <?php else: ?>
                            <ul class="space-y-1">
                                <?php foreach ($opportunites as $item): ?>
                                <li class="text-sm text-gray-700 flex items-start gap-2">
                                    <span class="text-blue-500 mt-0.5">&#x2197;</span>
                                    <span><?= h($item) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>

                        <!-- Menaces (T) -->
                        <div class="bg-amber-50 rounded-lg border-2 border-amber-200 p-4">
                            <h4 class="font-bold text-amber-700 mb-3 flex items-center gap-2">
                                <span class="bg-amber-200 text-amber-800 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">T</span>
                                Menaces (<?= count($menaces) ?>)
                            </h4>
                            <?php if (empty($menaces)): ?>
                            <p class="text-gray-400 italic text-sm">Aucun element</p>
                            <?php else: ?>
                            <ul class="space-y-1">
                                <?php foreach ($menaces as $item): ?>
                                <li class="text-sm text-gray-700 flex items-start gap-2">
                                    <span class="text-amber-500 mt-0.5">&#x26A0;</span>
                                    <span><?= h($item) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
