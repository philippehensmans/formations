<?php
/**
 * Vue en lecture seule de l'analyse SWOT d'un participant
 * Accessible par le formateur
 */

// Charger shared-auth pour l'authentification formateur
require_once __DIR__ . '/../shared-auth/config.php';

// Charger la config locale pour les donnees
require_once 'config/database.php';

// Verifier que c'est un formateur
if (!isFormateur()) {
    header('Location: index.php');
    exit;
}

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();

// Recuperer le participant
$stmt = $db->prepare("
    SELECT p.*, s.code as session_code, s.name as session_name
    FROM participants p
    JOIN sessions s ON p.session_id = s.id
    WHERE p.id = ?
");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve");
}

// Recuperer l'analyse SWOT
$stmt = $db->prepare("SELECT * FROM analyses WHERE participant_id = ?");
$stmt->execute([$participantId]);
$analyse = $stmt->fetch();

if (!$analyse) {
    $swotData = ['strengths' => [], 'weaknesses' => [], 'opportunities' => [], 'threats' => []];
    $towsData = null;
    $submitted = 0;
} else {
    $swotData = json_decode($analyse['swot_data'] ?? '{}', true) ?: ['strengths' => [], 'weaknesses' => [], 'opportunities' => [], 'threats' => []];
    $towsData = json_decode($analyse['tows_data'] ?? 'null', true);
    $submitted = $analyse['submitted'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse SWOT - <?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Barre de navigation -->
    <div class="bg-gradient-to-r from-blue-600 to-cyan-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></span>
                <span class="text-blue-200 text-sm ml-2"><?= h($participant['session_name']) ?> (<?= h($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm px-3 py-1 rounded-full <?= $submitted ? 'bg-green-500' : 'bg-yellow-500' ?>">
                    <?= $submitted ? 'Soumis' : 'Brouillon' ?>
                </span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Analyse SWOT / TOWS</h1>
            <p class="text-gray-600"><?= h($participant['organisation'] ?? 'Organisation non renseignee') ?></p>
        </div>

        <!-- Matrice SWOT -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Matrice SWOT</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Forces (Strengths) -->
                <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4">
                    <h3 class="font-bold text-green-800 mb-3 flex items-center">
                        <span class="text-2xl mr-2">💪</span> FORCES (Strengths)
                    </h3>
                    <ul class="space-y-2">
                        <?php if (empty($swotData['strengths'])): ?>
                            <li class="text-gray-400 italic">Aucune force identifiee</li>
                        <?php else: ?>
                            <?php foreach ($swotData['strengths'] as $item): ?>
                                <li class="flex items-start">
                                    <span class="text-green-600 mr-2">•</span>
                                    <span class="text-sm"><?= h($item) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Faiblesses (Weaknesses) -->
                <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4">
                    <h3 class="font-bold text-red-800 mb-3 flex items-center">
                        <span class="text-2xl mr-2">⚠️</span> FAIBLESSES (Weaknesses)
                    </h3>
                    <ul class="space-y-2">
                        <?php if (empty($swotData['weaknesses'])): ?>
                            <li class="text-gray-400 italic">Aucune faiblesse identifiee</li>
                        <?php else: ?>
                            <?php foreach ($swotData['weaknesses'] as $item): ?>
                                <li class="flex items-start">
                                    <span class="text-red-600 mr-2">•</span>
                                    <span class="text-sm"><?= h($item) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Opportunites (Opportunities) -->
                <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4">
                    <h3 class="font-bold text-blue-800 mb-3 flex items-center">
                        <span class="text-2xl mr-2">🎯</span> OPPORTUNITES (Opportunities)
                    </h3>
                    <ul class="space-y-2">
                        <?php if (empty($swotData['opportunities'])): ?>
                            <li class="text-gray-400 italic">Aucune opportunite identifiee</li>
                        <?php else: ?>
                            <?php foreach ($swotData['opportunities'] as $item): ?>
                                <li class="flex items-start">
                                    <span class="text-blue-600 mr-2">•</span>
                                    <span class="text-sm"><?= h($item) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Menaces (Threats) -->
                <div class="bg-orange-50 border-2 border-orange-300 rounded-lg p-4">
                    <h3 class="font-bold text-orange-800 mb-3 flex items-center">
                        <span class="text-2xl mr-2">⚡</span> MENACES (Threats)
                    </h3>
                    <ul class="space-y-2">
                        <?php if (empty($swotData['threats'])): ?>
                            <li class="text-gray-400 italic">Aucune menace identifiee</li>
                        <?php else: ?>
                            <?php foreach ($swotData['threats'] as $item): ?>
                                <li class="flex items-start">
                                    <span class="text-orange-600 mr-2">•</span>
                                    <span class="text-sm"><?= h($item) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Matrice TOWS (si disponible) -->
        <?php if ($towsData): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Matrice TOWS (Strategies)</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Strategies SO -->
                <div class="bg-gradient-to-br from-green-50 to-blue-50 border-2 border-green-300 rounded-lg p-4">
                    <h3 class="font-bold text-green-800 mb-3">SO - Strategies offensives</h3>
                    <p class="text-xs text-gray-600 mb-2">Forces + Opportunites</p>
                    <ul class="space-y-2">
                        <?php if (empty($towsData['so'])): ?>
                            <li class="text-gray-400 italic text-sm">Aucune strategie</li>
                        <?php else: ?>
                            <?php foreach ($towsData['so'] as $item): ?>
                                <li class="text-sm"><?= h($item) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Strategies WO -->
                <div class="bg-gradient-to-br from-red-50 to-blue-50 border-2 border-blue-300 rounded-lg p-4">
                    <h3 class="font-bold text-blue-800 mb-3">WO - Strategies de reorientation</h3>
                    <p class="text-xs text-gray-600 mb-2">Faiblesses + Opportunites</p>
                    <ul class="space-y-2">
                        <?php if (empty($towsData['wo'])): ?>
                            <li class="text-gray-400 italic text-sm">Aucune strategie</li>
                        <?php else: ?>
                            <?php foreach ($towsData['wo'] as $item): ?>
                                <li class="text-sm"><?= h($item) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Strategies ST -->
                <div class="bg-gradient-to-br from-green-50 to-orange-50 border-2 border-orange-300 rounded-lg p-4">
                    <h3 class="font-bold text-orange-800 mb-3">ST - Strategies de confrontation</h3>
                    <p class="text-xs text-gray-600 mb-2">Forces + Menaces</p>
                    <ul class="space-y-2">
                        <?php if (empty($towsData['st'])): ?>
                            <li class="text-gray-400 italic text-sm">Aucune strategie</li>
                        <?php else: ?>
                            <?php foreach ($towsData['st'] as $item): ?>
                                <li class="text-sm"><?= h($item) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Strategies WT -->
                <div class="bg-gradient-to-br from-red-50 to-orange-50 border-2 border-red-300 rounded-lg p-4">
                    <h3 class="font-bold text-red-800 mb-3">WT - Strategies defensives</h3>
                    <p class="text-xs text-gray-600 mb-2">Faiblesses + Menaces</p>
                    <ul class="space-y-2">
                        <?php if (empty($towsData['wt'])): ?>
                            <li class="text-gray-400 italic text-sm">Aucune strategie</li>
                        <?php else: ?>
                            <?php foreach ($towsData['wt'] as $item): ?>
                                <li class="text-sm"><?= h($item) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
