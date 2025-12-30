<?php
/**
 * Vue en lecture seule - Atelier IA
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-atelier-ia';

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

$stmt = $db->prepare("SELECT * FROM ateliers WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$atelier = $stmt->fetch();

$postIts = $atelier ? json_decode($atelier['post_its'] ?? '[]', true) : [];
$themes = $atelier ? json_decode($atelier['themes'] ?? '[]', true) : [];
$interactions = $atelier ? json_decode($atelier['interactions'] ?? '[]', true) : [];
$conditions = $atelier ? json_decode($atelier['conditions_reussite'] ?? '[]', true) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atelier IA - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-purple-600 to-indigo-700 text-white p-3 shadow-lg no-print sticky top-0 z-50">
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

    <div class="max-w-7xl mx-auto p-4 space-y-6">
        <!-- Association Info -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Atelier IA pour Associations</h1>
            <?php if (!empty($atelier['association_nom'])): ?>
                <p class="text-lg text-gray-700"><strong>Association:</strong> <?= h($atelier['association_nom']) ?></p>
            <?php endif; ?>
            <?php if (!empty($atelier['association_mission'])): ?>
                <p class="text-gray-600"><strong>Mission:</strong> <?= h($atelier['association_mission']) ?></p>
            <?php endif; ?>
            <?php if (!empty($atelier['notes'])): ?>
                <p class="text-gray-500 mt-2"><strong>Notes:</strong> <?= h($atelier['notes']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Post-its -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-purple-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">1</span>
                Problemes identifies
            </h2>
            <?php if (empty($postIts)): ?>
                <p class="text-gray-400 text-center py-4">Aucun element</p>
            <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <?php foreach ($postIts as $postIt):
                        $colorClass = match($postIt['color'] ?? 'yellow') {
                            'pink' => 'bg-pink-200 border-pink-400',
                            'green' => 'bg-green-200 border-green-400',
                            'blue' => 'bg-blue-200 border-blue-400',
                            default => 'bg-yellow-200 border-yellow-400'
                        };
                    ?>
                        <div class="<?= $colorClass ?> p-4 rounded-lg shadow-md border-2">
                            <p class="text-sm font-medium text-gray-800"><?= h($postIt['text'] ?? '') ?></p>
                            <?php if (!empty($postIt['themeId'])):
                                $theme = array_filter($themes, fn($t) => $t['id'] === $postIt['themeId']);
                                $theme = reset($theme);
                            ?>
                                <p class="text-xs text-gray-600 mt-2">Theme: <?= h($theme['name'] ?? 'Non assigne') ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Themes -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">2</span>
                Themes regroupes
            </h2>
            <?php if (empty($themes)): ?>
                <p class="text-gray-400 text-center py-4">Aucun theme</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($themes as $theme):
                        $themePostIts = array_filter($postIts, fn($p) => ($p['themeId'] ?? null) === $theme['id']);
                    ?>
                        <div class="bg-indigo-50 rounded-xl p-4 border-2 border-indigo-200">
                            <h3 class="font-bold text-indigo-800"><?= h($theme['name']) ?></h3>
                            <?php if (!empty($theme['description'])): ?>
                                <p class="text-sm text-indigo-600 mb-3"><?= h($theme['description']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($themePostIts)): ?>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <?php foreach ($themePostIts as $postIt):
                                        $colorClass = match($postIt['color'] ?? 'yellow') {
                                            'pink' => 'bg-pink-200',
                                            'green' => 'bg-green-200',
                                            'blue' => 'bg-blue-200',
                                            default => 'bg-yellow-200'
                                        };
                                    ?>
                                        <div class="<?= $colorClass ?> p-2 rounded-lg text-sm">
                                            <?= h($postIt['text'] ?? '') ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Interactions -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">3</span>
                Classification des interactions
            </h2>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-semibold text-green-700 mb-3">A preserver (humain)</h3>
                    <?php
                    $preserve = array_filter($interactions, fn($i) => ($i['type'] ?? '') === 'preserve');
                    if (empty($preserve)): ?>
                        <p class="text-gray-400 text-center py-4">Aucun element</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($preserve as $interaction): ?>
                                <div class="bg-green-100 p-3 rounded-lg border border-green-300">
                                    <p class="font-medium text-green-800"><?= h($interaction['name'] ?? '') ?></p>
                                    <?php if (!empty($interaction['reason'])): ?>
                                        <p class="text-sm text-green-600 mt-1"><?= h($interaction['reason']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-700 mb-3">Avec assistance IA</h3>
                    <?php
                    $ai = array_filter($interactions, fn($i) => ($i['type'] ?? '') === 'ai');
                    if (empty($ai)): ?>
                        <p class="text-gray-400 text-center py-4">Aucun element</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($ai as $interaction): ?>
                                <div class="bg-blue-100 p-3 rounded-lg border border-blue-300">
                                    <p class="font-medium text-blue-800"><?= h($interaction['name'] ?? '') ?></p>
                                    <?php if (!empty($interaction['reason'])): ?>
                                        <p class="text-sm text-blue-600 mt-1"><?= h($interaction['reason']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Conditions de reussite -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-green-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">4</span>
                Conditions de reussite
            </h2>
            <?php if (empty($conditions)): ?>
                <p class="text-gray-400 text-center py-4">Aucune condition</p>
            <?php else: ?>
                <div class="grid md:grid-cols-2 gap-4">
                    <?php foreach ($conditions as $condition): ?>
                        <div class="bg-green-50 p-4 rounded-xl border-2 border-green-200">
                            <h4 class="font-bold text-green-800"><?= h($condition['name'] ?? '') ?></h4>
                            <?php if (!empty($condition['indicator'])): ?>
                                <p class="text-sm text-gray-600"><span class="font-medium">Indicateur:</span> <?= h($condition['indicator']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($condition['target'])): ?>
                                <p class="text-sm text-gray-600"><span class="font-medium">Cible:</span> <?= h($condition['target']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
