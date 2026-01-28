<?php
/**
 * Vue en lecture seule - Prompt Engineering pour Public Jeune
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-prompt-jeunes';

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

$stmt = $db->prepare("SELECT * FROM travaux WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$travail = $stmt->fetch();

$syntheseCles = $travail ? json_decode($travail['synthese_cles'] ?? '[]', true) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prompt Engineering - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-pink-500 to-rose-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-pink-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
                <?php if ($travail && $travail['is_shared']): ?>
                    <span class="bg-green-500 text-white text-xs px-2 py-1 rounded ml-2">Soumis</span>
                <?php else: ?>
                    <span class="bg-yellow-500 text-white text-xs px-2 py-1 rounded ml-2">Brouillon</span>
                <?php endif; ?>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4 space-y-6">
        <!-- Header Info -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Atelier Prompt Engineering pour Public Jeune</h1>
            <?php if (!empty($travail['organisation_nom'])): ?>
                <p class="text-lg text-gray-700"><strong>Organisation:</strong> <?= h($travail['organisation_nom']) ?></p>
            <?php endif; ?>
            <?php if (!empty($travail['organisation_type'])): ?>
                <p class="text-gray-600"><strong>Type:</strong> <?= h($travail['organisation_type']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Step 2: Case Selection -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">2</span>
                Cas choisi et Prompt initial
            </h2>
            <?php if (!empty($travail['cas_choisi'])):
                $casLabels = [
                    'instagram' => ['label' => 'Publication Instagram', 'class' => 'bg-purple-100 text-purple-800'],
                    'benevoles' => ['label' => 'Appel a benevoles', 'class' => 'bg-green-100 text-green-800'],
                    'quiz' => ['label' => 'Quiz interactif', 'class' => 'bg-blue-100 text-blue-800'],
                    'jeu_role' => ['label' => 'Scenario jeu de role', 'class' => 'bg-amber-100 text-amber-800'],
                    'experience' => ['label' => 'Fiche experience scientifique', 'class' => 'bg-cyan-100 text-cyan-800'],
                    'impro' => ['label' => 'Themes improvisation', 'class' => 'bg-fuchsia-100 text-fuchsia-800'],
                    'education_medias' => ['label' => 'Education aux medias', 'class' => 'bg-rose-100 text-rose-800'],
                    'plaidoyer' => ['label' => 'Campagne de plaidoyer', 'class' => 'bg-emerald-100 text-emerald-800'],
                    'sensibilisation' => ['label' => 'Sensibilisation reseaux sociaux', 'class' => 'bg-sky-100 text-sky-800'],
                ];
                $cas = $casLabels[$travail['cas_choisi']] ?? ['label' => $travail['cas_choisi'], 'class' => 'bg-gray-100 text-gray-800'];
            ?>
                <div class="mb-4">
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?= $cas['class'] ?>">
                        <?= $cas['label'] ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php if (!empty($travail['cas_description'])): ?>
                <div class="mb-4">
                    <h4 class="font-medium text-gray-700 mb-1">Description du cas</h4>
                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg"><?= nl2br(h($travail['cas_description'])) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($travail['prompt_initial'])): ?>
                <div class="bg-pink-50 rounded-lg p-4">
                    <h4 class="font-medium text-pink-800 mb-2">Prompt initial</h4>
                    <p class="text-gray-700 whitespace-pre-wrap"><?= h($travail['prompt_initial']) ?></p>
                </div>
            <?php else: ?>
                <p class="text-gray-400 text-center py-4">Aucun prompt initial</p>
            <?php endif; ?>
        </div>

        <!-- Step 3: Test and Iterate -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">3</span>
                Test et iteration
            </h2>

            <?php if (!empty($travail['resultat_initial'])): ?>
                <div class="mb-4">
                    <h4 class="font-medium text-gray-700 mb-1">Resultat initial</h4>
                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg whitespace-pre-wrap"><?= h($travail['resultat_initial']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($travail['analyse_resultat'])): ?>
                <div class="mb-4 bg-yellow-50 rounded-lg p-4">
                    <h4 class="font-medium text-yellow-800 mb-1">Analyse du resultat</h4>
                    <p class="text-gray-700 whitespace-pre-wrap"><?= h($travail['analyse_resultat']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($travail['prompt_ameliore'])): ?>
                <div class="mb-4 bg-green-50 rounded-lg p-4">
                    <h4 class="font-medium text-green-800 mb-2">Prompt ameliore</h4>
                    <p class="text-gray-700 whitespace-pre-wrap"><?= h($travail['prompt_ameliore']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($travail['resultat_ameliore'])): ?>
                <div class="mb-4">
                    <h4 class="font-medium text-gray-700 mb-1">Resultat ameliore</h4>
                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg whitespace-pre-wrap"><?= h($travail['resultat_ameliore']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($travail['ameliorations_notes'])): ?>
                <div class="mb-4">
                    <h4 class="font-medium text-gray-700 mb-1">Notes sur les ameliorations</h4>
                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg"><?= nl2br(h($travail['ameliorations_notes'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($travail['resultat_initial']) && empty($travail['prompt_ameliore'])): ?>
                <p class="text-gray-400 text-center py-4">Aucune donnee de test</p>
            <?php endif; ?>
        </div>

        <!-- Step 4: Pair Feedback -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">4</span>
                Retour en binome
            </h2>

            <?php if (!empty($travail['feedback_binome'])): ?>
                <div class="mb-4">
                    <h4 class="font-medium text-gray-700 mb-1">Feedback du binome</h4>
                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg"><?= nl2br(h($travail['feedback_binome'])) ?></p>
                </div>
            <?php endif; ?>

            <div class="grid md:grid-cols-2 gap-4">
                <?php if (!empty($travail['points_forts'])): ?>
                    <div class="bg-green-50 rounded-lg p-4">
                        <h4 class="font-medium text-green-800 mb-2">Points forts</h4>
                        <p class="text-gray-700"><?= nl2br(h($travail['points_forts'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($travail['points_ameliorer'])): ?>
                    <div class="bg-orange-50 rounded-lg p-4">
                        <h4 class="font-medium text-orange-800 mb-2">Points a ameliorer</h4>
                        <p class="text-gray-700"><?= nl2br(h($travail['points_ameliorer'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($travail['feedback_binome']) && empty($travail['points_forts']) && empty($travail['points_ameliorer'])): ?>
                <p class="text-gray-400 text-center py-4">Aucun feedback enregistre</p>
            <?php endif; ?>
        </div>

        <!-- Step 5: AI Feedback -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">5</span>
                Feedback de l'IA
            </h2>

            <?php if (!empty($travail['feedback_ia'])): ?>
                <div class="bg-blue-50 rounded-lg p-4">
                    <h4 class="font-medium text-blue-800 mb-2">Retour de l'IA sur le prompt</h4>
                    <p class="text-gray-700 whitespace-pre-wrap"><?= h($travail['feedback_ia']) ?></p>
                </div>
            <?php else: ?>
                <p class="text-gray-400 text-center py-4">Aucun feedback IA enregistre</p>
            <?php endif; ?>
        </div>

        <!-- Step 6: Synthesis -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">6</span>
                Synthese des apprentissages
            </h2>

            <?php if (!empty($syntheseCles)): ?>
                <div class="space-y-2 mb-4">
                    <?php foreach ($syntheseCles as $item): ?>
                        <div class="flex items-center gap-3 bg-gray-50 p-3 rounded-lg">
                            <?php if ($item['checked'] ?? false): ?>
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php else: ?>
                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php endif; ?>
                            <span class="<?= ($item['checked'] ?? false) ? 'text-gray-400 line-through' : 'text-gray-700' ?>"><?= h($item['text'] ?? '') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-400 text-center py-4">Aucun apprentissage enregistre</p>
            <?php endif; ?>

            <?php if (!empty($travail['notes'])): ?>
                <div class="mt-4">
                    <h4 class="font-medium text-gray-700 mb-1">Notes complementaires</h4>
                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg"><?= nl2br(h($travail['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
