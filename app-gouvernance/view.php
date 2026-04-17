<?php
/**
 * Vue formateur - Détails d'un participant
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-gouvernance';
$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) { die("Participant non trouvé"); }

if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Accès refusé à cette session.");
}

$stmt = $db->prepare("SELECT * FROM evaluations WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$evaluation = $stmt->fetch();

$responses = json_decode($evaluation['responses'] ?? '{}', true) ?: [];
$isSubmitted = ($evaluation['is_submitted'] ?? 0) == 1;

$sections = require __DIR__ . '/sections.php';

function questionValue($q, $responses) {
    if (!isset($responses[$q['id']])) return null;
    $r = $responses[$q['id']];
    if ($q['type'] === 'scale') return (int)$r;
    return $r === 'yes' ? 3 : 1;
}

function computeSectionScore($section, $responses) {
    $count = 0; $sum = 0;
    foreach ($section['subsections'] as $sub) {
        foreach ($sub['questions'] as $q) {
            $v = questionValue($q, $responses);
            if ($v !== null) { $count++; $sum += $v; }
        }
    }
    return ['score' => $count > 0 ? $sum / $count : null, 'count' => $count];
}

function computeOverall($sections, $responses) {
    $count = 0; $sum = 0; $total = 0;
    foreach ($sections as $section) {
        foreach ($section['subsections'] as $sub) {
            foreach ($sub['questions'] as $q) {
                $total++;
                $v = questionValue($q, $responses);
                if ($v !== null) { $count++; $sum += $v; }
            }
        }
    }
    return ['score' => $count > 0 ? $sum / $count : null, 'count' => $count, 'total' => $total];
}

function scoreColor($s) {
    if ($s === null) return 'text-gray-400';
    if ($s >= 2.5) return 'text-green-600';
    if ($s >= 1.5) return 'text-amber-600';
    return 'text-red-600';
}

function formatScore($s) {
    return $s === null ? '—/3.0' : number_format($s, 1) . '/3.0';
}

$overall = computeOverall($sections, $responses);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évaluation de gouvernance - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></span>
                <span class="text-blue-100 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>">
                    <?= $isSubmitted ? 'Soumis' : 'Brouillon' ?>
                </span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4">
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl p-6 mb-6 text-center shadow-lg">
            <h1 class="text-2xl font-bold mb-2">Évaluation de Normes de Gouvernance</h1>
            <div class="text-4xl font-bold mt-3"><?= formatScore($overall['score']) ?></div>
            <p class="text-sm opacity-90 mt-2"><?= $overall['count'] ?>/<?= $overall['total'] ?> question(s) répondue(s)</p>
        </div>

        <!-- Scores par section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <?php foreach ($sections as $section): $s = computeSectionScore($section, $responses); ?>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <h3 class="text-sm text-gray-600 mb-2"><?= sanitize($section['title']) ?></h3>
                <div class="text-2xl font-bold <?= scoreColor($s['score']) ?>"><?= formatScore($s['score']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Détail par section -->
        <?php foreach ($sections as $section): $s = computeSectionScore($section, $responses); ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex justify-between items-center mb-4 pb-3 border-b">
                <h2 class="text-xl font-bold text-gray-800"><?= sanitize($section['title']) ?></h2>
                <span class="text-lg font-bold <?= scoreColor($s['score']) ?>"><?= formatScore($s['score']) ?></span>
            </div>
            <?php foreach ($section['subsections'] as $sub): ?>
            <div class="mb-5">
                <h3 class="font-semibold text-gray-700 mb-3"><?= sanitize($sub['title']) ?></h3>
                <div class="space-y-2">
                    <?php foreach ($sub['questions'] as $q):
                        $r = $responses[$q['id']] ?? null;
                        if ($r === null) {
                            $label = '<em class="text-gray-400">Non répondu</em>';
                            $badgeClass = 'bg-gray-100 text-gray-500';
                        } elseif ($q['type'] === 'scale') {
                            $label = $r . '/3';
                            $badgeClass = $r >= 3 ? 'bg-green-100 text-green-800' : ($r >= 2 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800');
                        } else {
                            $label = $r === 'yes' ? 'Oui' : 'Non';
                            $badgeClass = $r === 'yes' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                        }
                    ?>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded">
                        <div class="flex-1 text-sm text-gray-700"><?= sanitize($q['text']) ?></div>
                        <span class="text-sm px-3 py-1 rounded-full font-medium whitespace-nowrap <?= $badgeClass ?>"><?= $label ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
