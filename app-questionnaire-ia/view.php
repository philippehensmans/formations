<?php
/**
 * Vue individuelle d'un participant - Questionnaire IA
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-questionnaire-ia';

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.sujet as session_sujet, s.id as session_id
                      FROM participants p
                      JOIN sessions s ON p.session_id = s.id
                      WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) { die("Participant non trouve"); }

if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Acces refuse a cette session.");
}

$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

$questions = getQuestions($participant['session_id']);
$reponses = getReponses($participant['user_id'], $participant['session_id']);
$reponsesByQuestion = [];
foreach ($reponses as $r) {
    $reponsesByQuestion[$r['question_id']] = $r;
}

$totalReponses = count(array_filter($reponsesByQuestion, fn($r) => !empty($r['contenu'])));
$isShared = !empty($reponses) && $reponses[0]['is_shared'] == 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionnaire IA - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>📋</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } body { background: white !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-sky-600 to-blue-700 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <?php if (!empty($userInfo['organisation'])): ?>
                <span class="text-sky-200 text-sm ml-2">(<?= h($userInfo['organisation']) ?>)</span>
                <?php endif; ?>
                <span class="text-sky-200 text-sm ml-2">- <?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <span class="bg-white/20 px-3 py-1 rounded text-sm">
                    <?= $totalReponses ?>/<?= count($questions) ?> reponses
                    <?= $isShared ? '<span class="text-green-300">✓</span>' : '' ?>
                </span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="session-view.php?id=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Vue Session</a>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">📋 Fiche Participant — Carte de mon rapport a l'IA</h1>
            <p class="text-gray-600">
                <strong>Participant:</strong> <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?>
                <?php if (!empty($userInfo['organisation'])): ?> - <?= h($userInfo['organisation']) ?><?php endif; ?>
            </p>
            <p class="text-gray-500 text-sm mt-1">
                <strong>Session:</strong> <?= h($participant['session_nom']) ?> (<?= $participant['session_code'] ?>)
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="space-y-6">
                <?php foreach ($questions as $i => $q):
                    $r = $reponsesByQuestion[$q['id']] ?? null;
                ?>
                <div class="border-b border-gray-100 pb-4 last:border-0">
                    <div class="text-sm font-bold text-gray-500 mb-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 bg-sky-100 text-sky-700 rounded-full text-xs mr-2"><?= $i + 1 ?></span>
                        <?= h($q['label']) ?>
                    </div>
                    <?php if ($r && !empty($r['contenu'])): ?>
                        <?php if ($q['type'] === 'radio'): ?>
                        <span class="inline-block px-4 py-2 bg-sky-100 text-sky-700 rounded-lg font-medium"><?= h($r['contenu']) ?></span>
                        <?php else: ?>
                        <p class="text-gray-800 pl-8"><?= nl2br(h($r['contenu'])) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                    <p class="text-gray-300 italic pl-8">Pas de reponse</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
