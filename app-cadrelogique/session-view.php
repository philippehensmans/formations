<?php
/**
 * Vue globale de session - Cadre Logique
 * Affiche tous les cadres logiques de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-cadrelogique';
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

// Recuperer les cadres logiques de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM cadre_logique WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM cadre_logique WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$cadres = $stmt->fetchAll();

// Compter le total (partages vs tous)
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM cadre_logique WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

// Enrichir avec les infos utilisateur
$participantsData = [];
$totalCompletion = 0;
$completedCount = 0;

foreach ($cadres as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $matrice = json_decode($a['matrice_data'] ?? '{}', true) ?: [];
    $a['parsed_matrice'] = $matrice;

    $completion = (int)($a['completion_percent'] ?? 0);
    $a['completion'] = $completion;
    $totalCompletion += $completion;
    if ($completion >= 100) $completedCount++;

    $participantsData[] = $a;
}

$participantsCount = count($cadres);
$avgCompletion = $participantsCount > 0 ? round($totalCompletion / $participantsCount) : 0;

// Les niveaux du cadre logique
$niveaux = [
    'objectif_global' => ['label' => 'Objectif Global', 'color' => 'sky'],
    'objectif_specifique' => ['label' => 'Objectif Specifique', 'color' => 'blue'],
    'resultats' => ['label' => 'Resultats', 'color' => 'indigo'],
    'activites' => ['label' => 'Activites', 'color' => 'purple']
];

$colonnes = [
    'logique_intervention' => 'Logique d\'intervention',
    'indicateurs' => 'Indicateurs',
    'sources_verification' => 'Sources de verification',
    'hypotheses' => 'Hypotheses'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadre Logique - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-50 to-blue-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Cadre Logique</h1>
                    <p class="text-sky-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
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
                    <button onclick="window.print()" class="bg-sky-400 hover:bg-sky-300 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-sky-400 hover:bg-sky-300 px-3 py-1 rounded text-sm">
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
                <div class="text-3xl font-bold text-sky-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Partages</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $completedCount ?></div>
                <div class="text-gray-500 text-sm">Termines (100%)</div>
            </div>
        </div>

        <!-- Cadres par participant -->
        <?php if (empty($participantsData)): ?>
        <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
            Aucun participant dans cette session.
        </div>
        <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($participantsData as $a):
                $matrice = $a['parsed_matrice'];
                $completion = $a['completion'];
            ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Participant header -->
                <div class="bg-gradient-to-r from-sky-500 to-blue-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($a['user_prenom']) ?> <?= h($a['user_nom']) ?></span>
                            <?php if (!empty($a['user_organisation'])): ?>
                            <span class="text-sky-200 text-sm ml-2">(<?= h($a['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($a['titre_projet'])): ?>
                            <span class="block text-sky-100 text-sm mt-1">Projet: <?= h($a['titre_projet']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2 items-center">
                            <!-- Completion bar -->
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-white/30 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full <?= $completion >= 100 ? 'bg-green-400' : ($completion >= 50 ? 'bg-yellow-400' : 'bg-red-400') ?>" style="width: <?= min(100, $completion) ?>%"></div>
                                </div>
                                <span class="text-sm font-bold"><?= $completion ?>%</span>
                            </div>
                            <span class="px-2 py-1 rounded text-sm <?= $a['is_shared'] ? 'bg-green-400/50' : 'bg-yellow-500/50' ?>"><?= $a['is_shared'] ? 'Partage' : 'Brouillon' ?></span>
                        </div>
                    </div>
                </div>

                <!-- Matrice du cadre logique -->
                <div class="p-4 overflow-x-auto">
                    <?php if (empty($matrice)): ?>
                    <p class="text-gray-400 italic text-center py-4">Aucune donnee dans la matrice</p>
                    <?php else: ?>
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-sky-50">
                                <th class="border border-sky-200 px-3 py-2 text-left text-sky-800 font-bold w-1/5">Niveau</th>
                                <?php foreach ($colonnes as $colKey => $colLabel): ?>
                                <th class="border border-sky-200 px-3 py-2 text-left text-sky-800 font-bold"><?= $colLabel ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($niveaux as $nivKey => $niv):
                                $rowData = $matrice[$nivKey] ?? [];
                            ?>
                            <tr>
                                <td class="border border-sky-200 px-3 py-2 bg-<?= $niv['color'] ?>-50 font-bold text-<?= $niv['color'] ?>-700"><?= $niv['label'] ?></td>
                                <?php foreach ($colonnes as $colKey => $colLabel):
                                    $cellValue = '';
                                    if (is_array($rowData)) {
                                        if (isset($rowData[$colKey])) {
                                            $cellValue = is_array($rowData[$colKey]) ? implode(', ', array_filter($rowData[$colKey])) : $rowData[$colKey];
                                        }
                                    }
                                ?>
                                <td class="border border-sky-200 px-3 py-2 text-gray-700">
                                    <?= !empty(trim($cellValue)) ? h($cellValue) : '<span class="text-gray-300 italic">-</span>' ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
