<?php
/**
 * Vue globale de session - Empreinte Carbone
 * Affiche les scenarios et resultats de votes agreges
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-empreinte-carbone';

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

// Recuperer les scenarios de la session
$stmt = $db->prepare("SELECT * FROM scenarios WHERE session_id = ? ORDER BY id ASC");
$stmt->execute([$sessionId]);
$scenarios = $stmt->fetchAll();

// Pour chaque scenario, recuperer les votes et calculer les agregats
$scenariosData = [];
$totalVotes = 0;
$totalParticipantIds = [];

foreach ($scenarios as $scenario) {
    $stmtVotes = $db->prepare("SELECT * FROM votes WHERE scenario_id = ?");
    $stmtVotes->execute([$scenario['id']]);
    $votes = $stmtVotes->fetchAll();

    $totalVotes += count($votes);

    // Compter votes par option et accumuler scores
    $optionCounts = [1 => 0, 2 => 0, 3 => 0];
    $optionScores = [
        1 => ['impact' => [], 'qualite' => [], 'temps' => []],
        2 => ['impact' => [], 'qualite' => [], 'temps' => []],
        3 => ['impact' => [], 'qualite' => [], 'temps' => []],
    ];

    foreach ($votes as $v) {
        $opt = (int)$v['option_number'];
        if ($opt >= 1 && $opt <= 3) {
            $optionCounts[$opt]++;
            $optionScores[$opt]['impact'][] = (float)($v['impact'] ?? 0);
            $optionScores[$opt]['qualite'][] = (float)($v['qualite'] ?? 0);
            $optionScores[$opt]['temps'][] = (float)($v['temps'] ?? 0);
        }
        if (!empty($v['participant_id'])) {
            $totalParticipantIds[$v['participant_id']] = true;
        }
    }

    // Calculer les moyennes par option
    $optionAvgs = [];
    foreach ([1, 2, 3] as $opt) {
        $c = count($optionScores[$opt]['impact']);
        $optionAvgs[$opt] = [
            'count' => $optionCounts[$opt],
            'avg_impact' => $c > 0 ? round(array_sum($optionScores[$opt]['impact']) / $c, 1) : 0,
            'avg_qualite' => $c > 0 ? round(array_sum($optionScores[$opt]['qualite']) / $c, 1) : 0,
            'avg_temps' => $c > 0 ? round(array_sum($optionScores[$opt]['temps']) / $c, 1) : 0,
        ];
    }

    $optionNames = [
        1 => $scenario['option1_name'] ?? 'Option 1',
        2 => $scenario['option2_name'] ?? 'Option 2',
        3 => $scenario['option3_name'] ?? 'Option 3',
    ];

    $scenariosData[] = [
        'scenario' => $scenario,
        'votes_count' => count($votes),
        'option_names' => $optionNames,
        'option_avgs' => $optionAvgs,
    ];
}

// Statistiques globales
$scenariosCount = count($scenarios);
$participantsCount = count($totalParticipantIds);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empreinte Carbone - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        .scenario-card { transition: all 0.3s ease; }
        .scenario-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-emerald-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Empreinte Carbone</h1>
                    <p class="text-green-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
                </div>
                <div class="flex items-center gap-4">
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
        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $scenariosCount ?></div>
                <div class="text-gray-500 text-sm">Scenarios</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants votants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $totalVotes ?></div>
                <div class="text-gray-500 text-sm">Votes totaux</div>
            </div>
        </div>

        <!-- Scenarios -->
        <div class="space-y-8">
            <?php foreach ($scenariosData as $idx => $sd):
                $sc = $sd['scenario'];
                $maxVotes = max($sd['option_avgs'][1]['count'], $sd['option_avgs'][2]['count'], $sd['option_avgs'][3]['count'], 1);
            ?>
            <div class="scenario-card bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- En-tete scenario -->
                <div class="bg-gradient-to-r from-green-500 to-emerald-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-green-200 text-xs uppercase font-semibold">Scenario <?= $idx + 1 ?></span>
                            <h3 class="font-bold text-lg"><?= h($sc['title']) ?></h3>
                            <?php if (!empty($sc['description'])): ?>
                            <p class="text-green-100 text-sm mt-1"><?= h($sc['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="bg-white/30 px-3 py-1 rounded text-sm"><?= $sd['votes_count'] ?> votes</span>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <?php if ($sd['votes_count'] === 0): ?>
                    <p class="text-gray-400 text-center italic">Aucun vote pour ce scenario.</p>
                    <?php else: ?>
                    <!-- Options avec barres de votes et scores moyens -->
                    <div class="space-y-6">
                        <?php
                        $optColors = [
                            1 => ['bg' => 'bg-green-100', 'bar' => 'bg-green-500', 'text' => 'text-green-700', 'light' => 'text-green-500'],
                            2 => ['bg' => 'bg-blue-100', 'bar' => 'bg-blue-500', 'text' => 'text-blue-700', 'light' => 'text-blue-500'],
                            3 => ['bg' => 'bg-amber-100', 'bar' => 'bg-amber-500', 'text' => 'text-amber-700', 'light' => 'text-amber-500'],
                        ];
                        foreach ([1, 2, 3] as $opt):
                            $avg = $sd['option_avgs'][$opt];
                            $barW = $maxVotes > 0 ? round(($avg['count'] / $maxVotes) * 100) : 0;
                            $col = $optColors[$opt];
                        ?>
                        <div class="<?= $col['bg'] ?> rounded-lg p-4">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="font-bold <?= $col['text'] ?>"><?= h($sd['option_names'][$opt]) ?></h4>
                                <span class="<?= $col['text'] ?> font-bold text-lg"><?= $avg['count'] ?> vote<?= $avg['count'] > 1 ? 's' : '' ?></span>
                            </div>
                            <!-- Barre de votes -->
                            <div class="w-full bg-white/60 rounded-full h-3 mb-3">
                                <div class="<?= $col['bar'] ?> h-3 rounded-full" style="width: <?= $barW ?>%"></div>
                            </div>
                            <?php if ($avg['count'] > 0): ?>
                            <!-- Scores moyens -->
                            <div class="grid grid-cols-3 gap-3">
                                <div class="text-center">
                                    <div class="text-xs <?= $col['light'] ?> font-semibold uppercase">Impact</div>
                                    <div class="text-lg font-bold <?= $col['text'] ?>"><?= $avg['avg_impact'] ?>/5</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs <?= $col['light'] ?> font-semibold uppercase">Qualite</div>
                                    <div class="text-lg font-bold <?= $col['text'] ?>"><?= $avg['avg_qualite'] ?>/5</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs <?= $col['light'] ?> font-semibold uppercase">Temps</div>
                                    <div class="text-lg font-bold <?= $col['text'] ?>"><?= $avg['avg_temps'] ?>/5</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($scenariosData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <div class="text-6xl mb-4">&#x1F3AF;</div>
                <p class="text-gray-500 text-lg">Aucun scenario pour cette session.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
