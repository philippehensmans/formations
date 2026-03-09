<?php
/**
 * Vue globale de session - Carte d'Identite
 * Affiche les fiches d'identite de tous les participants
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-carte-identite';

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

// Recuperer les fiches de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM fiches WHERE session_id = ? ORDER BY created_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM fiches WHERE session_id = ? AND is_shared = 1 ORDER BY created_at DESC");
    $stmt->execute([$sessionId]);
}
$allFiches = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allFiches as &$f) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$f['user_id']]);
    $userInfo = $userStmt->fetch();
    $f['user_prenom'] = $userInfo['prenom'] ?? '';
    $f['user_nom'] = $userInfo['nom'] ?? '';
    $f['user_organisation'] = $userInfo['organisation'] ?? '';

    // Decoder les partenaires JSON
    $f['partenaires_arr'] = json_decode($f['partenaires'] ?? '[]', true) ?: [];
}
unset($f);

// Statistiques
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM fiches WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM fiches WHERE session_id = ?");
$stmt->execute([$sessionId]);
$participantsCount = $stmt->fetch()['count'];

// Moyenne de completion
$stmt = $db->prepare("SELECT AVG(completion_percent) as avg_completion FROM fiches WHERE session_id = ?");
$stmt->execute([$sessionId]);
$avgCompletion = round($stmt->fetch()['avg_completion'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte d'Identite - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        .fiche-card { transition: all 0.3s ease; }
        .fiche-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-50 to-blue-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-sky-600 to-blue-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Carte d'Identite</h1>
                    <p class="text-sky-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Partages seulement (<?= $totalShared ?>)
                    </a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">
                        Tous (<?= $totalAll ?>)
                    </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="bg-sky-500 hover:bg-sky-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-sky-500 hover:bg-sky-400 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Toutes les fiches</strong> - Vous voyez toutes les fiches (<?= $totalAll ?>), y compris celles non partagees.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-sky-600"><?= count($allFiches) ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Fiches (toutes)' : 'Fiches partagees' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalShared ?></div>
                <div class="text-gray-500 text-sm">Partagees</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
        </div>

        <!-- Fiches des participants -->
        <?php if (empty($allFiches)): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <p class="text-gray-500 text-lg">Aucune fiche <?= $showAll ? '' : 'partagee' ?> pour cette session.</p>
        </div>
        <?php else: ?>
        <div class="grid md:grid-cols-2 gap-6">
            <?php foreach ($allFiches as $fiche): ?>
            <div class="fiche-card bg-white rounded-xl shadow-lg overflow-hidden <?= (!$fiche['is_shared'] && $showAll) ? 'opacity-75 border-2 border-orange-300' : '' ?>">
                <!-- En-tete -->
                <div class="bg-gradient-to-r from-sky-500 to-blue-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold"><?= h($fiche['user_prenom']) ?> <?= h($fiche['user_nom']) ?></span>
                            <?php if (!empty($fiche['user_organisation'])): ?>
                            <span class="text-sky-200 text-sm ml-2">(<?= h($fiche['user_organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if (!$fiche['is_shared'] && $showAll): ?>
                            <span class="bg-orange-200 text-orange-800 text-xs px-2 py-0.5 rounded">Non partage</span>
                            <?php endif; ?>
                            <span class="bg-white/30 px-2 py-1 rounded text-xs"><?= (int)($fiche['completion_percent'] ?? 0) ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="p-5">
                    <!-- Barre de completion -->
                    <div class="mb-4">
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>Completion</span>
                            <span><?= (int)($fiche['completion_percent'] ?? 0) ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-sky-500 h-2 rounded-full transition-all duration-500" style="width: <?= (int)($fiche['completion_percent'] ?? 0) ?>%"></div>
                        </div>
                    </div>

                    <!-- Titre -->
                    <?php if (!empty($fiche['titre'])): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Titre</div>
                        <p class="text-gray-800 font-medium"><?= h($fiche['titre']) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Objectifs -->
                    <?php if (!empty($fiche['objectifs'])): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Objectifs</div>
                        <p class="text-gray-700 text-sm"><?= nl2br(h($fiche['objectifs'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Public cible -->
                    <?php if (!empty($fiche['public_cible'])): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Public cible</div>
                        <p class="text-gray-700 text-sm"><?= nl2br(h($fiche['public_cible'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Territoire -->
                    <?php if (!empty($fiche['territoire'])): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Territoire</div>
                        <p class="text-gray-700 text-sm"><?= h($fiche['territoire']) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Partenaires -->
                    <?php if (!empty($fiche['partenaires_arr'])): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Partenaires (<?= count($fiche['partenaires_arr']) ?>)</div>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($fiche['partenaires_arr'] as $partenaire): ?>
                            <span class="bg-sky-100 text-sky-700 text-xs px-2 py-1 rounded-full">
                                <?= h(is_array($partenaire) ? ($partenaire['nom'] ?? $partenaire['name'] ?? json_encode($partenaire)) : $partenaire) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Ressources -->
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <?php if (!empty($fiche['ressources_humaines'])): ?>
                        <div>
                            <div class="text-xs font-semibold text-sky-500 uppercase mb-1">RH</div>
                            <p class="text-gray-700 text-xs"><?= h($fiche['ressources_humaines']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($fiche['ressources_materielles'])): ?>
                        <div>
                            <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Materielles</div>
                            <p class="text-gray-700 text-xs"><?= h($fiche['ressources_materielles']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($fiche['ressources_financieres'])): ?>
                        <div>
                            <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Financieres</div>
                            <p class="text-gray-700 text-xs"><?= h($fiche['ressources_financieres']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Calendrier -->
                    <?php if (!empty($fiche['calendrier'])): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Calendrier</div>
                        <p class="text-gray-700 text-sm"><?= h($fiche['calendrier']) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Resultats -->
                    <?php if (!empty($fiche['resultats'])): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Resultats attendus</div>
                        <p class="text-gray-700 text-sm"><?= nl2br(h($fiche['resultats'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Notes -->
                    <?php if (!empty($fiche['notes'])): ?>
                    <div>
                        <div class="text-xs font-semibold text-sky-500 uppercase mb-1">Notes</div>
                        <p class="text-gray-600 text-sm italic"><?= nl2br(h($fiche['notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
