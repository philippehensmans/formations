<?php
/**
 * Vue lecture seule des activités d'un participant
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

// Vérifier si formateur connecté
if (!isLoggedIn() || !isFormateur()) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$participantId = (int)($_GET['id'] ?? 0);

if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

// Récupérer le participant
$stmt = $db->prepare("SELECT * FROM participants WHERE id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    header('Location: formateur.php');
    exit;
}

// Récupérer les infos utilisateur
$sharedDb = getSharedDB();
$userStmt = $sharedDb->prepare("SELECT username, prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userData = $userStmt->fetch();

// Récupérer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$participant['session_id']]);
$session = $stmt->fetch();

// Récupérer les activités de cette session
$activites = getActivites($participant['session_id']);
$categories = getCategories();
$frequences = getFrequences();
$priorites = getPriorites();
$stats = getStatistiques($participant['session_id']);
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('act.title') ?> - <?= htmlspecialchars($userData['prenom'] . ' ' . $userData['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-teal-50 to-cyan-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-teal-600 to-cyan-600 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold"><?= t('act.title') ?></h1>
                    <p class="text-teal-200 text-sm">
                        <?= htmlspecialchars($userData['prenom'] . ' ' . $userData['nom']) ?>
                        <?php if ($userData['organisation']): ?>
                            - <?= htmlspecialchars($userData['organisation']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-teal-200"><?= htmlspecialchars($session['nom']) ?> (<?= $session['code'] ?>)</span>
                    <?= renderLanguageSelector('bg-teal-500 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <a href="formateur.php?session=<?= $session['id'] ?>" class="bg-teal-500 hover:bg-teal-400 px-3 py-1 rounded text-sm">
                        ← <?= t('common.back') ?>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $stats['total'] ?></div>
                <div class="text-gray-500 text-sm"><?= t('act.total_activities') ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= count($stats['par_categorie']) ?></div>
                <div class="text-gray-500 text-sm"><?= t('act.categories_used') ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $stats['avec_potentiel_ia'] ?></div>
                <div class="text-gray-500 text-sm"><?= t('act.ai_potential') ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-orange-600">
                    <?= $stats['total'] > 0 ? round(($stats['avec_potentiel_ia'] / $stats['total']) * 100) : 0 ?>%
                </div>
                <div class="text-gray-500 text-sm"><?= t('act.ai_percentage') ?></div>
            </div>
        </div>

        <!-- Mode lecture seule -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
            <p class="text-yellow-800 text-sm">
                <span class="font-medium">📖 <?= t('common.read_only') ?></span>
            </p>
        </div>

        <!-- Activities List -->
        <div class="space-y-4">
            <?php if (empty($activites)): ?>
                <div class="bg-white rounded-xl shadow p-12 text-center">
                    <div class="text-6xl mb-4">📋</div>
                    <h3 class="text-xl font-medium text-gray-700 mb-2"><?= t('act.no_activities') ?></h3>
                    <p class="text-gray-500"><?= t('act.no_activities_desc') ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($activites as $activite):
                    $cat = $categories[$activite['categorie']] ?? $categories['autre'];
                ?>
                <div class="bg-white rounded-xl shadow p-4">
                    <div class="flex items-start gap-4">
                        <div class="text-3xl"><?= $cat['icon'] ?></div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($activite['nom']) ?></h3>
                                <?php if ($activite['potentiel_ia']): ?>
                                    <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full flex items-center gap-1">
                                        🤖 <?= t('act.ai_badge') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($activite['description']): ?>
                                <p class="text-gray-600 text-sm mb-2"><?= htmlspecialchars($activite['description']) ?></p>
                            <?php endif; ?>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="bg-<?= $cat['color'] ?>-100 text-<?= $cat['color'] ?>-700 px-2 py-1 rounded">
                                    <?= $cat['label'] ?>
                                </span>
                                <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded">
                                    <?= $frequences[$activite['frequence']] ?? $activite['frequence'] ?>
                                </span>
                                <?php if ($activite['temps_estime']): ?>
                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded">
                                        ⏱️ <?= htmlspecialchars($activite['temps_estime']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php $prio = $priorites[$activite['priorite']] ?? $priorites[2]; ?>
                                <span class="bg-<?= $prio['color'] ?>-100 text-<?= $prio['color'] ?>-700 px-2 py-1 rounded">
                                    <?= $prio['label'] ?>
                                </span>
                            </div>
                            <?php if ($activite['notes_ia']): ?>
                                <div class="mt-2 p-2 bg-green-50 rounded text-sm text-green-800">
                                    <strong>💡 <?= t('act.ai_notes') ?>:</strong> <?= htmlspecialchars($activite['notes_ia']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Export button -->
        <?php if (!empty($activites)): ?>
        <div class="mt-6 text-center">
            <a href="api.php?action=export&session_id=<?= $session['id'] ?>"
               class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm"
               target="_blank">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <?= t('act.export') ?>
            </a>
        </div>
        <?php endif; ?>
    </main>

    <?= renderLanguageScript() ?>
</body>
</html>
