<?php
/**
 * Vue globale de session - Carte Projet
 * Affiche les cartes projet de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-carte-projet';

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

// Option pour voir tous ou seulement les soumis
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les cartes projet de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM cartes_projet WHERE session_id = ? ORDER BY created_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM cartes_projet WHERE session_id = ? AND is_submitted = 1 ORDER BY created_at DESC");
    $stmt->execute([$sessionId]);
}
$allCartes = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allCartes as &$c) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$c['user_id']]);
    $userInfo = $userStmt->fetch();
    $c['user_prenom'] = $userInfo['prenom'] ?? '';
    $c['user_nom'] = $userInfo['nom'] ?? '';
    $c['user_organisation'] = $userInfo['organisation'] ?? '';

    // Decoder les partenaires JSON
    $c['partenaires_arr'] = json_decode($c['partenaires'] ?? '[]', true) ?: [];
}
unset($c);

// Statistiques
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) as submitted FROM cartes_projet WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalSubmitted = $counts['submitted'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM cartes_projet WHERE session_id = ?");
$stmt->execute([$sessionId]);
$participantsCount = $stmt->fetch()['count'];

// Moyenne de completion
$stmt = $db->prepare("SELECT AVG(completion_percent) as avg_completion FROM cartes_projet WHERE session_id = ?");
$stmt->execute([$sessionId]);
$avgCompletion = round($stmt->fetch()['avg_completion'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Projet - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        .projet-card { transition: all 0.3s ease; }
        .projet-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 to-violet-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-600 to-violet-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Carte Projet</h1>
                    <p class="text-purple-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Soumis seulement (<?= $totalSubmitted ?>)
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
                <strong>Mode: Toutes les cartes</strong> - Vous voyez toutes les cartes (<?= $totalAll ?>), y compris celles non soumises.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= count($allCartes) ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Cartes (toutes)' : 'Cartes soumises' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-violet-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalSubmitted ?></div>
                <div class="text-gray-500 text-sm">Soumises</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
        </div>

        <!-- Cartes des participants -->
        <div class="space-y-6">
            <?php foreach ($allCartes as $carte): ?>
            <div class="projet-card bg-white rounded-xl shadow-lg overflow-hidden <?= (!$carte['is_submitted'] && $showAll) ? 'opacity-75 border-2 border-orange-300' : '' ?>">
                <!-- En-tete participant -->
                <div class="bg-gradient-to-r from-purple-500 to-violet-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($carte['user_prenom']) ?> <?= h($carte['user_nom']) ?></span>
                            <?php if (!empty($carte['user_organisation'])): ?>
                            <span class="text-purple-200 text-sm ml-2">(<?= h($carte['user_organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if (!$carte['is_submitted'] && $showAll): ?>
                            <span class="bg-orange-200 text-orange-800 text-xs px-2 py-0.5 rounded">Non soumis</span>
                            <?php endif; ?>
                            <span class="bg-white/30 px-2 py-1 rounded text-xs"><?= (int)($carte['completion_percent'] ?? 0) ?>%</span>
                        </div>
                    </div>
                    <!-- Barre de completion -->
                    <div class="mt-2">
                        <div class="w-full bg-white/20 rounded-full h-1.5">
                            <div class="bg-white h-1.5 rounded-full" style="width: <?= (int)($carte['completion_percent'] ?? 0) ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Titre du projet -->
                    <?php if (!empty($carte['titre'])): ?>
                    <h3 class="text-xl font-bold text-purple-800 mb-4"><?= h($carte['titre']) ?></h3>
                    <?php endif; ?>

                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Objectifs -->
                        <?php if (!empty($carte['objectifs'])): ?>
                        <div class="bg-purple-50 rounded-lg p-4">
                            <div class="text-xs font-semibold text-purple-500 uppercase mb-1">Objectifs</div>
                            <p class="text-gray-700 text-sm"><?= nl2br(h($carte['objectifs'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Public cible -->
                        <?php if (!empty($carte['public_cible'])): ?>
                        <div class="bg-violet-50 rounded-lg p-4">
                            <div class="text-xs font-semibold text-violet-500 uppercase mb-1">Public cible</div>
                            <p class="text-gray-700 text-sm"><?= nl2br(h($carte['public_cible'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Territoire -->
                        <?php if (!empty($carte['territoire'])): ?>
                        <div class="bg-indigo-50 rounded-lg p-4">
                            <div class="text-xs font-semibold text-indigo-500 uppercase mb-1">Territoire</div>
                            <p class="text-gray-700 text-sm"><?= nl2br(h($carte['territoire'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Partenaires -->
                        <?php if (!empty($carte['partenaires_arr'])): ?>
                        <div class="bg-pink-50 rounded-lg p-4">
                            <div class="text-xs font-semibold text-pink-500 uppercase mb-1">Partenaires</div>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($carte['partenaires_arr'] as $partenaire): ?>
                                <span class="bg-pink-100 text-pink-700 text-xs px-2 py-1 rounded-full">
                                    <?= h(is_array($partenaire) ? ($partenaire['nom'] ?? $partenaire['name'] ?? json_encode($partenaire)) : $partenaire) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($allCartes)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <div class="text-6xl mb-4">&#x1F4CB;</div>
                <p class="text-gray-500 text-lg">Aucune carte projet <?= $showAll ? '' : 'soumise' ?> pour cette session.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
