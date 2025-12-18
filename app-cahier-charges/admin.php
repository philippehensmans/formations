<?php
require_once 'config.php';
requireAdmin();

$user = getCurrentUser();
$db = getDB();

$showAll = isset($_GET['all']);
$query = $showAll
    ? "SELECT c.*, u.username FROM cahiers c JOIN users u ON c.user_id = u.id ORDER BY c.updated_at DESC"
    : "SELECT c.*, u.username FROM cahiers c JOIN users u ON c.user_id = u.id WHERE c.is_shared = 1 ORDER BY c.updated_at DESC";
$stmt = $db->query($query);
$cahiers = $stmt->fetchAll();

$selectedCahier = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("SELECT c.*, u.username FROM cahiers c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
    $stmt->execute([$_GET['view']]);
    $selectedCahier = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interface Formateur - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto p-4 sm:p-6">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-6 rounded-xl mb-6 flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold">Interface Formateur</h1>
                <span class="opacity-80">Connecte : <?= sanitize($user['username']) ?></span>
            </div>
            <div class="flex gap-3">
                <a href="index.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded">Mon Cahier</a>
                <a href="logout.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded">Deconnexion</a>
            </div>
        </div>

        <!-- Stats -->
        <?php
        $totalParticipants = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
        $totalCahiers = $db->query("SELECT COUNT(*) FROM cahiers")->fetchColumn();
        $cahiersPartages = $db->query("SELECT COUNT(*) FROM cahiers WHERE is_shared = 1")->fetchColumn();
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-6 rounded-xl shadow text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $totalParticipants ?></div>
                <div class="text-gray-600">Participants inscrits</div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalCahiers ?></div>
                <div class="text-gray-600">Cahiers crees</div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $cahiersPartages ?></div>
                <div class="text-gray-600">Cahiers partages</div>
            </div>
        </div>

        <!-- Actions de nettoyage -->
        <?php if ($totalCahiers > 0 || $totalParticipants > 0): ?>
        <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 mb-6">
            <h3 class="font-bold text-red-800 mb-3">Nettoyage apres formation</h3>
            <div class="flex flex-wrap gap-3">
                <?php if ($totalCahiers > 0): ?>
                <button onclick="deleteAllCahiers(false)" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded text-sm">
                    Supprimer tous les cahiers (<?= $totalCahiers ?>)
                </button>
                <?php endif; ?>
                <?php if ($totalParticipants > 0): ?>
                <button onclick="deleteAllCahiers(true)" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">
                    Supprimer cahiers + participants (<?= $totalParticipants ?>)
                </button>
                <?php endif; ?>
            </div>
            <p class="text-xs text-red-600 mt-2">Attention : ces actions sont irreversibles !</p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Liste des participants -->
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="bg-gray-50 p-4 border-b flex justify-between items-center">
                    <span class="font-bold">Participants</span>
                    <?php if ($showAll): ?>
                        <a href="admin.php" class="text-sm text-blue-600 hover:underline">Partages seulement</a>
                    <?php else: ?>
                        <a href="admin.php?all=1" class="text-sm text-blue-600 hover:underline">Voir tous</a>
                    <?php endif; ?>
                </div>

                <?php if (empty($cahiers)): ?>
                    <div class="p-8 text-center text-gray-500">
                        <p class="font-semibold">Aucun cahier <?= $showAll ? '' : 'partage' ?></p>
                        <p class="text-sm">Les participants n'ont pas encore <?= $showAll ? 'cree' : 'partage' ?> de cahiers.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y max-h-96 overflow-y-auto">
                        <?php foreach ($cahiers as $cahier): ?>
                            <div class="flex items-center hover:bg-gray-50 <?= ($selectedCahier && $selectedCahier['id'] == $cahier['id']) ? 'bg-blue-50 border-l-4 border-blue-600' : '' ?>">
                                <a href="?view=<?= $cahier['id'] ?><?= $showAll ? '&all=1' : '' ?>" class="block p-4 flex-1">
                                    <div class="font-semibold"><?= sanitize($cahier['username']) ?></div>
                                    <div class="text-sm text-gray-600"><?= sanitize($cahier['titre_projet'] ?: 'Sans titre') ?></div>
                                    <div class="text-xs text-gray-400 mt-1 flex gap-2">
                                        <span><?= date('d/m/Y H:i', strtotime($cahier['updated_at'])) ?></span>
                                        <?php if ($cahier['is_shared']): ?>
                                            <span class="bg-green-100 text-green-700 px-2 rounded">Partage</span>
                                        <?php else: ?>
                                            <span class="bg-red-100 text-red-700 px-2 rounded">Non partage</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <button onclick="event.stopPropagation(); deleteCahier(<?= $cahier['id'] ?>, '<?= addslashes(sanitize($cahier['username'])) ?>')" class="p-2 mr-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded" title="Supprimer ce cahier">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Detail du cahier -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow overflow-hidden">
                <?php if ($selectedCahier): ?>
                    <div class="p-6 border-b bg-gray-50">
                        <div class="flex justify-between items-start flex-wrap gap-4">
                            <div>
                                <h2 class="text-xl font-bold"><?= sanitize($selectedCahier['titre_projet'] ?: 'Sans titre') ?></h2>
                                <p class="text-gray-600">
                                    <strong>Participant :</strong> <?= sanitize($selectedCahier['username']) ?><br>
                                    <strong>Chef de projet :</strong> <?= sanitize($selectedCahier['chef_projet'] ?: 'Non specifie') ?><br>
                                    <strong>Periode :</strong> <?= $selectedCahier['date_debut'] ? date('d/m/Y', strtotime($selectedCahier['date_debut'])) : '?' ?> - <?= $selectedCahier['date_fin'] ? date('d/m/Y', strtotime($selectedCahier['date_fin'])) : '?' ?>
                                </p>
                            </div>
                            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Imprimer</button>
                        </div>
                    </div>

                    <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                        <!-- Vision -->
                        <div class="bg-purple-50 p-4 rounded-lg border-2 border-purple-200">
                            <h3 class="font-bold text-purple-800 mb-3">Vision du Projet</h3>
                            <div class="space-y-2 text-sm">
                                <div><strong>Description :</strong> <?= nl2br(sanitize($selectedCahier['description_projet'] ?: 'Non renseigne')) ?></div>
                                <div><strong>Objectif :</strong> <?= nl2br(sanitize($selectedCahier['objectif_projet'] ?: 'Non renseigne')) ?></div>
                                <div><strong>Logique :</strong> <?= nl2br(sanitize($selectedCahier['logique_projet'] ?: 'Non renseigne')) ?></div>
                            </div>
                        </div>

                        <!-- Objectifs -->
                        <div class="bg-green-50 p-4 rounded-lg border-2 border-green-200">
                            <h3 class="font-bold text-green-800 mb-3">Objectifs</h3>
                            <div class="mb-3">
                                <strong class="text-sm">Objectif Global :</strong>
                                <p class="text-sm"><?= sanitize($selectedCahier['objectif_global'] ?: 'Non renseigne') ?></p>
                            </div>
                            <div>
                                <strong class="text-sm">Objectifs Specifiques :</strong>
                                <?php $objectifs = json_decode($selectedCahier['objectifs_specifiques'] ?? '[]', true); ?>
                                <?php if (empty($objectifs)): ?>
                                    <p class="text-sm text-gray-500 italic">Aucun objectif specifique</p>
                                <?php else: ?>
                                    <ul class="list-disc list-inside text-sm mt-1">
                                        <?php foreach ($objectifs as $obj): ?>
                                            <li><?= sanitize($obj) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Resultats -->
                        <div class="bg-orange-50 p-4 rounded-lg border-2 border-orange-200">
                            <h3 class="font-bold text-orange-800 mb-3">Cadre Logique - Resultats et Indicateurs</h3>
                            <?php $resultats = json_decode($selectedCahier['resultats'] ?? '[]', true); ?>
                            <?php if (empty($resultats)): ?>
                                <p class="text-sm text-gray-500 italic">Aucun resultat defini</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs border-collapse">
                                        <thead class="bg-orange-100">
                                            <tr>
                                                <th class="border p-2 text-left">Objectif specifique</th>
                                                <th class="border p-2 text-left">Acteurs vises</th>
                                                <th class="border p-2 text-left">Indicateurs</th>
                                                <th class="border p-2 text-left">Delivrables</th>
                                                <th class="border p-2 text-left bg-red-100">EXPECT</th>
                                                <th class="border p-2 text-left bg-yellow-100">LIKE</th>
                                                <th class="border p-2 text-left bg-green-100">LOVE</th>
                                                <th class="border p-2 text-left">Verification</th>
                                                <th class="border p-2 text-left">Lecons</th>
                                                <th class="border p-2 text-left">Ajustements</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($resultats as $r): ?>
                                                <tr>
                                                    <td class="border p-2"><?= nl2br(sanitize($r['objectif'] ?? '')) ?></td>
                                                    <td class="border p-2"><?= nl2br(sanitize($r['acteurs'] ?? '')) ?></td>
                                                    <td class="border p-2"><?= nl2br(sanitize($r['indicateurs'] ?? '')) ?></td>
                                                    <td class="border p-2"><?= nl2br(sanitize($r['delivrables'] ?? '')) ?></td>
                                                    <td class="border p-2 bg-red-50"><?= nl2br(sanitize($r['expect'] ?? '')) ?></td>
                                                    <td class="border p-2 bg-yellow-50"><?= nl2br(sanitize($r['like'] ?? '')) ?></td>
                                                    <td class="border p-2 bg-green-50"><?= nl2br(sanitize($r['love'] ?? '')) ?></td>
                                                    <td class="border p-2"><?= nl2br(sanitize($r['verification'] ?? '')) ?></td>
                                                    <td class="border p-2"><?= nl2br(sanitize($r['lecons'] ?? '')) ?></td>
                                                    <td class="border p-2"><?= nl2br(sanitize($r['ajustements'] ?? '')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Contraintes et Ressources -->
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="bg-red-50 p-4 rounded-lg border-2 border-red-200">
                                <h3 class="font-bold text-red-800 mb-3">Contraintes</h3>
                                <?php $contraintes = json_decode($selectedCahier['contraintes'] ?? '[]', true); ?>
                                <?php if (empty($contraintes)): ?>
                                    <p class="text-sm text-gray-500 italic">Aucune contrainte</p>
                                <?php else: ?>
                                    <ul class="list-disc list-inside text-sm">
                                        <?php foreach ($contraintes as $c): ?>
                                            <li><?= sanitize($c) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <div class="bg-indigo-50 p-4 rounded-lg border-2 border-indigo-200">
                                <h3 class="font-bold text-indigo-800 mb-3">Ressources</h3>
                                <div class="text-sm space-y-1">
                                    <div><strong>Budget :</strong> <?= sanitize($selectedCahier['budget'] ?: 'Non specifie') ?></div>
                                    <div><strong>RH :</strong> <?= sanitize($selectedCahier['ressources_humaines'] ?: 'Non specifie') ?></div>
                                    <div><strong>Materiel :</strong> <?= sanitize($selectedCahier['ressources_materielles'] ?: 'Non specifie') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Etapes -->
                        <div class="bg-teal-50 p-4 rounded-lg border-2 border-teal-200">
                            <h3 class="font-bold text-teal-800 mb-3">Etapes et Pilotage</h3>
                            <?php $etapes = json_decode($selectedCahier['etapes'] ?? '[]', true); ?>
                            <?php if (empty($etapes)): ?>
                                <p class="text-sm text-gray-500 italic">Aucune etape definie</p>
                            <?php else: ?>
                                <ul class="list-disc list-inside text-sm">
                                    <?php foreach ($etapes as $e): ?>
                                        <li><?= sanitize($e) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <div class="mt-3 text-sm">
                                <strong>Communication :</strong> <?= sanitize($selectedCahier['communication'] ?: 'Non specifie') ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center justify-center h-96 text-gray-400">
                        Selectionnez un participant pour voir son cahier des charges
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .bg-gradient-to-r, .grid-cols-1.sm\\:grid-cols-3, .lg\\:col-span-2 > div:first-child button { display: none !important; }
            .lg\\:col-span-2 { grid-column: span 3; }
            .max-h-\\[70vh\\] { max-height: none; overflow: visible; }
        }
    </style>

    <script>
    async function deleteAllCahiers(includeUsers) {
        const msg = includeUsers
            ? 'ATTENTION : Supprimer TOUS les cahiers ET les comptes participants ?\n\nCette action est IRREVERSIBLE !'
            : 'ATTENTION : Supprimer TOUS les cahiers ?\n\nCette action est IRREVERSIBLE !';

        if (confirm(msg)) {
            try {
                const url = 'api.php?action=deleteAll' + (includeUsers ? '&users=1' : '');
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    alert('Suppression effectuee avec succes.');
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Echec de la suppression'));
                }
            } catch (error) {
                alert('Erreur de connexion: ' + error.message);
            }
        }
    }

    async function deleteCahier(cahierId, username) {
        if (confirm('Supprimer le cahier de ' + username + ' ?')) {
            try {
                const response = await fetch('api.php?action=delete&id=' + cahierId);
                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Echec de la suppression'));
                }
            } catch (error) {
                alert('Erreur de connexion: ' + error.message);
            }
        }
    }
    </script>
</body>
</html>
