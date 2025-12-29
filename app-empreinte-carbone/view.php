<?php
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/config.php';

$appKey = 'app-empreinte-carbone';

// Vérifier accès formateur
if (!isLoggedIn() || !isFormateur()) {
    header('Location: formateur.php');
    exit;
}

// Vérifier accès à la session
$sessionId = (int)($_GET['session'] ?? 0);
if (!canAccessSession($appKey, $sessionId)) {
    die('Accès refusé');
}

$db = getDB();

// Récupérer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die('Session non trouvée');
}

// Récupérer le scénario actif
$scenario = getActiveScenario($db, $sessionId);
$results = $scenario ? calculateAverages($db, $scenario['id']) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌱 Résultats - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .result-bar {
            transition: width 0.8s ease;
        }
        .winner {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            50% { box-shadow: 0 0 0 20px rgba(34, 197, 94, 0); }
        }
        .score-big {
            font-size: 4rem;
            line-height: 1;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-800 to-green-600 min-h-screen p-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-green-800">🌱 Évaluation Empreinte Carbone IA</h1>
                    <p class="text-gray-600 text-lg"><?= h($session['nom']) ?></p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Code session</div>
                    <div class="text-2xl font-mono font-bold text-green-600"><?= h($session['code']) ?></div>
                </div>
            </div>
        </div>

        <?php if (!$scenario): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <div class="text-6xl mb-4">⏳</div>
            <h2 class="text-2xl font-bold text-gray-700">Aucun scénario actif</h2>
            <p class="text-gray-500">Le formateur n'a pas encore lancé de scénario.</p>
        </div>
        <?php else: ?>

        <!-- Scénario -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-green-800 mb-2"><?= h($scenario['title']) ?></h2>
            <p class="text-gray-600 bg-green-50 p-4 rounded-lg text-lg"><?= nl2br(h($scenario['description'])) ?></p>
        </div>

        <!-- Résultats -->
        <div class="grid md:grid-cols-3 gap-6 mb-6">
            <?php
            $maxScore = 0;
            $bestOption = 0;
            for ($i = 1; $i <= 3; $i++) {
                if ($results[$i]['score_global'] > $maxScore) {
                    $maxScore = $results[$i]['score_global'];
                    $bestOption = $i;
                }
            }

            $colors = [1 => 'blue', 2 => 'purple', 3 => 'orange'];

            for ($i = 1; $i <= 3; $i++):
                $isWinner = ($i == $bestOption && $maxScore > 0);
                $color = $colors[$i];
            ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden <?= $isWinner ? 'winner ring-4 ring-green-400' : '' ?>">
                <div class="bg-gradient-to-r from-<?= $color ?>-500 to-<?= $color ?>-600 text-white p-4">
                    <h3 class="font-bold text-xl"><?= h($scenario["option{$i}_name"]) ?></h3>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 text-sm mb-6 min-h-[40px]"><?= h($scenario["option{$i}_desc"]) ?></p>

                    <!-- Barres de résultats -->
                    <div class="space-y-4 mb-6">
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="font-medium">🌍 Impact environnemental</span>
                                <span class="font-bold"><?= $results[$i]['impact'] ?: '-' ?>/3</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-4">
                                <div class="result-bar bg-red-400 h-4 rounded-full" style="width: <?= ($results[$i]['impact'] / 3 * 100) ?>%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="font-medium">⭐ Qualité du résultat</span>
                                <span class="font-bold"><?= $results[$i]['qualite'] ?: '-' ?>/5</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-4">
                                <div class="result-bar bg-yellow-400 h-4 rounded-full" style="width: <?= ($results[$i]['qualite'] / 5 * 100) ?>%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="font-medium">⏱️ Gain de temps</span>
                                <span class="font-bold"><?= $results[$i]['temps'] ?: '-' ?>/3</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-4">
                                <div class="result-bar bg-blue-400 h-4 rounded-full" style="width: <?= ($results[$i]['temps'] / 3 * 100) ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Score -->
                    <div class="text-center pt-4 border-t">
                        <div class="text-gray-500 text-sm mb-1">Score global</div>
                        <div class="score-big font-bold <?= $isWinner ? 'text-green-500' : 'text-gray-700' ?>">
                            <?= $results[$i]['score_global'] ?: '-' ?>
                        </div>
                        <div class="text-gray-400">/100</div>
                        <div class="mt-2 text-sm text-gray-500">
                            <?= $results[$i]['voters'] ?> vote(s)
                        </div>
                        <?php if ($isWinner): ?>
                        <div class="mt-3 inline-block px-4 py-2 bg-green-100 text-green-700 rounded-full font-bold">
                            🏆 Meilleur équilibre
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Légende -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="font-bold text-green-800 mb-4">📊 Comment lire les résultats ?</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-700 mb-2">Critères évalués</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>🌍 <strong>Impact environnemental</strong> : 1 = faible impact, 3 = fort impact</li>
                        <li>⭐ <strong>Qualité du résultat</strong> : 1 = qualité faible, 5 = excellente</li>
                        <li>⏱️ <strong>Gain de temps</strong> : 1 = peu de gain, 3 = gain important</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-700 mb-2">Score global (/100)</h4>
                    <p class="text-sm text-gray-600">
                        Le score combine les trois critères en favorisant les solutions qui offrent
                        une bonne qualité et un gain de temps tout en minimisant l'impact environnemental.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh toutes les 3 secondes
        setTimeout(() => location.reload(), 3000);
    </script>
</body>
</html>
