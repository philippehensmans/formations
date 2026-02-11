<?php
/**
 * Vue globale de session - Mini-Plan de Communication
 * Affiche tous les plans de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-comm-plan';

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

$availableCanaux = getCanaux();
$typeLabels = [
    'teasing' => 'Teasing',
    'annonce' => 'Annonce',
    'rappel' => 'Rappel',
    'jour_j' => 'Jour J',
    'relance' => 'Relance',
    'remerciement' => 'Remerciement',
    'bilan' => 'Bilan'
];

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

// Agreger les donnees
$canalCounts = [];
$totalCanaux = 0;
$totalEtapes = 0;
$totalRessources = 0;
$participantsData = [];

foreach ($analyses as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $canauxData = json_decode($a['canaux_data'], true) ?: [];
    $calendrierData = json_decode($a['calendrier_data'], true) ?: [];
    $ressourcesData = json_decode($a['ressources_data'], true) ?: [];

    foreach ($canauxData as $c) {
        if (!empty($c['canal'])) {
            $canalCounts[$c['canal']] = ($canalCounts[$c['canal']] ?? 0) + 1;
            $totalCanaux++;
        }
    }
    $totalEtapes += count(array_filter($calendrierData, fn($e) => !empty($e['etape'])));
    $totalRessources += count(array_filter($ressourcesData, fn($r) => !empty($r['qui']) || !empty($r['quoi'])));

    $participantsData[$a['user_id']] = [
        'user' => ['prenom' => $a['user_prenom'], 'nom' => $a['user_nom'], 'organisation' => $a['user_organisation']],
        'nom_organisation' => $a['nom_organisation'],
        'action_communiquer' => $a['action_communiquer'],
        'objectif_smart' => $a['objectif_smart'],
        'public_prioritaire' => $a['public_prioritaire'],
        'message_cle' => $a['message_cle'],
        'canaux' => $canauxData,
        'calendrier' => $calendrierData,
        'ressources' => $ressourcesData,
        'notes' => $a['notes'],
        'is_shared' => $a['is_shared']
    ];
}

arsort($canalCounts);
$participantsCount = count($analyses);
$lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini-Plan de Communication - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .item-card { transition: all 0.3s ease; }
        .item-card:hover { transform: translateY(-2px); }
        .section-number { display: flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: #4f46e5; color: white; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-blue-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Mini-Plan de Communication</h1>
                    <p class="text-indigo-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
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
                    <?= renderLanguageSelector('bg-indigo-400 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <button onclick="window.print()" class="bg-indigo-400 hover:bg-indigo-300 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-indigo-400 hover:bg-indigo-300 px-3 py-1 rounded text-sm">
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

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= count($canalCounts) ?></div>
                <div class="text-gray-500 text-sm">Canaux differents</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-cyan-600"><?= $totalCanaux ?></div>
                <div class="text-gray-500 text-sm">Selections canaux</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-amber-600"><?= $totalEtapes ?></div>
                <div class="text-gray-500 text-sm">Etapes calendrier</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalRessources ?></div>
                <div class="text-gray-500 text-sm">Ressources</div>
            </div>
        </div>

        <!-- Canaux les plus utilises -->
        <?php if (!empty($canalCounts)): ?>
        <div class="bg-white rounded-xl shadow-lg p-5 mb-8">
            <h3 class="font-bold text-gray-800 mb-3">Canaux les plus choisis par les participants</h3>
            <div class="space-y-2">
                <?php
                $maxCount = max($canalCounts);
                foreach ($canalCounts as $canal => $count):
                    $pct = $maxCount > 0 ? round(($count / $maxCount) * 100) : 0;
                    $canalInfo = $availableCanaux[$canal] ?? null;
                ?>
                <div class="flex items-center gap-3">
                    <div class="w-48 text-sm text-gray-600 truncate">
                        <?= $canalInfo ? $canalInfo['icon'] . ' ' . $canalInfo['label'] : h($canal) ?>
                    </div>
                    <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                        <div class="bg-indigo-500 h-full rounded-full" style="width: <?= $pct ?>%"></div>
                    </div>
                    <div class="text-sm font-bold text-indigo-700 w-12 text-right"><?= $count ?> (<?= round(($count / $participantsCount) * 100) ?>%)</div>
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
                    <input type="radio" name="display" value="messages" onchange="setDisplayMode('messages')">
                    <span class="text-sm">Messages cles</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="participant" onchange="setDisplayMode('participant')">
                    <span class="text-sm">Par participant (detail)</span>
                </label>
            </div>
        </div>

        <!-- Vue Synthese comparative -->
        <div id="summaryView">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Synthese comparative des plans</h2>
            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucune analyse trouvee pour cette session.
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($participantsData as $userId => $data): ?>
                <div class="item-card bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-blue-500 text-white p-3">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-bold"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                                <?php if (!empty($data['nom_organisation'])): ?>
                                <span class="text-indigo-200 text-sm ml-2">- <?= h($data['nom_organisation']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <span class="bg-white/20 px-2 py-1 rounded text-xs"><?= count(array_filter($data['canaux'], fn($c) => !empty($c['canal']))) ?> canaux</span>
                                <span class="bg-white/20 px-2 py-1 rounded text-xs"><?= count(array_filter($data['calendrier'], fn($e) => !empty($e['etape']))) ?> etapes</span>
                                <span class="px-2 py-1 rounded text-xs <?= $data['is_shared'] ? 'bg-green-500/50' : 'bg-yellow-500/50' ?>"><?= $data['is_shared'] ? 'Soumis' : 'Brouillon' ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="grid md:grid-cols-2 gap-4 mb-3">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="section-number">1</div>
                                    <span class="text-xs font-semibold text-gray-500">Action</span>
                                </div>
                                <p class="text-sm text-gray-700"><?= h(mb_strimwidth($data['action_communiquer'], 0, 200, '...')) ?: '<em class="text-gray-400">-</em>' ?></p>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="section-number">2</div>
                                    <span class="text-xs font-semibold text-gray-500">Objectif SMART</span>
                                </div>
                                <p class="text-sm text-gray-700"><?= h(mb_strimwidth($data['objectif_smart'], 0, 200, '...')) ?: '<em class="text-gray-400">-</em>' ?></p>
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4 mb-3">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="section-number">3</div>
                                    <span class="text-xs font-semibold text-gray-500">Public prioritaire</span>
                                </div>
                                <p class="text-sm text-gray-700"><?= h(mb_strimwidth($data['public_prioritaire'], 0, 150, '...')) ?: '<em class="text-gray-400">-</em>' ?></p>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="section-number">4</div>
                                    <span class="text-xs font-semibold text-gray-500">Message cle</span>
                                </div>
                                <?php if (!empty(trim($data['message_cle']))): ?>
                                <p class="text-sm font-medium text-indigo-800 italic bg-indigo-50 px-3 py-1 rounded">&laquo; <?= h($data['message_cle']) ?> &raquo;</p>
                                <?php else: ?>
                                <p class="text-sm text-gray-400"><em>-</em></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Canaux en badges -->
                        <?php
                        $validCanaux = array_filter($data['canaux'], fn($c) => !empty($c['canal']));
                        if (!empty($validCanaux)):
                        ?>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <?php foreach ($validCanaux as $c):
                                $cInfo = $availableCanaux[$c['canal']] ?? null;
                            ?>
                            <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded border border-indigo-200">
                                <?= $cInfo ? $cInfo['icon'] . ' ' . $cInfo['label'] : h($c['canal']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue Messages cles -->
        <div id="messagesView" class="hidden">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Tous les messages cles</h2>
            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">Aucune analyse trouvee.</div>
            <?php else: ?>
            <div class="grid md:grid-cols-2 gap-4">
                <?php foreach ($participantsData as $userId => $data):
                    if (empty(trim($data['message_cle']))) continue;
                ?>
                <div class="item-card bg-white rounded-xl shadow-lg p-5 border-l-4 border-indigo-500">
                    <div class="bg-gradient-to-r from-indigo-50 to-violet-50 p-4 rounded-lg border-2 border-indigo-200 mb-3">
                        <p class="text-lg font-medium text-indigo-900 italic text-center">&laquo; <?= h($data['message_cle']) ?> &raquo;</p>
                    </div>
                    <div class="text-sm text-gray-600 mb-2">
                        <strong>Action :</strong> <?= h(mb_strimwidth($data['action_communiquer'], 0, 120, '...')) ?>
                    </div>
                    <div class="text-sm text-gray-600 mb-2">
                        <strong>Public :</strong> <?= h(mb_strimwidth($data['public_prioritaire'], 0, 120, '...')) ?>
                    </div>
                    <div class="flex justify-between items-center text-xs text-gray-400 mt-3 pt-2 border-t">
                        <span class="font-medium"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                        <?php if (!empty($data['nom_organisation'])): ?>
                        <span class="italic"><?= h($data['nom_organisation']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue Par Participant (detail) -->
        <div id="participantView" class="hidden space-y-6">
            <?php foreach ($participantsData as $userId => $data): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-blue-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['user']['organisation'])): ?>
                            <span class="text-indigo-200 text-sm ml-2">(<?= h($data['user']['organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($data['nom_organisation'])): ?>
                            <span class="block text-indigo-100 text-sm mt-1">Organisation: <?= h($data['nom_organisation']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="px-2 py-1 rounded text-sm <?= $data['is_shared'] ? 'bg-green-500/50' : 'bg-yellow-500/50' ?>"><?= $data['is_shared'] ? 'Soumis' : 'Brouillon' ?></span>
                    </div>
                </div>
                <div class="p-5 space-y-4">
                    <!-- Action -->
                    <?php if (!empty(trim($data['action_communiquer']))): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <div class="section-number">1</div>
                            <span class="font-bold text-gray-700 text-sm">Action a communiquer</span>
                        </div>
                        <p class="text-sm text-gray-700 bg-indigo-50 p-3 rounded-lg border border-indigo-200"><?= nl2br(h($data['action_communiquer'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Objectif -->
                    <?php if (!empty(trim($data['objectif_smart']))): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <div class="section-number">2</div>
                            <span class="font-bold text-gray-700 text-sm">Objectif SMART</span>
                        </div>
                        <p class="text-sm text-gray-700 bg-blue-50 p-3 rounded-lg border border-blue-200"><?= nl2br(h($data['objectif_smart'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Public -->
                    <?php if (!empty(trim($data['public_prioritaire']))): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <div class="section-number">3</div>
                            <span class="font-bold text-gray-700 text-sm">Public prioritaire</span>
                        </div>
                        <p class="text-sm text-gray-700 bg-purple-50 p-3 rounded-lg border border-purple-200"><?= nl2br(h($data['public_prioritaire'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Message cle -->
                    <?php if (!empty(trim($data['message_cle']))): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <div class="section-number">4</div>
                            <span class="font-bold text-gray-700 text-sm">Message cle</span>
                        </div>
                        <div class="bg-gradient-to-r from-indigo-50 to-violet-50 p-3 rounded-lg border-2 border-indigo-300">
                            <p class="text-sm font-medium text-indigo-900 italic">&laquo; <?= h($data['message_cle']) ?> &raquo;</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Canaux -->
                    <?php $validCanaux = array_filter($data['canaux'], fn($c) => !empty($c['canal'])); ?>
                    <?php if (!empty($validCanaux)): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="section-number">5</div>
                            <span class="font-bold text-gray-700 text-sm">Canaux (<?= count($validCanaux) ?>)</span>
                        </div>
                        <div class="grid md:grid-cols-2 gap-2">
                            <?php foreach ($validCanaux as $c):
                                $cInfo = $availableCanaux[$c['canal']] ?? null;
                            ?>
                            <div class="bg-gray-50 rounded-lg p-3 border text-sm">
                                <span class="font-bold"><?= $cInfo ? $cInfo['icon'] . ' ' . $cInfo['label'] : h($c['canal']) ?></span>
                                <?php if (!empty($c['frequence'])): ?>
                                <span class="text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded ml-1"><?= h($c['frequence']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty(trim($c['justification'] ?? ''))): ?>
                                <p class="text-xs text-gray-500 mt-1"><?= h($c['justification']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Calendrier -->
                    <?php $validEtapes = array_filter($data['calendrier'], fn($e) => !empty($e['etape']) || !empty($e['date'])); ?>
                    <?php if (!empty($validEtapes)): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="section-number">6</div>
                            <span class="font-bold text-gray-700 text-sm">Calendrier (<?= count($validEtapes) ?> etapes)</span>
                        </div>
                        <div class="space-y-2">
                            <?php foreach ($validEtapes as $e):
                                $typeColors = [
                                    'teasing' => 'border-purple-400 bg-purple-50',
                                    'annonce' => 'border-blue-400 bg-blue-50',
                                    'rappel' => 'border-amber-400 bg-amber-50',
                                    'jour_j' => 'border-green-400 bg-green-50',
                                    'relance' => 'border-orange-400 bg-orange-50',
                                    'remerciement' => 'border-pink-400 bg-pink-50',
                                    'bilan' => 'border-gray-400 bg-gray-50'
                                ];
                                $cls = $typeColors[$e['type'] ?? ''] ?? 'border-gray-300 bg-gray-50';
                            ?>
                            <div class="rounded-lg p-3 border-l-4 <?= $cls ?> text-sm">
                                <div class="flex items-center gap-2">
                                    <?php if (!empty($e['date'])): ?>
                                    <span class="font-bold text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded text-xs"><?= h($e['date']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($e['type'])): ?>
                                    <span class="text-xs font-semibold text-gray-600"><?= $typeLabels[$e['type']] ?? h($e['type']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($e['etape'])): ?>
                                <p class="text-gray-700 mt-1"><?= h($e['etape']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Ressources -->
                    <?php $validRes = array_filter($data['ressources'], fn($r) => !empty($r['qui']) || !empty($r['quoi'])); ?>
                    <?php if (!empty($validRes)): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="section-number">7</div>
                            <span class="font-bold text-gray-700 text-sm">Ressources</span>
                        </div>
                        <table class="w-full text-sm">
                            <thead><tr class="bg-gray-50"><th class="text-left p-2 text-gray-600 text-xs">Qui</th><th class="text-left p-2 text-gray-600 text-xs">Quoi</th><th class="text-left p-2 text-gray-600 text-xs">Temps/Budget</th></tr></thead>
                            <tbody>
                                <?php foreach ($validRes as $r): ?>
                                <tr class="border-t"><td class="p-2"><?= h($r['qui'] ?? '') ?></td><td class="p-2"><?= h($r['quoi'] ?? '') ?></td><td class="p-2"><?= h($r['temps_budget'] ?? '') ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
            document.getElementById('messagesView').classList.add('hidden');
            document.getElementById('participantView').classList.add('hidden');

            if (mode === 'summary') document.getElementById('summaryView').classList.remove('hidden');
            else if (mode === 'messages') document.getElementById('messagesView').classList.remove('hidden');
            else document.getElementById('participantView').classList.remove('hidden');
        }
    </script>
</body>
</html>
