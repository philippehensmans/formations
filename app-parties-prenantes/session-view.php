<?php
/**
 * Vue globale de session - Parties Prenantes
 * Affiche les cartographies de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-parties-prenantes';

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

// Recuperer les cartographies de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM cartographie WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM cartographie WHERE session_id = ? AND is_submitted = 1 ORDER BY submitted_at DESC");
    $stmt->execute([$sessionId]);
}
$allCartos = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allCartos as &$c) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$c['user_id']]);
    $userInfo = $userStmt->fetch();
    $c['user_prenom'] = $userInfo['prenom'] ?? '';
    $c['user_nom'] = $userInfo['nom'] ?? '';
    $c['user_organisation'] = $userInfo['organisation'] ?? '';
    $c['stakeholders'] = json_decode($c['stakeholders_data'] ?? '[]', true) ?: [];
}
unset($c);

// Statistiques
$totalCartos = count($allCartos);

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM cartographie WHERE session_id = ?");
$stmt->execute([$sessionId]);
$totalParticipants = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) as submitted FROM cartographie WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalSubmitted = $counts['submitted'];

$totalStakeholders = 0;
foreach ($allCartos as $c) {
    $totalStakeholders += count($c['stakeholders']);
}

$avgCompletion = $totalCartos > 0 ? round(array_sum(array_column($allCartos, 'completion_percent')) / $totalCartos) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parties Prenantes - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-teal-50 to-cyan-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-teal-600 to-cyan-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Parties Prenantes</h1>
                    <p class="text-teal-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Soumis seulement (<?= $totalSubmitted ?>)
                    </a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">
                        Tous (<?= $totalAll ?>)
                    </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="bg-teal-500 hover:bg-teal-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-teal-500 hover:bg-teal-400 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Tous</strong> - Vous voyez toutes les cartographies (<?= $totalAll ?>), y compris celles non soumises.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $totalParticipants ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-cyan-600"><?= $totalCartos ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Cartographies (toutes)' : 'Cartographies soumises' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?= $totalStakeholders ?></div>
                <div class="text-gray-500 text-sm">Parties prenantes</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalSubmitted ?></div>
                <div class="text-gray-500 text-sm">Soumises</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
        </div>

        <!-- Cartographies des participants -->
        <div class="space-y-6">
            <?php if (empty($allCartos)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <p class="text-gray-400 text-lg">Aucune cartographie <?= $showAll ? '' : 'soumise' ?> pour le moment.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($allCartos as $c): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden <?= (!$c['is_submitted'] && $showAll) ? 'opacity-75 border-2 border-orange-200' : '' ?>">
                <div class="bg-gradient-to-r from-teal-500 to-cyan-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($c['user_prenom']) ?> <?= h($c['user_nom']) ?></span>
                            <?php if (!empty($c['user_organisation'])): ?>
                            <span class="text-teal-200 text-sm ml-2">(<?= h($c['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!$c['is_submitted'] && $showAll): ?>
                            <span class="bg-orange-400 text-white text-xs px-2 py-0.5 rounded ml-2">Non soumis</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-right text-sm">
                            <div class="text-teal-200">Projet: <?= h($c['titre_projet'] ?: 'Non defini') ?></div>
                            <div class="text-teal-300 text-xs"><?= count($c['stakeholders']) ?> partie(s) prenante(s)</div>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <?php if (!empty($c['contexte'])): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-teal-500 uppercase mb-1">Contexte</div>
                        <p class="text-gray-700 text-sm"><?= nl2br(h($c['contexte'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($c['stakeholders'])): ?>
                    <?php
                        $categories = getCategories();
                    ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-teal-50">
                                    <th class="text-left px-3 py-2 font-semibold text-teal-700">Nom</th>
                                    <th class="text-left px-3 py-2 font-semibold text-teal-700">Categorie</th>
                                    <th class="text-center px-3 py-2 font-semibold text-teal-700">Influence</th>
                                    <th class="text-center px-3 py-2 font-semibold text-teal-700">Interet</th>
                                    <th class="text-left px-3 py-2 font-semibold text-teal-700">Quadrant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($c['stakeholders'] as $s):
                                    $catKey = $s['category'] ?? '';
                                    $catLabel = $categories[$catKey]['label'] ?? $catKey;
                                    $catColor = $categories[$catKey]['color'] ?? '#999';
                                    $influence = $s['influence'] ?? 0;
                                    $interest = $s['interest'] ?? 0;
                                    if ($influence > 5 && $interest > 5) $quadrant = 'Gerer etroitement';
                                    elseif ($influence > 5) $quadrant = 'Maintenir satisfait';
                                    elseif ($interest <= 5) $quadrant = 'Surveiller';
                                    else $quadrant = 'Tenir informe';
                                ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-3 py-2 font-medium text-gray-800">
                                        <span class="inline-block w-3 h-3 rounded-full mr-1" style="background:<?= $catColor ?>"></span>
                                        <?= h($s['name'] ?? '') ?>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600"><?= h($catLabel) ?></td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-2 py-0.5 rounded text-xs font-medium <?= $influence >= 6 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' ?>"><?= $influence ?>/10</span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-2 py-0.5 rounded text-xs font-medium <?= $interest >= 6 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' ?>"><?= $interest ?>/10</span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 text-xs"><?= $quadrant ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-400 text-sm italic text-center py-4">Aucune partie prenante identifiee.</p>
                    <?php endif; ?>

                    <?php if (!empty($c['notes'])): ?>
                    <div class="mt-3">
                        <div class="text-xs font-semibold text-teal-500 uppercase mb-1">Notes</div>
                        <p class="text-gray-600 text-sm italic"><?= nl2br(h($c['notes'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3 text-xs text-gray-400 text-right">
                        <?php if (!empty($c['submitted_at'])): ?>
                        Soumis le: <?= date('d/m/Y H:i', strtotime($c['submitted_at'])) ?>
                        <?php else: ?>
                        Mis a jour: <?= date('d/m/Y H:i', strtotime($c['updated_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
