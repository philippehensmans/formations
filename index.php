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

// Applications externes (hebergees ailleurs)
$externalApps = [
    'ext-prompt-asbl' => [
        'title' => 'Prompt ASBL',
        'description' => 'Exercice de prompting IA applique au contexte des ASBL et associations',
        'url' => 'https://www.k1m.be/exercices/PromptASBL',
        'color' => 'violet',
        'categories' => ['intelligence_artificielle', 'creativite'],
        'icon' => '🤖'
    ]
];

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
// Ajouter les apps externes aux categories
foreach ($externalApps as $extKey => $extApp) {
    foreach ($extApp['categories'] as $cat) {
        $categoryApps[$cat][] = $extKey;
    }
}

$totalAppCount = count($applications) + count($externalApps);

// Determiner quelles categories ont des apps existantes
$activeCategories = [];
foreach ($categoriesDef as $catKey => $catDef) {
    if (!empty($categoryApps[$catKey])) {
        $count = 0;
        foreach ($categoryApps[$catKey] as $appKey) {
            if (in_array($appKey, $applications) || isset($externalApps[$appKey])) $count++;
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
                        <a href="shared-auth/logout-global.php" class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded text-sm transition">
                            <?= t('auth.logout') ?? 'Deconnexion' ?>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white font-semibold rounded-lg text-sm transition">
                            <?= t('auth.login') ?? 'Connexion' ?>
                        </a>
                        <a href="register.php" class="px-4 py-2 bg-white text-indigo-700 hover:bg-indigo-50 font-semibold rounded-lg text-sm transition">
                            <?= t('auth.register') ?>
                        </a>
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
                <span id="visibleCount"><?= $totalAppCount ?></span> / <?= $totalAppCount ?> <?= t('home.apps_available') ?>
            </p>
        </div>

        <!-- Info Panel -->
        <div class="mb-10">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Tabs -->
                <div class="flex border-b">
                    <button onclick="switchInfoTab('apps')" id="infoTab-apps"
                            class="flex-1 flex items-center justify-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 border-indigo-500 text-indigo-700 bg-indigo-50/50 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Nos applications
                    </button>
                    <button onclick="switchInfoTab('legal')" id="infoTab-legal"
                            class="flex-1 flex items-center justify-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Mentions legales & Vie privee
                    </button>
                </div>

                <!-- Tab: Applications -->
                <div id="infoContent-apps" class="p-6">
                    <p class="text-gray-600 mb-5">
                        Ces applications pedagogiques sont concues pour accompagner vos formations, ateliers et projets collaboratifs. Chaque outil est autonome et peut etre utilise independamment selon vos besoins.
                    </p>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php
                        // Grouper par categorie pour l'affichage
                        $appDescriptions = [
                            ['icon' => '&#x1F4CA;', 'color' => 'blue', 'apps' => [
                                ['name' => 'Analyse SWOT', 'desc' => 'Forces, faiblesses, opportunites et menaces de votre projet.'],
                                ['name' => 'Analyse PESTEL', 'desc' => 'Facteurs Politique, Economique, Social, Technologique, Environnemental et Legal.'],
                                ['name' => 'Arbre a Problemes', 'desc' => 'Analyse des causes et effets pour identifier les problemes racines.'],
                                ['name' => 'Parties Prenantes', 'desc' => 'Cartographie et analyse de l\'influence des acteurs de votre projet.'],
                            ]],
                            ['icon' => '&#x1F3AF;', 'color' => 'emerald', 'apps' => [
                                ['name' => 'Cadre Logique', 'desc' => 'Construction de cadres logiques pour structurer vos projets.'],
                                ['name' => 'Objectifs SMART', 'desc' => 'Definir des objectifs Specifiques, Mesurables, Atteignables, Realistes et Temporels.'],
                                ['name' => 'Pilotage de Projet', 'desc' => 'Des objectifs aux taches concretes, avec phases et points de controle.'],
                                ['name' => 'Cahier des Charges', 'desc' => 'Redaction collaborative de cahiers des charges.'],
                                ['name' => 'Carte Projet', 'desc' => 'Visualisation synthetique et planification de projets.'],
                            ]],
                            ['icon' => '&#x1F4AC;', 'color' => 'cyan', 'apps' => [
                                ['name' => 'Publics & Personas', 'desc' => 'Cartographie des parties prenantes et creation de personas.'],
                                ['name' => 'Journey Mapping', 'desc' => 'Cartographie des parcours et points de contact avec vos publics.'],
                                ['name' => 'Mini-Plan de Com\'', 'desc' => 'Plan de communication concret : objectif, public, message, canaux et calendrier.'],
                            ]],
                            ['icon' => '&#x1F916;', 'color' => 'violet', 'apps' => [
                                ['name' => 'Atelier IA', 'desc' => 'Decouverte et experimentation de l\'intelligence artificielle.'],
                                ['name' => 'Guide de Prompting', 'desc' => 'Guides personnalises pour mieux utiliser l\'IA generative.'],
                                ['name' => 'Prompt Jeunes', 'desc' => 'Atelier de prompt engineering adapte au public jeune.'],
                                ['name' => 'Inventaire Activites', 'desc' => 'Cartographie des activites d\'une association pour identifier le potentiel IA.'],
                            ]],
                            ['icon' => '&#x1F504;', 'color' => 'amber', 'apps' => [
                                ['name' => 'Mesure d\'Impact', 'desc' => 'Evaluation et suivi de l\'impact social de vos projets.'],
                                ['name' => 'Stop Start Continue', 'desc' => 'Retrospective : ce qu\'il faut arreter, commencer ou continuer.'],
                                ['name' => 'Craintes & Attentes', 'desc' => 'Recueil des craintes et attentes des participants.'],
                                ['name' => 'Methodes Agiles', 'desc' => 'Planification et retrospectives Agile/Scrum.'],
                            ]],
                            ['icon' => '&#x1F3A8;', 'color' => 'pink', 'apps' => [
                                ['name' => 'Carte Mentale', 'desc' => 'Creation collaborative de cartes mentales (mindmaps).'],
                                ['name' => 'Tableau Blanc', 'desc' => 'Tableau blanc collaboratif avec post-its, dessins et formes.'],
                                ['name' => 'Six Chapeaux', 'desc' => 'Technique de reflexion creative selon les 6 chapeaux de Bono.'],
                                ['name' => 'Empreinte Carbone', 'desc' => 'Analyse detaillee de l\'impact environnemental de vos activites.'],
                            ]],
                        ];
                        foreach ($appDescriptions as $group):
                        ?>
                        <?php foreach ($group['apps'] as $app): ?>
                        <div class="flex items-start gap-2 p-2.5 rounded-lg bg-<?= $group['color'] ?>-50/60 border border-<?= $group['color'] ?>-100">
                            <span class="text-base mt-0.5"><?= $group['icon'] ?></span>
                            <div>
                                <p class="text-sm font-semibold text-gray-800"><?= $app['name'] ?></p>
                                <p class="text-xs text-gray-500"><?= $app['desc'] ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tab: Legal -->
                <div id="infoContent-legal" class="p-6 hidden">
                    <div class="space-y-6">
                        <!-- RGPD -->
                        <div class="flex gap-4 p-5 bg-blue-50 rounded-xl border border-blue-100">
                            <div class="shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-blue-800 mb-1">Protection des donnees (RGPD)</h4>
                                <p class="text-sm text-blue-700">Ces applications collectent uniquement les donnees strictement necessaires a leur fonctionnement pedagogique (nom, prenom, reponses aux exercices). Aucune donnee n'est vendue ni partagee avec des tiers. Les donnees sont stockees sur un serveur securise et peuvent etre supprimees sur simple demande aupres du formateur. Conformement au RGPD, vous disposez d'un droit d'acces, de rectification et de suppression de vos donnees personnelles.</p>
                            </div>
                        </div>

                        <!-- Open Source -->
                        <div class="flex gap-4 p-5 bg-emerald-50 rounded-xl border border-emerald-100">
                            <div class="shrink-0 w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-emerald-800 mb-1">Applications Open Source</h4>
                                <p class="text-sm text-emerald-700">L'ensemble de ces applications est publie sous licence open source. Le code source est librement accessible, modifiable et redistribuable. Nous encourageons la communaute educative et associative a reutiliser, adapter et ameliorer ces outils selon leurs besoins.</p>
                            </div>
                        </div>

                        <!-- Limitation de responsabilite -->
                        <div class="flex gap-4 p-5 bg-amber-50 rounded-xl border border-amber-100">
                            <div class="shrink-0 w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-amber-800 mb-1">Limitation de responsabilite</h4>
                                <p class="text-sm text-amber-700">Ces applications sont fournies "en l'etat" a des fins pedagogiques et de formation. <strong>K1M - Comme un Mardi</strong> ne peut etre tenu responsable des resultats obtenus, des decisions prises ou des consequences decoulant de l'utilisation de ces outils. Les contenus generes par l'intelligence artificielle sont indicatifs et doivent toujours etre valides par un jugement humain.</p>
                            </div>
                        </div>

                        <!-- Cookies -->
                        <div class="flex gap-4 p-5 bg-gray-50 rounded-xl border border-gray-200">
                            <div class="shrink-0 w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">Cookies & Sessions</h4>
                                <p class="text-sm text-gray-600">Ces applications utilisent uniquement des cookies techniques necessaires a l'authentification et au bon fonctionnement (session utilisateur, langue preferee). Aucun cookie publicitaire ou de tracage n'est utilise. Aucun outil d'analyse de trafic tiers n'est installe.</p>
                            </div>
                        </div>

                        <!-- Contact -->
                        <div class="flex gap-4 p-5 bg-purple-50 rounded-xl border border-purple-100">
                            <div class="shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-purple-800 mb-1">Contact</h4>
                                <p class="text-sm text-purple-700">Pour toute question relative a la protection de vos donnees, pour exercer vos droits ou pour signaler un probleme, contactez <strong>K1M - Comme un Mardi</strong> via votre formateur ou par les canaux de communication habituels.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Filters -->
        <?php if (!empty($activeCategories)): ?>
        <div class="mb-8">
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" onclick="filterByCategory('all')" id="cat-btn-all"
                    class="cat-btn active inline-flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-md border-2 border-indigo-500 text-indigo-700 font-medium text-sm">
                    Toutes
                    <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= $totalAppCount ?></span>
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

            <?php // Applications externes
            foreach ($externalApps as $extKey => $extApp):
                $cats = $extApp['categories'];
                $catsJson = htmlspecialchars(json_encode($cats), ENT_QUOTES);
                $color = $extApp['color'] ?? 'indigo';
            ?>
            <a href="<?= htmlspecialchars($extApp['url']) ?>" target="_blank"
               class="app-card bg-white rounded-xl shadow-md overflow-hidden"
               data-categories="<?= $catsJson ?>"
               data-app="<?= $extKey ?>">
                <div class="h-2 bg-<?= $color ?>-500"></div>
                <div class="p-6">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($extApp['title']) ?></h2>
                        <span class="shrink-0 px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold border border-blue-200" title="Application externe">&#x1F517; Externe</span>
                    </div>
                    <?php if (!empty($extApp['description'])): ?>
                        <p class="text-gray-600 text-sm mb-3"><?= htmlspecialchars($extApp['description']) ?></p>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
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

        function switchInfoTab(tab) {
            // Toggle content
            document.getElementById('infoContent-apps').classList.toggle('hidden', tab !== 'apps');
            document.getElementById('infoContent-legal').classList.toggle('hidden', tab !== 'legal');

            // Toggle tab styles
            ['apps', 'legal'].forEach(t => {
                const btn = document.getElementById('infoTab-' + t);
                if (t === tab) {
                    btn.classList.add('border-indigo-500', 'text-indigo-700', 'bg-indigo-50/50');
                    btn.classList.remove('border-transparent', 'text-gray-500');
                } else {
                    btn.classList.remove('border-indigo-500', 'text-indigo-700', 'bg-indigo-50/50');
                    btn.classList.add('border-transparent', 'text-gray-500');
                }
            });
        }
    </script>
</body>
</html>
