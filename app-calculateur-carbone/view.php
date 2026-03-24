<?php
/**
 * Vue en lecture seule des calculs carbone d'un participant
 * Accessible par le formateur
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Recuperer le participant
$stmt = $db->prepare("
    SELECT p.*, s.code as session_code, s.nom as session_name
    FROM participants p
    JOIN sessions s ON p.session_id = s.id
    WHERE p.id = ?
");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve.");
}

$appKey = 'app-calculateur-carbone';
if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Acces refuse.");
}

// Recuperer les infos utilisateur depuis la base partagee
$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

$displayName = trim(($userInfo['prenom'] ?? $participant['prenom'] ?? '') . ' ' . ($userInfo['nom'] ?? $participant['nom'] ?? ''));
$organisation = $userInfo['organisation'] ?? '';

// Recuperer les calculs de ce participant pour cette session
$stmt = $db->prepare("SELECT * FROM calculs WHERE session_id = ? AND user_id = ? ORDER BY use_case_id");
$stmt->execute([$participant['session_id'], $participant['user_id']]);
$calculs = $stmt->fetchAll();

$totalCo2 = 0;
foreach ($calculs as $c) {
    $totalCo2 += (float)($c['co2_total'] ?? 0);
}

// Charger les estimations pour afficher les noms des use cases
$estimations = getEstimations();
$useCases = $estimations['use_cases'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculateur Carbone - <?= h($displayName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-green-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-emerald-600 to-green-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Calculateur Carbone</h1>
                    <p class="text-emerald-200 text-sm"><?= h($participant['session_name']) ?> - <?= h($participant['session_code']) ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <button onclick="window.print()" class="bg-emerald-500 hover:bg-emerald-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="bg-emerald-500 hover:bg-emerald-400 px-3 py-1 rounded text-sm">
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8">
        <!-- Participant info -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-emerald-700"><?= h($displayName) ?></h2>
                    <?php if (!empty($organisation)): ?>
                    <p class="text-gray-500 text-sm"><?= h($organisation) ?></p>
                    <?php endif; ?>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-emerald-600"><?= number_format($totalCo2, 1) ?></div>
                    <div class="text-gray-500 text-sm">kg CO2 total</div>
                </div>
            </div>
        </div>

        <!-- Calculs -->
        <?php if (empty($calculs)): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <p class="text-gray-500 text-lg">Aucun calcul pour ce participant.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($calculs as $calcul):
                $useCaseId = $calcul['use_case_id'];
                $useCaseInfo = $useCases[$useCaseId] ?? null;
                $useCaseName = $useCaseInfo['nom'] ?? $useCaseInfo['name'] ?? ('Use case #' . $useCaseId);
                $co2 = (float)($calcul['co2_total'] ?? 0);
                $barWidth = $totalCo2 > 0 ? round(($co2 / $totalCo2) * 100) : 0;
            ?>
            <div class="bg-white rounded-xl shadow p-4 border border-emerald-100">
                <div class="flex justify-between items-start mb-2">
                    <div class="font-semibold text-emerald-700"><?= h($useCaseName) ?></div>
                    <span class="text-sm font-bold text-emerald-600"><?= number_format($co2, 2) ?> kg</span>
                </div>
                <div class="w-full bg-emerald-100 rounded-full h-2 mb-2">
                    <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= $barWidth ?>%"></div>
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
        <?php endif; ?>
    </main>
</body>
</html>
