<?php
/**
 * Vue en lecture seule - Carte Projet
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-carte-projet';

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

$stmt = $db->prepare("SELECT * FROM cartes WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$carte = $stmt->fetch();

$carteData = $carte ? json_decode($carte['carte_data'] ?? '{}', true) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Projet - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-rose-600 to-rose-800 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-rose-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Carte Projet</h1>
            <p class="text-gray-600">Projet: <?= h($carte['titre_projet'] ?? 'Non defini') ?></p>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <?php
            $sections = [
                'objectifs' => ['titre' => 'Objectifs', 'color' => 'blue'],
                'beneficiaires' => ['titre' => 'Beneficiaires', 'color' => 'green'],
                'activites' => ['titre' => 'Activites', 'color' => 'yellow'],
                'ressources' => ['titre' => 'Ressources', 'color' => 'purple'],
                'partenaires' => ['titre' => 'Partenaires', 'color' => 'pink'],
                'risques' => ['titre' => 'Risques', 'color' => 'red'],
                'indicateurs' => ['titre' => 'Indicateurs', 'color' => 'indigo'],
                'calendrier' => ['titre' => 'Calendrier', 'color' => 'teal']
            ];
            foreach ($sections as $key => $section):
                $content = $carteData[$key] ?? '';
            ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-<?= $section['color'] ?>-500 text-white p-3 font-semibold"><?= $section['titre'] ?></div>
                    <div class="p-4">
                        <?php if (empty($content)): ?>
                            <p class="text-gray-400 italic">Non renseigne</p>
                        <?php elseif (is_array($content)): ?>
                            <ul class="space-y-1">
                                <?php foreach ($content as $item): ?>
                                    <li class="text-sm"><?= h(is_array($item) ? ($item['text'] ?? json_encode($item)) : $item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-gray-700 whitespace-pre-wrap"><?= h($content) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
