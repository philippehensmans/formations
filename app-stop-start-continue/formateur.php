<?php
/**
 * Interface Formateur - Stop Start Continue
 */
require_once 'config/database.php';
requireFormateur();

$db = getDB();
$sessionId = $_SESSION['formateur_session_id'];
$sessionCode = $_SESSION['formateur_session_code'];
$sessionNom = $_SESSION['formateur_session_nom'];

// Recuperer les participants et leurs retrospectives
$stmt = $db->prepare("
    SELECT p.*, r.projet_nom, r.projet_contexte, r.items_cesser, r.items_commencer, r.items_continuer,
           r.notes, r.completion_percent, r.is_submitted, r.updated_at as retro_updated
    FROM participants p
    LEFT JOIN retrospectives r ON p.id = r.participant_id
    WHERE p.session_id = ?
    ORDER BY p.nom, p.prenom
");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formateur - Stop Start Continue</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-blue-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold">Stop Start Continue - Formateur</h1>
                    <p class="text-blue-200 text-sm">Session: <?= sanitize($sessionCode) ?> - <?= sanitize($sessionNom) ?></p>
                </div>
                <div class="flex gap-3">
                    <button onclick="location.reload()" class="bg-blue-700 hover:bg-blue-600 px-4 py-2 rounded text-sm">
                        Actualiser
                    </button>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">
                        Deconnexion
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <?php
            $totalParticipants = count($participants);
            $submitted = count(array_filter($participants, fn($p) => $p['is_submitted']));
            $avgCompletion = $totalParticipants > 0 ? round(array_sum(array_column($participants, 'completion_percent')) / $totalParticipants) : 0;
            ?>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-900"><?= $totalParticipants ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $submitted ?></div>
                <div class="text-gray-500 text-sm">Soumis</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-3xl font-bold text-orange-500"><?= $totalParticipants - $submitted ?></div>
                <div class="text-gray-500 text-sm">En cours</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moy.</div>
            </div>
        </div>

        <!-- Liste des participants -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b">
                <h2 class="text-lg font-semibold">Participants (<?= $totalParticipants ?>)</h2>
            </div>

            <?php if (empty($participants)): ?>
                <div class="p-8 text-center text-gray-500">
                    Aucun participant pour le moment
                </div>
            <?php else: ?>
                <div class="divide-y">
                    <?php foreach ($participants as $p): ?>
                        <?php
                        $cesser = json_decode($p['items_cesser'] ?: '[]', true);
                        $commencer = json_decode($p['items_commencer'] ?: '[]', true);
                        $continuer = json_decode($p['items_continuer'] ?: '[]', true);
                        ?>
                        <div class="p-4">
                            <div class="flex flex-wrap justify-between items-start gap-4 mb-3">
                                <div>
                                    <h3 class="font-semibold">
                                        <?= sanitize($p['prenom']) ?> <?= sanitize($p['nom']) ?>
                                        <?php if ($p['organisation']): ?>
                                            <span class="text-gray-500 font-normal">- <?= sanitize($p['organisation']) ?></span>
                                        <?php endif; ?>
                                    </h3>
                                    <?php if ($p['projet_nom']): ?>
                                        <p class="text-sm text-gray-600">Projet: <?= sanitize($p['projet_nom']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm text-gray-500"><?= $p['completion_percent'] ?>%</span>
                                    <?php if ($p['is_submitted']): ?>
                                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">Soumis</span>
                                    <?php else: ?>
                                        <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">En cours</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Resume des 3 categories -->
                            <div class="grid md:grid-cols-3 gap-4">
                                <!-- Cesser -->
                                <div class="bg-red-50 border border-red-200 rounded p-3">
                                    <h4 class="font-medium text-red-800 text-sm mb-2">🛑 A Cesser (<?= count($cesser) ?>)</h4>
                                    <?php if (empty($cesser)): ?>
                                        <p class="text-gray-400 text-xs italic">Aucun element</p>
                                    <?php else: ?>
                                        <ul class="text-xs space-y-1">
                                            <?php foreach (array_slice($cesser, 0, 5) as $item): ?>
                                                <li class="text-gray-700">• <?= sanitize(mb_substr($item['description'], 0, 60)) ?><?= mb_strlen($item['description']) > 60 ? '...' : '' ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($cesser) > 5): ?>
                                                <li class="text-gray-400 italic">+<?= count($cesser) - 5 ?> autres</li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>

                                <!-- Commencer -->
                                <div class="bg-green-50 border border-green-200 rounded p-3">
                                    <h4 class="font-medium text-green-800 text-sm mb-2">🚀 A Commencer (<?= count($commencer) ?>)</h4>
                                    <?php if (empty($commencer)): ?>
                                        <p class="text-gray-400 text-xs italic">Aucun element</p>
                                    <?php else: ?>
                                        <ul class="text-xs space-y-1">
                                            <?php foreach (array_slice($commencer, 0, 5) as $item): ?>
                                                <li class="text-gray-700">• <?= sanitize(mb_substr($item['description'], 0, 60)) ?><?= mb_strlen($item['description']) > 60 ? '...' : '' ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($commencer) > 5): ?>
                                                <li class="text-gray-400 italic">+<?= count($commencer) - 5 ?> autres</li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>

                                <!-- Continuer -->
                                <div class="bg-blue-50 border border-blue-200 rounded p-3">
                                    <h4 class="font-medium text-blue-800 text-sm mb-2">✅ A Continuer (<?= count($continuer) ?>)</h4>
                                    <?php if (empty($continuer)): ?>
                                        <p class="text-gray-400 text-xs italic">Aucun element</p>
                                    <?php else: ?>
                                        <ul class="text-xs space-y-1">
                                            <?php foreach (array_slice($continuer, 0, 5) as $item): ?>
                                                <li class="text-gray-700">• <?= sanitize(mb_substr($item['description'], 0, 60)) ?><?= mb_strlen($item['description']) > 60 ? '...' : '' ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($continuer) > 5): ?>
                                                <li class="text-gray-400 italic">+<?= count($continuer) - 5 ?> autres</li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($p['notes']): ?>
                                <div class="mt-3 text-xs text-gray-500">
                                    <strong>Notes:</strong> <?= sanitize(mb_substr($p['notes'], 0, 150)) ?><?= mb_strlen($p['notes']) > 150 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
