<?php
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: index.php'); exit; }

$appKey = 'app-pilotage-projet';
$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) { die("Participant non trouve"); }

if (!canAccessSession($appKey, $participant['session_id'])) { die("Acces refuse."); }

$sharedDb = getSharedDB();
$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userData = $userStmt->fetch();
$pPrenom = $userData['prenom'] ?? $participant['prenom'] ?? 'Participant';
$pNom = $userData['nom'] ?? $participant['nom'] ?? '';

$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$analyse = $stmt->fetch();

$objectifs = json_decode($analyse['objectifs_data'] ?? '[]', true) ?: [];
$phases = json_decode($analyse['phases_data'] ?? '[]', true) ?: [];
$checkpoints = json_decode($analyse['checkpoints_data'] ?? '[]', true) ?: [];
$lessons = json_decode($analyse['lessons_data'] ?? '[]', true) ?: [];
$taskStatuses = getTaskStatuses();
$checkpointTypes = getCheckpointTypes();
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;

$totalTasks = 0; $doneTasks = 0;
foreach ($phases as $p) {
    foreach ($p['taches'] ?? [] as $t) {
        if (!empty(trim($t['titre'] ?? ''))) { $totalTasks++; if (($t['statut'] ?? '') === 'done') $doneTasks++; }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilotage Projet - <?= sanitize($pPrenom) ?> <?= sanitize($pNom) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
        .section-number { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: #059669; color: white; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($pPrenom) ?> <?= sanitize($pNom) ?></span>
                <span class="text-emerald-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto p-4">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Pilotage de Projet</h1>
            <div class="text-xl font-semibold text-emerald-700 mb-3"><?= sanitize($analyse['nom_projet'] ?? '') ?: '<em class="text-gray-400">Projet sans nom</em>' ?></div>
            <?php if (!empty(trim($analyse['description_projet'] ?? ''))): ?>
            <p class="text-gray-600 mb-3"><?= nl2br(sanitize($analyse['description_projet'])) ?></p>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?= count($objectifs) ?></div>
                <div class="text-sm text-gray-500">Objectifs</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= count($phases) ?></div>
                <div class="text-sm text-gray-500">Phases</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $totalTasks ?></div>
                <div class="text-sm text-gray-500">Taches</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $doneTasks ?></div>
                <div class="text-sm text-gray-500">Terminees</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-amber-600"><?= count($checkpoints) ?></div>
                <div class="text-sm text-gray-500">Controles</div>
            </div>
        </div>

        <!-- Contexte / Contraintes -->
        <?php if (!empty(trim($analyse['contexte'] ?? '')) || !empty(trim($analyse['contraintes'] ?? ''))): ?>
        <div class="grid md:grid-cols-2 gap-4 mb-6">
            <?php if (!empty(trim($analyse['contexte'] ?? ''))): ?>
            <div class="bg-blue-50 rounded-xl shadow p-5 border border-blue-200">
                <h3 class="font-bold text-blue-800 text-sm mb-2">Contexte</h3>
                <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['contexte']) ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty(trim($analyse['contraintes'] ?? ''))): ?>
            <div class="bg-amber-50 rounded-xl shadow p-5 border border-amber-200">
                <h3 class="font-bold text-amber-800 text-sm mb-2">Contraintes</h3>
                <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['contraintes']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Objectifs -->
        <?php if (!empty($objectifs)): ?>
        <h2 class="text-lg font-bold text-gray-800 mb-3">Objectifs</h2>
        <div class="space-y-2 mb-6">
            <?php foreach ($objectifs as $i => $o): if (empty(trim($o['titre'] ?? ''))) continue; ?>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
                <div class="flex items-center gap-2">
                    <div class="section-number"><?= $i + 1 ?></div>
                    <span class="font-bold text-gray-800 text-sm"><?= sanitize($o['titre']) ?></span>
                </div>
                <?php if (!empty(trim($o['criteres'] ?? ''))): ?>
                <p class="text-xs text-gray-500 mt-1 ml-9">Criteres: <?= sanitize($o['criteres']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Phases & Taches -->
        <?php if (!empty($phases)): ?>
        <h2 class="text-lg font-bold text-gray-800 mb-3">Phases & Taches</h2>
        <?php foreach ($phases as $i => $p): if (empty(trim($p['nom'] ?? '')) && empty($p['taches'] ?? [])) continue; ?>
        <div class="bg-white rounded-xl shadow-lg p-5 mb-4 border-l-4 border-emerald-500">
            <div class="flex items-center gap-3 mb-3">
                <span class="bg-emerald-600 text-white text-xs font-bold px-3 py-1 rounded-full">Phase <?= $i + 1 ?></span>
                <h3 class="text-lg font-bold text-gray-800"><?= sanitize($p['nom'] ?? 'Sans nom') ?></h3>
                <?php if (!empty($p['dates'])): ?>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded"><?= sanitize($p['dates']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty(trim($p['livrable'] ?? ''))): ?>
            <p class="text-sm text-gray-600 mb-3">Livrable: <strong><?= sanitize($p['livrable']) ?></strong></p>
            <?php endif; ?>
            <?php if (!empty($p['taches'] ?? [])): ?>
            <div class="space-y-2">
                <?php foreach ($p['taches'] as $t): if (empty(trim($t['titre'] ?? ''))) continue;
                    $st = $taskStatuses[$t['statut'] ?? 'todo'] ?? $taskStatuses['todo'];
                ?>
                <div class="flex items-center gap-3 p-2 bg-gray-50 rounded border text-sm">
                    <span class="text-sm"><?= $st['icon'] ?></span>
                    <span class="flex-1 text-gray-700"><?= sanitize($t['titre']) ?></span>
                    <?php if (!empty($t['responsable'])): ?>
                    <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded"><?= sanitize($t['responsable']) ?></span>
                    <?php endif; ?>
                    <span class="text-xs px-2 py-0.5 rounded bg-<?= $st['color'] ?>-100 text-<?= $st['color'] ?>-700"><?= $st['label'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Points de controle -->
        <?php if (!empty($checkpoints)): ?>
        <h2 class="text-lg font-bold text-gray-800 mb-3 mt-6">Points de controle</h2>
        <div class="space-y-3 mb-6">
            <?php foreach ($checkpoints as $cp):
                $cpType = $checkpointTypes[$cp['type'] ?? ''] ?? null;
            ?>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 <?= $cpType ? 'border-' . $cpType['color'] . '-400' : 'border-gray-300' ?>">
                <div class="flex items-center gap-2 mb-1">
                    <?php if ($cpType): ?><span><?= $cpType['icon'] ?></span> <span class="font-semibold text-sm"><?= $cpType['label'] ?></span><?php endif; ?>
                    <?php if ($cp['apres_phase'] !== '' && isset($phases[(int)$cp['apres_phase']])): ?>
                    <span class="text-xs bg-gray-100 px-2 py-0.5 rounded">Apres: <?= sanitize($phases[(int)$cp['apres_phase']]['nom'] ?? 'Phase ' . ((int)$cp['apres_phase']+1)) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($cp['validateur'])): ?>
                    <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded"><?= sanitize($cp['validateur']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty(trim($cp['description'] ?? ''))): ?>
                <p class="text-sm text-gray-700"><?= sanitize($cp['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty(trim($cp['criteres'] ?? ''))): ?>
                <p class="text-xs text-gray-500 mt-1">Criteres: <?= sanitize($cp['criteres']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Lecons -->
        <?php if (!empty($lessons)): ?>
        <h2 class="text-lg font-bold text-gray-800 mb-3 mt-6">Lecons apprises</h2>
        <div class="space-y-2 mb-6">
            <?php foreach ($lessons as $l): if (empty(trim($l['lecon'] ?? ''))) continue;
                $catColors = ['success' => 'bg-green-50 border-green-400', 'problem' => 'bg-red-50 border-red-400', 'improvement' => 'bg-blue-50 border-blue-400'];
                $catIcons = ['success' => "\xE2\x9C\x85", 'problem' => "\xE2\x9A\xA0\xEF\xB8\x8F", 'improvement' => "\xF0\x9F\x92\xA1"];
                $cls = $catColors[$l['categorie'] ?? ''] ?? 'bg-gray-50 border-gray-300';
            ?>
            <div class="rounded-lg p-3 border-l-4 <?= $cls ?> text-sm">
                <span><?= $catIcons[$l['categorie'] ?? ''] ?? '' ?></span>
                <?= sanitize($l['lecon']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Synthese -->
        <?php if (!empty(trim($analyse['synthese'] ?? ''))): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
            <h2 class="text-lg font-bold text-gray-800 mb-3">Synthese</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['synthese']) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty(trim($analyse['notes'] ?? ''))): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mt-4">
            <h2 class="text-lg font-bold text-gray-800 mb-3">Notes</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
