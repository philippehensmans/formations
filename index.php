<?php
/**
 * Page d'accueil - Formation Interactive
 * Liste automatiquement toutes les applications disponibles
 */

require_once __DIR__ . '/shared-auth/config.php';
require_once __DIR__ . '/shared-auth/lang.php';

// Detecter automatiquement toutes les applications
function getApplications() {
    $apps = [];
    $dirs = glob(__DIR__ . '/app-*', GLOB_ONLYDIR);

    foreach ($dirs as $dir) {
        $appKey = basename($dir);
        // Verifier que l'app a un fichier login.php ou app.php
        if (file_exists($dir . '/login.php') || file_exists($dir . '/app.php')) {
            $apps[] = $appKey;
        }
    }

    sort($apps);
    return $apps;
}

$applications = getApplications();
$user = isLoggedIn() ? getLoggedUser() : null;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('home.title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .app-card {
            transition: all 0.3s ease;
        }
        .app-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold"><?= t('home.title') ?></h1>
                    <p class="text-indigo-200 mt-1"><?= t('home.subtitle') ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php include __DIR__ . '/shared-auth/lang-switcher.php'; ?>
                    <?php if ($user): ?>
                        <span class="text-indigo-200"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-12">
        <div class="mb-8 text-center">
            <p class="text-gray-600 text-lg"><?= t('home.description') ?></p>
            <p class="text-gray-500 mt-2"><?= count($applications) ?> <?= t('home.apps_available') ?></p>
        </div>

        <!-- Applications Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($applications as $appKey):
                $appId = str_replace('app-', '', $appKey);
                $title = t('apps.' . $appId . '.title');
                $description = t('apps.' . $appId . '.description');
                $color = t('apps.' . $appId . '.color') ?: 'indigo';

                // Si pas de traduction, utiliser le nom du dossier
                if ($title === 'apps.' . $appId . '.title') {
                    $title = ucfirst(str_replace('-', ' ', $appId));
                }
                if ($description === 'apps.' . $appId . '.description') {
                    $description = '';
                }
            ?>
            <a href="<?= $appKey ?>/login.php" class="app-card bg-white rounded-xl shadow-md overflow-hidden">
                <div class="h-2 bg-<?= $color ?>-500"></div>
                <div class="p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($title) ?></h2>
                    <?php if ($description): ?>
                        <p class="text-gray-600 text-sm"><?= htmlspecialchars($description) ?></p>
                    <?php endif; ?>
                </div>
                <div class="px-6 pb-4">
                    <span class="inline-flex items-center text-<?= $color ?>-600 text-sm font-medium">
                        <?= t('home.open_app') ?>
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Trainer Section -->
        <div class="mt-16 bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4"><?= t('home.trainer_section') ?></h2>
            <p class="text-gray-600 mb-6"><?= t('home.trainer_description') ?></p>
            <div class="flex flex-wrap gap-4">
                <?php foreach ($applications as $appKey):
                    $appId = str_replace('app-', '', $appKey);
                    $title = t('apps.' . $appId . '.title');
                    if ($title === 'apps.' . $appId . '.title') {
                        $title = ucfirst(str_replace('-', ' ', $appId));
                    }
                    if (file_exists(__DIR__ . '/' . $appKey . '/formateur.php')):
                ?>
                <a href="<?= $appKey ?>/formateur.php"
                   class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 text-sm transition">
                    <?= htmlspecialchars($title) ?>
                </a>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-400 mt-16 py-8">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p><?= t('home.footer') ?></p>
        </div>
    </footer>
</body>
</html>
