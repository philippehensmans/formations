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

$stmt = $db->prepare("SELECT * FROM cartes_projet WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$carte = $stmt->fetch();

$partenaires = $carte ? (json_decode($carte['partenaires'] ?? '[]', true) ?: []) : [];
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
    <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-purple-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Carte d'identite du projet</h1>
            <p class="text-gray-600 text-lg"><?= h($carte['titre'] ?? 'Non defini') ?></p>
            <?php if ($carte && $carte['is_submitted']): ?>
                <span class="inline-block mt-2 bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full">Soumis</span>
            <?php endif; ?>
        </div>

        <?php
        $sections = [
            ['key' => 'objectifs', 'titre' => 'Objectifs du projet', 'color' => 'blue'],
            ['key' => 'public_cible', 'titre' => 'Public(s) cible(s)', 'color' => 'green'],
            ['key' => 'territoire', 'titre' => 'Zone d\'action / Territoire', 'color' => 'yellow'],
            ['key' => 'calendrier', 'titre' => 'Calendrier previsionnel', 'color' => 'orange'],
            ['key' => 'resultats', 'titre' => 'Resultats attendus', 'color' => 'teal'],
            ['key' => 'notes', 'titre' => 'Notes complementaires', 'color' => 'gray'],
        ];
        ?>

        <div class="space-y-4">
            <?php foreach ($sections as $section):
                $content = $carte[$section['key']] ?? '';
            ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-<?= $section['color'] ?>-500 text-white p-3 font-semibold"><?= $section['titre'] ?></div>
                    <div class="p-4">
                        <?php if (empty($content)): ?>
                            <p class="text-gray-400 italic">Non renseigne</p>
                        <?php else: ?>
                            <p class="text-gray-700 whitespace-pre-wrap"><?= h($content) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Partenaires -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-pink-500 text-white p-3 font-semibold">Partenaires</div>
                <div class="p-4">
                    <?php if (empty($partenaires)): ?>
                        <p class="text-gray-400 italic">Non renseigne</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead class="bg-pink-50">
                                <tr>
                                    <th class="border border-pink-200 px-3 py-2 text-left text-sm">Structure</th>
                                    <th class="border border-pink-200 px-3 py-2 text-left text-sm">Role</th>
                                    <th class="border border-pink-200 px-3 py-2 text-left text-sm">Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partenaires as $p): ?>
                                    <tr>
                                        <td class="border border-pink-200 px-3 py-2 text-sm"><?= h($p['structure'] ?? '') ?></td>
                                        <td class="border border-pink-200 px-3 py-2 text-sm"><?= h($p['role'] ?? '') ?></td>
                                        <td class="border border-pink-200 px-3 py-2 text-sm"><?= h($p['contact'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ressources -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-indigo-500 text-white p-3 font-semibold">Ressources mobilisables</div>
                <div class="p-4 space-y-2">
                    <p><strong>Humaines:</strong> <?= h($carte['ressources_humaines'] ?? '') ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></p>
                    <p><strong>Materielles:</strong> <?= h($carte['ressources_materielles'] ?? '') ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></p>
                    <p><strong>Financieres:</strong> <?= h($carte['ressources_financieres'] ?? '') ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
