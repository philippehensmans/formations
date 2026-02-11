<?php
/**
 * Vue globale de session - Publics & Personas
 * Affiche tous les publics et personas de tous les participants d'une session
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-personas';

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

$families = getPublicFamilies();

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
$allStakeholders = [];
$allPersonas = [];
$familyCounts = [];
$priorityCounts = ['high' => 0, 'medium' => 0, 'low' => 0];
$participantsData = [];

foreach ($analyses as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    $stakeholders = json_decode($a['stakeholders_data'], true) ?: [];
    $personas = json_decode($a['personas_data'], true) ?: [];

    // Collecter les stakeholders
    foreach ($stakeholders as $s) {
        if (empty(trim($s['nom'] ?? ''))) continue;
        $famille = $s['famille'] ?? 'autre';
        $priorite = $s['priorite'] ?? 'medium';

        $allStakeholders[] = [
            'nom' => $s['nom'],
            'famille' => $famille,
            'sous_groupe' => $s['sous_groupe'] ?? '',
            'priorite' => $priorite,
            'attentes' => $s['attentes'] ?? '',
            'localisation' => $s['localisation'] ?? '',
            'communication_actuelle' => $s['communication_actuelle'] ?? '',
            'user_id' => $a['user_id'],
            'user_prenom' => $a['user_prenom'],
            'user_nom' => $a['user_nom'],
            'user_organisation' => $a['user_organisation'],
            'nom_organisation' => $a['nom_organisation']
        ];

        $familyCounts[$famille] = ($familyCounts[$famille] ?? 0) + 1;
        $priorityCounts[$priorite] = ($priorityCounts[$priorite] ?? 0) + 1;
    }

    // Collecter les personas
    foreach ($personas as $p) {
        if (empty(trim($p['prenom'] ?? ''))) continue;
        $allPersonas[] = [
            'prenom' => $p['prenom'],
            'age' => $p['age'] ?? '',
            'type_public' => $p['type_public'] ?? '',
            'situation' => $p['situation'] ?? '',
            'rapport_org' => $p['rapport_org'] ?? '',
            'besoins' => $p['besoins'] ?? '',
            'habitudes_medias' => $p['habitudes_medias'] ?? '',
            'ce_qui_touche' => $p['ce_qui_touche'] ?? '',
            'ce_qui_rebute' => $p['ce_qui_rebute'] ?? '',
            'message_ideal' => $p['message_ideal'] ?? '',
            'user_id' => $a['user_id'],
            'user_prenom' => $a['user_prenom'],
            'user_nom' => $a['user_nom'],
            'user_organisation' => $a['user_organisation']
        ];
    }

    // Stocker les donnees par participant
    $participantsData[$a['user_id']] = [
        'user' => [
            'prenom' => $a['user_prenom'],
            'nom' => $a['user_nom'],
            'organisation' => $a['user_organisation']
        ],
        'nom_organisation' => $a['nom_organisation'],
        'stakeholders' => $stakeholders,
        'personas' => $personas,
        'synthese' => $a['synthese'],
        'is_shared' => $a['is_shared']
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
    <title>Publics & Personas - Vue Session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .family-filter { transition: all 0.2s ease; }
        .family-filter:hover { transform: scale(1.05); }
        .family-filter.active { ring: 4px; transform: scale(1.1); }
        .item-card { transition: all 0.3s ease; }
        .item-card:hover { transform: translateY(-2px); }
        .priority-high { border-left: 4px solid #ef4444; }
        .priority-medium { border-left: 4px solid #f59e0b; }
        .priority-low { border-left: 4px solid #6b7280; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-rose-50 to-pink-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-rose-500 to-pink-600 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Publics & Personas</h1>
                    <p class="text-rose-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
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
                    <?= renderLanguageSelector('bg-rose-400 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <button onclick="window.print()" class="bg-rose-400 hover:bg-rose-300 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-rose-400 hover:bg-rose-300 px-3 py-1 rounded text-sm">
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
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-rose-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-pink-600"><?= count($allStakeholders) ?></div>
                <div class="text-gray-500 text-sm">Publics total</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= count($allPersonas) ?></div>
                <div class="text-gray-500 text-sm">Personas total</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-red-500">
                <div class="text-3xl font-bold text-red-600"><?= $priorityCounts['high'] ?></div>
                <div class="text-gray-500 text-sm">Priorite haute</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center border-t-4 border-amber-500">
                <div class="text-3xl font-bold text-amber-600"><?= $priorityCounts['medium'] ?></div>
                <div class="text-gray-500 text-sm">Priorite moyenne</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-amber-500"><?= count($familyCounts) ?></div>
                <div class="text-gray-500 text-sm">Familles couvertes</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 no-print">
            <div class="flex flex-wrap gap-3 items-center">
                <div class="font-medium text-gray-700">Filtrer par famille:</div>
                <button onclick="filterByFamily('all')" id="filter-all"
                        class="family-filter px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium active ring-rose-500 ring-4">
                    Tous (<?= count($allStakeholders) ?>)
                </button>
                <?php foreach ($families as $key => $fam):
                    $count = $familyCounts[$key] ?? 0;
                ?>
                <button onclick="filterByFamily('<?= $key ?>')" id="filter-<?= $key ?>"
                        class="family-filter px-3 py-2 bg-<?= $fam['color'] ?>-50 text-<?= $fam['color'] ?>-700 hover:opacity-80 rounded-lg text-sm font-medium border border-<?= $fam['color'] ?>-300">
                    <?= $fam['icon'] ?> <?= $fam['label'] ?> (<?= $count ?>)
                </button>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex flex-wrap gap-4 items-center">
                <div class="font-medium text-gray-700">Affichage:</div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="grid" checked onchange="setDisplayMode('grid')">
                    <span class="text-sm">Par famille</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="personas" onchange="setDisplayMode('personas')">
                    <span class="text-sm">Personas</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="participant" onchange="setDisplayMode('participant')">
                    <span class="text-sm">Par participant</span>
                </label>
            </div>
        </div>

        <!-- Vue Grille: par famille de publics -->
        <div id="gridView">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Carte des publics - Vue globale</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($families as $key => $fam):
                    $famStakeholders = array_filter($allStakeholders, function($s) use ($key) { return $s['famille'] === $key; });
                    if (empty($famStakeholders)) continue;
                ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden family-section" data-family="<?= $key ?>">
                    <div class="bg-<?= $fam['color'] ?>-500 text-white p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl"><?= $fam['icon'] ?></span>
                                <div>
                                    <div class="font-bold text-lg"><?= $fam['label'] ?></div>
                                    <div class="text-sm opacity-80"><?= $fam['description'] ?></div>
                                </div>
                            </div>
                            <span class="bg-white/30 px-3 py-1 rounded-full text-lg font-bold"><?= count($famStakeholders) ?></span>
                        </div>
                    </div>
                    <div class="p-4 max-h-[500px] overflow-y-auto">
                        <div class="space-y-3">
                            <?php foreach ($famStakeholders as $s): ?>
                            <div class="item-card p-3 bg-<?= $fam['color'] ?>-50 rounded-lg border border-<?= $fam['color'] ?>-200 priority-<?= $s['priorite'] ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-bold text-gray-800 text-sm"><?= h($s['nom']) ?></h4>
                                    <span class="px-2 py-0.5 rounded text-xs shrink-0 ml-2 <?= $s['priorite'] === 'high' ? 'bg-red-100 text-red-700' : ($s['priorite'] === 'low' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-700') ?>">
                                        <?= $s['priorite'] === 'high' ? 'Haute' : ($s['priorite'] === 'low' ? 'Basse' : 'Moyenne') ?>
                                    </span>
                                </div>
                                <?php if (!empty(trim($s['sous_groupe']))): ?>
                                <p class="text-xs text-gray-500 mb-2">Sous-groupe: <?= h($s['sous_groupe']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty(trim($s['attentes']))): ?>
                                <div class="text-xs mb-1"><span class="font-semibold text-blue-700">Attentes:</span> <?= h(mb_strimwidth($s['attentes'], 0, 120, '...')) ?></div>
                                <?php endif; ?>
                                <?php if (!empty(trim($s['localisation']))): ?>
                                <div class="text-xs mb-1"><span class="font-semibold text-green-700">Ou:</span> <?= h(mb_strimwidth($s['localisation'], 0, 120, '...')) ?></div>
                                <?php endif; ?>
                                <div class="flex justify-between items-center text-xs text-gray-400 mt-2 pt-2 border-t border-gray-100">
                                    <span class="font-medium"><?= h($s['user_prenom']) ?> <?= h($s['user_nom']) ?></span>
                                    <?php if (!empty($s['nom_organisation'])): ?>
                                    <span class="italic"><?= h($s['nom_organisation']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($allStakeholders)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucun public identifie dans cette session.
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue Personas -->
        <div id="personasView" class="hidden">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Tous les personas</h2>
            <?php if (empty($allPersonas)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucun persona cree dans cette session.
            </div>
            <?php else: ?>
            <div class="grid md:grid-cols-2 gap-6">
                <?php foreach ($allPersonas as $p):
                    $fam = $families[$p['type_public'] ?? ''] ?? null;
                ?>
                <div class="item-card bg-white rounded-xl shadow-lg p-5 border-l-4 border-rose-500 persona-card" data-family="<?= h($p['type_public'] ?? 'autre') ?>">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 bg-rose-100 rounded-full flex items-center justify-center text-xl font-bold text-rose-600">
                            <?= mb_strtoupper(mb_substr($p['prenom'], 0, 1)) ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800"><?= h($p['prenom']) ?><?= !empty($p['age']) ? ', ' . h($p['age']) : '' ?></h3>
                            <?php if ($fam): ?>
                            <span class="px-2 py-0.5 bg-<?= $fam['color'] ?>-100 text-<?= $fam['color'] ?>-700 rounded text-xs"><?= $fam['icon'] ?> <?= $fam['label'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php foreach ([
                        ['Situation', $p['situation']],
                        ['Rapport a l\'organisation', $p['rapport_org']],
                        ['Besoins et attentes', $p['besoins']],
                        ['Habitudes medias', $p['habitudes_medias']],
                        ['Ce qui le/la touche', $p['ce_qui_touche']],
                        ['Ce qui le/la rebute', $p['ce_qui_rebute']],
                    ] as [$label, $value]): ?>
                        <?php if (!empty(trim($value))): ?>
                        <div class="mb-2">
                            <span class="text-xs font-semibold text-gray-500"><?= $label ?></span>
                            <p class="text-sm text-gray-700"><?= h(mb_strimwidth($value, 0, 200, '...')) ?></p>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if (!empty(trim($p['message_ideal']))): ?>
                    <div class="mt-3 bg-rose-50 border-2 border-rose-200 rounded-lg p-3">
                        <span class="text-xs font-semibold text-rose-800">Message ideal</span>
                        <p class="text-sm font-medium text-rose-900 italic">&laquo; <?= h($p['message_ideal']) ?> &raquo;</p>
                    </div>
                    <?php endif; ?>

                    <div class="flex justify-between items-center text-xs text-gray-400 mt-3 pt-2 border-t border-gray-100">
                        <span class="font-medium"><?= h($p['user_prenom']) ?> <?= h($p['user_nom']) ?></span>
                        <?php if (!empty($p['user_organisation'])): ?>
                        <span class="italic"><?= h($p['user_organisation']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue Par Participant -->
        <div id="participantView" class="hidden space-y-6">
            <?php foreach ($participantsData as $userId => $data): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden participant-section">
                <div class="bg-gradient-to-r from-rose-500 to-pink-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-lg"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['user']['organisation'])): ?>
                            <span class="text-rose-200 text-sm ml-2">(<?= h($data['user']['organisation']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($data['nom_organisation'])): ?>
                            <span class="block text-rose-100 text-sm mt-1">Organisation analysee: <?= h($data['nom_organisation']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <?php
                            $validStakeholders = count(array_filter($data['stakeholders'], function($s) { return !empty(trim($s['nom'] ?? '')); }));
                            $validPersonas = count(array_filter($data['personas'], function($p) { return !empty(trim($p['prenom'] ?? '')); }));
                            ?>
                            <span class="bg-white/20 px-2 py-1 rounded text-sm"><?= $validStakeholders ?> publics</span>
                            <span class="bg-white/20 px-2 py-1 rounded text-sm"><?= $validPersonas ?> personas</span>
                            <span class="px-2 py-1 rounded text-sm <?= $data['is_shared'] ? 'bg-green-500/50' : 'bg-yellow-500/50' ?>"><?= $data['is_shared'] ? 'Soumis' : 'Brouillon' ?></span>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <!-- Publics du participant -->
                    <?php $validStakeholdersList = array_filter($data['stakeholders'], function($s) { return !empty(trim($s['nom'] ?? '')); }); ?>
                    <?php if (!empty($validStakeholdersList)): ?>
                    <h4 class="font-bold text-gray-700 mb-3">Publics identifies</h4>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                        <?php foreach ($validStakeholdersList as $s):
                            $fam = $families[$s['famille'] ?? ''] ?? null;
                            $priority = $s['priorite'] ?? 'medium';
                        ?>
                        <div class="p-3 rounded-lg border priority-<?= $priority ?> family-item" data-family="<?= h($s['famille'] ?? 'autre') ?>">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-sm text-gray-800"><?= h($s['nom']) ?></span>
                                <?php if ($fam): ?>
                                <span class="text-xs px-1.5 py-0.5 bg-<?= $fam['color'] ?>-100 text-<?= $fam['color'] ?>-700 rounded"><?= $fam['icon'] ?></span>
                                <?php endif; ?>
                                <span class="text-xs px-1.5 py-0.5 rounded <?= $priority === 'high' ? 'bg-red-100 text-red-700' : ($priority === 'low' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-700') ?>">
                                    <?= $priority === 'high' ? 'H' : ($priority === 'low' ? 'B' : 'M') ?>
                                </span>
                            </div>
                            <?php if (!empty(trim($s['attentes'] ?? ''))): ?>
                            <p class="text-xs text-gray-600"><?= h(mb_strimwidth($s['attentes'], 0, 100, '...')) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Personas du participant -->
                    <?php $validPersonasList = array_filter($data['personas'], function($p) { return !empty(trim($p['prenom'] ?? '')); }); ?>
                    <?php if (!empty($validPersonasList)): ?>
                    <h4 class="font-bold text-gray-700 mb-3">Personas</h4>
                    <div class="grid md:grid-cols-2 gap-3 mb-4">
                        <?php foreach ($validPersonasList as $p):
                            $fam = $families[$p['type_public'] ?? ''] ?? null;
                        ?>
                        <div class="p-3 bg-rose-50 rounded-lg border border-rose-200 persona-item" data-family="<?= h($p['type_public'] ?? 'autre') ?>">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-bold text-gray-800"><?= h($p['prenom']) ?><?= !empty($p['age']) ? ', ' . h($p['age']) : '' ?></span>
                                <?php if ($fam): ?>
                                <span class="text-xs px-1.5 py-0.5 bg-<?= $fam['color'] ?>-100 text-<?= $fam['color'] ?>-700 rounded"><?= $fam['icon'] ?> <?= $fam['label'] ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty(trim($p['situation'] ?? ''))): ?>
                            <p class="text-xs text-gray-600 mb-1"><strong>Situation:</strong> <?= h(mb_strimwidth($p['situation'], 0, 120, '...')) ?></p>
                            <?php endif; ?>
                            <?php if (!empty(trim($p['message_ideal'] ?? ''))): ?>
                            <p class="text-xs text-rose-700 italic mt-1">&laquo; <?= h(mb_strimwidth($p['message_ideal'], 0, 120, '...')) ?> &raquo;</p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Synthese du participant -->
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
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucune analyse trouvee pour cette session.
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?= renderLanguageScript() ?>

    <script>
        let currentFilter = 'all';
        let currentDisplay = 'grid';

        function filterByFamily(family) {
            currentFilter = family;

            // Update filter buttons
            document.querySelectorAll('.family-filter').forEach(btn => {
                btn.classList.remove('active', 'ring-4', 'ring-rose-500');
            });
            document.getElementById('filter-' + family).classList.add('active', 'ring-4', 'ring-rose-500');

            applyFilters();
        }

        function setDisplayMode(mode) {
            currentDisplay = mode;

            document.getElementById('gridView').classList.add('hidden');
            document.getElementById('personasView').classList.add('hidden');
            document.getElementById('participantView').classList.add('hidden');

            if (mode === 'grid') document.getElementById('gridView').classList.remove('hidden');
            else if (mode === 'personas') document.getElementById('personasView').classList.remove('hidden');
            else document.getElementById('participantView').classList.remove('hidden');

            applyFilters();
        }

        function applyFilters() {
            if (currentDisplay === 'grid') {
                document.querySelectorAll('.family-section').forEach(section => {
                    if (currentFilter === 'all' || section.dataset.family === currentFilter) {
                        section.style.display = '';
                    } else {
                        section.style.display = 'none';
                    }
                });
            } else if (currentDisplay === 'personas') {
                document.querySelectorAll('.persona-card').forEach(card => {
                    if (currentFilter === 'all' || card.dataset.family === currentFilter) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            } else if (currentDisplay === 'participant') {
                document.querySelectorAll('.family-item, .persona-item').forEach(item => {
                    if (currentFilter === 'all' || item.dataset.family === currentFilter) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
        }
    </script>
</body>
</html>
