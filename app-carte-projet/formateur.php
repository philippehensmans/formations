<?php
/**
 * Interface Formateur - Carte d'identite du projet
 */
require_once 'config/database.php';
requireFormateur();

$db = getDB();
$sessionId = $_SESSION['formateur_session_id'];
$sessionCode = $_SESSION['formateur_session_code'];
$sessionNom = $_SESSION['formateur_session_nom'];

// Recuperer les participants et leurs cartes projet
$stmt = $db->prepare("
    SELECT p.*, c.titre, c.objectifs, c.public_cible, c.territoire, c.partenaires,
           c.ressources_humaines, c.ressources_materielles, c.ressources_financieres,
           c.calendrier, c.resultats, c.notes, c.completion_percent, c.is_submitted, c.updated_at as carte_updated
    FROM participants p
    LEFT JOIN cartes_projet c ON p.id = c.participant_id
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
    <title>Formateur - Carte d'identite du projet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="p-4">
    <!-- Header -->
    <header class="max-w-7xl mx-auto mb-6">
        <div class="bg-purple-900 text-white rounded-lg p-4 flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="text-xl font-bold">Carte d'identite du projet - Formateur</h1>
                <p class="text-purple-200 text-sm">Session: <?= sanitize($sessionCode) ?> - <?= sanitize($sessionNom) ?></p>
            </div>
            <div class="flex gap-3">
                <button onclick="location.reload()" class="bg-purple-700 hover:bg-purple-600 px-4 py-2 rounded text-sm">
                    Actualiser
                </button>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">
                    Deconnexion
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto">
        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <?php
            $totalParticipants = count($participants);
            $submitted = count(array_filter($participants, fn($p) => $p['is_submitted']));
            $avgCompletion = $totalParticipants > 0 ? round(array_sum(array_column($participants, 'completion_percent')) / $totalParticipants) : 0;
            ?>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-900"><?= $totalParticipants ?></div>
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
                <h2 class="text-lg font-semibold">Cartes Projet (<?= $totalParticipants ?>)</h2>
            </div>

            <?php if (empty($participants)): ?>
                <div class="p-8 text-center text-gray-500">
                    Aucun participant pour le moment
                </div>
            <?php else: ?>
                <div class="divide-y">
                    <?php foreach ($participants as $p): ?>
                        <?php $partenaires = json_decode($p['partenaires'] ?: '[]', true); ?>
                        <div class="p-4">
                            <!-- En-tete participant -->
                            <div class="flex flex-wrap justify-between items-start gap-4 mb-4">
                                <div>
                                    <h3 class="font-semibold text-lg">
                                        <?= sanitize($p['prenom']) ?> <?= sanitize($p['nom']) ?>
                                        <?php if ($p['organisation']): ?>
                                            <span class="text-gray-500 font-normal text-sm">- <?= sanitize($p['organisation']) ?></span>
                                        <?php endif; ?>
                                    </h3>
                                    <?php if ($p['titre']): ?>
                                        <p class="text-purple-700 font-medium"><?= sanitize($p['titre']) ?></p>
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

                            <!-- Details de la carte -->
                            <div class="grid md:grid-cols-2 gap-4 text-sm">
                                <?php if ($p['objectifs']): ?>
                                    <div class="bg-blue-50 p-3 rounded">
                                        <strong class="text-blue-800">Objectifs:</strong>
                                        <p class="text-gray-700 mt-1"><?= nl2br(sanitize(mb_substr($p['objectifs'], 0, 200))) ?><?= mb_strlen($p['objectifs']) > 200 ? '...' : '' ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($p['public_cible']): ?>
                                    <div class="bg-green-50 p-3 rounded">
                                        <strong class="text-green-800">Public cible:</strong>
                                        <p class="text-gray-700 mt-1"><?= nl2br(sanitize(mb_substr($p['public_cible'], 0, 150))) ?><?= mb_strlen($p['public_cible']) > 150 ? '...' : '' ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($p['territoire']): ?>
                                    <div class="bg-yellow-50 p-3 rounded">
                                        <strong class="text-yellow-800">Territoire:</strong>
                                        <p class="text-gray-700 mt-1"><?= sanitize($p['territoire']) ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($p['calendrier']): ?>
                                    <div class="bg-orange-50 p-3 rounded">
                                        <strong class="text-orange-800">Calendrier:</strong>
                                        <p class="text-gray-700 mt-1"><?= sanitize($p['calendrier']) ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($partenaires)): ?>
                                    <div class="bg-pink-50 p-3 rounded">
                                        <strong class="text-pink-800">Partenaires (<?= count($partenaires) ?>):</strong>
                                        <ul class="text-gray-700 mt-1 list-disc list-inside">
                                            <?php foreach (array_slice($partenaires, 0, 3) as $part): ?>
                                                <li><?= sanitize($part['structure']) ?><?= $part['role'] ? ' - ' . sanitize($part['role']) : '' ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($partenaires) > 3): ?>
                                                <li class="text-gray-400">+<?= count($partenaires) - 3 ?> autres</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if ($p['ressources_humaines'] || $p['ressources_materielles'] || $p['ressources_financieres']): ?>
                                    <div class="bg-indigo-50 p-3 rounded">
                                        <strong class="text-indigo-800">Ressources:</strong>
                                        <ul class="text-gray-700 mt-1 text-xs">
                                            <?php if ($p['ressources_humaines']): ?>
                                                <li><em>H:</em> <?= sanitize(mb_substr($p['ressources_humaines'], 0, 50)) ?>...</li>
                                            <?php endif; ?>
                                            <?php if ($p['ressources_materielles']): ?>
                                                <li><em>M:</em> <?= sanitize(mb_substr($p['ressources_materielles'], 0, 50)) ?>...</li>
                                            <?php endif; ?>
                                            <?php if ($p['ressources_financieres']): ?>
                                                <li><em>F:</em> <?= sanitize(mb_substr($p['ressources_financieres'], 0, 50)) ?>...</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($p['resultats']): ?>
                                <div class="mt-3 bg-teal-50 p-3 rounded text-sm">
                                    <strong class="text-teal-800">Resultats attendus:</strong>
                                    <p class="text-gray-700 mt-1"><?= nl2br(sanitize(mb_substr($p['resultats'], 0, 200))) ?><?= mb_strlen($p['resultats']) > 200 ? '...' : '' ?></p>
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
