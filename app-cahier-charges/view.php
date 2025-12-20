<?php
/**
 * Vue en lecture seule - Cahier des Charges
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-cahier-charges';

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve");
}

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Acces refuse a cette session.");
}

$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

$stmt = $db->prepare("SELECT * FROM cahiers WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$cahier = $stmt->fetch();

function displayField($value) {
    return !empty($value) ? h($value) : '<span class="text-gray-400 italic">Non renseigne</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cahier des Charges - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } body { font-size: 10pt; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-emerald-600 to-emerald-800 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-emerald-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Cahier des Charges</h1>
            <h2 class="text-xl text-emerald-600"><?= displayField($cahier['titre_projet'] ?? '') ?></h2>
            <div class="grid md:grid-cols-2 gap-4 mt-4 text-sm">
                <p><strong>Date debut:</strong> <?= displayField($cahier['date_debut'] ?? '') ?></p>
                <p><strong>Date fin:</strong> <?= displayField($cahier['date_fin'] ?? '') ?></p>
            </div>
        </div>

        <!-- Equipe projet -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Equipe Projet</h3>
            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <div><strong>Chef de projet:</strong> <?= displayField($cahier['chef_projet'] ?? '') ?></div>
                <div><strong>Sponsor:</strong> <?= displayField($cahier['sponsor'] ?? '') ?></div>
                <div><strong>Groupe de travail:</strong> <?= displayField($cahier['groupe_travail'] ?? '') ?></div>
                <div><strong>Benevoles:</strong> <?= displayField($cahier['benevoles'] ?? '') ?></div>
            </div>
            <div class="mt-4 text-sm">
                <strong>Autres acteurs:</strong>
                <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['autres_acteurs'] ?? '') ?></p>
            </div>
        </div>

        <!-- Contexte strategique -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Contexte Strategique</h3>
            <div class="space-y-4 text-sm">
                <div>
                    <strong>Objectif strategique:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['objectif_strategique'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Inclusivite:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['inclusivite'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Aspect digital:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['aspect_digital'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Evolution:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['evolution'] ?? '') ?></p>
                </div>
            </div>
        </div>

        <!-- Description du projet -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Description du Projet</h3>
            <div class="space-y-4 text-sm">
                <div>
                    <strong>Description:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['description_projet'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Objectif du projet:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['objectif_projet'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Logique du projet:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['logique_projet'] ?? '') ?></p>
                </div>
            </div>
        </div>

        <!-- Objectifs -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Objectifs et Resultats</h3>
            <div class="space-y-4 text-sm">
                <div>
                    <strong>Objectif global:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['objectif_global'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Objectifs specifiques:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['objectifs_specifiques'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Resultats attendus:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['resultats'] ?? '') ?></p>
                </div>
            </div>
        </div>

        <!-- Contraintes et strategies -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Contraintes et Strategies</h3>
            <div class="grid md:grid-cols-2 gap-6 text-sm">
                <div>
                    <strong>Contraintes:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['contraintes'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Strategies:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['strategies'] ?? '') ?></p>
                </div>
            </div>
        </div>

        <!-- Ressources -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Ressources</h3>
            <div class="space-y-4 text-sm">
                <div><strong>Budget:</strong> <?= displayField($cahier['budget'] ?? '') ?></div>
                <div>
                    <strong>Ressources humaines:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['ressources_humaines'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Ressources materielles:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['ressources_materielles'] ?? '') ?></p>
                </div>
            </div>
        </div>

        <!-- Planification -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Planification et Communication</h3>
            <div class="space-y-4 text-sm">
                <div>
                    <strong>Etapes:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['etapes'] ?? '') ?></p>
                </div>
                <div>
                    <strong>Communication:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= displayField($cahier['communication'] ?? '') ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
