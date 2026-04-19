<?php
require_once __DIR__ . '/config.php';
if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-interviews';
$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) { header('Location: formateur.php'); exit; }
if (!canAccessSession($appKey, $sessionId)) die('Accès refusé');

$db = getDB();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) die('Session introuvable');

$stmt = $db->prepare("SELECT p.*, f.sujet, f.message1, f.message2, f.message3, f.anecdote, f.a_eviter, f.is_submitted, f.updated_at as fiche_updated
    FROM participants p
    LEFT JOIN fiches f ON f.user_id = p.user_id AND f.session_id = p.session_id
    WHERE p.session_id = ?
    ORDER BY p.nom, p.prenom");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();

$total = count($participants);
$submitted = count(array_filter($participants, fn($p) => $p['is_submitted']));

function hasContent($p) {
    return !empty(trim($p['sujet'] ?? ''))
        || !empty(trim($p['message1'] ?? ''))
        || !empty(trim($p['message2'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Synthèse — <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-rose-600 to-pink-600 text-white p-3 shadow-lg sticky top-0 z-50 no-print">
        <div class="max-w-6xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium">Synthèse de session</span>
                <span class="text-rose-200 text-sm ml-2"><?= h($session['nom']) ?> (<?= h($session['code']) ?>)</span>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $sessionId ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto p-4">
        <div class="bg-gradient-to-r from-rose-600 to-pink-600 text-white rounded-xl p-6 mb-6 text-center shadow">
            <h1 class="text-2xl font-bold mb-2">Préparation à l'interview</h1>
            <p class="text-sm opacity-90"><?= $total ?> participant(s) · <?= $submitted ?> fiche(s) soumise(s)</p>
            <div class="mt-3 flex justify-center gap-6 text-sm">
                <span><?= $submitted ?> soumis</span>
                <span><?= count(array_filter($participants, fn($p) => hasContent($p) && !$p['is_submitted'])) ?> brouillon(s)</span>
                <span><?= count(array_filter($participants, fn($p) => !hasContent($p))) ?> vide(s)</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="font-bold text-gray-800">Fiches des participants</h2>
            </div>
            <?php if (empty($participants)): ?>
            <div class="p-8 text-center text-gray-500">Aucun participant.</div>
            <?php else: ?>
            <div class="divide-y">
                <?php foreach ($participants as $p):
                    $hasFiche = hasContent($p);
                    $isSubmitted = (bool)$p['is_submitted'];
                ?>
                <div class="p-4 hover:bg-rose-50 cursor-pointer" onclick="location.href='view.php?id=<?= $p['id'] ?>'">
                    <div class="flex flex-wrap items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-semibold text-gray-800"><?= h($p['prenom']) ?> <?= h($p['nom']) ?></span>
                            <?php if ($isSubmitted): ?>
                            <a href="view.php?id=<?= $p['id'] ?>" class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs hover:bg-green-200">Soumis</a>
                            <?php elseif ($hasFiche): ?>
                            <a href="view.php?id=<?= $p['id'] ?>" class="px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-xs hover:bg-yellow-200">Brouillon</a>
                            <?php else: ?>
                            <a href="view.php?id=<?= $p['id'] ?>" class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-xs hover:bg-gray-200">Vide</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty(trim($p['sujet'] ?? ''))): ?>
                        <p class="text-sm text-gray-600 truncate">📌 <?= h(mb_substr($p['sujet'], 0, 100)) ?><?= mb_strlen($p['sujet']) > 100 ? '…' : '' ?></p>
                        <?php endif; ?>
                        <?php if (!empty(trim($p['message1'] ?? ''))): ?>
                        <p class="text-xs text-gray-500 mt-0.5 truncate">💬 <?= h(mb_substr($p['message1'], 0, 80)) ?><?= mb_strlen($p['message1']) > 80 ? '…' : '' ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="text-sm text-rose-600 font-medium no-print whitespace-nowrap">Voir →</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
