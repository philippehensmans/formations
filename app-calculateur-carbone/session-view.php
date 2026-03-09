<?php
/**
 * Vue globale de session - Calculateur Carbone IA
 * Affiche les calculs de tous les participants avec vue agregee
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-calculateur-carbone';

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

// Recuperer tous les calculs de la session
$stmt = $db->prepare("SELECT * FROM calculs WHERE session_id = ? ORDER BY user_id, created_at DESC");
$stmt->execute([$sessionId]);
$allCalculs = $stmt->fetchAll();

// Agreger par utilisateur
$byUser = [];
foreach ($allCalculs as $c) {
    $uid = $c['user_id'];
    if (!isset($byUser[$uid])) {
        $byUser[$uid] = [
            'user_id' => $uid,
            'calculs' => [],
            'total_co2' => 0,
        ];
    }
    $byUser[$uid]['calculs'][] = $c;
    $byUser[$uid]['total_co2'] += (float)$c['co2_total'];
}

// Enrichir avec les infos utilisateur
foreach ($byUser as &$u) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$u['user_id']]);
    $userInfo = $userStmt->fetch();
    $u['user_prenom'] = $userInfo['prenom'] ?? '';
    $u['user_nom'] = $userInfo['nom'] ?? '';
    $u['user_organisation'] = $userInfo['organisation'] ?? '';
}
unset($u);

// Trier par CO2 total decroissant
uasort($byUser, fn($a, $b) => $b['total_co2'] <=> $a['total_co2']);

// Statistiques
$totalParticipants = count($byUser);
$totalCalculs = count($allCalculs);
$totalCO2 = array_sum(array_column($byUser, 'total_co2'));
$maxCO2 = $totalParticipants > 0 ? max(array_column($byUser, 'total_co2')) : 0;
$avgCO2 = $totalParticipants > 0 ? $totalCO2 / $totalParticipants : 0;

// Formater CO2
function formatCO2($grammes) {
    if ($grammes >= 1000000) {
        return round($grammes / 1000000, 2) . ' t';
    } elseif ($grammes >= 1000) {
        return round($grammes / 1000, 1) . ' kg';
    }
    return round($grammes, 1) . ' g';
}

// Charger les estimations pour les noms des use cases
$estimations = getEstimations();
$useCaseNames = [];
if (!empty($estimations['use_cases'])) {
    foreach ($estimations['use_cases'] as $id => $uc) {
        $useCaseNames[$id] = $uc['nom'] ?? $uc['name'] ?? $id;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculateur Carbone - Vue Session - <?= h($session['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>🌍</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-green-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-emerald-600 to-green-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">🌍 Calculateur Carbone IA</h1>
                    <p class="text-emerald-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <button onclick="window.print()" class="bg-emerald-500 hover:bg-emerald-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-emerald-500 hover:bg-emerald-400 px-3 py-1 rounded text-sm">
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?= $totalParticipants ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalCalculs ?></div>
                <div class="text-gray-500 text-sm">Calculs effectues</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= formatCO2($totalCO2) ?></div>
                <div class="text-gray-500 text-sm">CO2 total</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-cyan-600"><?= formatCO2($avgCO2) ?></div>
                <div class="text-gray-500 text-sm">Moyenne / participant</div>
            </div>
        </div>

        <!-- Graphique de comparaison (barres textuelles) -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Comparaison CO2 par participant</h2>
            <?php if (empty($byUser)): ?>
            <p class="text-gray-400 text-center py-4">Aucune donnee pour le moment.</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($byUser as $u): ?>
                <?php $percent = $maxCO2 > 0 ? round(($u['total_co2'] / $maxCO2) * 100) : 0; ?>
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium text-gray-700">
                            <?= h($u['user_prenom']) ?> <?= h($u['user_nom']) ?>
                            <?php if (!empty($u['user_organisation'])): ?>
                            <span class="text-gray-400 text-xs">(<?= h($u['user_organisation']) ?>)</span>
                            <?php endif; ?>
                        </span>
                        <span class="text-sm font-bold text-emerald-700"><?= formatCO2($u['total_co2']) ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div class="h-4 rounded-full transition-all duration-500 <?= $percent > 75 ? 'bg-red-400' : ($percent > 50 ? 'bg-orange-400' : ($percent > 25 ? 'bg-yellow-400' : 'bg-emerald-400')) ?>" style="width: <?= max($percent, 2) ?>%"></div>
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5"><?= count($u['calculs']) ?> calcul(s)</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Detail par participant -->
        <h2 class="text-lg font-bold text-gray-800 mb-4">Detail par participant</h2>
        <div class="space-y-6">
            <?php if (empty($byUser)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <p class="text-gray-400 text-lg">Aucun calcul pour le moment.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($byUser as $u): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-500 to-green-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($u['user_prenom']) ?> <?= h($u['user_nom']) ?></span>
                            <?php if (!empty($u['user_organisation'])): ?>
                            <span class="text-emerald-200 text-sm ml-2">(<?= h($u['user_organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-bold"><?= formatCO2($u['total_co2']) ?></div>
                            <div class="text-emerald-200 text-xs"><?= count($u['calculs']) ?> calcul(s)</div>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-emerald-50">
                                    <th class="text-left px-3 py-2 font-semibold text-emerald-700">Cas d'usage</th>
                                    <th class="text-center px-3 py-2 font-semibold text-emerald-700">Frequence</th>
                                    <th class="text-center px-3 py-2 font-semibold text-emerald-700">Quantite</th>
                                    <th class="text-right px-3 py-2 font-semibold text-emerald-700">CO2</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($u['calculs'] as $c): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-3 py-2 text-gray-800"><?= h($useCaseNames[$c['use_case_id']] ?? $c['use_case_id']) ?></td>
                                    <td class="px-3 py-2 text-center text-gray-600"><?= h($c['frequence']) ?></td>
                                    <td class="px-3 py-2 text-center text-gray-600"><?= (int)$c['quantite'] ?></td>
                                    <td class="px-3 py-2 text-right font-medium text-emerald-700"><?= formatCO2($c['co2_total']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
