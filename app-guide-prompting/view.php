<?php
require_once __DIR__ . '/config.php';
if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-guide-prompting';
$db = getDB();
$sharedDb = getSharedDB();

// Support both old format (id) and new format (user_id + session_id)
if (isset($_GET['user_id']) && isset($_GET['session_id'])) {
    $userId = (int)$_GET['user_id'];
    $sessionId = (int)$_GET['session_id'];
} elseif (isset($_GET['id'])) {
    $participantId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT user_id, session_id FROM participants WHERE id = ?");
    $stmt->execute([$participantId]);
    $p = $stmt->fetch();
    if (!$p) die("Participant non trouve");
    $userId = $p['user_id'];
    $sessionId = $p['session_id'];
} else {
    header('Location: formateur.php');
    exit;
}

// Get session info
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) die("Session non trouvee");

if (!canAccessSession($appKey, $sessionId)) die("Acces refuse.");

$participant = ['user_id' => $userId, 'session_id' => $sessionId, 'session_nom' => $session['nom']];

$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch();
if (!$userInfo) die("Utilisateur non trouve");

$stmt = $db->prepare("SELECT * FROM guides WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$guide = $stmt->fetch();

$tasks = $guide ? json_decode($guide['tasks'] ?? '[]', true) : [];
$templates = $guide ? json_decode($guide['templates'] ?? '[]', true) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white p-3 shadow-lg sticky top-0 z-50">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-indigo-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4 space-y-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h1 class="text-2xl font-bold text-indigo-800 mb-4">Guide de Prompting</h1>
            <?php if (!empty($guide['organisation_nom'])): ?>
                <p class="text-lg"><strong>Organisation:</strong> <?= h($guide['organisation_nom']) ?></p>
            <?php endif; ?>
            <?php if (!empty($guide['organisation_mission'])): ?>
                <p class="text-gray-600"><strong>Mission:</strong> <?= h($guide['organisation_mission']) ?></p>
            <?php endif; ?>
            <?php if (!empty($guide['guide_intro'])): ?>
                <div class="mt-4 p-4 bg-indigo-50 rounded-lg">
                    <h3 class="font-semibold mb-2">Introduction</h3>
                    <p><?= nl2br(h($guide['guide_intro'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php foreach ($tasks as $i => $task): ?>
            <?php $tpl = array_filter($templates, fn($t) => $t['taskId'] === $task['id']); $tpl = reset($tpl) ?: []; ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-indigo-700 mb-3">Fiche <?= $i + 1 ?>: <?= h($task['name'] ?? '') ?></h2>
                <?php if (!empty($task['objective'])): ?>
                    <p class="text-gray-600 mb-1"><strong>Objectif:</strong> <?= h($task['objective']) ?></p>
                <?php endif; ?>
                <?php if (!empty($task['audience'])): ?>
                    <p class="text-gray-600 mb-1"><strong>Public:</strong> <?= h($task['audience']) ?></p>
                <?php endif; ?>
                <?php if (!empty($task['style'])): ?>
                    <p class="text-gray-600 mb-3"><strong>Style:</strong> <?= h($task['style']) ?></p>
                <?php endif; ?>

                <?php
                $fullPrompt = implode("\n\n", array_filter([
                    $tpl['context'] ?? '',
                    $tpl['task'] ?? '',
                    $tpl['format'] ?? '',
                    $tpl['instructions'] ?? '',
                    $tpl['examples'] ?? ''
                ]));
                if ($fullPrompt): ?>
                    <div class="bg-gray-100 p-4 rounded-lg font-mono text-sm whitespace-pre-wrap"><?= h($fullPrompt) ?></div>
                <?php endif; ?>

                <?php if (!empty($tpl['tips'])): ?>
                    <p class="text-sm text-gray-500 mt-3"><em>Conseils: <?= h($tpl['tips']) ?></em></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if (empty($tasks)): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 text-center text-gray-400">
                Aucune tache definie
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
