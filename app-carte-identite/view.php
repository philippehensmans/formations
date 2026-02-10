<?php
/**
 * Vue d'une fiche participant - Carte d'identite du Projet
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Recuperer le participant
$stmt = $db->prepare("SELECT * FROM participants WHERE id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    header('Location: formateur.php');
    exit;
}

// Verifier l'acces a cette session
if (!canAccessSession('app-carte-identite', $participant['session_id'])) {
    die("Acces refuse.");
}

// Recuperer la fiche
$stmt = $db->prepare("SELECT * FROM fiches WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$fiche = $stmt->fetch();

// Recuperer les infos utilisateur
$userStmt = $sharedDb->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

// Recuperer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$participant['session_id']]);
$session = $stmt->fetch();

$partenaires = $fiche ? json_decode($fiche['partenaires'] ?? '[]', true) : [];
$lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('cip.title') ?> - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <!-- Header -->
    <header class="max-w-4xl mx-auto mb-4 no-print">
        <div class="bg-white/90 backdrop-blur rounded-lg shadow-lg px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🗂️</span>
                <div>
                    <div class="font-bold text-gray-800"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></div>
                    <div class="text-sm text-gray-500">Session: <?= h($session['code'] ?? '') ?> - <?= h($session['nom'] ?? '') ?></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <?= renderLanguageSelector('text-sm border rounded px-2 py-1') ?>
                <button onclick="window.print()" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-sm">
                    🖨️ <?= t('common.print') ?>
                </button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded text-sm">
                    <?= t('common.back') ?>
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-2xl p-6 md:p-8">
        <div class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">🗂️ <?= t('cip.title') ?></h1>
            <p class="text-gray-600"><?= h($userInfo['organisation'] ?? '') ?></p>
        </div>

        <?php if (!$fiche): ?>
        <div class="text-center py-12 text-gray-500">
            <p class="text-lg"><?= t('common.no_data') ?></p>
        </div>
        <?php else: ?>

        <div class="space-y-6">
            <!-- Titre du projet -->
            <div class="bg-purple-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">🏷️ <?= t('cip.project_title') ?></label>
                <p class="text-gray-700"><?= h($fiche['titre']) ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></p>
            </div>

            <!-- Objectifs -->
            <div class="bg-blue-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">🎯 <?= t('cip.objectives') ?></label>
                <p class="text-gray-700 whitespace-pre-line"><?= h($fiche['objectifs']) ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></p>
            </div>

            <!-- Public cible -->
            <div class="bg-green-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">👥 <?= t('cip.target_audience') ?></label>
                <p class="text-gray-700 whitespace-pre-line"><?= h($fiche['public_cible']) ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></p>
            </div>

            <!-- Territoire -->
            <div class="bg-yellow-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">📍 <?= t('cip.territory') ?></label>
                <p class="text-gray-700"><?= h($fiche['territoire']) ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></p>
            </div>

            <!-- Partenaires -->
            <div class="bg-pink-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">🤝 <?= t('cip.partners') ?></label>
                <?php if (empty($partenaires)): ?>
                <p class="text-gray-400 italic">Aucun partenaire renseigne</p>
                <?php else: ?>
                <table class="w-full border-collapse border-2 border-pink-200">
                    <thead class="bg-pink-100">
                        <tr>
                            <th class="border border-pink-200 px-3 py-2 text-left text-sm font-semibold"><?= t('cip.partner_name') ?></th>
                            <th class="border border-pink-200 px-3 py-2 text-left text-sm font-semibold"><?= t('cip.partner_role') ?></th>
                            <th class="border border-pink-200 px-3 py-2 text-left text-sm font-semibold"><?= t('cip.partner_contact') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partenaires as $p): ?>
                        <tr>
                            <td class="border border-pink-200 px-3 py-2"><?= h($p['structure'] ?? '') ?></td>
                            <td class="border border-pink-200 px-3 py-2"><?= h($p['role'] ?? '') ?></td>
                            <td class="border border-pink-200 px-3 py-2"><?= h($p['contact'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Ressources -->
            <div class="bg-indigo-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-3">💰 <?= t('cip.resources') ?></label>
                <div class="space-y-2">
                    <div>
                        <span class="font-medium text-gray-700"><?= t('cip.resources_human') ?> :</span>
                        <span class="text-gray-600"><?= h($fiche['ressources_humaines']) ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700"><?= t('cip.resources_material') ?> :</span>
                        <span class="text-gray-600"><?= h($fiche['ressources_materielles']) ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700"><?= t('cip.resources_financial') ?> :</span>
                        <span class="text-gray-600"><?= h($fiche['ressources_financieres']) ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></span>
                    </div>
                </div>
            </div>

            <!-- Calendrier -->
            <div class="bg-orange-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">🗓️ <?= t('cip.calendar') ?></label>
                <p class="text-gray-700"><?= h($fiche['calendrier']) ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></p>
            </div>

            <!-- Resultats -->
            <div class="bg-teal-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">📈 <?= t('cip.expected_results') ?></label>
                <p class="text-gray-700 whitespace-pre-line"><?= h($fiche['resultats']) ?: '<span class="text-gray-400 italic">Non renseigne</span>' ?></p>
            </div>

            <!-- Notes -->
            <?php if (!empty($fiche['notes'])): ?>
            <div class="bg-gray-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">✏️ <?= t('cip.notes') ?></label>
                <p class="text-gray-700 whitespace-pre-line"><?= h($fiche['notes']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>

    <?= renderLanguageScript() ?>
</body>
</html>
