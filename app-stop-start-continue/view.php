<?php
/**
 * Vue en lecture seule - Stop Start Continue
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

$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve");
}

$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

$stmt = $db->prepare("SELECT * FROM retrospectives WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$retro = $stmt->fetch();

$stopItems = $retro ? json_decode($retro['stop_items'] ?? '[]', true) : [];
$startItems = $retro ? json_decode($retro['start_items'] ?? '[]', true) : [];
$continueItems = $retro ? json_decode($retro['continue_items'] ?? '[]', true) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stop Start Continue - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-teal-600 to-teal-800 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-teal-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Retrospective Stop Start Continue</h1>
            <p class="text-gray-600">Titre: <?= h($retro['titre'] ?? 'Non defini') ?></p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <!-- STOP -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-red-500 text-white p-4 font-bold text-center">STOP</div>
                <div class="p-4">
                    <?php if (empty($stopItems)): ?>
                        <p class="text-gray-400 text-center">Aucun element</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($stopItems as $item): ?>
                                <li class="p-3 bg-red-50 rounded-lg border-l-4 border-red-500"><?= h(is_array($item) ? ($item['text'] ?? '') : $item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- START -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-green-500 text-white p-4 font-bold text-center">START</div>
                <div class="p-4">
                    <?php if (empty($startItems)): ?>
                        <p class="text-gray-400 text-center">Aucun element</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($startItems as $item): ?>
                                <li class="p-3 bg-green-50 rounded-lg border-l-4 border-green-500"><?= h(is_array($item) ? ($item['text'] ?? '') : $item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONTINUE -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-blue-500 text-white p-4 font-bold text-center">CONTINUE</div>
                <div class="p-4">
                    <?php if (empty($continueItems)): ?>
                        <p class="text-gray-400 text-center">Aucun element</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($continueItems as $item): ?>
                                <li class="p-3 bg-blue-50 rounded-lg border-l-4 border-blue-500"><?= h(is_array($item) ? ($item['text'] ?? '') : $item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
