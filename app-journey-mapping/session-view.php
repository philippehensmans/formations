<?php
/**
 * Vue globale de session - Journey Mapping
 * Affiche tous les parcours de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-journey-mapping';

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

$channels = getChannels();
$emotions = getEmotions();

// Option pour voir toutes les analyses ou seulement les partagees
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les analyses de la session
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM analyses WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM analyses WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$analyses = $stmt->fetchAll();

// Compter le total (partages vs tous)
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM analyses WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

// Agreger les donnees
$totalSteps = 0;
$channelCounts = [];
$emotionCounts = [];
$totalFrictions = 0;
$totalOpportunities = 0;
$participantsData = [];

foreach ($analyses as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $journeyData = json_decode($a['journey_data'], true) ?: [];
    $totalSteps += count($journeyData);

    foreach ($journeyData as $step) {
        // Compter les canaux
        if (!empty($step['canal'])) {
            $channelCounts[$step['canal']] = ($channelCounts[$step['canal']] ?? 0) + 1;
        }
        // Compter les emotions
        if (!empty($step['emotions']) && is_array($step['emotions'])) {
            foreach ($step['emotions'] as $emo) {
                $emotionCounts[$emo] = ($emotionCounts[$emo] ?? 0) + 1;
            }
        }
        // Compter frictions et opportunites
        if (!empty(trim($step['friction'] ?? ''))) $totalFrictions++;
        if (!empty(trim($step['opportunites'] ?? ''))) $totalOpportunities++;
    }

    // Stocker les donnees par participant
    $participantsData[$a['user_id']] = [
        'user' => [
            'prenom' => $a['user_prenom'],
            'nom' => $a['user_nom'],
            'organisation' => $a['user_organisation']
        ],
        'nom_organisation' => $a['nom_organisation'],
        'public_cible' => $a['public_cible'] ?? '',
        'objectif_audit' => $a['objectif_audit'] ?? '',
        'journey_data' => $journeyData,
        'synthese' => $a['synthese'],
        'recommandations' => $a['recommandations'] ?? '',
        'is_shared' => $a['is_shared']
    ];
}

// Trier canaux et emotions par frequence
arsort($channelCounts);
arsort($emotionCounts);

$participantsCount = count($analyses);
$lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journey Mapping - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .channel-filter { transition: all 0.2s ease; }
        .channel-filter:hover { transform: scale(1.05); }
        .channel-filter.active { ring: 4px; transform: scale(1.1); }
        .item-card { transition: all 0.3s ease; }
        .item-card:hover { transform: translateY(-2px); }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-cyan-50 to-teal-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-cyan-500 to-teal-600 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Journey Mapping - Audit de Communication</h1>
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
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-cyan-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $totalSteps ?></div>
                <div class="text-gray-500 text-sm">Etapes total</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= count($channelCounts) ?></div>
                <div class="text-gray-500 text-sm">Canaux utilises</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-red-500">
                <div class="text-3xl font-bold text-red-600"><?= $totalFrictions ?></div>
                <div class="text-gray-500 text-sm">Points de friction</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-green-500">
                <div class="text-3xl font-bold text-green-600"><?= $totalOpportunities ?></div>
                <div class="text-gray-500 text-sm">Opportunites</div>
            </div>
        </div>

        <!-- Synthese des canaux et emotions -->
        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <!-- Canaux les plus utilises -->
            <div class="bg-white rounded-xl shadow-lg p-5">
                <h3 class="font-bold text-gray-800 mb-3">Canaux les plus utilises</h3>
                <?php if (empty($channelCounts)): ?>
                <p class="text-gray-400 text-sm">Aucun canal identifie</p>
                <?php else: ?>
                <div class="space-y-2">
                    <?php
                    $maxCount = max($channelCounts);
                    foreach (array_slice($channelCounts, 0, 8, true) as $channel => $count):
                        $pct = $maxCount > 0 ? round(($count / $maxCount) * 100) : 0;
                    ?>
                    <div class="flex items-center gap-3">
                        <div class="w-40 text-sm text-gray-600 truncate"><?= h($channels[$channel] ?? $channel) ?></div>
                        <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                            <div class="bg-cyan-500 h-full rounded-full" style="width: <?= $pct ?>%"></div>
                        </div>
                        <div class="text-sm font-bold text-cyan-700 w-8 text-right"><?= $count ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Emotions les plus frequentes -->
            <div class="bg-white rounded-xl shadow-lg p-5">
                <h3 class="font-bold text-gray-800 mb-3">Emotions les plus frequentes</h3>
                <?php if (empty($emotionCounts)): ?>
                <p class="text-gray-400 text-sm">Aucune emotion identifiee</p>
                <?php else: ?>
                <div class="space-y-2">
                    <?php
                    $maxEmo = max($emotionCounts);
                    foreach ($emotionCounts as $emo => $count):
                        $emoData = $emotions[$emo] ?? null;
                        $pct = $maxEmo > 0 ? round(($count / $maxEmo) * 100) : 0;
                        $isNegative = in_array($emo, ['frustration', 'confusion', 'inquietude']);
                    ?>
                    <div class="flex items-center gap-3">
                        <div class="w-40 text-sm text-gray-600">
                            <?= $emoData ? $emoData['emoji'] . ' ' . $emoData['label'] : h($emo) ?>
                        </div>
                        <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                            <div class="<?= $isNegative ? 'bg-red-400' : 'bg-green-400' ?> h-full rounded-full" style="width: <?= $pct ?>%"></div>
                        </div>
                        <div class="text-sm font-bold <?= $isNegative ? 'text-red-600' : 'text-green-600' ?> w-8 text-right"><?= $count ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 no-print">
            <div class="flex flex-wrap gap-4 items-center">
                <div class="font-medium text-gray-700">Affichage:</div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="overview" checked onchange="setDisplayMode('overview')">
                    <span class="text-sm">Vue d'ensemble</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="frictions" onchange="setDisplayMode('frictions')">
                    <span class="text-sm">Frictions & Opportunites</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="participant" onchange="setDisplayMode('participant')">
                    <span class="text-sm">Par participant</span>
                </label>
            </div>
        </div>

        <!-- Vue d'ensemble: tous les parcours resumes -->
        <div id="overviewView">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Parcours de tous les participants</h2>
            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucune analyse trouvee pour cette session.
            </div>
            <?php else: ?>
            <?php foreach ($participantsData as $userId => $data): ?>
            <div class="bg-white rounded-xl shadow-lg mb-6 overflow-hidden">
                <div class="bg-gradient-to-r from-cyan-500 to-teal-500 text-white p-3">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['nom_organisation'])): ?>
                            <span class="text-cyan-200 text-sm ml-2">- <?= h($data['nom_organisation']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($data['public_cible'])): ?>
                            <span class="block text-cyan-100 text-xs mt-1">Public: <?= h($data['public_cible']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <span class="bg-white/20 px-2 py-1 rounded text-sm"><?= count($data['journey_data']) ?> etapes</span>
                            <span class="px-2 py-1 rounded text-sm <?= $data['is_shared'] ? 'bg-green-500/50' : 'bg-yellow-500/50' ?>"><?= $data['is_shared'] ? 'Soumis' : 'Brouillon' ?></span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($data['journey_data'])): ?>
                <div class="p-4 overflow-x-auto">
                    <div class="flex gap-3 min-w-max">
                        <?php foreach ($data['journey_data'] as $i => $step): ?>
                        <div class="w-56 shrink-0 bg-gray-50 rounded-lg border p-3">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-cyan-600 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $i + 1 ?></span>
                                <span class="font-bold text-sm text-gray-800 truncate"><?= h($step['titre'] ?? 'Sans titre') ?></span>
                            </div>
                            <?php if (!empty($step['canal'])): ?>
                            <div class="text-xs text-cyan-700 bg-cyan-50 rounded px-2 py-1 mb-2"><?= h($channels[$step['canal']] ?? $step['canal']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($step['emotions']) && is_array($step['emotions'])): ?>
                            <div class="flex flex-wrap gap-1 mb-2">
                                <?php foreach ($step['emotions'] as $emo):
                                    $emoData = $emotions[$emo] ?? null;
                                ?>
                                <span class="text-sm" title="<?= h($emoData['label'] ?? $emo) ?>"><?= $emoData['emoji'] ?? '' ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty(trim($step['friction'] ?? ''))): ?>
                            <div class="text-xs bg-red-50 text-red-700 rounded px-2 py-1 mb-1 truncate" title="<?= h($step['friction']) ?>">
                                ⚠ <?= h(mb_strimwidth($step['friction'], 0, 60, '...')) ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty(trim($step['opportunites'] ?? ''))): ?>
                            <div class="text-xs bg-green-50 text-green-700 rounded px-2 py-1 truncate" title="<?= h($step['opportunites']) ?>">
                                ✨ <?= h(mb_strimwidth($step['opportunites'], 0, 60, '...')) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="p-4 text-gray-400 text-center text-sm">Aucune etape definie</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Vue Frictions & Opportunites -->
        <div id="frictionsView" class="hidden">
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Frictions -->
                <div>
                    <h2 class="text-xl font-bold text-red-700 mb-4">⚠ Points de friction (<?= $totalFrictions ?>)</h2>
                    <?php
                    $allFrictions = [];
                    foreach ($participantsData as $userId => $data) {
                        foreach ($data['journey_data'] as $step) {
                            if (!empty(trim($step['friction'] ?? ''))) {
                                $allFrictions[] = [
                                    'titre_etape' => $step['titre'] ?? '',
                                    'canal' => $step['canal'] ?? '',
                                    'friction' => $step['friction'],
                                    'user_prenom' => $data['user']['prenom'],
                                    'user_nom' => $data['user']['nom'],
                                    'nom_organisation' => $data['nom_organisation']
                                ];
                            }
                        }
                    }
                    ?>
                    <?php if (empty($allFrictions)): ?>
                    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-400">Aucun point de friction identifie</div>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($allFrictions as $f): ?>
                        <div class="item-card bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-bold text-sm text-gray-800"><?= h($f['titre_etape']) ?></span>
                                <?php if (!empty($f['canal'])): ?>
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded"><?= h($channels[$f['canal']] ?? $f['canal']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-red-800 whitespace-pre-wrap"><?= h($f['friction']) ?></p>
                            <div class="text-xs text-gray-400 mt-2">
                                <?= h($f['user_prenom']) ?> <?= h($f['user_nom']) ?>
                                <?php if (!empty($f['nom_organisation'])): ?>
                                - <?= h($f['nom_organisation']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Opportunites -->
                <div>
                    <h2 class="text-xl font-bold text-green-700 mb-4">✨ Opportunites (<?= $totalOpportunities ?>)</h2>
                    <?php
                    $allOpportunities = [];
                    foreach ($participantsData as $userId => $data) {
                        foreach ($data['journey_data'] as $step) {
                            if (!empty(trim($step['opportunites'] ?? ''))) {
                                $allOpportunities[] = [
                                    'titre_etape' => $step['titre'] ?? '',
                                    'canal' => $step['canal'] ?? '',
                                    'opportunites' => $step['opportunites'],
                                    'user_prenom' => $data['user']['prenom'],
                                    'user_nom' => $data['user']['nom'],
                                    'nom_organisation' => $data['nom_organisation']
                                ];
                            }
                        }
                    }
                    ?>
                    <?php if (empty($allOpportunities)): ?>
                    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-400">Aucune opportunite identifiee</div>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($allOpportunities as $o): ?>
                        <div class="item-card bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-bold text-sm text-gray-800"><?= h($o['titre_etape']) ?></span>
                                <?php if (!empty($o['canal'])): ?>
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded"><?= h($channels[$o['canal']] ?? $o['canal']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-green-800 whitespace-pre-wrap"><?= h($o['opportunites']) ?></p>
                            <div class="text-xs text-gray-400 mt-2">
                                <?= h($o['user_prenom']) ?> <?= h($o['user_nom']) ?>
                                <?php if (!empty($o['nom_organisation'])): ?>
                                - <?= h($o['nom_organisation']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Vue Par Participant (detaillee) -->
        <div id="participantView" class="hidden space-y-6">
            <?php foreach ($participantsData as $userId => $data): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-cyan-500 to-teal-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['user']['organisation'])): ?>
                            <span class="text-cyan-200 text-sm ml-2">(<?= h($data['user']['organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($data['nom_organisation'])): ?>
                            <span class="block text-cyan-100 text-sm mt-1">Organisation: <?= h($data['nom_organisation']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <?php
                            $stepCount = count($data['journey_data']);
                            $frictions = count(array_filter($data['journey_data'], function($s) { return !empty(trim($s['friction'] ?? '')); }));
                            $opps = count(array_filter($data['journey_data'], function($s) { return !empty(trim($s['opportunites'] ?? '')); }));
                            ?>
                            <span class="bg-white/20 px-2 py-1 rounded text-sm"><?= $stepCount ?> etapes</span>
                            <span class="bg-red-400/50 px-2 py-1 rounded text-sm"><?= $frictions ?> frictions</span>
                            <span class="bg-green-400/50 px-2 py-1 rounded text-sm"><?= $opps ?> opportunites</span>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <?php if (empty($data['journey_data'])): ?>
                    <p class="text-gray-400 text-center text-sm">Aucune etape definie</p>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($data['journey_data'] as $i => $step): ?>
                        <div class="p-3 bg-gray-50 rounded-lg border-l-4 border-cyan-500">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-cyan-600 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $i + 1 ?></span>
                                <span class="font-bold text-sm text-gray-800"><?= h($step['titre'] ?? 'Sans titre') ?></span>
                                <?php if (!empty($step['canal'])): ?>
                                <span class="text-xs bg-cyan-50 text-cyan-700 px-2 py-0.5 rounded"><?= h($channels[$step['canal']] ?? $step['canal']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($step['emotions']) && is_array($step['emotions'])): ?>
                                <span class="text-sm">
                                    <?php foreach ($step['emotions'] as $emo):
                                        $emoData = $emotions[$emo] ?? null;
                                    ?>
                                    <span title="<?= h($emoData['label'] ?? $emo) ?>"><?= $emoData['emoji'] ?? '' ?></span>
                                    <?php endforeach; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="grid md:grid-cols-2 gap-2 text-sm">
                                <?php if (!empty(trim($step['point_contact'] ?? ''))): ?>
                                <div><span class="text-gray-500 text-xs">Contact:</span> <?= h($step['point_contact']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty(trim($step['action_utilisateur'] ?? ''))): ?>
                                <div><span class="text-gray-500 text-xs">Action:</span> <?= h($step['action_utilisateur']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-3 mt-2">
                                <?php if (!empty(trim($step['friction'] ?? ''))): ?>
                                <div class="flex-1 text-xs bg-red-50 text-red-700 rounded px-2 py-1">
                                    <strong>Friction:</strong> <?= h($step['friction']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty(trim($step['opportunites'] ?? ''))): ?>
                                <div class="flex-1 text-xs bg-green-50 text-green-700 rounded px-2 py-1">
                                    <strong>Opportunite:</strong> <?= h($step['opportunites']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty(trim($data['synthese'] ?? ''))): ?>
                    <div class="mt-4 bg-gray-50 rounded-lg p-3 border">
                        <h4 class="font-bold text-gray-700 text-sm mb-1">Synthese</h4>
                        <p class="text-sm text-gray-600"><?= nl2br(h(mb_strimwidth($data['synthese'], 0, 300, '...'))) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty(trim($data['recommandations'] ?? ''))): ?>
                    <div class="mt-2 bg-gray-50 rounded-lg p-3 border">
                        <h4 class="font-bold text-gray-700 text-sm mb-1">Recommandations</h4>
                        <p class="text-sm text-gray-600"><?= nl2br(h(mb_strimwidth($data['recommandations'], 0, 300, '...'))) ?></p>
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
        let currentDisplay = 'overview';

        function setDisplayMode(mode) {
            currentDisplay = mode;

            document.getElementById('overviewView').classList.add('hidden');
            document.getElementById('frictionsView').classList.add('hidden');
            document.getElementById('participantView').classList.add('hidden');

            if (mode === 'overview') document.getElementById('overviewView').classList.remove('hidden');
            else if (mode === 'frictions') document.getElementById('frictionsView').classList.remove('hidden');
            else document.getElementById('participantView').classList.remove('hidden');
        }
    </script>
</body>
</html>
