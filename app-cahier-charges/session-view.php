<?php
/**
 * Vue globale de session - Cahier des Charges
 * Affiche les cahiers des charges de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-cahier-charges';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse.");
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

// Recuperer les cahiers de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM cahiers WHERE session_id = ? ORDER BY created_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM cahiers WHERE session_id = ? AND is_shared = 1 ORDER BY created_at DESC");
    $stmt->execute([$sessionId]);
}
$allCahiers = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allCahiers as &$c) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$c['user_id']]);
    $userInfo = $userStmt->fetch();
    $c['user_prenom'] = $userInfo['prenom'] ?? '';
    $c['user_nom'] = $userInfo['nom'] ?? '';
    $c['user_organisation'] = $userInfo['organisation'] ?? '';
}
unset($c);

// Statistiques
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM cahiers WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM cahiers WHERE session_id = ?");
$stmt->execute([$sessionId]);
$participantsCount = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cahier des Charges - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        .cahier-card { transition: all 0.3s ease; }
        .cahier-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-gray-200 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-slate-700 to-gray-800 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Cahier des Charges</h1>
                    <p class="text-slate-300 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
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
                    <button onclick="window.print()" class="bg-slate-600 hover:bg-slate-500 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-slate-600 hover:bg-slate-500 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Tous les cahiers</strong> - Vous voyez tous les cahiers (<?= $totalAll ?>), y compris ceux non partages.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-slate-700"><?= count($allCahiers) ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Cahiers (tous)' : 'Cahiers partages' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-gray-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Partages</div>
            </div>
        </div>

        <!-- Cahiers des participants -->
        <div class="space-y-6">
            <?php foreach ($allCahiers as $cahier): ?>
            <div class="cahier-card bg-white rounded-xl shadow-lg overflow-hidden <?= (!$cahier['is_shared'] && $showAll) ? 'opacity-75 border-2 border-orange-300' : '' ?>">
                <!-- En-tete participant -->
                <div class="bg-gradient-to-r from-slate-600 to-gray-700 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($cahier['user_prenom']) ?> <?= h($cahier['user_nom']) ?></span>
                            <?php if (!empty($cahier['user_organisation'])): ?>
                            <span class="text-slate-300 text-sm ml-2">(<?= h($cahier['user_organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if (!$cahier['is_shared'] && $showAll): ?>
                            <span class="bg-orange-200 text-orange-800 text-xs px-2 py-0.5 rounded">Non partage</span>
                            <?php endif; ?>
                            <?php if (!empty($cahier['titre_projet'])): ?>
                            <span class="bg-white/20 px-3 py-1 rounded text-sm"><?= h($cahier['titre_projet']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Titre du projet -->
                    <?php if (!empty($cahier['titre_projet'])): ?>
                    <h3 class="text-xl font-bold text-slate-800 mb-4"><?= h($cahier['titre_projet']) ?></h3>
                    <?php endif; ?>

                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Chef de projet -->
                        <?php if (!empty($cahier['chef_projet'])): ?>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <div class="text-xs font-semibold text-slate-500 uppercase mb-1">Chef de projet</div>
                            <p class="text-gray-700 text-sm"><?= h($cahier['chef_projet']) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Objectif strategique -->
                        <?php if (!empty($cahier['objectif_strategique'])): ?>
                        <div class="bg-amber-50 rounded-lg p-4">
                            <div class="text-xs font-semibold text-amber-600 uppercase mb-1">Objectif strategique</div>
                            <p class="text-gray-700 text-sm"><?= nl2br(h($cahier['objectif_strategique'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Description du projet -->
                        <?php if (!empty($cahier['description_projet'])): ?>
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="text-xs font-semibold text-blue-500 uppercase mb-1">Description du projet</div>
                            <p class="text-gray-700 text-sm"><?= nl2br(h($cahier['description_projet'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Objectif du projet -->
                        <?php if (!empty($cahier['objectif_projet'])): ?>
                        <div class="bg-teal-50 rounded-lg p-4">
                            <div class="text-xs font-semibold text-teal-500 uppercase mb-1">Objectif du projet</div>
                            <p class="text-gray-700 text-sm"><?= nl2br(h($cahier['objectif_projet'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($allCahiers)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <div class="text-6xl mb-4">&#x1F4D1;</div>
                <p class="text-gray-500 text-lg">Aucun cahier des charges <?= $showAll ? '' : 'partage' ?> pour cette session.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
