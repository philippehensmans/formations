<?php
/**
 * Vue globale de session - Prompt Jeunes
 * Affiche tous les travaux de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-prompt-jeunes';

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

// Recuperer les travaux de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM travaux WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM travaux WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$allTravaux = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allTravaux as &$t) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$t['user_id']]);
    $userInfo = $userStmt->fetch();
    $t['user_prenom'] = $userInfo['prenom'] ?? '';
    $t['user_nom'] = $userInfo['nom'] ?? '';
    $t['user_organisation'] = $userInfo['organisation'] ?? '';
}
unset($t);

// Statistiques
$totalTravaux = count($allTravaux);

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM travaux WHERE session_id = ?");
$stmt->execute([$sessionId]);
$totalParticipants = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM travaux WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$avgCompletion = $totalTravaux > 0 ? round(array_sum(array_column($allTravaux, 'completion_percent')) / $totalTravaux) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prompt Jeunes - Vue Session - <?= h($session['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>🚀</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-rose-50 to-pink-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-rose-600 to-pink-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">🚀 Prompt Jeunes</h1>
                    <p class="text-rose-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
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
                    <button onclick="window.print()" class="bg-rose-500 hover:bg-rose-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-rose-500 hover:bg-rose-400 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Tous les travaux</strong> - Vous voyez tous les travaux (<?= $totalAll ?>), y compris ceux non partages.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-rose-600"><?= $totalParticipants ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-pink-600"><?= $totalTravaux ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Travaux (tous)' : 'Travaux partages' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Travaux partages</div>
            </div>
        </div>

        <!-- Travaux des participants -->
        <div class="space-y-6">
            <?php if (empty($allTravaux)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <p class="text-gray-400 text-lg">Aucun travail pour le moment.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($allTravaux as $t): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden <?= (!$t['is_shared'] && $showAll) ? 'opacity-75 border-2 border-orange-200' : '' ?>">
                <div class="bg-gradient-to-r from-rose-500 to-pink-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($t['user_prenom']) ?> <?= h($t['user_nom']) ?></span>
                            <?php if (!empty($t['user_organisation'])): ?>
                            <span class="text-rose-200 text-sm ml-2">(<?= h($t['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!$t['is_shared'] && $showAll): ?>
                            <span class="bg-orange-400 text-white text-xs px-2 py-0.5 rounded ml-2">Non partage</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-rose-200">Organisation: <?= h($t['organisation_nom'] ?: 'Non definie') ?></span>
                            <span class="bg-white/20 px-2 py-0.5 rounded text-xs">Exercice <?= (int)$t['exercice_num'] ?></span>
                        </div>
                    </div>
                    <!-- Barre de completion -->
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-rose-200 mb-1">
                            <span>Progression</span>
                            <span><?= (int)$t['completion_percent'] ?>%</span>
                        </div>
                        <div class="w-full bg-white/20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full transition-all" style="width: <?= (int)$t['completion_percent'] ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <!-- Cas choisi -->
                    <?php if (!empty($t['cas_choisi'])): ?>
                    <div class="mb-4">
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-rose-500">🎯</span> Cas choisi
                        </h4>
                        <p class="text-sm text-gray-700 bg-rose-50 rounded-lg p-3 border border-rose-100"><?= h($t['cas_choisi']) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Prompt initial -->
                    <?php if (!empty($t['prompt_initial'])): ?>
                    <div class="mb-4">
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-rose-500">💬</span> Prompt initial
                        </h4>
                        <div class="bg-rose-50 rounded-lg p-3 border border-rose-100">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($t['prompt_initial']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Resultat initial -->
                    <?php if (!empty($t['resultat_initial'])): ?>
                    <div class="mb-4">
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-pink-500">📄</span> Resultat initial
                        </h4>
                        <div class="bg-pink-50 rounded-lg p-3 border border-pink-100">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($t['resultat_initial']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Prompt ameliore -->
                    <?php if (!empty($t['prompt_ameliore'])): ?>
                    <div class="mb-4">
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-rose-600">✨</span> Prompt ameliore
                        </h4>
                        <div class="bg-gradient-to-r from-rose-50 to-pink-50 rounded-lg p-3 border border-rose-200">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($t['prompt_ameliore']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Resultat ameliore -->
                    <?php if (!empty($t['resultat_ameliore'])): ?>
                    <div class="mb-4">
                        <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="text-pink-600">🌟</span> Resultat ameliore
                        </h4>
                        <div class="bg-gradient-to-r from-pink-50 to-fuchsia-50 rounded-lg p-3 border border-pink-200">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($t['resultat_ameliore']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($t['cas_choisi']) && empty($t['prompt_initial']) && empty($t['prompt_ameliore'])): ?>
                    <p class="text-gray-400 text-sm italic">Aucun contenu encore.</p>
                    <?php endif; ?>

                    <div class="mt-3 text-xs text-gray-400 text-right">
                        Mis a jour: <?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
