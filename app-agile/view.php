<?php
/**
 * Vue en lecture seule - Projet Agile
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-agile';

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

// Recuperer les infos utilisateur (si user_id existe)
$userInfo = null;
if (!empty($participant['user_id'])) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$participant['user_id']]);
    $userInfo = $userStmt->fetch();
}

// Fallback sur les donnees locales du participant
if (!$userInfo) {
    $userInfo = [
        'prenom' => $participant['prenom'] ?? '',
        'nom' => $participant['nom'] ?? '',
        'organisation' => $participant['organisation'] ?? ''
    ];
}

// Chercher le projet - essayer plusieurs methodes
$project = null;

// 1. Chercher par user_id ET session_id
if (!empty($participant['user_id'])) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$participant['user_id'], $participant['session_id']]);
    $project = $stmt->fetch();

    // 2. Fallback: chercher par user_id seulement (anciens projets sans session_id)
    if (!$project) {
        $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? AND (session_id IS NULL OR session_id = 0)");
        $stmt->execute([$participant['user_id']]);
        $project = $stmt->fetch();
    }
}

$cards = $project ? json_decode($project['cards'] ?? '[]', true) : [];
$userStories = $project ? json_decode($project['user_stories'] ?? '[]', true) : [];
$retrospective = $project ? json_decode($project['retrospective'] ?? '{}', true) : [];
$sprint = $project ? json_decode($project['sprint'] ?? '{}', true) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projet Agile - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-cyan-600 to-cyan-800 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-cyan-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Projet Agile</h1>
            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <p><strong>Projet:</strong> <?= h($project['project_name'] ?? 'Non defini') ?></p>
                <p><strong>Equipe:</strong> <?= h($project['team_name'] ?? 'Non defini') ?></p>
            </div>
        </div>

        <!-- Sprint -->
        <?php if (!empty($sprint)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Sprint <?= $sprint['number'] ?? 1 ?></h2>
            <div class="grid md:grid-cols-3 gap-4 text-sm">
                <p><strong>Debut:</strong> <?= h($sprint['start'] ?? '-') ?></p>
                <p><strong>Fin:</strong> <?= h($sprint['end'] ?? '-') ?></p>
                <p><strong>Objectif:</strong> <?= h($sprint['goal'] ?? '-') ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- User Stories -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">User Stories (<?= count($userStories) ?>)</h2>
            <?php if (empty($userStories)): ?>
                <p class="text-gray-400">Aucune user story</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($userStories as $story): ?>
                        <div class="p-4 bg-gray-50 rounded-lg border-l-4 border-cyan-500">
                            <p class="font-medium"><?= h($story['title'] ?? $story['text'] ?? '') ?></p>
                            <?php if (!empty($story['description'])): ?>
                                <p class="text-sm text-gray-600 mt-1"><?= h($story['description']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($story['priority'])): ?>
                                <span class="text-xs px-2 py-1 bg-cyan-100 text-cyan-700 rounded mt-2 inline-block">Priorite: <?= h($story['priority']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Retrospective -->
        <?php if (!empty($retrospective)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Retrospective</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <h3 class="font-semibold text-green-600 mb-2">Ce qui va bien</h3>
                    <ul class="space-y-1">
                        <?php foreach ($retrospective['good'] ?? [] as $item): ?>
                            <li class="text-sm p-2 bg-green-50 rounded"><?= h(is_array($item) ? $item['text'] : $item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-orange-600 mb-2">A ameliorer</h3>
                    <ul class="space-y-1">
                        <?php foreach ($retrospective['improve'] ?? [] as $item): ?>
                            <li class="text-sm p-2 bg-orange-50 rounded"><?= h(is_array($item) ? $item['text'] : $item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-600 mb-2">Actions</h3>
                    <ul class="space-y-1">
                        <?php foreach ($retrospective['actions'] ?? [] as $item): ?>
                            <li class="text-sm p-2 bg-blue-50 rounded"><?= h(is_array($item) ? $item['text'] : $item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
