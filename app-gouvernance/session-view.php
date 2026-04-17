<?php
/**
 * Vue formateur - Tableau agrégé d'une session
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-gouvernance';
$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) { header('Location: formateur.php'); exit; }

if (!canAccessSession($appKey, $sessionId)) {
    die("Accès refusé à cette session.");
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) { die("Session introuvable."); }

$stmt = $db->prepare("SELECT p.*, e.responses, e.is_submitted, e.updated_at FROM participants p LEFT JOIN evaluations e ON e.user_id = p.user_id AND e.session_id = p.session_id WHERE p.session_id = ? ORDER BY p.nom, p.prenom");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();

$sections = require __DIR__ . '/sections.php';

function questionValue($q, $responses) {
    if (!isset($responses[$q['id']])) return null;
    $r = $responses[$q['id']];
    if ($q['type'] === 'scale') return (int)$r;
    return $r === 'yes' ? 3 : 1;
}

function sectionScore($section, $responses) {
    $count = 0; $sum = 0;
    foreach ($section['subsections'] as $sub) {
        foreach ($sub['questions'] as $q) {
            $v = questionValue($q, $responses);
            if ($v !== null) { $count++; $sum += $v; }
        }
    }
    return $count > 0 ? $sum / $count : null;
}

function overallScore($sections, $responses) {
    $count = 0; $sum = 0;
    foreach ($sections as $section) {
        foreach ($section['subsections'] as $sub) {
            foreach ($sub['questions'] as $q) {
                $v = questionValue($q, $responses);
                if ($v !== null) { $count++; $sum += $v; }
            }
        }
    }
    return $count > 0 ? $sum / $count : null;
}

function scoreColor($s) {
    if ($s === null) return 'text-gray-400';
    if ($s >= 2.5) return 'text-green-600';
    if ($s >= 1.5) return 'text-amber-600';
    return 'text-red-600';
}

function formatScore($s) {
    return $s === null ? '—' : number_format($s, 1);
}

// Calcul des moyennes de la session
$sessionSectionAverages = [];
foreach ($sections as $section) {
    $vals = [];
    foreach ($participants as $p) {
        $r = json_decode($p['responses'] ?? '{}', true) ?: [];
        $s = sectionScore($section, $r);
        if ($s !== null) $vals[] = $s;
    }
    $sessionSectionAverages[$section['id']] = count($vals) > 0 ? array_sum($vals) / count($vals) : null;
}

$overallValues = [];
$submittedCount = 0;
foreach ($participants as $p) {
    $r = json_decode($p['responses'] ?? '{}', true) ?: [];
    $s = overallScore($sections, $r);
    if ($s !== null) $overallValues[] = $s;
    if ($p['is_submitted']) $submittedCount++;
}
$sessionOverall = count($overallValues) > 0 ? array_sum($overallValues) / count($overallValues) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Synthèse session - <?= sanitize($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium">Synthèse de session</span>
                <span class="text-blue-100 text-sm ml-2"><?= sanitize($session['nom']) ?> (<?= sanitize($session['code']) ?>)</span>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $sessionId ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl p-6 mb-6 text-center shadow-lg">
            <h1 class="text-2xl font-bold mb-2">Score moyen de la session</h1>
            <div class="text-4xl font-bold mt-3"><?= formatScore($sessionOverall) ?>/3.0</div>
            <p class="text-sm opacity-90 mt-2">
                <?= count($participants) ?> participant(s) · <?= $submittedCount ?> soumis
            </p>
        </div>

        <!-- Moyennes par section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <?php foreach ($sections as $section): $avg = $sessionSectionAverages[$section['id']]; ?>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <h3 class="text-sm text-gray-600 mb-2"><?= sanitize($section['title']) ?></h3>
                <div class="text-2xl font-bold <?= scoreColor($avg) ?>"><?= formatScore($avg) ?>/3.0</div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tableau des participants -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="font-bold text-gray-800">Détail des participants</h2>
            </div>
            <?php if (empty($participants)): ?>
            <div class="p-8 text-center text-gray-500">Aucun participant dans cette session.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-700">Participant</th>
                            <th class="text-center p-3 font-medium text-gray-700">Statut</th>
                            <?php foreach ($sections as $section): ?>
                            <th class="text-center p-3 font-medium text-gray-700 text-xs" title="<?= sanitize($section['title']) ?>">
                                <?= sanitize(mb_substr($section['title'], 0, 20)) ?><?= mb_strlen($section['title']) > 20 ? '…' : '' ?>
                            </th>
                            <?php endforeach; ?>
                            <th class="text-center p-3 font-medium text-gray-700">Global</th>
                            <th class="text-center p-3 font-medium text-gray-700 no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $p):
                            $r = json_decode($p['responses'] ?? '{}', true) ?: [];
                            $overall = overallScore($sections, $r);
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-medium text-gray-800"><?= sanitize($p['prenom']) ?> <?= sanitize($p['nom']) ?></td>
                            <td class="p-3 text-center">
                                <?php if ($p['is_submitted']): ?>
                                <span class="inline-block px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Soumis</span>
                                <?php elseif (!empty($r)): ?>
                                <span class="inline-block px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Brouillon</span>
                                <?php else: ?>
                                <span class="inline-block px-2 py-1 bg-gray-100 text-gray-500 rounded text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($sections as $section):
                                $s = sectionScore($section, $r);
                            ?>
                            <td class="p-3 text-center font-semibold <?= scoreColor($s) ?>"><?= formatScore($s) ?></td>
                            <?php endforeach; ?>
                            <td class="p-3 text-center font-bold <?= scoreColor($overall) ?>"><?= formatScore($overall) ?></td>
                            <td class="p-3 text-center no-print">
                                <a href="view.php?id=<?= $p['id'] ?>" class="text-blue-600 hover:text-blue-800 text-xs">Voir</a>
                            </td>
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
