<?php
/**
 * Vue globale de session - Auto-Diagnostic Communication
 * Affiche tous les diagnostics de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-comm-diagnostic';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse a cette session.");
}

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: formateur.php');
    exit;
}

$showAll = isset($_GET['all']) && $_GET['all'] == '1';

if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM analyses WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM analyses WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$analyses = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM analyses WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

$budgetLabels = ['moins_2' => 'Moins de 2%', '2_5' => '2-5%', '5_10' => '5-10%', 'plus_10' => 'Plus de 10%', 'ne_sais_pas' => 'Ne sais pas'];
$ressNonFinLabels = ['benevoles' => 'Benevoles', 'partenariats' => 'Partenariats', 'competences' => 'Competences internes', 'reseaux' => 'Reseaux personnels'];

// Aggregate data
$participantsData = [];
$allValeurScores = [];
$allEngagementScores = [];
$allTransformationScores = [];
$budgetCounts = [];
$ressNonFinCounts = [];
$allContraintes = [];
$allAtouts = [];

foreach ($analyses as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $defaults = getDefaultData();
    $s1 = json_decode($a['section1_data'] ?? '{}', true) ?: $defaults['section1_data'];
    $s2 = json_decode($a['section2_data'] ?? '{}', true) ?: $defaults['section2_data'];
    $s3 = json_decode($a['section3_data'] ?? '{}', true) ?: $defaults['section3_data'];
    $s4 = json_decode($a['section4_data'] ?? '{}', true) ?: $defaults['section4_data'];
    $s5 = json_decode($a['section5_data'] ?? '{}', true) ?: $defaults['section5_data'];

    // Collect scores
    foreach (($s1['valeurs_scores'] ?? []) as $vs) {
        if (($vs['score'] ?? 0) > 0) $allValeurScores[] = (int)$vs['score'];
    }
    foreach (($s3['parties_prenantes'] ?? []) as $pp) {
        if (($pp['engagement'] ?? 0) > 0) $allEngagementScores[] = (int)$pp['engagement'];
    }
    if (($s3['transformation_score'] ?? 0) > 0) $allTransformationScores[] = (int)$s3['transformation_score'];

    // Budget distribution
    $budget = $s2['budget'] ?? '';
    if (!empty($budget)) $budgetCounts[$budget] = ($budgetCounts[$budget] ?? 0) + 1;

    // Ressources non-financieres
    foreach (($s2['ressources_non_financieres'] ?? []) as $r) {
        $ressNonFinCounts[$r] = ($ressNonFinCounts[$r] ?? 0) + 1;
    }

    // Collect contraintes and atouts
    foreach (($s2['contraintes'] ?? []) as $c) {
        if (!empty(trim($c))) $allContraintes[] = $c;
    }
    foreach (($s2['atouts'] ?? []) as $at) {
        if (!empty(trim($at))) $allAtouts[] = $at;
    }

    $participantsData[$a['user_id']] = [
        'user' => ['prenom' => $a['user_prenom'], 'nom' => $a['user_nom'], 'organisation' => $a['user_organisation']],
        'nom_organisation' => $a['nom_organisation'],
        's1' => $s1, 's2' => $s2, 's3' => $s3, 's4' => $s4, 's5' => $s5,
        'is_shared' => $a['is_shared']
    ];
}

$participantsCount = count($analyses);
$avgValeur = !empty($allValeurScores) ? round(array_sum($allValeurScores) / count($allValeurScores), 1) : 0;
$avgEngagement = !empty($allEngagementScores) ? round(array_sum($allEngagementScores) / count($allEngagementScores), 1) : 0;
$avgTransformation = !empty($allTransformationScores) ? round(array_sum($allTransformationScores) / count($allTransformationScores), 1) : 0;

arsort($ressNonFinCounts);
$lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Diagnostic Communication - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .item-card { transition: all 0.3s ease; }
        .item-card:hover { transform: translateY(-2px); }
        .section-number { display: flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: #0891b2; color: white; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-cyan-50 to-teal-100 min-h-screen">
    <header class="bg-gradient-to-r from-cyan-500 to-teal-600 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Auto-Diagnostic Communication</h1>
                    <p class="text-cyan-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Partages seulement (<?= $totalShared ?>)
                    </a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">
                        Voir toutes (<?= $totalAll ?>)
                    </a>
                    <?php endif; ?>
                    <?= renderLanguageSelector('bg-cyan-400 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <button onclick="window.print()" class="bg-cyan-400 hover:bg-cyan-300 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-cyan-400 hover:bg-cyan-300 px-3 py-1 rounded text-sm">
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
                <strong>Mode: Toutes les analyses</strong> - Vous voyez toutes les analyses (<?= $totalAll ?>), y compris celles non partagees.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques globales -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-cyan-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $avgValeur ?><span class="text-lg text-gray-400">/5</span></div>
                <div class="text-gray-500 text-sm">Visibilite valeurs (moy.)</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $avgEngagement ?><span class="text-lg text-gray-400">/5</span></div>
                <div class="text-gray-500 text-sm">Engagement parties prenantes (moy.)</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-amber-600"><?= $avgTransformation ?><span class="text-lg text-gray-400">/5</span></div>
                <div class="text-gray-500 text-sm">Transformation (moy.)</div>
            </div>
        </div>

        <!-- Repartition budget -->
        <?php if (!empty($budgetCounts)): ?>
        <div class="bg-white rounded-xl shadow-lg p-5 mb-6">
            <h3 class="font-bold text-gray-800 mb-3">Repartition des budgets communication</h3>
            <div class="space-y-2">
                <?php
                $maxBudget = max($budgetCounts);
                foreach ($budgetCounts as $budget => $count):
                    $pct = $maxBudget > 0 ? round(($count / $maxBudget) * 100) : 0;
                ?>
                <div class="flex items-center gap-3">
                    <div class="w-32 text-sm text-gray-600 truncate"><?= h($budgetLabels[$budget] ?? $budget) ?></div>
                    <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                        <div class="bg-cyan-500 h-full rounded-full" style="width: <?= $pct ?>%"></div>
                    </div>
                    <div class="text-sm font-bold text-cyan-700 w-16 text-right"><?= $count ?> (<?= $participantsCount > 0 ? round(($count / $participantsCount) * 100) : 0 ?>%)</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ressources non-financieres -->
        <?php if (!empty($ressNonFinCounts)): ?>
        <div class="bg-white rounded-xl shadow-lg p-5 mb-6">
            <h3 class="font-bold text-gray-800 mb-3">Ressources non-financieres a mobiliser</h3>
            <div class="space-y-2">
                <?php
                $maxRes = max($ressNonFinCounts);
                foreach ($ressNonFinCounts as $res => $count):
                    $pct = $maxRes > 0 ? round(($count / $maxRes) * 100) : 0;
                ?>
                <div class="flex items-center gap-3">
                    <div class="w-40 text-sm text-gray-600 truncate"><?= h($ressNonFinLabels[$res] ?? $res) ?></div>
                    <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                        <div class="bg-teal-500 h-full rounded-full" style="width: <?= $pct ?>%"></div>
                    </div>
                    <div class="text-sm font-bold text-teal-700 w-16 text-right"><?= $count ?> (<?= $participantsCount > 0 ? round(($count / $participantsCount) * 100) : 0 ?>%)</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filtres d'affichage -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 no-print">
            <div class="flex flex-wrap gap-4 items-center">
                <div class="font-medium text-gray-700">Affichage:</div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="summary" checked onchange="setDisplayMode('summary')">
                    <span class="text-sm">Synthese comparative</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="forces" onchange="setDisplayMode('forces')">
                    <span class="text-sm">Forces et defis</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="participant" onchange="setDisplayMode('participant')">
                    <span class="text-sm">Par participant (detail)</span>
                </label>
            </div>
        </div>

        <!-- Vue Synthese comparative -->
        <div id="summaryView">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Synthese comparative des diagnostics</h2>
            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucune analyse trouvee pour cette session.
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($participantsData as $userId => $data):
                    $scores = calculateSectionScores([
                        'section1_data' => $data['s1'],
                        'section3_data' => $data['s3']
                    ]);
                ?>
                <div class="item-card bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-cyan-500 to-teal-500 text-white p-3">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-bold"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                                <?php if (!empty($data['nom_organisation'])): ?>
                                <span class="text-cyan-200 text-sm ml-2">- <?= h($data['nom_organisation']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <span class="bg-white/20 px-2 py-1 rounded text-xs">Valeurs: <?= $scores['valeurs'] ?>/5</span>
                                <span class="bg-white/20 px-2 py-1 rounded text-xs">Engagement: <?= $scores['engagement'] ?>/5</span>
                                <span class="bg-white/20 px-2 py-1 rounded text-xs">Transformation: <?= $scores['transformation'] ?>/5</span>
                                <span class="px-2 py-1 rounded text-xs <?= $data['is_shared'] ? 'bg-green-500/50' : 'bg-yellow-500/50' ?>"><?= $data['is_shared'] ? 'Soumis' : 'Brouillon' ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="grid md:grid-cols-3 gap-4 mb-3">
                            <!-- Valeurs -->
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="section-number">1</div>
                                    <span class="text-xs font-semibold text-gray-500">Valeurs</span>
                                </div>
                                <div class="space-y-1">
                                    <?php foreach (($data['s1']['valeurs'] ?? []) as $i => $v):
                                        if (empty(trim($v))) continue;
                                        $sc = $data['s1']['valeurs_scores'][$i]['score'] ?? 0;
                                    ?>
                                    <div class="flex items-center gap-1 text-sm">
                                        <span class="w-5 h-5 rounded-full text-xs flex items-center justify-center font-bold <?= $sc >= 4 ? 'bg-green-100 text-green-700' : ($sc >= 3 ? 'bg-yellow-100 text-yellow-700' : ($sc > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400')) ?>"><?= $sc ?: '-' ?></span>
                                        <span class="text-gray-700"><?= h($v) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <!-- Contraintes vs Atouts -->
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="section-number">2</div>
                                    <span class="text-xs font-semibold text-gray-500">Budget: <?= h($budgetLabels[$data['s2']['budget'] ?? ''] ?? '-') ?></span>
                                </div>
                                <div class="text-xs text-red-600 mb-1">
                                    <?php foreach (array_filter($data['s2']['contraintes'] ?? [], fn($c) => !empty(trim($c))) as $c): ?>
                                    <div>- <?= h($c) ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-xs text-green-600">
                                    <?php foreach (array_filter($data['s2']['atouts'] ?? [], fn($a) => !empty(trim($a))) as $at): ?>
                                    <div>+ <?= h($at) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <!-- Parties prenantes -->
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="section-number">3</div>
                                    <span class="text-xs font-semibold text-gray-500">Parties prenantes</span>
                                </div>
                                <div class="space-y-1">
                                    <?php foreach (($data['s3']['parties_prenantes'] ?? []) as $pp):
                                        if (empty(trim($pp['nom'] ?? ''))) continue;
                                        $eng = $pp['engagement'] ?? 0;
                                    ?>
                                    <div class="flex items-center gap-1 text-sm">
                                        <span class="w-5 h-5 rounded-full text-xs flex items-center justify-center font-bold <?= $eng >= 4 ? 'bg-green-100 text-green-700' : ($eng >= 3 ? 'bg-yellow-100 text-yellow-700' : ($eng > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400')) ?>"><?= $eng ?: '-' ?></span>
                                        <span class="text-gray-700"><?= h($pp['nom']) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue Forces et defis -->
        <div id="forcesView" class="hidden">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Forces distinctives et defis prioritaires</h2>
            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">Aucune analyse trouvee.</div>
            <?php else: ?>
            <div class="grid md:grid-cols-2 gap-4">
                <?php foreach ($participantsData as $userId => $data):
                    $force = $data['s4']['force_distinctive'] ?? '';
                    $defi = $data['s4']['defi_prioritaire'] ?? '';
                    if (empty(trim($force)) && empty(trim($defi))) continue;
                ?>
                <div class="item-card bg-white rounded-xl shadow-lg p-5 border-l-4 border-cyan-500">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <span class="font-bold text-gray-800"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['nom_organisation'])): ?>
                            <span class="text-gray-500 text-sm block"><?= h($data['nom_organisation']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty(trim($force))): ?>
                    <div class="mb-3">
                        <span class="text-xs font-semibold text-green-600 uppercase">Force distinctive</span>
                        <p class="text-sm text-gray-700 bg-green-50 p-2 rounded mt-1"><?= h($force) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty(trim($defi))): ?>
                    <div>
                        <span class="text-xs font-semibold text-amber-600 uppercase">Defi prioritaire</span>
                        <p class="text-sm text-gray-700 bg-amber-50 p-2 rounded mt-1"><?= h($defi) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue Par Participant (detail) -->
        <div id="participantView" class="hidden space-y-6">
            <?php foreach ($participantsData as $userId => $data): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-cyan-500 to-teal-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['nom_organisation'])): ?>
                            <span class="block text-cyan-100 text-sm mt-1">Organisation: <?= h($data['nom_organisation']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="px-2 py-1 rounded text-sm <?= $data['is_shared'] ? 'bg-green-500/50' : 'bg-yellow-500/50' ?>"><?= $data['is_shared'] ? 'Soumis' : 'Brouillon' ?></span>
                    </div>
                </div>
                <div class="p-5 space-y-4">
                    <!-- Section 1: Valeurs -->
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="section-number">1</div>
                            <span class="font-bold text-gray-700 text-sm">Valeurs et Mission</span>
                        </div>
                        <div class="space-y-1 mb-2">
                            <?php for ($i = 0; $i < 3; $i++):
                                $v = $data['s1']['valeurs'][$i] ?? '';
                                if (empty(trim($v))) continue;
                                $sc = $data['s1']['valeurs_scores'][$i]['score'] ?? 0;
                                $comm = $data['s1']['valeurs_scores'][$i]['commentaire'] ?? '';
                            ?>
                            <div class="flex items-center gap-2 text-sm bg-cyan-50 p-2 rounded">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs <?= $sc >= 4 ? 'bg-green-200 text-green-700' : ($sc >= 3 ? 'bg-yellow-200 text-yellow-700' : ($sc > 0 ? 'bg-red-200 text-red-700' : 'bg-gray-200 text-gray-400')) ?>"><?= $sc ?: '-' ?></span>
                                <span class="font-medium"><?= h($v) ?></span>
                                <?php if (!empty(trim($comm))): ?>
                                <span class="text-gray-500 text-xs ml-auto"><?= h($comm) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <?php if (!empty(trim($data['s1']['exemple_positif'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-green-50 p-2 rounded border border-green-200 mb-1"><span class="text-green-600 font-semibold text-xs">Positif:</span> <?= h($data['s1']['exemple_positif']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty(trim($data['s1']['exemple_decalage'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-red-50 p-2 rounded border border-red-200"><span class="text-red-600 font-semibold text-xs">Decalage:</span> <?= h($data['s1']['exemple_decalage']) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Section 2: Contraintes et Ressources -->
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="section-number">2</div>
                            <span class="font-bold text-gray-700 text-sm">Contraintes et Ressources</span>
                            <span class="text-xs bg-cyan-100 text-cyan-700 px-2 py-0.5 rounded"><?= h($budgetLabels[$data['s2']['budget'] ?? ''] ?? '-') ?></span>
                        </div>
                        <div class="grid md:grid-cols-2 gap-3 mb-2">
                            <div>
                                <span class="text-xs font-semibold text-red-600">Contraintes</span>
                                <?php foreach (array_filter($data['s2']['contraintes'] ?? [], fn($c) => !empty(trim($c))) as $c): ?>
                                <div class="text-sm text-gray-700 bg-red-50 p-1.5 rounded mt-1">- <?= h($c) ?></div>
                                <?php endforeach; ?>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-green-600">Atouts</span>
                                <?php foreach (array_filter($data['s2']['atouts'] ?? [], fn($a) => !empty(trim($a))) as $at): ?>
                                <div class="text-sm text-gray-700 bg-green-50 p-1.5 rounded mt-1">+ <?= h($at) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if (!empty(trim($data['s2']['action_efficace'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-emerald-50 p-2 rounded border border-emerald-200 mb-1"><span class="text-emerald-600 font-semibold text-xs">Action efficace:</span> <?= h($data['s2']['action_efficace']) ?></p>
                        <?php endif; ?>
                        <?php
                        $selRes = $data['s2']['ressources_non_financieres'] ?? [];
                        if (!empty($selRes)): ?>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php foreach ($selRes as $r): ?>
                            <span class="text-xs bg-cyan-100 text-cyan-700 px-2 py-0.5 rounded"><?= h($ressNonFinLabels[$r] ?? $r) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Section 3: Mobilisation -->
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="section-number">3</div>
                            <span class="font-bold text-gray-700 text-sm">Mobilisation et Engagement</span>
                            <?php $transf = $data['s3']['transformation_score'] ?? 0; if ($transf > 0): ?>
                            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded">Transformation: <?= $transf ?>/5</span>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-1 mb-2">
                            <?php foreach (($data['s3']['parties_prenantes'] ?? []) as $pp):
                                if (empty(trim($pp['nom'] ?? ''))) continue;
                                $eng = $pp['engagement'] ?? 0;
                            ?>
                            <div class="flex items-center gap-2 text-sm bg-sky-50 p-2 rounded">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs <?= $eng >= 4 ? 'bg-green-200 text-green-700' : ($eng >= 3 ? 'bg-yellow-200 text-yellow-700' : ($eng > 0 ? 'bg-red-200 text-red-700' : 'bg-gray-200 text-gray-400')) ?>"><?= $eng ?: '-' ?></span>
                                <span class="font-medium"><?= h($pp['nom']) ?></span>
                                <?php if (!empty(trim($pp['actions'] ?? ''))): ?>
                                <span class="text-gray-500 text-xs ml-auto"><?= h($pp['actions']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php
                        $obstacles = array_filter($data['s3']['obstacles'] ?? [], fn($o) => !empty(trim($o)));
                        if (!empty($obstacles)): ?>
                        <div class="mb-2">
                            <span class="text-xs font-semibold text-amber-600">Obstacles</span>
                            <?php foreach ($obstacles as $o): ?>
                            <div class="text-sm text-gray-700 bg-amber-50 p-1.5 rounded mt-1">- <?= h($o) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty(trim($data['s3']['exemple_mobilisation'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-blue-50 p-2 rounded border border-blue-200"><span class="text-blue-600 font-semibold text-xs">Mobilisation reussie:</span> <?= h($data['s3']['exemple_mobilisation']) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Section 4 & 5 -->
                    <?php if (!empty(trim($data['s4']['force_distinctive'] ?? '')) || !empty(trim($data['s4']['defi_prioritaire'] ?? ''))): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="section-number">4</div>
                            <span class="font-bold text-gray-700 text-sm">Synthese</span>
                        </div>
                        <?php if (!empty(trim($data['s4']['force_distinctive'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-cyan-50 p-2 rounded border border-cyan-200 mb-1"><span class="text-cyan-600 font-semibold text-xs">Force:</span> <?= h($data['s4']['force_distinctive']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty(trim($data['s4']['defi_prioritaire'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-amber-50 p-2 rounded border border-amber-200 mb-1"><span class="text-amber-600 font-semibold text-xs">Defi:</span> <?= h($data['s4']['defi_prioritaire']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty(trim($data['s4']['articulation'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-purple-50 p-2 rounded border border-purple-200"><span class="text-purple-600 font-semibold text-xs">Articulation:</span> <?= h($data['s4']['articulation']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty(trim($data['s5']['piste_valeurs'] ?? '')) || !empty(trim($data['s5']['piste_ressources'] ?? '')) || !empty(trim($data['s5']['piste_mobilisation'] ?? ''))): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="section-number">5</div>
                            <span class="font-bold text-gray-700 text-sm">Pistes d'action</span>
                        </div>
                        <?php if (!empty(trim($data['s5']['piste_valeurs'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-cyan-50 p-2 rounded mb-1"><span class="text-cyan-600 font-semibold text-xs">Valeurs:</span> <?= h($data['s5']['piste_valeurs']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty(trim($data['s5']['piste_ressources'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-emerald-50 p-2 rounded mb-1"><span class="text-emerald-600 font-semibold text-xs">Ressources:</span> <?= h($data['s5']['piste_ressources']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty(trim($data['s5']['piste_mobilisation'] ?? ''))): ?>
                        <p class="text-sm text-gray-700 bg-amber-50 p-2 rounded"><span class="text-amber-600 font-semibold text-xs">Mobilisation:</span> <?= h($data['s5']['piste_mobilisation']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucune analyse trouvee pour cette session.
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?= renderLanguageScript() ?>

    <script>
        function setDisplayMode(mode) {
            document.getElementById('summaryView').classList.add('hidden');
            document.getElementById('forcesView').classList.add('hidden');
            document.getElementById('participantView').classList.add('hidden');

            if (mode === 'summary') document.getElementById('summaryView').classList.remove('hidden');
            else if (mode === 'forces') document.getElementById('forcesView').classList.remove('hidden');
            else document.getElementById('participantView').classList.remove('hidden');
        }
    </script>
</body>
</html>
