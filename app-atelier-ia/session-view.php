<?php
/**
 * Vue globale de session - Atelier IA
 * Affiche tous les ateliers IA de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-atelier-ia';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse a cette session.");
}

// Recuperer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: formateur.php');
    exit;
}

// Option pour voir tous les ateliers ou seulement les partages
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les ateliers de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM ateliers WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM ateliers WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$ateliers = $stmt->fetchAll();

// Compter le total (partages vs tous)
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM ateliers WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

// Enrichir avec les infos utilisateur
$participantsData = [];
$totalPostIts = 0;
$totalThemes = 0;
$totalCompletion = 0;
$completedCount = 0;

foreach ($ateliers as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $postIts = json_decode($a['post_its'] ?? '[]', true) ?: [];
    $themes = json_decode($a['themes'] ?? '[]', true) ?: [];

    $a['parsed_post_its'] = $postIts;
    $a['parsed_themes'] = $themes;

    $postItCount = is_array($postIts) ? count($postIts) : 0;
    $themeCount = is_array($themes) ? count($themes) : 0;
    $completion = (int)($a['completion_percent'] ?? 0);

    $a['post_it_count'] = $postItCount;
    $a['theme_count'] = $themeCount;
    $a['completion'] = $completion;

    $totalPostIts += $postItCount;
    $totalThemes += $themeCount;
    $totalCompletion += $completion;
    if ($completion >= 100) $completedCount++;

    $participantsData[] = $a;
}

$participantsCount = count($ateliers);
$avgCompletion = $participantsCount > 0 ? round($totalCompletion / $participantsCount) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atelier IA - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        .post-it {
            background: #fef9c3;
            transform: rotate(-1deg);
            box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
        }
        .post-it:nth-child(even) {
            transform: rotate(1deg);
        }
        .post-it:nth-child(3n) {
            transform: rotate(-0.5deg);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 to-fuchsia-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-600 to-fuchsia-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Atelier IA</h1>
                    <p class="text-purple-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Partages seulement (<?= $totalShared ?>)
                    </a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">
                        Voir tous (<?= $totalAll ?>)
                    </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="bg-purple-500 hover:bg-purple-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-purple-500 hover:bg-purple-400 px-3 py-1 rounded text-sm">
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($showAll): ?>
        <div class="bg-orange-100 border border-orange-300 rounded-xl p-4 mb-6">
            <p class="text-orange-800 text-sm">
                <strong>Mode: Tous les ateliers</strong> - Vous voyez tous les ateliers (<?= $totalAll ?>), y compris ceux non partages.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques globales -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-fuchsia-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Partages</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-yellow-400">
                <div class="text-3xl font-bold text-yellow-600"><?= $totalPostIts ?></div>
                <div class="text-gray-500 text-sm">Post-its total</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-purple-500">
                <div class="text-3xl font-bold text-purple-600"><?= $totalThemes ?></div>
                <div class="text-gray-500 text-sm">Themes total</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
        </div>

        <!-- Ateliers par participant -->
        <?php if (empty($participantsData)): ?>
        <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
            Aucun atelier trouve pour cette session.
        </div>
        <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($participantsData as $a):
                $postIts = $a['parsed_post_its'];
                $themes = $a['parsed_themes'];
                $completion = $a['completion'];
            ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Participant header -->
                <div class="bg-gradient-to-r from-purple-500 to-fuchsia-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($a['user_prenom']) ?> <?= h($a['user_nom']) ?></span>
                            <?php if (!empty($a['user_organisation'])): ?>
                            <span class="text-purple-200 text-sm ml-2">(<?= h($a['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($a['association_nom'])): ?>
                            <span class="block text-purple-100 text-sm mt-1">Association: <?= h($a['association_nom']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2 items-center">
                            <span class="bg-yellow-400/30 px-2 py-1 rounded text-sm"><?= $a['post_it_count'] ?> post-its</span>
                            <span class="bg-purple-400/30 px-2 py-1 rounded text-sm"><?= $a['theme_count'] ?> themes</span>
                            <!-- Completion bar -->
                            <div class="flex items-center gap-2">
                                <div class="w-20 bg-white/30 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full <?= $completion >= 100 ? 'bg-green-400' : ($completion >= 50 ? 'bg-yellow-400' : 'bg-red-400') ?>" style="width: <?= min(100, $completion) ?>%"></div>
                                </div>
                                <span class="text-sm font-bold"><?= $completion ?>%</span>
                            </div>
                            <span class="px-2 py-1 rounded text-sm <?= $a['is_shared'] ? 'bg-green-400/50' : 'bg-yellow-500/50' ?>"><?= $a['is_shared'] ? 'Partage' : 'Brouillon' ?></span>
                        </div>
                    </div>
                </div>

                <div class="p-4 space-y-4">
                    <!-- Themes -->
                    <?php if (!empty($themes)): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 mb-3">Themes (<?= count($themes) ?>)</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($themes as $theme): ?>
                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm font-medium border border-purple-200">
                                <?= h(is_array($theme) ? ($theme['name'] ?? $theme['nom'] ?? $theme['label'] ?? json_encode($theme)) : $theme) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Post-its grouped by theme -->
                    <?php if (!empty($postIts)): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 mb-3">Post-its (<?= count($postIts) ?>)</h4>
                        <?php
                        // Group post-its by theme
                        $postItsByTheme = ['_sans_theme' => []];
                        foreach ($postIts as $postIt) {
                            if (is_array($postIt)) {
                                $themeName = $postIt['theme'] ?? $postIt['theme_id'] ?? $postIt['categorie'] ?? '';
                                $content = $postIt['content'] ?? $postIt['contenu'] ?? $postIt['text'] ?? $postIt['texte'] ?? json_encode($postIt);
                            } else {
                                $themeName = '';
                                $content = $postIt;
                            }
                            if (empty($themeName)) {
                                $postItsByTheme['_sans_theme'][] = $content;
                            } else {
                                $postItsByTheme[$themeName][] = $content;
                            }
                        }

                        // Remove empty sans_theme group
                        if (empty($postItsByTheme['_sans_theme'])) {
                            unset($postItsByTheme['_sans_theme']);
                        }
                        ?>

                        <?php foreach ($postItsByTheme as $themeName => $themePostIts): ?>
                        <?php if ($themeName !== '_sans_theme'): ?>
                        <div class="mb-3">
                            <span class="text-sm font-semibold text-purple-600 mb-2 block"><?= h($themeName) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex flex-wrap gap-3 mb-4">
                            <?php foreach ($themePostIts as $content): ?>
                            <div class="post-it rounded-lg p-3 max-w-[200px]">
                                <p class="text-sm text-gray-700"><?= h(is_string($content) ? $content : json_encode($content)) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($postIts) && empty($themes)): ?>
                    <p class="text-gray-400 italic text-center py-4">Aucune donnee dans cet atelier</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
