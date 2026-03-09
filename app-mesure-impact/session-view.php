<?php
/**
 * Vue globale de session - Mesure d'Impact
 * Affiche la progression des participants dans les etapes de mesure d'impact
 */
require_once __DIR__ . '/config.php';

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

// Recuperer les mesures d'impact via JOIN participants pour obtenir user_id
if ($showAll) {
    $stmt = $db->prepare("SELECT m.*, p.user_id FROM mesure_impact m JOIN participants p ON m.participant_id = p.id WHERE m.session_id = ? ORDER BY m.completion_percent DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT m.*, p.user_id FROM mesure_impact m JOIN participants p ON m.participant_id = p.id WHERE m.session_id = ? AND m.is_submitted = 1 ORDER BY m.completion_percent DESC");
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

    // Decoder les JSON des etapes
    $m['etape1_data'] = json_decode($m['etape1_classification'] ?? '{}', true) ?: [];
    $m['etape2_data'] = json_decode($m['etape2_theorie_changement'] ?? '{}', true) ?: [];
    $m['etape3_data'] = json_decode($m['etape3_indicateurs'] ?? '{}', true) ?: [];
    $m['etape4_data'] = json_decode($m['etape4_plan_collecte'] ?? '{}', true) ?: [];
    $m['etape5_data'] = json_decode($m['etape5_synthese'] ?? '{}', true) ?: [];
}
unset($m);

// Statistiques
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) as submitted FROM mesure_impact WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalSubmitted = $counts['submitted'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT participant_id) as count FROM mesure_impact WHERE session_id = ?");
$stmt->execute([$sessionId]);
$participantsCount = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT AVG(completion_percent) as avg_completion FROM mesure_impact WHERE session_id = ?");
$stmt->execute([$sessionId]);
$avgCompletion = round($stmt->fetch()['avg_completion'] ?? 0);

$etapeLabels = [
    1 => 'Classification',
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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        .mesure-card { transition: all 0.3s ease; }
        .mesure-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-teal-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Mesure d'Impact</h1>
                    <p class="text-emerald-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
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
                <strong>Mode: Tous les travaux</strong> - Vous voyez tous les travaux (<?= $totalAll ?>), y compris ceux non soumis.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?= count($allMesures) ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Travaux (tous)' : 'Travaux soumis' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalSubmitted ?></div>
                <div class="text-gray-500 text-sm">Soumis</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-cyan-600"><?= $avgCompletion ?>%</div>
                <div class="text-gray-500 text-sm">Completion moyenne</div>
            </div>
        </div>

        <!-- Participants -->
        <div class="space-y-6">
            <?php foreach ($allMesures as $mesure): ?>
            <?php $etapeCourante = (int)($mesure['etape_courante'] ?? 1); ?>
            <div class="mesure-card bg-white rounded-xl shadow-lg overflow-hidden <?= (!$mesure['is_submitted'] && $showAll) ? 'opacity-75 border-2 border-orange-300' : '' ?>">
                <!-- En-tete participant -->
                <div class="bg-gradient-to-r from-emerald-500 to-teal-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($mesure['user_prenom']) ?> <?= h($mesure['user_nom']) ?></span>
                            <?php if (!empty($mesure['user_organisation'])): ?>
                            <span class="text-emerald-200 text-sm ml-2">(<?= h($mesure['user_organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if ($mesure['is_submitted']): ?>
                            <span class="bg-green-400/50 text-white text-xs px-2 py-0.5 rounded">Soumis</span>
                            <?php elseif ($showAll): ?>
                            <span class="bg-orange-200 text-orange-800 text-xs px-2 py-0.5 rounded">Non soumis</span>
                            <?php endif; ?>
                            <span class="bg-white/30 px-2 py-1 rounded text-xs">Etape <?= $etapeCourante ?>/5</span>
                            <span class="bg-white/30 px-2 py-1 rounded text-xs"><?= (int)($mesure['completion_percent'] ?? 0) ?>%</span>
                        </div>
                    </div>
                    <!-- Barre de completion -->
                    <div class="mt-2">
                        <div class="w-full bg-white/20 rounded-full h-1.5">
                            <div class="bg-white h-1.5 rounded-full" style="width: <?= (int)($mesure['completion_percent'] ?? 0) ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <!-- Progression des etapes -->
                    <div class="flex items-center justify-between mb-4">
                        <?php for ($i = 1; $i <= 5; $i++):
                            $isCompleted = $i < $etapeCourante || $mesure['is_submitted'];
                            $isCurrent = $i == $etapeCourante && !$mesure['is_submitted'];
                            $etapeData = $mesure['etape' . $i . '_data'] ?? [];
                            $hasData = !empty($etapeData);
                        ?>
                        <div class="flex-1 text-center">
                            <div class="mx-auto w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold mb-1
                                <?php if ($isCompleted): ?>
                                    bg-emerald-500 text-white
                                <?php elseif ($isCurrent): ?>
                                    bg-emerald-200 text-emerald-700 ring-2 ring-emerald-400
                                <?php else: ?>
                                    bg-gray-200 text-gray-400
                                <?php endif; ?>
                            "><?= $i ?></div>
                            <div class="text-xs <?= $isCompleted ? 'text-emerald-600 font-semibold' : ($isCurrent ? 'text-emerald-500' : 'text-gray-400') ?>">
                                <?= $etapeLabels[$i] ?>
                            </div>
                        </div>
                        <?php if ($i < 5): ?>
                        <div class="flex-shrink-0 w-8 h-0.5 mt-[-12px] <?= $i < $etapeCourante ? 'bg-emerald-400' : 'bg-gray-200' ?>"></div>
                        <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <!-- Resume des donnees remplies -->
                    <div class="grid grid-cols-5 gap-2">
                        <?php for ($i = 1; $i <= 5; $i++):
                            $etapeData = $mesure['etape' . $i . '_data'];
                            $hasData = !empty($etapeData);
                        ?>
                        <div class="rounded-lg p-2 text-center text-xs <?= $hasData ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-50 text-gray-400' ?>">
                            <?php if ($hasData): ?>
                                <?= count($etapeData) ?> element<?= count($etapeData) > 1 ? 's' : '' ?>
                            <?php else: ?>
                                Non rempli
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($allMesures)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <div class="text-6xl mb-4">&#x1F4CA;</div>
                <p class="text-gray-500 text-lg">Aucun travail de mesure d'impact <?= $showAll ? '' : 'soumis' ?> pour cette session.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
