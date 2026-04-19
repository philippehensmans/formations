<?php
require_once __DIR__ . '/config.php';
if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-interviews';
$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id
    FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) die('Participant non trouvé');
if (!canAccessSession($appKey, $participant['session_id'])) die('Accès refusé');

$stmt = $db->prepare("SELECT * FROM fiches WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$fiche = $stmt->fetch() ?: [];
$isSubmitted = ($fiche['is_submitted'] ?? 0) == 1;

function field($v) { return !empty(trim($v ?? '')) ? h($v) : '<em class="text-gray-400">Non renseigné</em>'; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche — <?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-rose-600 to-pink-600 text-white p-3 shadow-lg sticky top-0 z-50 no-print">
        <div class="max-w-4xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></span>
                <span class="text-rose-200 text-sm ml-2"><?= h($participant['session_nom']) ?> (<?= h($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="session-view.php?id=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto p-4">
        <div class="bg-gradient-to-r from-rose-600 to-pink-600 text-white rounded-xl p-6 mb-6 shadow">
            <h1 class="text-xl font-bold">Fiche de préparation à l'interview</h1>
            <p class="opacity-80 text-sm mt-1"><?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></p>
        </div>

        <!-- Sujet -->
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h2 class="font-bold text-gray-800 mb-2 text-sm uppercase tracking-wide text-rose-700">Sujet / contexte</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= field($fiche['sujet'] ?? '') ?></p>
        </div>

        <!-- Messages clés -->
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h2 class="font-bold text-gray-800 mb-3 text-sm uppercase tracking-wide text-rose-700">Messages clés</h2>
            <div class="space-y-3">
                <?php foreach ([1, 2, 3] as $i): $val = $fiche['message' . $i] ?? ''; ?>
                <div class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-7 h-7 rounded-full bg-rose-100 text-rose-700 font-bold text-sm flex items-center justify-center"><?= $i ?></span>
                    <p class="text-gray-700 pt-0.5 whitespace-pre-wrap"><?= field($val) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Anecdote -->
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h2 class="font-bold text-gray-800 mb-2 text-sm uppercase tracking-wide text-rose-700">Anecdote / exemple concret</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= field($fiche['anecdote'] ?? '') ?></p>
        </div>

        <!-- À éviter -->
        <div class="bg-white rounded-xl shadow p-5 mb-4">
            <h2 class="font-bold text-gray-800 mb-2 text-sm uppercase tracking-wide text-red-600">Ce qu'il ne faut PAS dire</h2>
            <p class="text-gray-700 whitespace-pre-wrap bg-red-50 p-3 rounded"><?= field($fiche['a_eviter'] ?? '') ?></p>
        </div>
    </div>
</body>
</html>
