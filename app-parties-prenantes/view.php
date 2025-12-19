<?php
/**
 * Vue en lecture seule - Parties Prenantes
 */
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Recuperer le participant
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve");
}

// Recuperer les infos utilisateur
$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

// Recuperer l'analyse
$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$analyse = $stmt->fetch();

$partiesPrenantes = $analyse ? json_decode($analyse['parties_prenantes'] ?? '[]', true) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parties Prenantes - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-purple-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Analyse des Parties Prenantes</h1>
            <p class="text-gray-600">Projet: <?= h($analyse['titre_projet'] ?? 'Non defini') ?></p>
        </div>

        <?php if (empty($partiesPrenantes)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucune partie prenante definie
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="text-left p-3">Partie Prenante</th>
                            <th class="text-left p-3">Role</th>
                            <th class="text-center p-3">Interet</th>
                            <th class="text-center p-3">Influence</th>
                            <th class="text-left p-3">Strategie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($partiesPrenantes as $pp): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium"><?= h($pp['nom'] ?? '') ?></td>
                                <td class="p-3 text-gray-600"><?= h($pp['role'] ?? '') ?></td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-1 rounded text-xs <?= ($pp['interet'] ?? 0) >= 4 ? 'bg-green-100 text-green-700' : (($pp['interet'] ?? 0) >= 2 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') ?>">
                                        <?= $pp['interet'] ?? 0 ?>/5
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-1 rounded text-xs <?= ($pp['influence'] ?? 0) >= 4 ? 'bg-blue-100 text-blue-700' : (($pp['influence'] ?? 0) >= 2 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') ?>">
                                        <?= $pp['influence'] ?? 0 ?>/5
                                    </span>
                                </td>
                                <td class="p-3 text-gray-600"><?= h($pp['strategie'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
