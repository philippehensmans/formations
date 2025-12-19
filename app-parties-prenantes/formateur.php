<?php
require_once 'config/database.php';

if (!isFormateurLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$sessionId = $_SESSION['formateur_session_id'];
$sessionCode = $_SESSION['formateur_session_code'];
$categories = getCategories();

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

$stmt = $db->prepare("
    SELECT p.*, c.titre_projet, c.completion_percent, c.is_submitted, c.submitted_at, c.updated_at as carto_updated, c.stakeholders_data
    FROM participants p
    LEFT JOIN cartographie c ON p.id = c.participant_id
    WHERE p.session_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();

$stats = getSessionStats($sessionId);

$selectedParticipant = null;
$selectedStakeholders = [];
if (isset($_GET['view'])) {
    $stmt = $db->prepare("
        SELECT p.*, c.*
        FROM participants p
        LEFT JOIN cartographie c ON p.id = c.participant_id
        WHERE p.id = ? AND p.session_id = ?
    ");
    $stmt->execute([$_GET['view'], $sessionId]);
    $selectedParticipant = $stmt->fetch();
    if ($selectedParticipant && $selectedParticipant['stakeholders_data']) {
        $selectedStakeholders = json_decode($selectedParticipant['stakeholders_data'], true) ?: [];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formateur - <?= sanitize($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .progress-bar { transition: width 0.3s ease; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-gray-900 text-white p-4">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="text-xl font-bold">Interface Formateur - Parties Prenantes</h1>
                <p class="text-gray-400"><?= sanitize($session['nom']) ?></p>
            </div>
            <div class="flex items-center gap-4">
                <div class="bg-gray-800 px-4 py-2 rounded">
                    <span class="text-sm text-gray-400">Code:</span>
                    <span class="font-mono font-bold"><?= sanitize($sessionCode) ?></span>
                </div>
                <a href="admin_sessions.php" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded transition">Sessions</a>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded transition">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-gray-900"><?= $stats['total_participants'] ?></div>
                <div class="text-gray-600">Participants</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-green-600"><?= $stats['submitted_count'] ?></div>
                <div class="text-gray-600">Soumis</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-blue-900"><?= round($stats['avg_completion']) ?>%</div>
                <div class="text-gray-600">Completion moyenne</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-orange-600"><?= $stats['total_participants'] - $stats['submitted_count'] ?></div>
                <div class="text-gray-600">En cours</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Liste participants -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-4 border-b">
                        <h2 class="font-semibold">Participants (<?= count($participants) ?>)</h2>
                    </div>
                    <div class="divide-y max-h-[600px] overflow-y-auto">
                        <?php if (empty($participants)): ?>
                            <div class="p-4 text-gray-500 text-center">Aucun participant</div>
                        <?php else: ?>
                            <?php foreach ($participants as $p): ?>
                                <?php $stakeholderCount = $p['stakeholders_data'] ? count(json_decode($p['stakeholders_data'], true) ?: []) : 0; ?>
                                <a href="?view=<?= $p['id'] ?>"
                                   class="block p-4 hover:bg-gray-50 transition <?= (isset($_GET['view']) && $_GET['view'] == $p['id']) ? 'bg-blue-50' : '' ?>">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-medium"><?= sanitize($p['prenom'] . ' ' . $p['nom']) ?></div>
                                            <div class="text-sm text-gray-500"><?= sanitize($p['organisation'] ?? '-') ?></div>
                                            <?php if ($p['titre_projet']): ?>
                                                <div class="text-sm text-blue-900 mt-1"><?= sanitize($p['titre_projet']) ?></div>
                                            <?php endif; ?>
                                            <div class="text-xs text-gray-400 mt-1"><?= $stakeholderCount ?> parties prenantes</div>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($p['is_submitted']): ?>
                                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Soumis</span>
                                            <?php else: ?>
                                                <span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded">En cours</span>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <div class="text-xs text-gray-500 mb-1"><?= $p['completion_percent'] ?? 0 ?>%</div>
                                                <div class="w-20 bg-gray-200 rounded-full h-2">
                                                    <div class="progress-bar bg-blue-900 h-2 rounded-full"
                                                         style="width: <?= $p['completion_percent'] ?? 0 ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Visualisation -->
            <div class="lg:col-span-2">
                <?php if ($selectedParticipant): ?>
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-4 border-b flex justify-between items-center">
                            <div>
                                <h2 class="font-semibold"><?= sanitize($selectedParticipant['prenom'] . ' ' . $selectedParticipant['nom']) ?></h2>
                                <p class="text-sm text-gray-500"><?= sanitize($selectedParticipant['titre_projet'] ?: 'Sans titre') ?></p>
                            </div>
                            <?php if ($selectedParticipant['is_submitted']): ?>
                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                    Soumis le <?= date('d/m/Y H:i', strtotime($selectedParticipant['submitted_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="p-4">
                            <?php if (!empty($selectedStakeholders)): ?>
                                <h3 class="font-semibold mb-4">Parties Prenantes (<?= count($selectedStakeholders) ?>)</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-100">
                                                <th class="text-left p-2">Nom</th>
                                                <th class="text-left p-2">Categorie</th>
                                                <th class="text-center p-2">Influence</th>
                                                <th class="text-center p-2">Interet</th>
                                                <th class="text-left p-2">Strategie</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($selectedStakeholders as $s): ?>
                                                <?php
                                                $quadrant = '';
                                                if ($s['influence'] > 5 && $s['interest'] > 5) $quadrant = 'Gerer etroitement';
                                                elseif ($s['influence'] > 5) $quadrant = 'Maintenir satisfait';
                                                elseif ($s['interest'] > 5) $quadrant = 'Tenir informe';
                                                else $quadrant = 'Surveiller';
                                                ?>
                                                <tr class="border-b">
                                                    <td class="p-2">
                                                        <span class="inline-block w-3 h-3 rounded-full mr-2" style="background: <?= $categories[$s['category']]['color'] ?? '#999' ?>"></span>
                                                        <?= sanitize($s['name']) ?>
                                                    </td>
                                                    <td class="p-2 text-gray-600"><?= $categories[$s['category']]['label'] ?? $s['category'] ?></td>
                                                    <td class="p-2 text-center"><?= $s['influence'] ?>/10</td>
                                                    <td class="p-2 text-center"><?= $s['interest'] ?>/10</td>
                                                    <td class="p-2 text-gray-600"><?= $quadrant ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($selectedParticipant['notes']): ?>
                                    <div class="mt-4 p-4 bg-gray-50 rounded">
                                        <h4 class="font-semibold mb-2">Notes</h4>
                                        <p class="text-sm text-gray-600"><?= nl2br(sanitize($selectedParticipant['notes'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-8">Aucune partie prenante ajoutee</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        <p class="text-4xl mb-4">🎯</p>
                        <p>Selectionnez un participant pour voir sa cartographie</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>setTimeout(() => location.reload(), 30000);</script>
</body>
</html>
