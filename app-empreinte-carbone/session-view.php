<?php
/**
 * Vue globale de session - Empreinte Carbone IA
 * Affiche les scenarios et votes/choix des participants
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

// Recuperer tous les scenarios de la session
$stmt = $db->prepare("SELECT * FROM scenarios WHERE session_id = ? ORDER BY created_at DESC");
$stmt->execute([$sessionId]);
$scenarios = $stmt->fetchAll();

// Pour chaque scenario, recuperer les votes et les moyennes
$scenarioData = [];
foreach ($scenarios as $sc) {
    $averages = calculateAverages($db, $sc['id']);
    $votes = getVotes($db, $sc['id']);

    // Enrichir les votes avec infos participant
    $votesByParticipant = [];
    foreach ($votes as $v) {
        $pid = $v['participant_id'];
        if (!isset($votesByParticipant[$pid])) {
            // Recuperer le user_id depuis la table participants
            $pStmt = $db->prepare("SELECT user_id FROM participants WHERE id = ?");
            $pStmt->execute([$pid]);
            $pRow = $pStmt->fetch();
            $userId = $pRow['user_id'] ?? null;

            $userName = 'Participant #' . $pid;
            $userOrg = '';
            if ($userId) {
                $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
                $userStmt->execute([$userId]);
                $userInfo = $userStmt->fetch();
                if ($userInfo) {
                    $userName = trim(($userInfo['prenom'] ?? '') . ' ' . ($userInfo['nom'] ?? ''));
                    $userOrg = $userInfo['organisation'] ?? '';
                }
            }

            $votesByParticipant[$pid] = [
                'name' => $userName,
                'organisation' => $userOrg,
                'votes' => []
            ];
        }
        $votesByParticipant[$pid]['votes'][$v['option_number']] = $v;
    }

    $totalVoters = 0;
    for ($i = 1; $i <= 3; $i++) {
        $totalVoters = max($totalVoters, $averages[$i]['voters'] ?? 0);
    }

    $scenarioData[] = [
        'scenario' => $sc,
        'averages' => $averages,
        'votesByParticipant' => $votesByParticipant,
        'totalVoters' => count($votesByParticipant),
    ];
}

// Statistiques globales
$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM participants WHERE session_id = ?");
$stmt->execute([$sessionId]);
$totalParticipants = $stmt->fetch()['count'];

$totalScenarios = count($scenarios);
$totalVotes = 0;
foreach ($scenarioData as $sd) {
    $totalVotes += $sd['totalVoters'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empreinte Carbone IA - Vue Session - <?= h($session['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>🌱</text></svg>">
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
                    <h1 class="text-2xl font-bold">🌱 Empreinte Carbone IA</h1>
                    <p class="text-green-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
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
                <div class="text-3xl font-bold text-green-600"><?= $totalParticipants ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?= $totalScenarios ?></div>
                <div class="text-gray-500 text-sm">Scenarios</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $totalVotes ?></div>
                <div class="text-gray-500 text-sm">Participants ayant vote</div>
            </div>
        </div>

        <!-- Scenarios et resultats -->
        <?php if (empty($scenarioData)): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <p class="text-gray-400 text-lg">Aucun scenario pour le moment.</p>
        </div>
        <?php endif; ?>

        <?php foreach ($scenarioData as $sd):
            $sc = $sd['scenario'];
            $avg = $sd['averages'];
        ?>
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-green-500 to-emerald-500 text-white p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold"><?= h($sc['title']) ?></h2>
                        <?php if (!empty($sc['description'])): ?>
                        <p class="text-green-200 text-sm mt-1"><?= h($sc['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <span class="text-green-200 text-sm"><?= $sd['totalVoters'] ?> votant(s)</span>
                        <?php if (!$sc['is_active']): ?>
                        <span class="bg-gray-500 text-white text-xs px-2 py-0.5 rounded ml-2">Inactif</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <!-- Resultats par option -->
                <h3 class="font-bold text-gray-700 mb-4">Resultats par option</h3>
                <div class="grid md:grid-cols-3 gap-4 mb-6">
                    <?php
                    $optionNames = [
                        1 => $sc['option1_name'] ?? 'Option 1',
                        2 => $sc['option2_name'] ?? 'Option 2',
                        3 => $sc['option3_name'] ?? 'Option 3',
                    ];
                    $optionDescs = [
                        1 => $sc['option1_desc'] ?? '',
                        2 => $sc['option2_desc'] ?? '',
                        3 => $sc['option3_desc'] ?? '',
                    ];
                    $optColors = [1 => 'red', 2 => 'yellow', 3 => 'green'];
                    ?>
                    <?php for ($opt = 1; $opt <= 3; $opt++): ?>
                    <div class="border rounded-lg p-4 <?= $opt === 1 ? 'border-red-200 bg-red-50' : ($opt === 2 ? 'border-yellow-200 bg-yellow-50' : 'border-green-200 bg-green-50') ?>">
                        <h4 class="font-bold text-sm mb-1"><?= h($optionNames[$opt]) ?></h4>
                        <p class="text-xs text-gray-500 mb-3"><?= h($optionDescs[$opt]) ?></p>
                        <div class="space-y-2">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">Votants</span>
                                <span class="font-bold"><?= $avg[$opt]['voters'] ?></span>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs mb-0.5">
                                    <span class="text-gray-600">Impact env.</span>
                                    <span class="font-medium"><?= $avg[$opt]['impact'] ?>/3</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-red-400 h-2 rounded-full" style="width: <?= round($avg[$opt]['impact'] / 3 * 100) ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs mb-0.5">
                                    <span class="text-gray-600">Qualite</span>
                                    <span class="font-medium"><?= $avg[$opt]['qualite'] ?>/5</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-400 h-2 rounded-full" style="width: <?= round($avg[$opt]['qualite'] / 5 * 100) ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs mb-0.5">
                                    <span class="text-gray-600">Temps</span>
                                    <span class="font-medium"><?= $avg[$opt]['temps'] ?>/3</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-purple-400 h-2 rounded-full" style="width: <?= round($avg[$opt]['temps'] / 3 * 100) ?>%"></div>
                                </div>
                            </div>
                            <div class="pt-2 border-t">
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-600 font-medium">Score global</span>
                                    <span class="font-bold text-green-700"><?= $avg[$opt]['score_global'] ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- Detail des votes par participant -->
                <?php if (!empty($sd['votesByParticipant'])): ?>
                <h3 class="font-bold text-gray-700 mb-3">Detail des votes</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-green-50">
                                <th class="text-left px-3 py-2 font-semibold text-green-700">Participant</th>
                                <?php for ($opt = 1; $opt <= 3; $opt++): ?>
                                <th class="text-center px-3 py-2 font-semibold text-green-700" colspan="3">
                                    <?= h($optionNames[$opt]) ?>
                                    <div class="text-xs font-normal text-gray-400">Imp / Qual / Tps</div>
                                </th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sd['votesByParticipant'] as $pid => $pData): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium text-gray-800">
                                    <?= h($pData['name']) ?>
                                    <?php if (!empty($pData['organisation'])): ?>
                                    <span class="text-gray-400 text-xs">(<?= h($pData['organisation']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <?php for ($opt = 1; $opt <= 3; $opt++): ?>
                                <?php $v = $pData['votes'][$opt] ?? null; ?>
                                <?php if ($v): ?>
                                <td class="px-1 py-2 text-center text-xs"><?= (int)$v['impact'] ?></td>
                                <td class="px-1 py-2 text-center text-xs"><?= (int)$v['qualite'] ?></td>
                                <td class="px-1 py-2 text-center text-xs"><?= (int)$v['temps'] ?></td>
                                <?php else: ?>
                                <td class="px-1 py-2 text-center text-gray-300 text-xs" colspan="3">-</td>
                                <?php endif; ?>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </main>
</body>
</html>
