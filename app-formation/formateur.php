<?php
require_once 'config/database.php';

if (!isFormateurLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$sessionId = $_SESSION['session_id'];
$sessionCode = $_SESSION['session_code'];

// Recuperer infos session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

// Recuperer les participants avec leurs cadres logiques
$stmt = $db->prepare("
    SELECT p.*, cl.titre_projet, cl.completion_percent, cl.is_submitted, cl.submitted_at, cl.updated_at as cadre_updated
    FROM participants p
    LEFT JOIN cadre_logique cl ON p.id = cl.participant_id
    WHERE p.session_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();

// Stats
$stats = getSessionStats($sessionId);

// Participant selectionne pour visualisation
$selectedParticipant = null;
$selectedCadre = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("
        SELECT p.*, cl.*
        FROM participants p
        LEFT JOIN cadre_logique cl ON p.id = cl.participant_id
        WHERE p.id = ? AND p.session_id = ?
    ");
    $stmt->execute([$_GET['view'], $sessionId]);
    $selectedParticipant = $stmt->fetch();
    if ($selectedParticipant && $selectedParticipant['matrice_data']) {
        $selectedCadre = json_decode($selectedParticipant['matrice_data'], true);
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
<body class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Interface Formateur</h1>
                    <p class="text-purple-200"><?= sanitize($session['nom']) ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 px-4 py-2 rounded-lg">
                        <span class="text-sm">Code session:</span>
                        <span class="font-mono font-bold text-lg"><?= sanitize($sessionCode) ?></span>
                    </div>
                    <a href="admin_sessions.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition">
                        Gerer Sessions
                    </a>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition">
                        Deconnexion
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-6">
                <div class="text-3xl font-bold text-purple-600"><?= $stats['total_participants'] ?></div>
                <div class="text-gray-600">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-6">
                <div class="text-3xl font-bold text-green-600"><?= $stats['submitted_count'] ?></div>
                <div class="text-gray-600">Soumis</div>
            </div>
            <div class="bg-white rounded-xl shadow p-6">
                <div class="text-3xl font-bold text-blue-600"><?= round($stats['avg_completion']) ?>%</div>
                <div class="text-gray-600">Completion moyenne</div>
            </div>
            <div class="bg-white rounded-xl shadow p-6">
                <div class="text-3xl font-bold text-orange-600"><?= $stats['total_participants'] - $stats['submitted_count'] ?></div>
                <div class="text-gray-600">En cours</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
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
                                   class="block p-4 hover:bg-gray-50 transition <?= (isset($_GET['view']) && $_GET['view'] == $p['id']) ? 'bg-purple-50' : '' ?>">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-medium"><?= sanitize($p['prenom'] . ' ' . $p['nom']) ?></div>
                                            <div class="text-sm text-gray-500"><?= sanitize($p['organisation'] ?? '-') ?></div>
                                            <?php if ($p['titre_projet']): ?>
                                                <div class="text-sm text-purple-600 mt-1"><?= sanitize($p['titre_projet']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($p['is_submitted']): ?>
                                                <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded">
                                                    Soumis
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-block bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded">
                                                    En cours
                                                </span>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <div class="text-xs text-gray-500 mb-1"><?= $p['completion_percent'] ?? 0 ?>%</div>
                                                <div class="w-20 bg-gray-200 rounded-full h-2">
                                                    <div class="progress-bar bg-purple-600 h-2 rounded-full"
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

            <!-- Visualisation du cadre selectionne -->
            <div class="lg:col-span-2">
                <?php if ($selectedParticipant): ?>
                    <div class="bg-white rounded-xl shadow">
                        <div class="p-4 border-b flex justify-between items-center">
                            <div>
                                <h2 class="text-lg font-semibold">
                                    <?= sanitize($selectedParticipant['prenom'] . ' ' . $selectedParticipant['nom']) ?>
                                </h2>
                                <p class="text-sm text-gray-500">
                                    <?= sanitize($selectedParticipant['titre_projet'] ?: 'Sans titre') ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-sm text-gray-500">
                                    Maj: <?= $selectedParticipant['cadre_updated'] ? date('d/m H:i', strtotime($selectedParticipant['cadre_updated'])) : '-' ?>
                                </span>
                                <?php if ($selectedParticipant['is_submitted']): ?>
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                        Soumis le <?= date('d/m/Y H:i', strtotime($selectedParticipant['submitted_at'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($selectedCadre): ?>
                            <div class="p-4 space-y-4">
                                <!-- Infos projet -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <div class="text-xs text-gray-500">Organisation</div>
                                        <div class="font-medium"><?= sanitize($selectedParticipant['organisation'] ?? '-') ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Zone geographique</div>
                                        <div class="font-medium"><?= sanitize($selectedParticipant['zone_geo'] ?? '-') ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Duree</div>
                                        <div class="font-medium"><?= sanitize($selectedParticipant['duree'] ?? '-') ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Completion</div>
                                        <div class="font-medium text-purple-600"><?= $selectedParticipant['completion_percent'] ?? 0 ?>%</div>
                                    </div>
                                </div>

                                <!-- Matrice -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr class="bg-gray-100">
                                                <th class="border p-2 text-left w-32">Niveau</th>
                                                <th class="border p-2 text-left">Description</th>
                                                <th class="border p-2 text-left">Indicateurs</th>
                                                <th class="border p-2 text-left">Sources</th>
                                                <th class="border p-2 text-left">Hypotheses</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Objectif Global -->
                                            <tr class="bg-blue-50">
                                                <td class="border p-2 font-medium text-blue-800">Objectif Global</td>
                                                <td class="border p-2"><?= nl2br(sanitize($selectedCadre['objectif_global']['description'] ?? '')) ?></td>
                                                <td class="border p-2"><?= nl2br(sanitize($selectedCadre['objectif_global']['indicateurs'] ?? '')) ?></td>
                                                <td class="border p-2"><?= nl2br(sanitize($selectedCadre['objectif_global']['sources'] ?? '')) ?></td>
                                                <td class="border p-2"><?= nl2br(sanitize($selectedCadre['objectif_global']['hypotheses'] ?? '')) ?></td>
                                            </tr>
                                            <!-- Objectif Specifique -->
                                            <tr class="bg-green-50">
                                                <td class="border p-2 font-medium text-green-800">Objectif Specifique</td>
                                                <td class="border p-2"><?= nl2br(sanitize($selectedCadre['objectif_specifique']['description'] ?? '')) ?></td>
                                                <td class="border p-2"><?= nl2br(sanitize($selectedCadre['objectif_specifique']['indicateurs'] ?? '')) ?></td>
                                                <td class="border p-2"><?= nl2br(sanitize($selectedCadre['objectif_specifique']['sources'] ?? '')) ?></td>
                                                <td class="border p-2"><?= nl2br(sanitize($selectedCadre['objectif_specifique']['hypotheses'] ?? '')) ?></td>
                                            </tr>
                                            <!-- Resultats et Activites -->
                                            <?php if (isset($selectedCadre['resultats'])): ?>
                                                <?php foreach ($selectedCadre['resultats'] as $rIndex => $resultat): ?>
                                                    <tr class="bg-yellow-50">
                                                        <td class="border p-2 font-medium text-yellow-800">
                                                            <?= sanitize($resultat['id'] ?? 'R' . ($rIndex + 1)) ?>
                                                        </td>
                                                        <td class="border p-2"><?= nl2br(sanitize($resultat['description'] ?? '')) ?></td>
                                                        <td class="border p-2"><?= nl2br(sanitize($resultat['indicateurs'] ?? '')) ?></td>
                                                        <td class="border p-2"><?= nl2br(sanitize($resultat['sources'] ?? '')) ?></td>
                                                        <td class="border p-2"><?= nl2br(sanitize($resultat['hypotheses'] ?? '')) ?></td>
                                                    </tr>
                                                    <?php if (isset($resultat['activites'])): ?>
                                                        <?php foreach ($resultat['activites'] as $aIndex => $activite): ?>
                                                            <tr class="bg-red-50">
                                                                <td class="border p-2 font-medium text-red-800 pl-6">
                                                                    <?= sanitize($activite['id'] ?? 'A' . ($rIndex + 1) . '.' . ($aIndex + 1)) ?>
                                                                </td>
                                                                <td class="border p-2"><?= nl2br(sanitize($activite['description'] ?? '')) ?></td>
                                                                <td class="border p-2"><?= nl2br(sanitize($activite['ressources'] ?? '')) ?></td>
                                                                <td class="border p-2"><?= nl2br(sanitize($activite['budget'] ?? '')) ?></td>
                                                                <td class="border p-2"><?= nl2br(sanitize($activite['preconditions'] ?? '')) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-500">
                                <p>Ce participant n'a pas encore commence son cadre logique.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p>Selectionnez un participant pour voir son cadre logique</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tableau comparatif -->
        <div class="mt-8 bg-white rounded-xl shadow">
            <div class="p-4 border-b">
                <h2 class="text-lg font-semibold">Tableau comparatif</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border-b p-3 text-left">Participant</th>
                            <th class="border-b p-3 text-left">Projet</th>
                            <th class="border-b p-3 text-left">Organisation</th>
                            <th class="border-b p-3 text-center">Completion</th>
                            <th class="border-b p-3 text-center">Statut</th>
                            <th class="border-b p-3 text-left">Derniere activite</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $p): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="border-b p-3 font-medium">
                                    <?= sanitize($p['prenom'] . ' ' . $p['nom']) ?>
                                </td>
                                <td class="border-b p-3">
                                    <?= sanitize($p['titre_projet'] ?: '-') ?>
                                </td>
                                <td class="border-b p-3">
                                    <?= sanitize($p['organisation'] ?? '-') ?>
                                </td>
                                <td class="border-b p-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="progress-bar bg-purple-600 h-2 rounded-full"
                                                 style="width: <?= $p['completion_percent'] ?? 0 ?>%"></div>
                                        </div>
                                        <span class="text-xs"><?= $p['completion_percent'] ?? 0 ?>%</span>
                                    </div>
                                </td>
                                <td class="border-b p-3 text-center">
                                    <?php if ($p['is_submitted']): ?>
                                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Soumis</span>
                                    <?php else: ?>
                                        <span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded">En cours</span>
                                    <?php endif; ?>
                                </td>
                                <td class="border-b p-3 text-gray-500 text-sm">
                                    <?= $p['cadre_updated'] ? date('d/m/Y H:i', strtotime($p['cadre_updated'])) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh toutes les 30 secondes
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
