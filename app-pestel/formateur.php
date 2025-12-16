<?php
require_once 'config/database.php';

if (!isFormateurLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$sessionId = $_SESSION['formateur_session_id'];
$sessionCode = $_SESSION['formateur_session_code'];

// Recuperer infos session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

// Recuperer les participants avec leurs analyses
$stmt = $db->prepare("
    SELECT p.*, ap.nom_projet, ap.completion_percent, ap.is_submitted, ap.submitted_at, ap.updated_at as analyse_updated
    FROM participants p
    LEFT JOIN analyse_pestel ap ON p.id = ap.participant_id
    WHERE p.session_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();

// Stats
$stats = getSessionStats($sessionId);

// Participant selectionne pour visualisation
$selectedParticipant = null;
$selectedPestel = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("
        SELECT p.*, ap.*
        FROM participants p
        LEFT JOIN analyse_pestel ap ON p.id = ap.participant_id
        WHERE p.id = ? AND p.session_id = ?
    ");
    $stmt->execute([$_GET['view'], $sessionId]);
    $selectedParticipant = $stmt->fetch();
    if ($selectedParticipant && $selectedParticipant['pestel_data']) {
        $selectedPestel = json_decode($selectedParticipant['pestel_data'], true);
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
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="p-4">
    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-4 bg-white/90 backdrop-blur rounded-lg shadow-lg p-4">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Interface Formateur - PESTEL</h1>
                <p class="text-gray-600"><?= sanitize($session['nom']) ?></p>
            </div>
            <div class="flex items-center gap-4">
                <div class="bg-indigo-100 px-4 py-2 rounded-lg">
                    <span class="text-sm text-indigo-600">Code session:</span>
                    <span class="font-mono font-bold text-lg text-indigo-800"><?= sanitize($sessionCode) ?></span>
                </div>
                <a href="admin_sessions.php" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg transition">
                    Gerer Sessions
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">
                    Deconnexion
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow p-6">
                <div class="text-3xl font-bold text-indigo-600"><?= $stats['total_participants'] ?></div>
                <div class="text-gray-600">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-6">
                <div class="text-3xl font-bold text-green-600"><?= $stats['submitted_count'] ?></div>
                <div class="text-gray-600">Soumis</div>
            </div>
            <div class="bg-white rounded-xl shadow p-6">
                <div class="text-3xl font-bold text-purple-600"><?= round($stats['avg_completion']) ?>%</div>
                <div class="text-gray-600">Completion moyenne</div>
            </div>
            <div class="bg-white rounded-xl shadow p-6">
                <div class="text-3xl font-bold text-orange-600"><?= $stats['total_participants'] - $stats['submitted_count'] ?></div>
                <div class="text-gray-600">En cours</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Liste des participants -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow">
                    <div class="p-4 border-b">
                        <h2 class="text-lg font-semibold">Participants (<?= count($participants) ?>)</h2>
                    </div>
                    <div class="divide-y max-h-[600px] overflow-y-auto">
                        <?php if (empty($participants)): ?>
                            <div class="p-4 text-gray-500 text-center">
                                Aucun participant pour le moment
                            </div>
                        <?php else: ?>
                            <?php foreach ($participants as $p): ?>
                                <a href="?view=<?= $p['id'] ?>"
                                   class="block p-4 hover:bg-gray-50 transition <?= (isset($_GET['view']) && $_GET['view'] == $p['id']) ? 'bg-indigo-50' : '' ?>">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-medium"><?= sanitize($p['prenom'] . ' ' . $p['nom']) ?></div>
                                            <div class="text-sm text-gray-500"><?= sanitize($p['organisation'] ?? '-') ?></div>
                                            <?php if ($p['nom_projet']): ?>
                                                <div class="text-sm text-indigo-600 mt-1"><?= sanitize($p['nom_projet']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($p['is_submitted']): ?>
                                                <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Soumis</span>
                                            <?php else: ?>
                                                <span class="inline-block bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded">En cours</span>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <div class="text-xs text-gray-500 mb-1"><?= $p['completion_percent'] ?? 0 ?>%</div>
                                                <div class="w-20 bg-gray-200 rounded-full h-2">
                                                    <div class="progress-bar bg-indigo-600 h-2 rounded-full"
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

            <!-- Visualisation de l'analyse selectionnee -->
            <div class="lg:col-span-2">
                <?php if ($selectedParticipant): ?>
                    <div class="bg-white rounded-xl shadow">
                        <div class="p-4 border-b flex justify-between items-center">
                            <div>
                                <h2 class="text-lg font-semibold">
                                    <?= sanitize($selectedParticipant['prenom'] . ' ' . $selectedParticipant['nom']) ?>
                                </h2>
                                <p class="text-sm text-gray-500">
                                    <?= sanitize($selectedParticipant['nom_projet'] ?: 'Sans titre') ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-sm text-gray-500">
                                    Maj: <?= $selectedParticipant['analyse_updated'] ? date('d/m H:i', strtotime($selectedParticipant['analyse_updated'])) : '-' ?>
                                </span>
                                <?php if ($selectedParticipant['is_submitted']): ?>
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                        Soumis le <?= date('d/m/Y H:i', strtotime($selectedParticipant['submitted_at'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($selectedPestel): ?>
                            <div class="p-4 space-y-4">
                                <!-- Infos projet -->
                                <div class="grid grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <div class="text-xs text-gray-500">Zone geographique</div>
                                        <div class="font-medium"><?= sanitize($selectedParticipant['zone'] ?? '-') ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Participants</div>
                                        <div class="font-medium"><?= sanitize($selectedParticipant['participants_analyse'] ?? '-') ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Completion</div>
                                        <div class="font-medium text-indigo-600"><?= $selectedParticipant['completion_percent'] ?? 0 ?>%</div>
                                    </div>
                                </div>

                                <!-- PESTEL Grid -->
                                <div class="grid grid-cols-2 gap-4">
                                    <?php
                                    $categories = [
                                        'politique' => ['color' => 'red', 'icon' => '🏛️', 'label' => 'Politique'],
                                        'economique' => ['color' => 'green', 'icon' => '💰', 'label' => 'Economique'],
                                        'socioculturel' => ['color' => 'purple', 'icon' => '👥', 'label' => 'Socioculturel'],
                                        'technologique' => ['color' => 'blue', 'icon' => '🔬', 'label' => 'Technologique'],
                                        'environnemental' => ['color' => 'teal', 'icon' => '🌱', 'label' => 'Environnemental'],
                                        'legal' => ['color' => 'amber', 'icon' => '⚖️', 'label' => 'Legal']
                                    ];
                                    foreach ($categories as $key => $cat):
                                        $items = $selectedPestel[$key] ?? [];
                                    ?>
                                    <div class="bg-<?= $cat['color'] ?>-50 p-4 rounded-lg border border-<?= $cat['color'] ?>-200">
                                        <h4 class="font-semibold text-<?= $cat['color'] ?>-800 mb-2">
                                            <?= $cat['icon'] ?> <?= $cat['label'] ?>
                                        </h4>
                                        <?php if (!empty(array_filter($items))): ?>
                                            <ul class="text-sm space-y-1">
                                                <?php foreach ($items as $item): ?>
                                                    <?php if (trim($item)): ?>
                                                        <li class="text-<?= $cat['color'] ?>-700">• <?= sanitize($item) ?></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-400 italic">Aucun element</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Synthese -->
                                <?php if ($selectedParticipant['synthese']): ?>
                                <div class="bg-indigo-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-indigo-800 mb-2">🎯 Synthese</h4>
                                    <p class="text-sm text-indigo-700"><?= nl2br(sanitize($selectedParticipant['synthese'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-500">
                                <p>Ce participant n'a pas encore commence son analyse.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
                        <p class="text-4xl mb-4">🌍</p>
                        <p>Selectionnez un participant pour voir son analyse PESTEL</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh toutes les 30 secondes
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
