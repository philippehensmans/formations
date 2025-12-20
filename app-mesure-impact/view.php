<?php
/**
 * Vue en lecture seule - Mesure d'Impact
 */
require_once __DIR__ . '/config/database.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-mesure-impact';

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve");
}

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Acces refuse a cette session.");
}

$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

$stmt = $db->prepare("SELECT * FROM mesure_impact WHERE participant_id = ?");
$stmt->execute([$participantId]);
$mesure = $stmt->fetch();

$etape1 = $mesure ? json_decode($mesure['etape1_classification'] ?? '{}', true) : [];
$etape2 = $mesure ? json_decode($mesure['etape2_theorie_changement'] ?? '{}', true) : [];
$etape3 = $mesure ? json_decode($mesure['etape3_indicateurs'] ?? '{}', true) : [];
$etape4 = $mesure ? json_decode($mesure['etape4_plan_collecte'] ?? '{}', true) : [];
$etape5 = $mesure ? json_decode($mesure['etape5_synthese'] ?? '{}', true) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesure d'Impact - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-indigo-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <span class="px-3 py-1 rounded <?= ($mesure['is_submitted'] ?? 0) ? 'bg-green-500' : 'bg-yellow-500' ?>">
                    <?= ($mesure['is_submitted'] ?? 0) ? 'Soumis' : 'Brouillon' ?>
                </span>
                <span class="text-sm">Completion: <?= $mesure['completion_percent'] ?? 0 ?>%</span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Mesure d'Impact Social</h1>
            <p class="text-gray-600">Etape actuelle: <?= $mesure['etape_courante'] ?? 1 ?> / 5</p>
        </div>

        <!-- Etape 1: Classification -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-indigo-600 mb-4">Etape 1: Classification Output/Outcome/Impact</h2>
            <?php if (!empty($etape1['reponses'])): ?>
                <div class="space-y-2">
                    <?php foreach ($etape1['reponses'] as $rep): ?>
                        <div class="p-3 rounded <?= ($rep['correct'] ?? false) ? 'bg-green-50' : 'bg-red-50' ?>">
                            <span class="text-sm"><?= h($rep['enonce'] ?? '') ?></span>
                            <span class="ml-2 px-2 py-1 rounded text-xs <?= ($rep['correct'] ?? false) ? 'bg-green-200' : 'bg-red-200' ?>">
                                <?= h($rep['reponse'] ?? '') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-400">Non complete</p>
            <?php endif; ?>
        </div>

        <!-- Etape 2: Theorie du changement -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-indigo-600 mb-4">Etape 2: Theorie du Changement</h2>
            <div class="space-y-4 text-sm">
                <div>
                    <strong>Probleme:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= h($etape2['probleme'] ?? 'Non renseigne') ?></p>
                </div>
                <div>
                    <strong>Activites:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= h($etape2['activites'] ?? 'Non renseigne') ?></p>
                </div>
                <div>
                    <strong>Outputs:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= h($etape2['outputs'] ?? 'Non renseigne') ?></p>
                </div>
                <div>
                    <strong>Outcomes:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= h($etape2['outcomes'] ?? 'Non renseigne') ?></p>
                </div>
                <div>
                    <strong>Impact:</strong>
                    <p class="mt-1 whitespace-pre-wrap"><?= h($etape2['impact'] ?? 'Non renseigne') ?></p>
                </div>
            </div>
        </div>

        <!-- Etape 3: Indicateurs -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-indigo-600 mb-4">Etape 3: Indicateurs</h2>
            <?php if (!empty($etape3['indicateurs'])): ?>
                <div class="space-y-3">
                    <?php foreach ($etape3['indicateurs'] as $ind): ?>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="font-medium"><?= h($ind['nom'] ?? '') ?></p>
                            <p class="text-sm text-gray-600"><?= h($ind['description'] ?? '') ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-400">Non complete</p>
            <?php endif; ?>
        </div>

        <!-- Etape 4: Plan de collecte -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-indigo-600 mb-4">Etape 4: Plan de Collecte</h2>
            <div class="text-sm whitespace-pre-wrap">
                <?= !empty($etape4) ? h(json_encode($etape4, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '<p class="text-gray-400">Non complete</p>' ?>
            </div>
        </div>

        <!-- Etape 5: Synthese -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-indigo-600 mb-4">Etape 5: Synthese</h2>
            <div class="text-sm whitespace-pre-wrap">
                <?= !empty($etape5['synthese']) ? h($etape5['synthese']) : '<p class="text-gray-400">Non complete</p>' ?>
            </div>
        </div>
    </div>
</body>
</html>
