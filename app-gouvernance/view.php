<?php
require_once __DIR__ . '/config.php';
if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-gouvernance';
$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id
    FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) die('Participant non trouvé');
if (!canAccessSession($appKey, $participant['session_id'])) die('Accès refusé');

$stmt = $db->prepare("SELECT * FROM evaluations WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$eval = $stmt->fetch();
$responses = json_decode($eval['responses'] ?? '{}', true) ?: [];
$isSubmitted = ($eval['is_submitted'] ?? 0) == 1;

$scaleLevels = getScaleLevels();
$maxLevel = count($scaleLevels) ? max(array_map(fn($l) => (int)$l['niveau'], $scaleLevels)) : 4;
$na = getNaSettings();
$domains = getAllDomains();
$overall = computeOverallScore($domains, $responses, $maxLevel);

function scoreColor($s, $max) {
    if ($s === null) return 'text-gray-400';
    $frac = $s / $max;
    if ($frac >= 0.80) return 'text-green-600';
    if ($frac >= 0.55) return 'text-amber-600';
    return 'text-red-600';
}
function fmt($s, $max) { return $s === null ? '—/' . number_format($max, 1) : number_format($s, 1) . '/' . number_format($max, 1); }

$scaleByLevel = [];
foreach ($scaleLevels as $l) $scaleByLevel[(int)$l['niveau']] = $l;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évaluation — <?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-3 shadow-lg sticky top-0 z-50 no-print">
        <div class="max-w-5xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= h($participant['prenom']) ?> <?= h($participant['nom']) ?></span>
                <span class="text-blue-200 text-sm ml-2"><?= h($participant['session_nom']) ?> (<?= h($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4">
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl p-6 mb-6 text-center shadow">
            <h1 class="text-2xl font-bold mb-2"><?= h(getConfig('app_title', APP_NAME)) ?></h1>
            <div class="text-4xl font-bold mt-3"><?= fmt($overall['score'], $maxLevel) ?></div>
            <p class="text-sm opacity-90 mt-2"><?= $overall['count'] ?>/<?= $overall['total'] ?> question(s) répondue(s)</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <?php foreach ($domains as $d): $s = computeDomainScore($d, $responses, $maxLevel); ?>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <h3 class="text-sm text-gray-600 mb-2"><?= h($d['titre']) ?></h3>
                <div class="text-2xl font-bold <?= scoreColor($s['score'], $maxLevel) ?>"><?= fmt($s['score'], $maxLevel) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($domains as $d): $s = computeDomainScore($d, $responses, $maxLevel); ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex justify-between items-center mb-4 pb-3 border-b">
                <h2 class="text-xl font-bold text-gray-800"><?= h($d['titre']) ?></h2>
                <span class="text-lg font-bold <?= scoreColor($s['score'], $maxLevel) ?>"><?= fmt($s['score'], $maxLevel) ?></span>
            </div>
            <?php if (!empty($d['description'])): ?><p class="text-gray-600 text-sm mb-4 italic"><?= h($d['description']) ?></p><?php endif; ?>
            <div class="space-y-3">
                <?php foreach ($d['questions'] as $q):
                    $r = $responses[$q['slug']] ?? null;
                    if ($r === null) { $label = '<em class="text-gray-400">Non répondu</em>'; $badge = 'bg-gray-100 text-gray-500'; $anchor = null; }
                    elseif ($r === 'na') { $label = 'N/A'; $badge = 'bg-gray-200 text-gray-700'; $anchor = null; }
                    else {
                        $lvl = $scaleByLevel[(int)$r] ?? null;
                        $label = (int)$r . ' — ' . h($lvl['label'] ?? '');
                        $frac = (int)$r / $maxLevel;
                        $badge = $frac >= 0.80 ? 'bg-green-100 text-green-800' : ($frac >= 0.55 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800');
                        $anchor = $q['ancrages'][(int)$r] ?? null;
                    }
                ?>
                <div class="bg-gray-50 p-3 rounded">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-800 text-sm"><?= h($q['intitule']) ?></div>
                            <p class="text-gray-700 text-sm mt-1"><?= h($q['texte']) ?></p>
                            <?php if (!empty($q['aide'])): ?><details class="mt-2"><summary class="text-xs text-sky-700 cursor-pointer">ℹ️ Aide</summary><p class="text-xs text-sky-900 mt-1 bg-sky-50 p-2 rounded"><?= h($q['aide']) ?></p></details><?php endif; ?>
                            <?php if ($anchor): ?><div class="text-xs text-gray-700 mt-2 border-l-2 border-gray-300 pl-2 italic"><?= h($anchor) ?></div><?php endif; ?>
                        </div>
                        <span class="text-sm px-3 py-1 rounded-full font-medium whitespace-nowrap <?= $badge ?>"><?= $label ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
