<?php
/**
 * Vue globale de session - Mesure d'Impact Social
 * Affiche la progression de tous les participants
 */
require_once __DIR__ . '/config/database.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-mesure-impact';

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

// Option pour voir tous ou seulement les soumis
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les mesures d'impact via participant_id
if ($showAll) {
    $stmt = $db->prepare("SELECT m.*, p.user_id FROM mesure_impact m JOIN participants p ON m.participant_id = p.id WHERE m.session_id = ? ORDER BY m.updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT m.*, p.user_id FROM mesure_impact m JOIN participants p ON m.participant_id = p.id WHERE m.session_id = ? AND m.is_submitted = 1 ORDER BY m.submitted_at DESC");
    $stmt->execute([$sessionId]);
}
$allMesures = $stmt->fetchAll();

// Enrichir avec les infos utilisateur
foreach ($allMesures as &$m) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$m['user_id']]);
    $userInfo = $userStmt->fetch();
    $m['user_prenom'] = $userInfo['prenom'] ?? '';
    $m['user_nom'] = $userInfo['nom'] ?? '';
    $m['user_organisation'] = $userInfo['organisation'] ?? '';
    $m['etape1_data'] = json_decode($m['etape1_classification'] ?? '{}', true) ?: [];
    $m['etape2_data'] = json_decode($m['etape2_theorie_changement'] ?? '{}', true) ?: [];
    $m['etape3_data'] = json_decode($m['etape3_indicateurs'] ?? '{}', true) ?: [];
    $m['etape4_data'] = json_decode($m['etape4_plan_collecte'] ?? '{}', true) ?: [];
    $m['etape5_data'] = json_decode($m['etape5_synthese'] ?? '{}', true) ?: [];
}
unset($m);

// Statistiques
$totalMesures = count($allMesures);

$stmt = $db->prepare("SELECT COUNT(DISTINCT p.user_id) as count FROM participants p WHERE p.session_id = ?");
$stmt->execute([$sessionId]);
$totalParticipants = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN m.is_submitted = 1 THEN 1 ELSE 0 END) as submitted FROM mesure_impact m WHERE m.session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalSubmitted = $counts['submitted'];

$avgCompletion = $totalMesures > 0 ? round(array_sum(array_column($allMesures, 'completion_percent')) / $totalMesures) : 0;

$etapeNames = [
    1 => 'Classification (Output/Outcome/Impact)',
    2 => 'Theorie du changement',
    3 => 'Indicateurs',
    4 => 'Plan de collecte',
    5 => 'Synthese',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesure d'Impact - Vue Session - <?= h($session['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>📊</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-teal-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">📊 Mesure d'Impact Social</h1>
                    <p class="text-emerald-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
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
                    <button onclick="window.print()" class="bg-emerald-500 hover:bg-emerald-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-emerald-500 hover:bg-emerald-400 px-3 py-1 rounded text-sm">
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
                <div class="text-3xl font-bold text-emerald-600"><?= $totalParticipants ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $totalMesures ?></div>
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

        <!-- Participants et leur progression -->
        <div class="space-y-6">
            <?php if (empty($allMesures)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <p class="text-gray-400 text-lg">Aucune mesure d'impact pour le moment.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($allMesures as $m): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden <?= (!$m['is_submitted'] && $showAll) ? 'opacity-75 border-2 border-orange-200' : '' ?>">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($m['user_prenom']) ?> <?= h($m['user_nom']) ?></span>
                            <?php if (!empty($m['user_organisation'])): ?>
                            <span class="text-emerald-200 text-sm ml-2">(<?= h($m['user_organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!$m['is_submitted'] && $showAll): ?>
                            <span class="bg-orange-400 text-white text-xs px-2 py-0.5 rounded ml-2">Non soumis</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-right text-sm">
                            <span class="text-emerald-200">Etape <?= (int)$m['etape_courante'] ?>/5</span>
                        </div>
                    </div>
                    <!-- Barre de completion -->
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-emerald-200 mb-1">
                            <span>Progression</span>
                            <span><?= (int)$m['completion_percent'] ?>%</span>
                        </div>
                        <div class="w-full bg-white/20 rounded-full h-2">
                            <div class="bg-white h-2 rounded-full transition-all" style="width: <?= (int)$m['completion_percent'] ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <!-- Indicateur des etapes -->
                    <div class="flex items-center gap-2 mb-4 overflow-x-auto">
                        <?php for ($e = 1; $e <= 5; $e++):
                            $completed = $e < (int)$m['etape_courante'];
                            $current = $e == (int)$m['etape_courante'];
                            $etapeData = $m["etape{$e}_data"] ?? [];
                            $hasData = !empty($etapeData) && $etapeData !== '{}';
                        ?>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?= $completed ? 'bg-emerald-500 text-white' : ($current ? 'bg-emerald-200 text-emerald-800 ring-2 ring-emerald-400' : 'bg-gray-100 text-gray-400') ?>">
                                <?= $e ?>
                            </div>
                            <span class="text-xs <?= $completed ? 'text-emerald-600 font-medium' : ($current ? 'text-emerald-700 font-medium' : 'text-gray-400') ?> hidden md:inline">
                                <?= $etapeNames[$e] ?>
                            </span>
                            <?php if ($e < 5): ?>
                            <div class="w-4 h-0.5 <?= $completed ? 'bg-emerald-400' : 'bg-gray-200' ?>"></div>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Contenu des etapes -->
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php for ($e = 1; $e <= 5; $e++):
                            $data = $m["etape{$e}_data"];
                            $hasContent = !empty($data) && $data !== [];
                        ?>
                        <div class="border border-gray-100 rounded-lg p-3 <?= $hasContent ? 'bg-emerald-50' : 'bg-gray-50' ?>">
                            <h5 class="text-xs font-bold text-gray-600 mb-1">Etape <?= $e ?>: <?= $etapeNames[$e] ?></h5>
                            <?php if ($hasContent): ?>
                                <?php if (is_array($data)): ?>
                                    <?php
                                    // Afficher un resume du contenu
                                    $summary = [];
                                    foreach ($data as $key => $val) {
                                        if (is_string($val) && strlen($val) > 0) {
                                            $summary[] = h(mb_substr($val, 0, 80)) . (mb_strlen($val) > 80 ? '...' : '');
                                        } elseif (is_array($val) && count($val) > 0) {
                                            $summary[] = count($val) . ' element(s)';
                                        }
                                    }
                                    if (empty($summary)) {
                                        $summary[] = count($data) . ' donnee(s)';
                                    }
                                    ?>
                                    <?php foreach (array_slice($summary, 0, 3) as $s): ?>
                                    <p class="text-xs text-gray-600"><?= $s ?></p>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-xs text-gray-600"><?= h(mb_substr((string)$data, 0, 100)) ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 italic">Non commence</p>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div class="mt-3 text-xs text-gray-400 text-right">
                        <?php if ($m['submitted_at']): ?>
                        Soumis le: <?= date('d/m/Y H:i', strtotime($m['submitted_at'])) ?>
                        <?php else: ?>
                        Mis a jour: <?= date('d/m/Y H:i', strtotime($m['updated_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
