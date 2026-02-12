<?php
/**
 * Vue globale de session - Pilotage de Projet
 * Affiche tous les projets de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-pilotage-projet';

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

$taskStatuses = getTaskStatuses();
$checkpointTypes = getCheckpointTypes();

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

// Agreger
$totalObjectifs = 0;
$totalPhases = 0;
$totalTasks = 0;
$doneTasks = 0;
$totalCheckpoints = 0;
$totalLessons = 0;
$participantsData = [];

foreach ($analyses as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $objectifs = json_decode($a['objectifs_data'], true) ?: [];
    $phases = json_decode($a['phases_data'], true) ?: [];
    $checkpoints = json_decode($a['checkpoints_data'], true) ?: [];
    $lessons = json_decode($a['lessons_data'], true) ?: [];

    $totalObjectifs += count(array_filter($objectifs, fn($o) => !empty(trim($o['titre'] ?? ''))));
    $totalPhases += count(array_filter($phases, fn($p) => !empty(trim($p['nom'] ?? ''))));
    $totalCheckpoints += count($checkpoints);
    $totalLessons += count(array_filter($lessons, fn($l) => !empty(trim($l['lecon'] ?? ''))));

    $pTasks = 0; $pDone = 0;
    foreach ($phases as $p) {
        foreach ($p['taches'] ?? [] as $t) {
            if (!empty(trim($t['titre'] ?? ''))) { $pTasks++; $totalTasks++; if (($t['statut'] ?? '') === 'done') { $pDone++; $doneTasks++; } }
        }
    }

    $participantsData[$a['user_id']] = [
        'user' => ['prenom' => $a['user_prenom'], 'nom' => $a['user_nom'], 'organisation' => $a['user_organisation']],
        'nom_projet' => $a['nom_projet'],
        'description_projet' => $a['description_projet'],
        'objectifs' => $objectifs,
        'phases' => $phases,
        'checkpoints' => $checkpoints,
        'lessons' => $lessons,
        'synthese' => $a['synthese'],
        'is_shared' => $a['is_shared'],
        'task_count' => $pTasks,
        'done_count' => $pDone
    ];
}

$participantsCount = count($analyses);
$lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilotage Projet - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .item-card { transition: all 0.3s ease; }
        .item-card:hover { transform: translateY(-2px); }
        .section-number { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: #059669; color: white; font-weight: 700; font-size: 0.7rem; }
        @media print { .no-print { display: none !important; } body { background: white !important; } }
    </style>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-teal-100 min-h-screen">
    <header class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Pilotage de Projet</h1>
                    <p class="text-emerald-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-400 hover:bg-green-300 px-3 py-1 rounded text-sm">Partages seulement (<?= $totalShared ?>)</a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">Voir toutes (<?= $totalAll ?>)</a>
                    <?php endif; ?>
                    <?= renderLanguageSelector('bg-emerald-400 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <button onclick="window.print()" class="bg-emerald-400 hover:bg-emerald-300 px-3 py-1 rounded text-sm">Imprimer</button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-emerald-400 hover:bg-emerald-300 px-3 py-1 rounded text-sm">Retour</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($showAll): ?>
        <div class="bg-orange-100 border border-orange-300 rounded-xl p-4 mb-6">
            <p class="text-orange-800 text-sm"><strong>Mode: Toutes les analyses</strong> - Vous voyez toutes les analyses (<?= $totalAll ?>), y compris celles non partagees.</p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $totalObjectifs ?></div>
                <div class="text-gray-500 text-sm">Objectifs</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $totalPhases ?></div>
                <div class="text-gray-500 text-sm">Phases</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $totalTasks ?></div>
                <div class="text-gray-500 text-sm">Taches</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-green-500">
                <div class="text-3xl font-bold text-green-600"><?= $doneTasks ?></div>
                <div class="text-gray-500 text-sm">Terminees</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-amber-500">
                <div class="text-3xl font-bold text-amber-600"><?= $totalCheckpoints ?></div>
                <div class="text-gray-500 text-sm">Controles</div>
            </div>
        </div>

        <!-- Affichage -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 no-print">
            <div class="flex flex-wrap gap-4 items-center">
                <div class="font-medium text-gray-700">Affichage:</div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="summary" checked onchange="setDisplayMode('summary')">
                    <span class="text-sm">Vue d'ensemble</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="participant" onchange="setDisplayMode('participant')">
                    <span class="text-sm">Detail par participant</span>
                </label>
            </div>
        </div>

        <!-- Vue d'ensemble -->
        <div id="summaryView">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Tous les projets</h2>
            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">Aucune analyse trouvee.</div>
            <?php else: ?>
            <div class="grid md:grid-cols-2 gap-6">
                <?php foreach ($participantsData as $userId => $data):
                    $pct = $data['task_count'] > 0 ? round(($data['done_count'] / $data['task_count']) * 100) : 0;
                ?>
                <div class="item-card bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-emerald-500 to-teal-500 text-white p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-bold"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                                <?php if (!empty($data['user']['organisation'])): ?>
                                <span class="text-emerald-200 text-sm ml-1">(<?= h($data['user']['organisation']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <span class="px-2 py-1 rounded text-xs <?= $data['is_shared'] ? 'bg-green-500/50' : 'bg-yellow-500/50' ?>"><?= $data['is_shared'] ? 'Soumis' : 'Brouillon' ?></span>
                        </div>
                        <div class="text-lg font-semibold mt-1"><?= h($data['nom_projet']) ?: '<em class="opacity-50">Sans nom</em>' ?></div>
                    </div>
                    <div class="p-4">
                        <?php if (!empty(trim($data['description_projet']))): ?>
                        <p class="text-sm text-gray-600 mb-3"><?= h(mb_strimwidth($data['description_projet'], 0, 200, '...')) ?></p>
                        <?php endif; ?>

                        <!-- Progression -->
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-1 bg-gray-200 rounded-full h-3">
                                <div class="bg-emerald-500 h-3 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                            </div>
                            <span class="text-sm font-bold text-emerald-700"><?= $pct ?>%</span>
                        </div>

                        <div class="grid grid-cols-4 gap-2 text-center text-xs">
                            <div class="bg-emerald-50 rounded p-2"><div class="font-bold text-emerald-700"><?= count(array_filter($data['objectifs'], fn($o) => !empty(trim($o['titre'] ?? '')))) ?></div><div class="text-gray-500">Obj.</div></div>
                            <div class="bg-blue-50 rounded p-2"><div class="font-bold text-blue-700"><?= count(array_filter($data['phases'], fn($p) => !empty(trim($p['nom'] ?? '')))) ?></div><div class="text-gray-500">Phases</div></div>
                            <div class="bg-purple-50 rounded p-2"><div class="font-bold text-purple-700"><?= $data['done_count'] ?>/<?= $data['task_count'] ?></div><div class="text-gray-500">Taches</div></div>
                            <div class="bg-amber-50 rounded p-2"><div class="font-bold text-amber-700"><?= count($data['checkpoints']) ?></div><div class="text-gray-500">Ctrl.</div></div>
                        </div>

                        <!-- Objectifs resumes -->
                        <?php $validObj = array_filter($data['objectifs'], fn($o) => !empty(trim($o['titre'] ?? ''))); ?>
                        <?php if (!empty($validObj)): ?>
                        <div class="mt-3 pt-3 border-t">
                            <span class="text-xs font-semibold text-gray-500">Objectifs:</span>
                            <ul class="text-sm text-gray-700 mt-1 space-y-0.5">
                                <?php foreach (array_slice($validObj, 0, 3) as $o): ?>
                                <li class="flex items-center gap-1"><div class="section-number text-[0.6rem]">O</div> <?= h(mb_strimwidth($o['titre'], 0, 80, '...')) ?></li>
                                <?php endforeach; ?>
                                <?php if (count($validObj) > 3): ?>
                                <li class="text-gray-400 text-xs">+ <?= count($validObj) - 3 ?> autre(s)</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue detail par participant -->
        <div id="participantView" class="hidden space-y-6">
            <?php foreach ($participantsData as $userId => $data): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['user']['organisation'])): ?>
                            <span class="text-emerald-200 text-sm ml-2">(<?= h($data['user']['organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php $pct = $data['task_count'] > 0 ? round(($data['done_count'] / $data['task_count']) * 100) : 0; ?>
                        <div class="flex items-center gap-3">
                            <div class="w-20 bg-white/30 rounded-full h-2"><div class="bg-white h-2 rounded-full" style="width: <?= $pct ?>%"></div></div>
                            <span class="text-sm"><?= $pct ?>%</span>
                        </div>
                    </div>
                    <div class="text-xl font-semibold mt-1"><?= h($data['nom_projet']) ?: '<em class="opacity-50">Sans nom</em>' ?></div>
                </div>
                <div class="p-5 space-y-4">
                    <?php if (!empty(trim($data['description_projet']))): ?>
                    <p class="text-sm text-gray-600"><?= nl2br(h($data['description_projet'])) ?></p>
                    <?php endif; ?>

                    <!-- Objectifs -->
                    <?php $validObj = array_filter($data['objectifs'], fn($o) => !empty(trim($o['titre'] ?? ''))); ?>
                    <?php if (!empty($validObj)): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 text-sm mb-2">Objectifs</h4>
                        <div class="space-y-1">
                            <?php foreach ($validObj as $i => $o): ?>
                            <div class="flex items-center gap-2 text-sm bg-emerald-50 rounded p-2 border border-emerald-200">
                                <div class="section-number"><?= $i + 1 ?></div>
                                <span class="font-medium text-gray-800"><?= h($o['titre']) ?></span>
                                <?php if (!empty(trim($o['criteres'] ?? ''))): ?>
                                <span class="text-xs text-gray-500 ml-auto">Criteres: <?= h(mb_strimwidth($o['criteres'], 0, 60, '...')) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Phases -->
                    <?php foreach ($data['phases'] as $i => $p): if (empty(trim($p['nom'] ?? '')) && empty($p['taches'] ?? [])) continue; ?>
                    <div class="border-l-4 border-emerald-500 pl-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="bg-emerald-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">P<?= $i + 1 ?></span>
                            <span class="font-bold text-sm text-gray-800"><?= h($p['nom'] ?? 'Sans nom') ?></span>
                            <?php if (!empty($p['dates'])): ?>
                            <span class="text-xs text-gray-500"><?= h($p['dates']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($p['taches'] ?? [])): ?>
                        <div class="space-y-1">
                            <?php foreach ($p['taches'] as $t): if (empty(trim($t['titre'] ?? ''))) continue;
                                $st = $taskStatuses[$t['statut'] ?? 'todo'] ?? $taskStatuses['todo'];
                            ?>
                            <div class="flex items-center gap-2 text-xs bg-gray-50 rounded p-1.5 border">
                                <span><?= $st['icon'] ?></span>
                                <span class="flex-1"><?= h($t['titre']) ?></span>
                                <?php if (!empty($t['responsable'])): ?>
                                <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded"><?= h($t['responsable']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Checkpoints -->
                    <?php if (!empty($data['checkpoints'])): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 text-sm mb-2">Points de controle</h4>
                        <div class="space-y-1">
                            <?php foreach ($data['checkpoints'] as $cp):
                                $cpType = $checkpointTypes[$cp['type'] ?? ''] ?? null;
                            ?>
                            <div class="flex items-center gap-2 text-xs bg-gray-50 rounded p-2 border">
                                <?php if ($cpType): ?><span><?= $cpType['icon'] ?></span> <span class="font-semibold"><?= $cpType['label'] ?></span><?php endif; ?>
                                <span class="text-gray-600"><?= h($cp['description'] ?? '') ?></span>
                                <?php if (!empty($cp['validateur'])): ?>
                                <span class="ml-auto text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded"><?= h($cp['validateur']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Synthese -->
                    <?php if (!empty(trim($data['synthese'] ?? ''))): ?>
                    <div class="bg-gray-50 rounded-lg p-3 border">
                        <h4 class="font-bold text-gray-700 text-sm mb-1">Synthese</h4>
                        <p class="text-sm text-gray-600"><?= nl2br(h(mb_strimwidth($data['synthese'], 0, 300, '...'))) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">Aucune analyse trouvee pour cette session.</div>
            <?php endif; ?>
        </div>
    </main>

    <?= renderLanguageScript() ?>

    <script>
        function setDisplayMode(mode) {
            document.getElementById('summaryView').classList.add('hidden');
            document.getElementById('participantView').classList.add('hidden');
            document.getElementById(mode + 'View').classList.remove('hidden');
        }
    </script>
</body>
</html>
