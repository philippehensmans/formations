<?php
/**
 * Vue globale de session - Arbre a Problemes
 * Affiche les arbres a problemes et solutions de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-arbreproblemes';

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

// Option pour voir tous ou seulement les partages
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les arbres de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM arbres WHERE session_id = ? ORDER BY created_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM arbres WHERE session_id = ? AND is_shared = 1 ORDER BY created_at DESC");
    $stmt->execute([$sessionId]);
}
$allArbres = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allArbres as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    // Decoder les champs JSON
    $a['consequences_arr'] = json_decode($a['consequences'] ?? '[]', true) ?: [];
    $a['causes_arr'] = json_decode($a['causes'] ?? '[]', true) ?: [];
    $a['objectifs_arr'] = json_decode($a['objectifs'] ?? '[]', true) ?: [];
    $a['moyens_arr'] = json_decode($a['moyens'] ?? '[]', true) ?: [];
}
unset($a);

// Statistiques
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM arbres WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM arbres WHERE session_id = ?");
$stmt->execute([$sessionId]);
$participantsCount = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arbre a Problemes - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        .tree-card { transition: all 0.3s ease; }
        .tree-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gradient-to-br from-amber-50 to-yellow-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-amber-600 to-yellow-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">🌳 Arbre a Problemes</h1>
                    <p class="text-amber-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
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
                    <button onclick="window.print()" class="bg-amber-500 hover:bg-amber-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-amber-500 hover:bg-amber-400 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Tous les arbres</strong> - Vous voyez tous les arbres (<?= $totalAll ?>), y compris ceux non partages.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-amber-600"><?= count($allArbres) ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Arbres (tous)' : 'Arbres partages' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-yellow-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Partages</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-gray-600"><?= $totalAll ?></div>
                <div class="text-gray-500 text-sm">Total</div>
            </div>
        </div>

        <!-- Arbres des participants -->
        <div class="space-y-8">
            <?php foreach ($allArbres as $arbre): ?>
            <div class="tree-card bg-white rounded-xl shadow-lg overflow-hidden <?= (!$arbre['is_shared'] && $showAll) ? 'opacity-75 border-2 border-orange-300' : '' ?>">
                <!-- En-tete participant -->
                <div class="bg-gradient-to-r from-amber-500 to-yellow-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($arbre['user_prenom']) ?> <?= h($arbre['user_nom']) ?></span>
                            <?php if (!empty($arbre['user_organisation'])): ?>
                            <span class="text-amber-200 text-sm ml-2">(<?= h($arbre['user_organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if (!$arbre['is_shared'] && $showAll): ?>
                            <span class="bg-orange-200 text-orange-800 text-xs px-2 py-0.5 rounded">Non partage</span>
                            <?php endif; ?>
                            <?php if (!empty($arbre['nom_projet'])): ?>
                            <span class="bg-white/30 px-3 py-1 rounded text-sm"><?= h($arbre['nom_projet']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Arbre a Problemes -->
                        <div>
                            <h3 class="text-lg font-bold text-red-700 mb-4 flex items-center gap-2">
                                <span class="text-xl">🔴</span> Arbre a Problemes
                            </h3>

                            <!-- Consequences -->
                            <?php if (!empty($arbre['consequences_arr'])): ?>
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-red-400 uppercase mb-1">Consequences</div>
                                <div class="space-y-1">
                                    <?php foreach ($arbre['consequences_arr'] as $item): ?>
                                    <div class="bg-red-50 border border-red-200 rounded px-3 py-2 text-sm text-red-800">
                                        <?= h(is_array($item) ? ($item['text'] ?? $item['label'] ?? json_encode($item)) : $item) ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Fleche -->
                            <div class="text-center text-red-300 text-2xl my-1">&#8593;</div>

                            <!-- Probleme central -->
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-red-500 uppercase mb-1">Probleme central</div>
                                <div class="bg-red-100 border-2 border-red-400 rounded-lg px-4 py-3 text-center font-bold text-red-900">
                                    <?= h($arbre['probleme_central'] ?? '-') ?>
                                </div>
                            </div>

                            <!-- Fleche -->
                            <div class="text-center text-red-300 text-2xl my-1">&#8593;</div>

                            <!-- Causes -->
                            <?php if (!empty($arbre['causes_arr'])): ?>
                            <div>
                                <div class="text-xs font-semibold text-red-400 uppercase mb-1">Causes</div>
                                <div class="space-y-1">
                                    <?php foreach ($arbre['causes_arr'] as $item): ?>
                                    <div class="bg-red-50 border border-red-200 rounded px-3 py-2 text-sm text-red-800">
                                        <?= h(is_array($item) ? ($item['text'] ?? $item['label'] ?? json_encode($item)) : $item) ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Arbre a Solutions -->
                        <div>
                            <h3 class="text-lg font-bold text-green-700 mb-4 flex items-center gap-2">
                                <span class="text-xl">🟢</span> Arbre a Solutions
                            </h3>

                            <!-- Objectifs -->
                            <?php if (!empty($arbre['objectifs_arr'])): ?>
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-green-400 uppercase mb-1">Objectifs</div>
                                <div class="space-y-1">
                                    <?php foreach ($arbre['objectifs_arr'] as $item): ?>
                                    <div class="bg-green-50 border border-green-200 rounded px-3 py-2 text-sm text-green-800">
                                        <?= h(is_array($item) ? ($item['text'] ?? $item['label'] ?? json_encode($item)) : $item) ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Fleche -->
                            <div class="text-center text-green-300 text-2xl my-1">&#8593;</div>

                            <!-- Objectif central -->
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-green-500 uppercase mb-1">Objectif central</div>
                                <div class="bg-green-100 border-2 border-green-400 rounded-lg px-4 py-3 text-center font-bold text-green-900">
                                    <?= h($arbre['objectif_central'] ?? '-') ?>
                                </div>
                            </div>

                            <!-- Fleche -->
                            <div class="text-center text-green-300 text-2xl my-1">&#8593;</div>

                            <!-- Moyens -->
                            <?php if (!empty($arbre['moyens_arr'])): ?>
                            <div>
                                <div class="text-xs font-semibold text-green-400 uppercase mb-1">Moyens</div>
                                <div class="space-y-1">
                                    <?php foreach ($arbre['moyens_arr'] as $item): ?>
                                    <div class="bg-green-50 border border-green-200 rounded px-3 py-2 text-sm text-green-800">
                                        <?= h(is_array($item) ? ($item['text'] ?? $item['label'] ?? json_encode($item)) : $item) ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Participants du projet -->
                    <?php if (!empty($arbre['participants'])): ?>
                    <div class="mt-4 pt-4 border-t">
                        <span class="text-xs font-semibold text-gray-500 uppercase">Participants du projet:</span>
                        <span class="text-sm text-gray-700 ml-2"><?= h($arbre['participants']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($allArbres)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <div class="text-6xl mb-4">🌳</div>
                <p class="text-gray-500 text-lg">Aucun arbre <?= $showAll ? '' : 'partage' ?> pour cette session.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
