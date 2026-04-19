<?php
require_once __DIR__ . '/config.php';
if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-gouvernance';
$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) { header('Location: formateur.php'); exit; }
if (!canAccessSession($appKey, $sessionId)) die('Accès refusé');

$db = getDB();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) die('Session introuvable');

$stmt = $db->prepare("SELECT p.*, e.responses, e.is_submitted, e.updated_at
    FROM participants p LEFT JOIN evaluations e ON e.user_id = p.user_id AND e.session_id = p.session_id
    WHERE p.session_id = ? ORDER BY p.nom, p.prenom");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();

$domains = getAllDomains();
$scaleLevels = getScaleLevels();
$maxLevel = count($scaleLevels) ? max(array_map(fn($l) => (int)$l['niveau'], $scaleLevels)) : 4;

$domainAvg = [];
foreach ($domains as $d) {
    $vals = [];
    foreach ($participants as $p) {
        $r = json_decode($p['responses'] ?? '{}', true) ?: [];
        $s = computeDomainScore($d, $r, $maxLevel);
        if ($s['score'] !== null) $vals[] = $s['score'];
    }
    $domainAvg[$d['slug']] = count($vals) ? array_sum($vals) / count($vals) : null;
}

$overallValues = [];
$submittedCount = 0;
foreach ($participants as $p) {
    $r = json_decode($p['responses'] ?? '{}', true) ?: [];
    $s = computeOverallScore($domains, $r, $maxLevel);
    if ($s['score'] !== null) $overallValues[] = $s['score'];
    if ($p['is_submitted']) $submittedCount++;
}
$sessionOverall = count($overallValues) ? array_sum($overallValues) / count($overallValues) : null;

function scoreColor($s, $max) {
    if ($s === null) return 'text-gray-400';
    $f = $s / $max;
    if ($f >= 0.80) return 'text-green-600';
    if ($f >= 0.55) return 'text-amber-600';
    return 'text-red-600';
}
function fmt($s) { return $s === null ? '—' : number_format($s, 1); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Synthèse — <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-3 shadow-lg sticky top-0 z-50 no-print">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium">Synthèse de session</span>
                <span class="text-blue-200 text-sm ml-2"><?= h($session['nom']) ?> (<?= h($session['code']) ?>)</span>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $sessionId ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl p-6 mb-6 text-center shadow">
            <h1 class="text-2xl font-bold mb-2">Score moyen de la session</h1>
            <div class="text-4xl font-bold mt-3"><?= fmt($sessionOverall) ?>/<?= number_format($maxLevel, 1) ?></div>
            <p class="text-sm opacity-90 mt-2"><?= count($participants) ?> participant(s) · <?= $submittedCount ?> soumis</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <?php foreach ($domains as $d): $avg = $domainAvg[$d['slug']]; ?>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <h3 class="text-sm text-gray-600 mb-2"><?= h($d['titre']) ?></h3>
                <div class="text-2xl font-bold <?= scoreColor($avg, $maxLevel) ?>"><?= fmt($avg) ?>/<?= number_format($maxLevel, 1) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-4 border-b bg-gray-50"><h2 class="font-bold text-gray-800">Détail par participant</h2></div>
            <?php if (empty($participants)): ?>
            <div class="p-8 text-center text-gray-500">Aucun participant.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-700">Participant</th>
                            <th class="text-center p-3 font-medium text-gray-700">Statut</th>
                            <?php foreach ($domains as $d): ?>
                            <th class="text-center p-3 font-medium text-gray-700 text-xs" title="<?= h($d['titre']) ?>"><?= h(mb_substr($d['titre'], 0, 18)) ?><?= mb_strlen($d['titre']) > 18 ? '…' : '' ?></th>
                            <?php endforeach; ?>
                            <th class="text-center p-3 font-medium text-gray-700">Global</th>
                            <th class="text-center p-3 font-medium text-gray-700 no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $p):
                            $r = json_decode($p['responses'] ?? '{}', true) ?: [];
                            $o = computeOverallScore($domains, $r, $maxLevel);
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-medium text-gray-800"><?= h($p['prenom']) ?> <?= h($p['nom']) ?></td>
                            <td class="p-3 text-center">
                                <?php if ($p['is_submitted']): ?><span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Soumis</span>
                                <?php elseif (!empty($r)): ?><span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Brouillon</span>
                                <?php else: ?><span class="px-2 py-1 bg-gray-100 text-gray-500 rounded text-xs">—</span><?php endif; ?>
                            </td>
                            <?php foreach ($domains as $d): $ds = computeDomainScore($d, $r, $maxLevel); ?>
                            <td class="p-3 text-center font-semibold <?= scoreColor($ds['score'], $maxLevel) ?>"><?= fmt($ds['score']) ?></td>
                            <?php endforeach; ?>
                            <td class="p-3 text-center font-bold <?= scoreColor($o['score'], $maxLevel) ?>"><?= fmt($o['score']) ?></td>
                            <td class="p-3 text-center no-print"><a href="view.php?id=<?= $p['id'] ?>" class="text-blue-600 hover:text-blue-800 text-xs">Voir</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
