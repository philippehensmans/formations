<?php
/**
 * Vue en lecture seule - Craintes et Attentes
 * Affiche les avis d'un participant specifique
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-craintes-attentes';

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();
$chapeaux = getChapeaux();

// Recuperer le participant
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.sujet as session_sujet, s.id as session_id
                      FROM participants p
                      JOIN sessions s ON p.session_id = s.id
                      WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve");
}

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Acces refuse a cette session.");
}

// Recuperer les infos utilisateur
$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

// Recuperer les avis du participant
$avis = getAvisParticipant($participant['user_id'], $participant['session_id']);

// Grouper par categorie
$avisByChapeau = [];
foreach ($chapeaux as $key => $ch) {
    $avisByChapeau[$key] = [];
}
foreach ($avis as $a) {
    if (isset($avisByChapeau[$a['chapeau']])) {
        $avisByChapeau[$a['chapeau']][] = $a;
    }
}

// Statistiques
$totalAvis = count($avis);
$avisPartages = count(array_filter($avis, fn($a) => $a['is_shared'] == 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Craintes & Attentes - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>⚖️</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-teal-600 to-emerald-700 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <?php if (!empty($userInfo['organisation'])): ?>
                <span class="text-teal-200 text-sm ml-2">(<?= h($userInfo['organisation']) ?>)</span>
                <?php endif; ?>
                <span class="text-teal-200 text-sm ml-2">- <?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <span class="bg-white/20 px-3 py-1 rounded text-sm">
                    <?= $totalAvis ?> avis (<?= $avisPartages ?> partages)
                </span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="session-view.php?id=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Vue Session</a>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">⚖️ Craintes & Attentes</h1>
            <p class="text-gray-600">
                <strong>Participant:</strong> <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?>
                <?php if (!empty($userInfo['organisation'])): ?>
                - <?= h($userInfo['organisation']) ?>
                <?php endif; ?>
            </p>
            <p class="text-gray-500 text-sm mt-1">
                <strong>Session:</strong> <?= h($participant['session_nom']) ?> (<?= $participant['session_code'] ?>)
            </p>
            <?php if (!empty($participant['session_sujet'])): ?>
            <div class="mt-4 p-4 bg-teal-50 rounded-lg">
                <strong class="text-teal-700">Sujet:</strong>
                <p class="text-gray-700 mt-1"><?= nl2br(h($participant['session_sujet'])) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Avis par categorie -->
        <div class="grid md:grid-cols-2 gap-6">
            <?php foreach ($chapeaux as $key => $chapeau): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="<?= $chapeau['bg'] ?> <?= $chapeau['text'] ?> p-4 border-b-4 <?= $chapeau['border'] ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl"><?= $chapeau['icon'] ?></span>
                        <div>
                            <div class="font-bold text-lg"><?= $chapeau['nom'] ?></div>
                            <div class="text-xs opacity-80"><?= $chapeau['description'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <?php if (empty($avisByChapeau[$key])): ?>
                    <p class="text-gray-400 text-center py-4 text-sm">Aucun avis</p>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($avisByChapeau[$key] as $a): ?>
                        <div class="p-3 <?= $chapeau['bg'] ?> rounded-lg border <?= $chapeau['border'] ?>">
                            <p class="text-gray-700 text-sm"><?= nl2br(h($a['contenu'])) ?></p>
                            <div class="flex justify-between items-center mt-2 text-xs text-gray-500">
                                <span><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></span>
                                <?php if ($a['is_shared']): ?>
                                <span class="bg-green-200 text-green-800 px-2 py-0.5 rounded">Partage</span>
                                <?php else: ?>
                                <span class="bg-gray-200 text-gray-600 px-2 py-0.5 rounded">Non partage</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
