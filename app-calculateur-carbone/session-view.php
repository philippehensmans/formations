<?php
/**
 * Vue globale de session - Calculateur Carbone
 * Affiche les calculs CO2 de tous les participants avec barres et detail
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

// Recuperer tous les calculs de la session
$stmt = $db->prepare("SELECT * FROM calculs WHERE session_id = ? ORDER BY user_id, use_case_id");
$stmt->execute([$sessionId]);
$allCalculs = $stmt->fetchAll();

// Grouper par user_id
$byUser = [];
foreach ($allCalculs as $c) {
    $uid = $c['user_id'];
    if (!isset($byUser[$uid])) {
        $byUser[$uid] = [
            'calculs' => [],
            'total_co2' => 0,
        ];
    }
    $byUser[$uid]['calculs'][] = $c;
    $byUser[$uid]['total_co2'] += (float)($c['co2_total'] ?? 0);
}

// Enrichir avec les infos utilisateur
foreach ($byUser as $uid => &$data) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$uid]);
    $userInfo = $userStmt->fetch();
    $data['user_prenom'] = $userInfo['prenom'] ?? '';
    $data['user_nom'] = $userInfo['nom'] ?? '';
    $data['user_organisation'] = $userInfo['organisation'] ?? '';
}
unset($data);

// Trier par total CO2 decroissant
uasort($byUser, fn($a, $b) => $b['total_co2'] <=> $a['total_co2']);

// Statistiques
$participantsCount = count($byUser);
$totalCalculs = count($allCalculs);
$globalCo2 = array_sum(array_column($byUser, 'total_co2'));
$avgCo2 = $participantsCount > 0 ? round($globalCo2 / $participantsCount, 1) : 0;
$maxCo2 = $participantsCount > 0 ? max(array_column($byUser, 'total_co2')) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculateur Carbone - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        .participant-card { transition: all 0.3s ease; }
        .participant-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-green-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-emerald-600 to-green-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Calculateur Carbone</h1>
                    <p class="text-emerald-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
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
                <div class="text-3xl font-bold text-emerald-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalCalculs ?></div>
                <div class="text-gray-500 text-sm">Calculs</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= number_format($globalCo2, 1) ?></div>
                <div class="text-gray-500 text-sm">CO2 total (kg)</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-cyan-600"><?= number_format($avgCo2, 1) ?></div>
                <div class="text-gray-500 text-sm">Moyenne / participant (kg)</div>
            </div>
        </div>

        <!-- Participants -->
        <div class="space-y-6">
            <?php foreach ($byUser as $uid => $data): ?>
            <?php $barWidth = $maxCo2 > 0 ? round(($data['total_co2'] / $maxCo2) * 100) : 0; ?>
            <div class="participant-card bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- En-tete participant -->
                <div class="bg-gradient-to-r from-emerald-500 to-green-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($data['user_prenom']) ?> <?= h($data['user_nom']) ?></span>
                            <?php if (!empty($data['user_organisation'])): ?>
                            <span class="text-emerald-200 text-sm ml-2">(<?= h($data['user_organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="bg-white/30 px-3 py-1 rounded text-sm font-bold"><?= number_format($data['total_co2'], 1) ?> kg CO2</span>
                        </div>
                    </div>
                    <!-- Barre de CO2 relative -->
                    <div class="mt-2">
                        <div class="w-full bg-white/20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full" style="width: <?= $barWidth ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <!-- Detail par use_case_id -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($data['calculs'] as $calcul): ?>
                        <div class="bg-emerald-50 rounded-lg p-3 border border-emerald-100">
                            <div class="flex justify-between items-start mb-1">
                                <div class="text-xs font-semibold text-emerald-600 uppercase">Use case #<?= h($calcul['use_case_id']) ?></div>
                                <span class="text-sm font-bold text-emerald-700"><?= number_format((float)($calcul['co2_total'] ?? 0), 2) ?> kg</span>
                            </div>
                            <div class="text-xs text-gray-500 space-y-0.5">
                                <?php if (!empty($calcul['frequence'])): ?>
                                <div>Frequence: <span class="text-gray-700"><?= h($calcul['frequence']) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($calcul['quantite'])): ?>
                                <div>Quantite: <span class="text-gray-700"><?= h($calcul['quantite']) ?></span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($byUser)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <div class="text-6xl mb-4">&#x1F33F;</div>
                <p class="text-gray-500 text-lg">Aucun calcul pour cette session.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
