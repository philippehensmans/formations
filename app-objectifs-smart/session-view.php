<?php
/**
 * Vue globale de session - Objectifs SMART
 * Affiche les objectifs de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-objectifs-smart';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse.");
}

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: formateur.php');
    exit;
}

$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les objectifs SMART de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM objectifs_smart WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM objectifs_smart WHERE session_id = ? AND is_submitted = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$allObjectifs = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allObjectifs as &$o) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$o['user_id']]);
    $userInfo = $userStmt->fetch();
    $o['user_prenom'] = $userInfo['prenom'] ?? '';
    $o['user_nom'] = $userInfo['nom'] ?? '';
    $o['user_organisation'] = $userInfo['organisation'] ?? '';
    $o['etape1'] = json_decode($o['etape1_analyses'] ?? '[]', true) ?: [];
    $o['etape2'] = json_decode($o['etape2_reformulations'] ?? '[]', true) ?: [];
    $o['etape3'] = json_decode($o['etape3_creations'] ?? '[]', true) ?: [];
}
unset($o);

// Statistiques
$totalObjectifs = count($allObjectifs);

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM objectifs_smart WHERE session_id = ?");
$stmt->execute([$sessionId]);
$totalParticipants = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) as submitted FROM objectifs_smart WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalSubmitted = $counts['submitted'];

$avgCompletion = $totalObjectifs > 0 ? round(array_sum(array_column($allObjectifs, 'completion_percent')) / $totalObjectifs) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objectifs SMART - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Objectifs SMART</h1>
                    <p class="text-blue-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Soumis seulement (<?= $totalSubmitted ?>)
                    </a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">
                        Tous (<?= $totalAll ?>)
                    </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-blue-500 hover:bg-blue-400 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Tous</strong> - Vous voyez tous les travaux (<?= $totalAll ?>), y compris ceux non soumis.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $totalParticipants ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $totalObjectifs ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Travaux (tous)' : 'Travaux soumis' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-cyan-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalSubmitted ?></div>
                <div class="text-gray-500 text-sm">Soumis</div>
            </div>
        </div>

        <!-- Objectifs des participants -->
        <div class="space-y-6">
            <?php if (empty($allObjectifs)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <p class="text-gray-400 text-lg">Aucun objectif SMART <?= $showAll ? '' : 'soumis' ?> pour le moment.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($allObjectifs as $o): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden <?= (!$o['is_submitted'] && $showAll) ? 'opacity-75 border-2 border-orange-200' : '' ?>">
                <div class="bg-gradient-to-r from-blue-500 to-indigo-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($o['user_prenom']) ?> <?= h($o['user_nom']) ?></span>
                            <?php if (!empty($o['user_organisation'])): ?>
                            <span class="text-blue-200 text-sm ml-2">(<?= h($o['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!$o['is_submitted'] && $showAll): ?>
                            <span class="bg-orange-400 text-white text-xs px-2 py-0.5 rounded ml-2">Non soumis</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-right text-sm">
                            <span class="text-blue-200">Etape <?= (int)$o['etape_courante'] ?>/3</span>
                        </div>
                    </div>
                    <!-- Barre de completion -->
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-blue-200 mb-1">
                            <span>Progression</span>
                            <span><?= (int)$o['completion_percent'] ?>%</span>
                        </div>
                        <div class="w-full bg-white/20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full transition-all" style="width: <?= (int)$o['completion_percent'] ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <div class="grid md:grid-cols-3 gap-4">
                        <!-- Etape 1: Analyses -->
                        <div class="border border-blue-100 rounded-lg p-3">
                            <h4 class="font-bold text-blue-700 mb-2 text-sm flex items-center gap-1">
                                <span class="bg-blue-100 text-blue-700 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold">1</span>
                                Analyses (<?= count($o['etape1']) ?>)
                            </h4>
                            <?php if (!empty($o['etape1'])): ?>
                            <div class="space-y-2">
                                <?php foreach ($o['etape1'] as $item): ?>
                                <div class="bg-blue-50 rounded p-2 text-xs text-gray-700">
                                    <?= h(is_array($item) ? ($item['objectif'] ?? $item['texte'] ?? $item['description'] ?? json_encode($item)) : $item) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-gray-400 text-xs italic">Non commence</p>
                            <?php endif; ?>
                        </div>

                        <!-- Etape 2: Reformulations -->
                        <div class="border border-indigo-100 rounded-lg p-3">
                            <h4 class="font-bold text-indigo-700 mb-2 text-sm flex items-center gap-1">
                                <span class="bg-indigo-100 text-indigo-700 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold">2</span>
                                Reformulations (<?= count($o['etape2']) ?>)
                            </h4>
                            <?php if (!empty($o['etape2'])): ?>
                            <div class="space-y-2">
                                <?php foreach ($o['etape2'] as $item): ?>
                                <div class="bg-indigo-50 rounded p-2 text-xs text-gray-700">
                                    <?= h(is_array($item) ? ($item['objectif'] ?? $item['texte'] ?? $item['description'] ?? json_encode($item)) : $item) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-gray-400 text-xs italic">Non commence</p>
                            <?php endif; ?>
                        </div>

                        <!-- Etape 3: Creations -->
                        <div class="border border-purple-100 rounded-lg p-3">
                            <h4 class="font-bold text-purple-700 mb-2 text-sm flex items-center gap-1">
                                <span class="bg-purple-100 text-purple-700 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold">3</span>
                                Creations (<?= count($o['etape3']) ?>)
                            </h4>
                            <?php if (!empty($o['etape3'])): ?>
                            <div class="space-y-2">
                                <?php foreach ($o['etape3'] as $item): ?>
                                <div class="bg-purple-50 rounded p-2 text-xs text-gray-700">
                                    <?= h(is_array($item) ? ($item['objectif'] ?? $item['texte'] ?? $item['description'] ?? json_encode($item)) : $item) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-gray-400 text-xs italic">Non commence</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3 text-xs text-gray-400 text-right">
                        Mis a jour: <?= date('d/m/Y H:i', strtotime($o['updated_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
