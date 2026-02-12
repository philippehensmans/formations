<?php
/**
 * Page d'accueil - Formation Interactive
 * Liste automatiquement toutes les applications disponibles
 * avec filtrage par categories
 */

require_once __DIR__ . '/shared-auth/config.php';
require_once __DIR__ . '/shared-auth/lang.php';

// Charger la configuration des categories
$categoriesConfig = file_exists(__DIR__ . '/categories.php')
    ? require __DIR__ . '/categories.php'
    : ['categories' => [], 'apps' => []];

$categoriesDef = $categoriesConfig['categories'] ?? [];
$appCategories = $categoriesConfig['apps'] ?? [];

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

// Construire un index inverse : pour chaque categorie, quelles apps
$categoryApps = [];
foreach ($appCategories as $appKey => $cats) {
    foreach ($cats as $cat) {
        $categoryApps[$cat][] = $appKey;
    }
}

// Determiner quelles categories ont des apps existantes
$activeCategories = [];
foreach ($categoriesDef as $catKey => $catDef) {
    if (!empty($categoryApps[$catKey])) {
        $count = 0;
        foreach ($categoryApps[$catKey] as $appKey) {
            if (in_array($appKey, $applications)) $count++;
        }
        if ($count > 0) {
            $activeCategories[$catKey] = array_merge($catDef, ['count' => $count]);
        }
    }
}

$restrictedApps = getRestrictedApps();
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
        .app-card.hidden-by-filter {
            display: none;
        }
        .cat-btn {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .cat-btn:hover {
            transform: translateY(-1px);
        }
        .cat-btn.active {
            ring: 2px;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3);
        }
        .cat-badge {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <img src="logo.png" alt="Logo" class="h-12">
                    <div>
                        <h1 class="text-3xl font-bold"><?= t('home.title') ?></h1>
                        <p class="text-indigo-200 mt-1"><?= t('home.subtitle') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <?= renderLanguageSelector('bg-white/20 text-white border-0 rounded px-2 py-1 cursor-pointer') ?>
                    <?php if ($user): ?>
                        <span class="text-indigo-200"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></span>
                        <?php if ($user['is_admin'] ?? false): ?>
                        <a href="admin-categories.php" class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded text-sm transition">Categories</a>
                        <a href="shared-auth/admin.php" class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded text-sm transition">Admin</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-12">
        <div class="mb-6 text-center">
            <p class="text-gray-600 text-lg"><?= t('home.description') ?></p>
            <p class="text-gray-500 mt-2">
                <span id="visibleCount"><?= count($applications) ?></span> / <?= count($applications) ?> <?= t('home.apps_available') ?>
            </p>
        </div>

        <!-- Category Filters -->
        <?php if (!empty($activeCategories)): ?>
        <div class="mb-8">
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" onclick="filterByCategory('all')" id="cat-btn-all"
                    class="cat-btn active inline-flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-md border-2 border-indigo-500 text-indigo-700 font-medium text-sm">
                    Toutes
                    <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($applications) ?></span>
                </button>
                <?php foreach ($activeCategories as $catKey => $catDef): ?>
                <button type="button" onclick="filterByCategory('<?= $catKey ?>')" id="cat-btn-<?= $catKey ?>"
                    class="cat-btn inline-flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-md border-2 border-gray-200 text-gray-700 font-medium text-sm hover:border-<?= $catDef['color'] ?>-400">
                    <span><?= $catDef['icon'] ?></span>
                    <?= htmlspecialchars($catDef['label']) ?>
                    <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full"><?= $catDef['count'] ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Applications Grid -->
        <div id="appsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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

                // Categories de cette app
                $cats = $appCategories[$appKey] ?? [];
                $catsJson = htmlspecialchars(json_encode($cats), ENT_QUOTES);
                $isRestricted = isset($restrictedApps[$appKey]);
            ?>
            <a href="<?= $appKey ?>/login.php" target="_blank"
               class="app-card bg-white rounded-xl shadow-md overflow-hidden"
               data-categories="<?= $catsJson ?>"
               data-app="<?= $appKey ?>">
                <div class="h-2 bg-<?= $color ?>-500"></div>
                <div class="p-6">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($title) ?></h2>
                        <?php if ($isRestricted): ?>
                        <span class="shrink-0 px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-bold border border-amber-200" title="Acces restreint - Autorisation requise">&#x1F512; IA</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($description): ?>
                        <p class="text-gray-600 text-sm mb-3"><?= htmlspecialchars($description) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($cats)): ?>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($cats as $cat):
                            if (isset($categoriesDef[$cat])):
                        ?>
                        <span class="cat-badge inline-flex items-center gap-1 px-2 py-0.5 bg-<?= $categoriesDef[$cat]['color'] ?>-50 text-<?= $categoriesDef[$cat]['color'] ?>-700 rounded text-xs border border-<?= $categoriesDef[$cat]['color'] ?>-200">
                            <?= $categoriesDef[$cat]['icon'] ?> <?= htmlspecialchars($categoriesDef[$cat]['label']) ?>
                        </span>
                        <?php endif; endforeach; ?>
                    </div>
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

        <!-- Empty state when filtering -->
        <div id="noResults" class="hidden text-center py-12 text-gray-500">
            <p class="text-4xl mb-3">&#x1F50D;</p>
            <p class="text-lg">Aucune application dans cette categorie</p>
        </div>

        <!-- Trainer Section -->
        <div class="mt-16 bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4"><?= t('home.trainer_section') ?></h2>
            <p class="text-gray-600 mb-6"><?= t('home.trainer_description') ?></p>
            <div id="trainerLinks" class="flex flex-wrap gap-4">
                <?php foreach ($applications as $appKey):
                    $appId = str_replace('app-', '', $appKey);
                    $title = t('apps.' . $appId . '.title');
                    if ($title === 'apps.' . $appId . '.title') {
                        $title = ucfirst(str_replace('-', ' ', $appId));
                    }
                    $cats = $appCategories[$appKey] ?? [];
                    $catsJson = htmlspecialchars(json_encode($cats), ENT_QUOTES);
                    if (file_exists(__DIR__ . '/' . $appKey . '/formateur.php')):
                ?>
                <a href="<?= $appKey ?>/formateur.php" target="_blank"
                   class="trainer-link inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 text-sm transition"
                   data-categories="<?= $catsJson ?>">
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

    <?= renderLanguageScript() ?>
    <script>
        let currentFilter = 'all';

        function filterByCategory(category) {
            currentFilter = category;
            const cards = document.querySelectorAll('.app-card');
            const trainerLinks = document.querySelectorAll('.trainer-link');
            let visibleCount = 0;

            // Filtrer les cartes d'applications
            cards.forEach(card => {
                const cats = JSON.parse(card.dataset.categories || '[]');
                const visible = (category === 'all' || cats.includes(category));
                card.classList.toggle('hidden-by-filter', !visible);
                if (visible) visibleCount++;
            });

            // Filtrer les liens formateur
            trainerLinks.forEach(link => {
                const cats = JSON.parse(link.dataset.categories || '[]');
                link.style.display = (category === 'all' || cats.includes(category)) ? '' : 'none';
            });

            // Compteur
            document.getElementById('visibleCount').textContent = visibleCount;

            // Message si aucun resultat
            document.getElementById('noResults').classList.toggle('hidden', visibleCount > 0);

            // Style des boutons : reset tous, puis activer le bon
            document.querySelectorAll('.cat-btn').forEach(btn => {
                btn.style.boxShadow = '';
                btn.style.fontWeight = '';
                btn.style.borderColor = '#e5e7eb';
                btn.style.color = '#374151';
            });
            const activeBtn = document.getElementById('cat-btn-' + category);
            if (activeBtn) {
                activeBtn.style.boxShadow = '0 0 0 3px rgba(79, 70, 229, 0.3)';
                activeBtn.style.fontWeight = '700';
                activeBtn.style.borderColor = '#6366f1';
                activeBtn.style.color = '#4338ca';
            }
        }
    </script>
</body>
</html>
