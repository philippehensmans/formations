<?php
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-objectifs-smart';

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$sharedDb = getSharedDB();

$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) { die("Participant non trouve"); }

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Acces refuse a cette session.");
}

// Enrichir avec les infos utilisateur partagees
$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();
$participant['prenom'] = $userInfo['prenom'] ?? ($participant['prenom'] ?? '');
$participant['nom'] = $userInfo['nom'] ?? ($participant['nom'] ?? '');
$participant['organisation'] = $userInfo['organisation'] ?? '';

$stmt = $db->prepare("SELECT * FROM objectifs_smart WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$data = $stmt->fetch() ?: ['etape1_analyses' => '[]', 'etape2_reformulations' => '[]', 'etape3_creations' => '[]', 'etape_courante' => 1, 'completion_percent' => 0, 'is_submitted' => 0];

$etape1 = json_decode($data['etape1_analyses'] ?? '[]', true) ?: [];
$etape2 = json_decode($data['etape2_reformulations'] ?? '[]', true) ?: [];
$etape3 = json_decode($data['etape3_creations'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objectifs SMART - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; }}</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-cyan-600 to-blue-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></span>
                <?php if (!empty($participant['organisation'])): ?>
                <span class="text-cyan-200 text-sm ml-2">(<?= sanitize($participant['organisation']) ?>)</span>
                <?php endif; ?>
                <span class="text-cyan-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm px-3 py-1 rounded-full <?= !empty($data['is_submitted']) ? 'bg-green-500' : 'bg-yellow-500' ?>">
                    <?= !empty($data['is_submitted']) ? 'Soumis' : 'Brouillon' ?>
                </span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>
    <div class="max-w-7xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Objectifs SMART</h1>
            <p class="text-sm text-gray-600">
                Etape courante: <?= (int)($data['etape_courante'] ?? 1) ?>/3 -
                Completion: <?= (int)($data['completion_percent'] ?? 0) ?>%
            </p>
        </div>

        <?php if (!empty($etape1)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-cyan-700 mb-4">Etape 1 : Analyse d'objectifs</h2>
            <?php foreach ($etape1 as $idx => $analyse):
                $evaluations = is_array($analyse) ? ($analyse['evaluations'] ?? []) : [];
                $score = 0;
                foreach (['S','M','A','R','T'] as $L) {
                    $ev = $evaluations[$L] ?? [];
                    if (is_array($ev) && ($ev['reponse'] ?? '') === 'oui') $score++;
                }
                $texte = is_array($analyse) ? ($analyse['texte'] ?? '') : '';
            ?>
            <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-bold text-sm">Objectif #<?= $idx + 1 ?></h3>
                    <div class="text-sm font-bold <?= $score >= 4 ? 'text-green-600' : ($score >= 2 ? 'text-yellow-600' : 'text-red-600') ?>">
                        Score : <?= $score ?>/5
                    </div>
                </div>
                <?php if ($texte !== ''): ?>
                <p class="italic text-gray-700 mb-3">"<?= sanitize($texte) ?>"</p>
                <?php endif; ?>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-2 text-xs">
                    <?php foreach (['S','M','A','R','T'] as $L):
                        $ev = is_array($evaluations[$L] ?? null) ? $evaluations[$L] : [];
                        $rep = $ev['reponse'] ?? '';
                        $just = $ev['justification'] ?? '';
                        $color = $rep === 'oui' ? 'text-green-600' : ($rep === 'non' ? 'text-red-600' : 'text-orange-600');
                    ?>
                    <div class="bg-white p-2 rounded border">
                        <div><span class="font-bold"><?= $L ?>:</span> <span class="<?= $color ?>"><?= sanitize($rep ?: '—') ?></span></div>
                        <?php if ($just !== ''): ?>
                        <div class="text-gray-500 mt-1"><?= sanitize($just) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($etape2)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-blue-700 mb-4">Etape 2 : Reformulation d'objectifs</h2>
            <?php foreach ($etape2 as $idx => $reform):
                $composantes = is_array($reform) ? ($reform['composantes'] ?? []) : [];
                $final = is_array($reform) ? ($reform['objectif_final'] ?? ($reform['reformulation'] ?? '')) : (string)$reform;
                $texteOrig = is_array($reform) ? ($reform['texte'] ?? '') : '';
            ?>
            <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                <h3 class="font-bold text-sm mb-2">Reformulation #<?= $idx + 1 ?></h3>
                <?php if ($texteOrig !== ''): ?>
                <p class="italic text-gray-600 mb-2">Depart : "<?= sanitize($texteOrig) ?>"</p>
                <?php endif; ?>
                <?php if (!empty($composantes)): ?>
                <div class="space-y-1 text-sm mb-2">
                    <?php foreach (['S','M','A','R','T'] as $L): if (!empty($composantes[$L])): ?>
                    <div><span class="font-bold text-blue-700"><?= $L ?>:</span> <?= sanitize($composantes[$L]) ?></div>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($final !== ''): ?>
                <p class="text-sm bg-white border border-blue-200 rounded p-2"><strong>SMART :</strong> <?= sanitize($final) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($etape3)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-green-700 mb-4">Etape 3 : Creation d'objectifs SMART</h2>
            <?php foreach ($etape3 as $idx => $obj):
                $composantes = is_array($obj) ? ($obj['composantes'] ?? []) : [];
                $final = is_array($obj) ? ($obj['objectif_final'] ?? ($obj['objectif'] ?? '')) : (string)$obj;
                $contexte = is_array($obj) ? ($obj['contexte'] ?? '') : '';
                $thematique = is_array($obj) ? ($obj['thematique'] ?? '') : '';
            ?>
            <div class="mb-4 p-4 bg-green-50 rounded-lg">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-bold text-sm">Objectif #<?= $idx + 1 ?></h3>
                    <?php if ($contexte !== '' || $thematique !== ''): ?>
                    <div class="text-xs uppercase tracking-wide text-green-700 font-semibold">
                        <?= sanitize($contexte) ?><?= ($contexte !== '' && $thematique !== '') ? ' - ' : '' ?><?= sanitize($thematique) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($composantes)): ?>
                <div class="space-y-1 text-sm mb-2">
                    <?php foreach (['S','M','A','R','T'] as $L): if (!empty($composantes[$L])): ?>
                    <div><span class="font-bold text-green-700"><?= $L ?>:</span> <?= sanitize($composantes[$L]) ?></div>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($final !== ''): ?>
                <p class="text-sm bg-white border border-green-200 rounded p-2"><strong>SMART :</strong> <?= sanitize($final) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($etape1) && empty($etape2) && empty($etape3)): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <p class="text-gray-400">Aucune donnee pour ce participant.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
