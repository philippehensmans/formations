<?php
/**
 * Vue globale de session - Six Chapeaux de Bono
 * Affiche tous les avis de tous les participants avec filtrage par chapeau
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-six-chapeaux';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();
$chapeaux = getChapeaux();

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

// Option pour voir tous les avis ou seulement les partages
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Recuperer les avis de la session (tous ou seulement partages)
if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM avis WHERE session_id = ? ORDER BY chapeau, created_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM avis WHERE session_id = ? AND is_shared = 1 ORDER BY chapeau, created_at DESC");
    $stmt->execute([$sessionId]);
}
$allAvis = $stmt->fetchAll();

// Enrichir avec les infos utilisateur et grouper par chapeau
$avisByChapeau = [];
foreach ($chapeaux as $key => $ch) {
    $avisByChapeau[$key] = [];
}

foreach ($allAvis as &$a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $a['user_prenom'] = $userInfo['prenom'] ?? '';
    $a['user_nom'] = $userInfo['nom'] ?? '';
    $a['user_organisation'] = $userInfo['organisation'] ?? '';

    if (isset($avisByChapeau[$a['chapeau']])) {
        $avisByChapeau[$a['chapeau']][] = $a;
    }
}

// Statistiques
$totalAvis = count($allAvis);

// Stats par chapeau (selon le filtre actuel)
$statsQuery = $showAll
    ? "SELECT chapeau, COUNT(*) as count FROM avis WHERE session_id = ? GROUP BY chapeau"
    : "SELECT chapeau, COUNT(*) as count FROM avis WHERE session_id = ? AND is_shared = 1 GROUP BY chapeau";
$stmt = $db->prepare($statsQuery);
$stmt->execute([$sessionId]);
$statsResults = $stmt->fetchAll();
$stats = [];
foreach ($statsResults as $row) {
    $stats[$row['chapeau']] = $row['count'];
}

// Recuperer le nombre de participants
$participantsQuery = $showAll
    ? "SELECT COUNT(DISTINCT user_id) as count FROM avis WHERE session_id = ?"
    : "SELECT COUNT(DISTINCT user_id) as count FROM avis WHERE session_id = ? AND is_shared = 1";
$stmt = $db->prepare($participantsQuery);
$stmt->execute([$sessionId]);
$participantsCount = $stmt->fetch()['count'];

// Compter le total des avis (partages vs tous)
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM avis WHERE session_id = ?");
$stmt->execute([$sessionId]);
$avisCounts = $stmt->fetch();
$totalAllAvis = $avisCounts['total'];
$totalSharedAvis = $avisCounts['shared'];

// Preparer les donnees JSON pour le filtrage cote client
$avisJson = [];
foreach ($allAvis as $a) {
    $avisJson[] = [
        'id' => $a['id'],
        'chapeau' => $a['chapeau'],
        'chapeau_nom' => $chapeaux[$a['chapeau']]['nom'] ?? '',
        'chapeau_icon' => $chapeaux[$a['chapeau']]['icon'] ?? '',
        'contenu' => $a['contenu'],
        'user_prenom' => $a['user_prenom'],
        'user_nom' => $a['user_nom'],
        'user_organisation' => $a['user_organisation'],
        'user_id' => $a['user_id'],
        'created_at' => $a['created_at']
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Six Chapeaux - Vue Session - <?= h($session['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect x='1' y='1' width='9' height='9' fill='%23e5e7eb'/><rect x='11' y='1' width='9' height='9' fill='%23ef4444'/><rect x='21' y='1' width='9' height='9' fill='%231e293b'/><rect x='1' y='11' width='9' height='9' fill='%23eab308'/><rect x='11' y='11' width='9' height='9' fill='%2322c55e'/><rect x='21' y='11' width='9' height='9' fill='%233b82f6'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .chapeau-filter { transition: all 0.2s ease; }
        .chapeau-filter:hover { transform: scale(1.05); }
        .chapeau-filter.active { ring: 4px; transform: scale(1.1); }
        .avis-card { transition: all 0.3s ease; }
        .avis-card:hover { transform: translateY(-2px); }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Six Chapeaux de Bono</h1>
                    <p class="text-indigo-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">
                        Partages seulement (<?= $totalSharedAvis ?>)
                    </a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">
                        Voir tous les avis (<?= $totalAllAvis ?>)
                    </a>
                    <?php endif; ?>
                    <?php if (isSuperAdmin()): ?>
                    <button onclick="generateAISummary()" id="aiSummaryBtn" class="bg-amber-500 hover:bg-amber-400 px-3 py-1 rounded text-sm flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        Synthese IA
                    </button>
                    <?php endif; ?>
                    <?= renderLanguageSelector('bg-indigo-500 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <button onclick="window.print()" class="bg-indigo-500 hover:bg-indigo-400 px-3 py-1 rounded text-sm">
                        Imprimer
                    </button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-indigo-500 hover:bg-indigo-400 px-3 py-1 rounded text-sm">
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Sujet de la session -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <h2 class="text-lg font-bold text-gray-800 mb-2">Sujet de reflexion</h2>
                    <?php if (!empty($session['sujet'])): ?>
                    <p class="text-gray-700" id="sujetDisplay"><?= nl2br(h($session['sujet'])) ?></p>
                    <?php else: ?>
                    <p class="text-gray-400 italic" id="sujetDisplay">Aucun sujet defini</p>
                    <?php endif; ?>
                </div>
                <button onclick="openSujetModal()" class="no-print ml-4 px-3 py-1 bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded text-sm">
                    Modifier
                </button>
            </div>
        </div>

        <?php if ($showAll): ?>
        <div class="bg-orange-100 border border-orange-300 rounded-xl p-4 mb-6">
            <p class="text-orange-800 text-sm">
                <strong>Mode: Tous les avis</strong> - Vous voyez tous les avis (<?= $totalAllAvis ?>), y compris ceux non partages.
                Les avis non partages sont marques d'un badge orange.
            </p>
        </div>
        <?php endif; ?>

        <!-- Statistiques globales -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $totalAvis ?></div>
                <div class="text-gray-500 text-sm"><?= $showAll ? 'Avis (tous)' : 'Avis partages' ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= count(array_filter($stats)) ?></div>
                <div class="text-gray-500 text-sm">Chapeaux utilises</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-orange-600"><?= $participantsCount > 0 ? round($totalAvis / $participantsCount, 1) : 0 ?></div>
                <div class="text-gray-500 text-sm">Moyenne avis/participant</div>
            </div>
        </div>

        <!-- Repartition par chapeau -->
        <div class="bg-white rounded-xl shadow p-6 mb-8">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Repartition par chapeau</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php foreach ($chapeaux as $key => $chapeau):
                    $count = $stats[$key] ?? 0;
                    $percent = $totalAvis > 0 ? round(($count / $totalAvis) * 100) : 0;
                ?>
                <div class="flex flex-col items-center p-4 <?= $chapeau['bg'] ?> rounded-xl border-2 <?= $chapeau['border'] ?>">
                    <span class="text-3xl mb-2"><?= $chapeau['icon'] ?></span>
                    <div class="font-bold <?= $chapeau['text'] ?>"><?= $count ?></div>
                    <div class="text-xs <?= $key === 'noir' ? 'text-gray-400' : 'text-gray-500' ?>"><?= $percent ?>%</div>
                    <div class="text-xs <?= $chapeau['text'] ?> mt-1 text-center"><?= $chapeau['nom'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 no-print">
            <div class="flex flex-wrap gap-4 items-center">
                <div class="font-medium text-gray-700">Filtrer par chapeau:</div>
                <button onclick="filterByChapeau('all')" id="filter-all"
                        class="chapeau-filter px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium active ring-indigo-500">
                    Tous (<?= $totalAvis ?>)
                </button>
                <?php foreach ($chapeaux as $key => $chapeau):
                    $count = $stats[$key] ?? 0;
                ?>
                <button onclick="filterByChapeau('<?= $key ?>')" id="filter-<?= $key ?>"
                        class="chapeau-filter px-3 py-2 <?= $chapeau['bg'] ?> <?= $chapeau['text'] ?> hover:opacity-80 rounded-lg text-sm font-medium border <?= $chapeau['border'] ?>">
                    <?= $chapeau['icon'] ?> <?= $count ?>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex gap-4 items-center">
                <div class="font-medium text-gray-700">Affichage:</div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="grid" checked onchange="setDisplayMode('grid')">
                    <span class="text-sm">Grille par chapeau</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="list" onchange="setDisplayMode('list')">
                    <span class="text-sm">Liste chronologique</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="participant" onchange="setDisplayMode('participant')">
                    <span class="text-sm">Par participant</span>
                </label>
            </div>
        </div>

        <!-- Vue Grille (par chapeau) -->
        <div id="gridView" class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($chapeaux as $key => $chapeau): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden chapeau-section" data-chapeau="<?= $key ?>">
                <div class="<?= $chapeau['bg'] ?> <?= $chapeau['text'] ?> p-4 border-b-4 <?= $chapeau['border'] ?>">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl"><?= $chapeau['icon'] ?></span>
                            <div>
                                <div class="font-bold"><?= $chapeau['nom'] ?></div>
                                <div class="text-xs opacity-80"><?= $chapeau['description'] ?></div>
                            </div>
                        </div>
                        <span class="bg-white/30 px-2 py-1 rounded text-sm font-bold"><?= count($avisByChapeau[$key]) ?></span>
                    </div>
                </div>
                <div class="p-4 max-h-96 overflow-y-auto">
                    <?php if (empty($avisByChapeau[$key])): ?>
                    <p class="text-gray-400 text-center py-8 text-sm">Aucun avis</p>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($avisByChapeau[$key] as $a): ?>
                        <div class="avis-card p-3 <?= $chapeau['bg'] ?> rounded-lg border <?= $chapeau['border'] ?> <?= (!$a['is_shared'] && $showAll) ? 'opacity-75' : '' ?>">
                            <?php if (!$a['is_shared'] && $showAll): ?>
                            <div class="mb-2"><span class="bg-orange-200 text-orange-800 text-xs px-2 py-0.5 rounded">Non partage</span></div>
                            <?php endif; ?>
                            <p class="<?= $key === 'noir' ? 'text-gray-200' : 'text-gray-700' ?> text-sm"><?= nl2br(h($a['contenu'])) ?></p>
                            <div class="flex justify-between items-center mt-2 text-xs <?= $key === 'noir' ? 'text-gray-400' : 'text-gray-500' ?>">
                                <span class="font-medium"><?= h($a['user_prenom']) ?> <?= h($a['user_nom']) ?></span>
                                <span><?= date('d/m H:i', strtotime($a['created_at'])) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Vue Liste (chronologique) -->
        <div id="listView" class="hidden space-y-4">
            <?php
            // Trier tous les avis par date
            usort($allAvis, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
            foreach ($allAvis as $a):
                $ch = $chapeaux[$a['chapeau']] ?? $chapeaux['blanc'];
            ?>
            <div class="avis-card bg-white rounded-xl shadow p-4 border-l-4 <?= $ch['border'] ?> avis-item <?= (!$a['is_shared'] && $showAll) ? 'opacity-75' : '' ?>" data-chapeau="<?= $a['chapeau'] ?>">
                <div class="flex items-start gap-4">
                    <span class="text-2xl"><?= $ch['icon'] ?></span>
                    <div class="flex-1">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-bold <?= $ch['text'] ?>"><?= $ch['nom'] ?></span>
                                <span class="text-gray-500 text-sm ml-2">- <?= h($a['user_prenom']) ?> <?= h($a['user_nom']) ?></span>
                                <?php if (!empty($a['user_organisation'])): ?>
                                <span class="text-gray-400 text-xs ml-1">(<?= h($a['user_organisation']) ?>)</span>
                                <?php endif; ?>
                                <?php if (!$a['is_shared'] && $showAll): ?>
                                <span class="bg-orange-200 text-orange-800 text-xs px-2 py-0.5 rounded ml-2">Non partage</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></span>
                        </div>
                        <p class="text-gray-700"><?= nl2br(h($a['contenu'])) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Vue Par Participant -->
        <div id="participantView" class="hidden space-y-6">
            <?php
            // Grouper par participant
            $avisByParticipant = [];
            foreach ($allAvis as $a) {
                $key = $a['user_id'];
                if (!isset($avisByParticipant[$key])) {
                    $avisByParticipant[$key] = [
                        'user' => [
                            'prenom' => $a['user_prenom'],
                            'nom' => $a['user_nom'],
                            'organisation' => $a['user_organisation']
                        ],
                        'avis' => []
                    ];
                }
                $avisByParticipant[$key]['avis'][] = $a;
            }
            foreach ($avisByParticipant as $userId => $data):
            ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden participant-section">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-500 text-white p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold"><?= h($data['user']['prenom']) ?> <?= h($data['user']['nom']) ?></span>
                            <?php if (!empty($data['user']['organisation'])): ?>
                            <span class="text-indigo-200 text-sm ml-2">(<?= h($data['user']['organisation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <span class="bg-white/30 px-2 py-1 rounded text-sm"><?= count($data['avis']) ?> avis</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($data['avis'] as $a):
                            $ch = $chapeaux[$a['chapeau']] ?? $chapeaux['blanc'];
                        ?>
                        <div class="avis-card p-3 <?= $ch['bg'] ?> rounded-lg border <?= $ch['border'] ?> avis-item" data-chapeau="<?= $a['chapeau'] ?>">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-lg"><?= $ch['icon'] ?></span>
                                <span class="font-medium <?= $ch['text'] ?> text-sm"><?= $ch['nom'] ?></span>
                            </div>
                            <p class="<?= $a['chapeau'] === 'noir' ? 'text-gray-200' : 'text-gray-700' ?> text-sm"><?= nl2br(h($a['contenu'])) ?></p>
                            <div class="text-xs <?= $a['chapeau'] === 'noir' ? 'text-gray-400' : 'text-gray-500' ?> mt-2">
                                <?= date('d/m H:i', strtotime($a['created_at'])) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Modal pour la synthese IA -->
    <?php if (isSuperAdmin()): ?>
    <div id="aiModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 no-print">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-6 border-b">
                <div class="flex items-center gap-3">
                    <div class="bg-amber-100 p-2 rounded-lg">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Synthese IA</h3>
                </div>
                <button onclick="closeAIModal()" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="aiContent" class="p-6 overflow-y-auto flex-1">
                <!-- Le contenu de la synthese sera insere ici -->
            </div>
            <div class="p-4 border-t flex justify-end gap-3">
                <button onclick="closeAIModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    Fermer
                </button>
                <button onclick="printAISummary()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Imprimer
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal pour editer le sujet -->
    <div id="sujetModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 no-print">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Modifier le sujet de reflexion</h3>
                <button onclick="closeSujetModal()" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Sujet / Question de reflexion</label>
                <textarea id="sujetInput" rows="4"
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          placeholder="Entrez le sujet ou la question sur laquelle les participants doivent reflechir..."><?= h($session['sujet'] ?? '') ?></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button onclick="closeSujetModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    Annuler
                </button>
                <button onclick="saveSujet()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">
                    Enregistrer
                </button>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>

    <script>
        const sessionId = <?= $sessionId ?>;

        function openSujetModal() {
            document.getElementById('sujetModal').classList.remove('hidden');
            document.getElementById('sujetInput').focus();
        }

        function closeSujetModal() {
            document.getElementById('sujetModal').classList.add('hidden');
        }

        async function saveSujet() {
            const sujet = document.getElementById('sujetInput').value.trim();

            try {
                const response = await fetch('api/update_session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: sessionId,
                        sujet: sujet
                    })
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('sujetDisplay').innerHTML = sujet
                        ? sujet.replace(/\n/g, '<br>')
                        : '<span class="text-gray-400 italic">Aucun sujet defini</span>';
                    closeSujetModal();
                } else {
                    alert('Erreur: ' + (result.error || 'Erreur inconnue'));
                }
            } catch (e) {
                alert('Erreur de connexion');
                console.error(e);
            }
        }

        // Fermer avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSujetModal();
                closeAIModal();
            }
        });

        let currentFilter = 'all';
        let currentDisplay = 'grid';

        function filterByChapeau(chapeau) {
            currentFilter = chapeau;

            // Update filter buttons
            document.querySelectorAll('.chapeau-filter').forEach(btn => {
                btn.classList.remove('active', 'ring-4', 'ring-indigo-500');
            });
            document.getElementById('filter-' + chapeau).classList.add('active', 'ring-4', 'ring-indigo-500');

            applyFilters();
        }

        function setDisplayMode(mode) {
            currentDisplay = mode;

            // Hide all views
            document.getElementById('gridView').classList.add('hidden');
            document.getElementById('listView').classList.add('hidden');
            document.getElementById('participantView').classList.add('hidden');

            // Show selected view
            document.getElementById(mode + 'View').classList.remove('hidden');

            applyFilters();
        }

        function applyFilters() {
            if (currentDisplay === 'grid') {
                // Grid view: show/hide sections
                document.querySelectorAll('.chapeau-section').forEach(section => {
                    if (currentFilter === 'all' || section.dataset.chapeau === currentFilter) {
                        section.style.display = '';
                    } else {
                        section.style.display = 'none';
                    }
                });
            } else if (currentDisplay === 'list') {
                // List view: show/hide items
                document.querySelectorAll('#listView .avis-item').forEach(item => {
                    if (currentFilter === 'all' || item.dataset.chapeau === currentFilter) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            } else if (currentDisplay === 'participant') {
                // Participant view: show/hide items within each participant
                document.querySelectorAll('#participantView .avis-item').forEach(item => {
                    if (currentFilter === 'all' || item.dataset.chapeau === currentFilter) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Hide participant sections with no visible items
                document.querySelectorAll('.participant-section').forEach(section => {
                    const visibleItems = section.querySelectorAll('.avis-item:not([style*="display: none"])');
                    if (currentFilter !== 'all' && visibleItems.length === 0) {
                        section.style.display = 'none';
                    } else {
                        section.style.display = '';
                    }
                });
            }
        }

        <?php if (isSuperAdmin()): ?>
        // ===== Synthese IA =====
        let aiGenerating = false;

        async function generateAISummary() {
            if (aiGenerating) return;
            aiGenerating = true;

            const btn = document.getElementById('aiSummaryBtn');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Generation...';
            btn.disabled = true;
            btn.classList.add('opacity-75');

            // Ouvrir la modal avec un loader
            document.getElementById('aiModal').classList.remove('hidden');
            document.getElementById('aiContent').innerHTML = `
                <div class="flex flex-col items-center justify-center py-16">
                    <svg class="w-12 h-12 text-amber-500 animate-spin mb-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <p class="text-gray-600 font-medium">Analyse des avis en cours...</p>
                    <p class="text-gray-400 text-sm mt-2">Claude analyse les contributions de tous les participants</p>
                </div>
            `;

            try {
                const response = await fetch('api/ai-summary.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_id: sessionId })
                });
                const data = await response.json();

                if (!response.ok || data.error) {
                    throw new Error(data.error || 'Erreur lors de la generation');
                }

                document.getElementById('aiContent').innerHTML = data.summary;
            } catch (e) {
                document.getElementById('aiContent').innerHTML = `
                    <div class="flex flex-col items-center justify-center py-16">
                        <svg class="w-12 h-12 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <p class="text-red-600 font-medium">Erreur</p>
                        <p class="text-gray-500 text-sm mt-2">${e.message}</p>
                    </div>
                `;
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                btn.classList.remove('opacity-75');
                aiGenerating = false;
            }
        }

        function closeAIModal() {
            document.getElementById('aiModal').classList.add('hidden');
        }

        function printAISummary() {
            const content = document.getElementById('aiContent').innerHTML;
            const w = window.open('', '_blank');
            w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Synthese IA - <?= h($session['nom']) ?></title>
                <script src="https://cdn.tailwindcss.com"><\/script>
                <style>body{font-family:system-ui,sans-serif;padding:2rem;}</style></head>
                <body><h1 class="text-2xl font-bold mb-2">Synthese IA - <?= h($session['nom']) ?></h1>
                <p class="text-gray-500 mb-6">Session <?= $session['code'] ?> | Genere le ${new Date().toLocaleDateString('fr-FR')} a ${new Date().toLocaleTimeString('fr-FR')}</p>
                ${content}</body></html>`);
            w.document.close();
            setTimeout(() => { w.print(); }, 500);
        }
        <?php endif; ?>
    </script>
</body>
</html>
