<?php
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: index.php'); exit; }

$appKey = 'app-personas';
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

$stakeholders = json_decode($analyse['stakeholders_data'] ?? '[]', true) ?: [];
$personas = json_decode($analyse['personas_data'] ?? '[]', true) ?: [];
$families = getPublicFamilies();
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publics & Personas - <?= sanitize($pPrenom) ?> <?= sanitize($pNom) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } } .priority-high { border-left: 4px solid #ef4444; } .priority-medium { border-left: 4px solid #f59e0b; } .priority-low { border-left: 4px solid #6b7280; }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-rose-600 to-pink-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($pPrenom) ?> <?= sanitize($pNom) ?></span>
                <span class="text-rose-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Publics & Personas</h1>
            <div class="text-gray-600">Organisation : <strong><?= sanitize($analyse['nom_organisation'] ?? '') ?: '<em class="text-gray-400">Non renseigne</em>' ?></strong></div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-rose-600"><?= count($stakeholders) ?></div>
                <div class="text-sm text-gray-500">Publics</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-pink-600"><?= count($personas) ?></div>
                <div class="text-sm text-gray-500">Personas</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-red-500"><?= count(array_filter($stakeholders, function($s) { return ($s['priorite'] ?? '') === 'high'; })) ?></div>
                <div class="text-sm text-gray-500">Priorite haute</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <?php $fams = []; foreach ($stakeholders as $s) { if (!empty($s['famille'])) $fams[$s['famille']] = true; } ?>
                <div class="text-3xl font-bold text-amber-500"><?= count($fams) ?></div>
                <div class="text-sm text-gray-500">Familles</div>
            </div>
        </div>

        <!-- Carte des publics -->
        <h2 class="text-xl font-bold text-gray-800 mb-4">Carte des publics</h2>
        <?php if (empty($stakeholders)): ?>
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-400 mb-6">Aucun public identifie</div>
        <?php else: ?>
            <?php foreach ($stakeholders as $i => $s):
                $priority = $s['priorite'] ?? 'medium';
                $fam = $families[$s['famille'] ?? ''] ?? null;
            ?>
            <div class="bg-white rounded-xl shadow-lg p-5 mb-3 priority-<?= $priority ?>">
                <div class="flex items-center gap-3 mb-3">
                    <span class="bg-rose-600 text-white text-xs font-bold px-3 py-1 rounded-full">Public <?= $i + 1 ?></span>
                    <h3 class="text-lg font-bold text-gray-800"><?= sanitize($s['nom'] ?? 'Sans nom') ?></h3>
                    <?php if ($fam): ?>
                    <span class="px-2 py-0.5 bg-<?= $fam['color'] ?>-100 text-<?= $fam['color'] ?>-700 rounded text-xs"><?= $fam['icon'] ?> <?= $fam['label'] ?></span>
                    <?php endif; ?>
                    <span class="px-2 py-0.5 rounded text-xs <?= $priority === 'high' ? 'bg-red-100 text-red-700' : ($priority === 'low' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-700') ?>">
                        Priorite <?= $priority === 'high' ? 'haute' : ($priority === 'low' ? 'basse' : 'moyenne') ?>
                    </span>
                </div>
                <?php if (!empty(trim($s['sous_groupe'] ?? ''))): ?>
                <p class="text-sm text-gray-500 mb-3">Sous-groupe : <?= sanitize($s['sous_groupe']) ?></p>
                <?php endif; ?>
                <div class="grid md:grid-cols-3 gap-3">
                    <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                        <div class="text-xs font-semibold text-blue-800 mb-1">Que veut ce public ?</div>
                        <p class="text-sm whitespace-pre-wrap"><?= sanitize($s['attentes'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></p>
                    </div>
                    <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                        <div class="text-xs font-semibold text-green-800 mb-1">Ou est-il ?</div>
                        <p class="text-sm whitespace-pre-wrap"><?= sanitize($s['localisation'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></p>
                    </div>
                    <div class="bg-amber-50 p-3 rounded-lg border border-amber-200">
                        <div class="text-xs font-semibold text-amber-800 mb-1">Communication actuelle</div>
                        <p class="text-sm whitespace-pre-wrap"><?= sanitize($s['communication_actuelle'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Personas -->
        <h2 class="text-xl font-bold text-gray-800 mb-4 mt-8">Personas</h2>
        <?php if (empty($personas)): ?>
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-400 mb-6">Aucun persona cree</div>
        <?php else: ?>
            <?php foreach ($personas as $i => $p):
                $fam = $families[$p['type_public'] ?? ''] ?? null;
            ?>
            <div class="bg-white rounded-xl shadow-lg p-6 mb-4 border-l-4 border-rose-500">
                <div class="flex items-center gap-3 mb-4">
                    <span class="bg-rose-600 text-white text-sm font-bold px-3 py-1 rounded-full">Persona <?= $i + 1 ?></span>
                    <h3 class="text-xl font-bold text-gray-800"><?= sanitize($p['prenom'] ?? '') ?: 'Sans prenom' ?><?= !empty($p['age']) ? ', ' . sanitize($p['age']) : '' ?></h3>
                    <?php if ($fam): ?>
                    <span class="px-2 py-0.5 bg-<?= $fam['color'] ?>-100 text-<?= $fam['color'] ?>-700 rounded text-xs"><?= $fam['icon'] ?> <?= $fam['label'] ?></span>
                    <?php endif; ?>
                </div>

                <?php foreach ([
                    ['Situation', $p['situation'] ?? ''],
                    ['Rapport a l\'organisation', $p['rapport_org'] ?? ''],
                    ['Besoins et attentes', $p['besoins'] ?? ''],
                    ['Habitudes medias', $p['habitudes_medias'] ?? ''],
                    ['Ce qui le/la touche', $p['ce_qui_touche'] ?? ''],
                    ['Ce qui le/la rebute', $p['ce_qui_rebute'] ?? ''],
                ] as [$label, $value]): ?>
                    <?php if (!empty(trim($value))): ?>
                    <div class="mb-2">
                        <span class="text-xs font-semibold text-gray-500"><?= $label ?></span>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= sanitize($value) ?></p>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (!empty(trim($p['message_ideal'] ?? ''))): ?>
                <div class="mt-3 bg-rose-50 border-2 border-rose-200 rounded-lg p-3">
                    <span class="text-xs font-semibold text-rose-800">Message ideal</span>
                    <p class="text-sm font-medium text-rose-900 italic">&laquo; <?= sanitize($p['message_ideal']) ?> &raquo;</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Synthese -->
        <?php if (!empty(trim($analyse['synthese'] ?? ''))): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
            <h2 class="text-xl font-bold text-gray-800 mb-3">Synthese</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['synthese']) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty(trim($analyse['notes'] ?? ''))): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mt-4">
            <h2 class="text-xl font-bold text-gray-800 mb-3">Notes</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
