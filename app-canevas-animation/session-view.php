<?php
/**
 * Vue globale de session - Canevas d'animation IA
 * Affiche tous les canevas de tous les participants
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-canevas-animation';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) { header('Location: formateur.php'); exit; }

$db = getDB();
$sharedDb = getSharedDB();

if (!canAccessSession($appKey, $sessionId)) {
    die("Accès refusé à cette session.");
}

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) { header('Location: formateur.php'); exit; }

$pointsAttention = getPointsAttention();
$publics = getPublics();
$modalitesEval = getModalitesEval();
$formats = getFormats();

$showAll = isset($_GET['all']) && $_GET['all'] == '1';

if ($showAll) {
    $stmt = $db->prepare("SELECT * FROM canevas WHERE session_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $db->prepare("SELECT * FROM canevas WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
    $stmt->execute([$sessionId]);
}
$rows = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared FROM canevas WHERE session_id = ?");
$stmt->execute([$sessionId]);
$counts = $stmt->fetch();
$totalAll = $counts['total'];
$totalShared = $counts['shared'];

// Agrégations
$participantsData = [];
$pointsCounts = array_fill_keys(array_keys($pointsAttention), 0);
$publicsCounts = array_fill_keys(array_keys($publics), 0);
$formatsCounts = array_fill_keys(array_keys($formats), 0);
$totalSeqRemplies = 0;

foreach ($rows as $r) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$r['user_id']]);
    $u = $userStmt->fetch();
    $d = json_decode($r['data'] ?? '{}', true) ?: [];

    foreach (($d['points_coches'] ?? []) as $p) {
        if (isset($pointsCounts[$p])) $pointsCounts[$p]++;
    }
    if (!empty($d['public']) && isset($publicsCounts[$d['public']])) $publicsCounts[$d['public']]++;
    if (!empty($d['format']) && isset($formatsCounts[$d['format']])) $formatsCounts[$d['format']]++;
    foreach (($d['sequences'] ?? []) as $s) {
        if (!empty(trim($s['objectif'] ?? '')) || !empty(trim($s['activite'] ?? ''))) $totalSeqRemplies++;
    }

    $participantsData[$r['user_id']] = [
        'user' => $u,
        'data' => $d,
        'is_shared' => $r['is_shared'],
        'updated_at' => $r['updated_at'],
    ];
}

arsort($pointsCounts);
$participantsCount = count($rows);
$lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canevas d'animation - Vue session - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .item-card { transition: all 0.3s ease; }
        .item-card:hover { transform: translateY(-2px); }
        .section-number { display: flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: #4f46e5; color: white; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }
        @media print { .no-print { display: none !important; } body { background: white !important; } }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Canevas d'animation IA</h1>
                    <p class="text-indigo-200 text-sm"><?= h($session['nom']) ?> - <?= h($session['code']) ?></p>
                </div>
                <div class="flex items-center gap-4 flex-wrap">
                    <?php if ($showAll): ?>
                    <a href="?id=<?= $sessionId ?>" class="bg-green-500 hover:bg-green-400 px-3 py-1 rounded text-sm">Soumis seulement (<?= $totalShared ?>)</a>
                    <?php else: ?>
                    <a href="?id=<?= $sessionId ?>&all=1" class="bg-orange-500 hover:bg-orange-400 px-3 py-1 rounded text-sm">Voir tous (<?= $totalAll ?>)</a>
                    <?php endif; ?>
                    <?= renderLanguageSelector('bg-indigo-400 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <button onclick="window.print()" class="bg-indigo-400 hover:bg-indigo-300 px-3 py-1 rounded text-sm">Imprimer</button>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-indigo-400 hover:bg-indigo-300 px-3 py-1 rounded text-sm">Retour</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($showAll): ?>
        <div class="bg-orange-100 border border-orange-300 rounded-xl p-4 mb-6">
            <p class="text-orange-800 text-sm"><strong>Mode : Tous les canevas</strong> (<?= $totalAll ?>) — y compris les brouillons.</p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $participantsCount ?></div>
                <div class="text-gray-500 text-sm">Animateur·rices</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= array_sum($pointsCounts) ?></div>
                <div class="text-gray-500 text-sm">Points d'attention cochés</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $totalSeqRemplies ?></div>
                <div class="text-gray-500 text-sm">Séquences remplies</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $totalShared ?>/<?= $totalAll ?></div>
                <div class="text-gray-500 text-sm">Canevas soumis</div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <!-- Points d'attention -->
            <?php if (array_sum($pointsCounts) > 0): ?>
            <div class="bg-white rounded-xl shadow-lg p-5">
                <h3 class="font-bold text-gray-800 mb-3">Points d'attention les plus choisis</h3>
                <div class="space-y-2">
                    <?php
                    $maxPC = max($pointsCounts);
                    foreach ($pointsCounts as $key => $count):
                        if ($count === 0) continue;
                        $pct = $maxPC > 0 ? round(($count / $maxPC) * 100) : 0;
                        $p = $pointsAttention[$key];
                    ?>
                    <div class="flex items-center gap-3">
                        <div class="w-48 text-sm text-gray-700 truncate"><?= $p['icon'] ?> <?= h($p['titre']) ?></div>
                        <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                            <div class="bg-<?= $p['color'] ?>-500 h-full rounded-full" style="width: <?= $pct ?>%"></div>
                        </div>
                        <div class="text-sm font-bold text-gray-700 w-12 text-right"><?= $count ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Publics & formats -->
            <div class="bg-white rounded-xl shadow-lg p-5">
                <h3 class="font-bold text-gray-800 mb-3">Publics et formats</h3>
                <h4 class="text-sm font-semibold text-gray-600 mb-2">Publics visés</h4>
                <div class="space-y-1 mb-4">
                    <?php foreach ($publicsCounts as $key => $count): if ($count === 0) continue; ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-700"><?= h($publics[$key]) ?></span>
                        <span class="font-bold text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <h4 class="text-sm font-semibold text-gray-600 mb-2">Formats choisis</h4>
                <div class="space-y-1">
                    <?php foreach ($formatsCounts as $key => $count): if ($count === 0) continue; ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-700"><?= h($formats[$key]) ?></span>
                        <span class="font-bold text-purple-700 bg-purple-100 px-2 py-0.5 rounded"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Filtres d'affichage -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 no-print">
            <div class="flex flex-wrap gap-4 items-center">
                <div class="font-medium text-gray-700">Affichage :</div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="summary" checked onchange="setDisplayMode('summary')">
                    <span class="text-sm">Synthèse comparative</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="objectifs" onchange="setDisplayMode('objectifs')">
                    <span class="text-sm">Objectifs & fils rouges</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="display" value="detail" onchange="setDisplayMode('detail')">
                    <span class="text-sm">Par animateur (détail)</span>
                </label>
            </div>
        </div>

        <!-- Vue synthèse -->
        <div id="summaryView">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Synthèse comparative des canevas</h2>
            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">Aucun canevas trouvé.</div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($participantsData as $uid => $info):
                    $d = $info['data'];
                    $u = $info['user'];
                    $nbPts = count($d['points_coches'] ?? []);
                    $nbSeq = count(array_filter($d['sequences'] ?? [], fn($s) => !empty($s['objectif']) || !empty($s['activite'])));
                ?>
                <div class="item-card bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-500 text-white p-3">
                        <div class="flex justify-between items-center flex-wrap gap-2">
                            <div>
                                <span class="font-bold"><?= h($u['prenom'] ?? '') ?> <?= h($u['nom'] ?? '') ?></span>
                                <?php if (!empty($d['classe_groupe'])): ?>
                                <span class="text-indigo-200 text-sm ml-2">· <?= h($d['classe_groupe']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2 flex-wrap">
                                <span class="bg-white/20 px-2 py-1 rounded text-xs"><?= h($formats[$d['format'] ?? '90'] ?? '90 min') ?></span>
                                <span class="bg-white/20 px-2 py-1 rounded text-xs"><?= $nbSeq ?> séquences</span>
                                <span class="bg-white/20 px-2 py-1 rounded text-xs"><?= $nbPts ?>/7 points</span>
                                <span class="px-2 py-1 rounded text-xs <?= $info['is_shared'] ? 'bg-green-500/50' : 'bg-yellow-500/50' ?>"><?= $info['is_shared'] ? 'Soumis' : 'Brouillon' ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="grid md:grid-cols-2 gap-3">
                            <div>
                                <div class="text-xs font-semibold text-gray-500 mb-1">Public visé</div>
                                <p class="text-sm text-gray-700"><?= h($publics[$d['public'] ?? ''] ?? 'Non précisé') ?></p>
                            </div>
                            <div>
                                <div class="text-xs font-semibold text-gray-500 mb-1">Objectif principal</div>
                                <p class="text-sm text-gray-700"><?= h(mb_strimwidth($d['objectif_principal'] ?? '', 0, 180, '...')) ?: '<em class="text-gray-400">-</em>' ?></p>
                            </div>
                        </div>
                        <?php if (!empty(trim($d['fil_rouge'] ?? ''))): ?>
                        <div class="bg-gradient-to-r from-indigo-50 to-violet-50 mt-3 p-2 rounded border border-indigo-200">
                            <p class="text-sm italic text-indigo-900">&laquo; <?= h($d['fil_rouge']) ?> &raquo;</p>
                        </div>
                        <?php endif; ?>
                        <?php if ($nbPts > 0): ?>
                        <div class="flex flex-wrap gap-1 mt-3">
                            <?php foreach (($d['points_coches'] ?? []) as $pk):
                                $p = $pointsAttention[$pk] ?? null;
                                if (!$p) continue;
                            ?>
                            <span class="text-xs bg-<?= $p['color'] ?>-50 text-<?= $p['color'] ?>-700 px-2 py-0.5 rounded border border-<?= $p['color'] ?>-200"><?= $p['icon'] ?> <?= h($p['titre']) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue objectifs/fils rouges -->
        <div id="objectifsView" class="hidden">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Objectifs et fils rouges</h2>
            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">Aucun canevas trouvé.</div>
            <?php else: ?>
            <div class="grid md:grid-cols-2 gap-4">
                <?php foreach ($participantsData as $uid => $info):
                    $d = $info['data']; $u = $info['user'];
                ?>
                <div class="item-card bg-white rounded-xl shadow-lg p-5 border-l-4 border-indigo-500">
                    <?php if (!empty(trim($d['fil_rouge'] ?? ''))): ?>
                    <div class="bg-gradient-to-r from-indigo-50 to-violet-50 p-3 rounded-lg border-2 border-indigo-200 mb-3">
                        <p class="text-sm italic text-indigo-900 text-center">&laquo; <?= h($d['fil_rouge']) ?> &raquo;</p>
                    </div>
                    <?php endif; ?>
                    <div class="text-sm mb-2"><strong class="text-gray-600">Objectif principal :</strong> <?= h(mb_strimwidth($d['objectif_principal'] ?? '', 0, 200, '...')) ?: '<em class="text-gray-400">-</em>' ?></div>
                    <?php if (!empty(trim($d['objectif_sec_1'] ?? '')) || !empty(trim($d['objectif_sec_2'] ?? ''))): ?>
                    <div class="text-sm text-gray-600 mb-2">
                        <strong>Objectifs secondaires :</strong>
                        <ul class="list-disc list-inside text-xs mt-1">
                            <?php if (!empty(trim($d['objectif_sec_1'] ?? ''))): ?><li><?= h($d['objectif_sec_1']) ?></li><?php endif; ?>
                            <?php if (!empty(trim($d['objectif_sec_2'] ?? ''))): ?><li><?= h($d['objectif_sec_2']) ?></li><?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between items-center text-xs text-gray-400 mt-3 pt-2 border-t">
                        <span class="font-medium"><?= h($u['prenom'] ?? '') ?> <?= h($u['nom'] ?? '') ?></span>
                        <?php if (!empty($d['classe_groupe'])): ?>
                        <span class="italic"><?= h($d['classe_groupe']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vue détail par animateur -->
        <div id="detailView" class="hidden space-y-6">
            <?php foreach ($participantsData as $uid => $info):
                $d = $info['data']; $u = $info['user'];
                $sequences = $d['sequences'] ?? [];
            ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-500 text-white p-4">
                    <div class="flex justify-between items-center flex-wrap gap-2">
                        <div>
                            <span class="font-bold text-lg"><?= h($u['prenom'] ?? '') ?> <?= h($u['nom'] ?? '') ?></span>
                            <?php if (!empty($d['classe_groupe'])): ?>
                            <span class="block text-indigo-100 text-sm">Classe : <?= h($d['classe_groupe']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="px-2 py-1 rounded text-sm <?= $info['is_shared'] ? 'bg-green-500/50' : 'bg-yellow-500/50' ?>"><?= $info['is_shared'] ? 'Soumis' : 'Brouillon' ?></span>
                    </div>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid md:grid-cols-3 gap-3 text-sm">
                        <div><strong class="text-gray-600">Animateur :</strong> <?= h($d['animateur'] ?? '') ?></div>
                        <div><strong class="text-gray-600">Date / lieu :</strong> <?= h($d['date_lieu'] ?? '') ?></div>
                        <div><strong class="text-gray-600">Format :</strong> <?= h($formats[$d['format'] ?? '90'] ?? '') ?></div>
                    </div>

                    <?php if (!empty(trim($d['objectif_principal'] ?? ''))): ?>
                    <div class="bg-indigo-50 p-3 rounded border border-indigo-200">
                        <strong class="text-indigo-800 text-sm">Objectif principal :</strong>
                        <p class="text-sm text-gray-700 mt-1"><?= nl2br(h($d['objectif_principal'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($sequences)): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 text-sm mb-2">Séquençage</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead><tr class="bg-gray-50"><th class="p-2 text-left">Min</th><th class="p-2 text-left">Objectif</th><th class="p-2 text-left">Activité</th><th class="p-2 text-left">Animation</th></tr></thead>
                                <tbody>
                                    <?php foreach ($sequences as $s):
                                        if (empty($s['min']) && empty($s['objectif']) && empty($s['activite'])) continue;
                                    ?>
                                    <tr class="border-t align-top">
                                        <td class="p-2 font-mono text-indigo-700 font-semibold"><?= h($s['min'] ?? '') ?></td>
                                        <td class="p-2 text-gray-700"><?= h($s['objectif'] ?? '') ?></td>
                                        <td class="p-2 text-gray-700"><?= h($s['activite'] ?? '') ?></td>
                                        <td class="p-2 text-gray-700"><?= h($s['animation'] ?? '') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php $pCoches = $d['points_coches'] ?? []; ?>
                    <?php if (!empty($pCoches)): ?>
                    <div>
                        <h4 class="font-bold text-gray-700 text-sm mb-2">Points d'attention (<?= count($pCoches) ?>/7)</h4>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($pCoches as $pk):
                                $p = $pointsAttention[$pk] ?? null;
                                if (!$p) continue;
                            ?>
                            <span class="text-xs bg-<?= $p['color'] ?>-50 text-<?= $p['color'] ?>-700 px-2 py-1 rounded border border-<?= $p['color'] ?>-200"><?= $p['icon'] ?> <?= h($p['titre']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid md:grid-cols-2 gap-3 text-sm">
                        <?php if (!empty(trim($d['outil_manipule_1'] ?? ''))): ?>
                        <div class="bg-emerald-50 p-2 rounded">
                            <strong class="text-emerald-800 text-xs">Outil manipulé :</strong>
                            <p class="text-gray-700 text-xs mt-1"><?= h($d['outil_manipule_1']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty(trim($d['outil_projete_1'] ?? ''))): ?>
                        <div class="bg-blue-50 p-2 rounded">
                            <strong class="text-blue-800 text-xs">Outil projeté :</strong>
                            <p class="text-gray-700 text-xs mt-1"><?= h($d['outil_projete_1']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($participantsData)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">Aucun canevas trouvé.</div>
            <?php endif; ?>
        </div>
    </main>

    <?= renderLanguageScript() ?>

    <script>
        function setDisplayMode(mode) {
            ['summaryView', 'objectifsView', 'detailView'].forEach(id => document.getElementById(id).classList.add('hidden'));
            document.getElementById(mode + 'View').classList.remove('hidden');
        }
    </script>
</body>
</html>
