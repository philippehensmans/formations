<?php
/**
 * Page Infos - Presentation des applications et mentions legales
 */

require_once __DIR__ . '/shared-auth/config.php';
require_once __DIR__ . '/shared-auth/lang.php';

$user = isLoggedIn() ? getLoggedUser() : null;

// Onglet actif via parametre URL
$activeTab = ($_GET['tab'] ?? 'apps') === 'legal' ? 'legal' : 'apps';
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Infos - <?= t('home.title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <a href="index.php" class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded text-sm transition">
                        Accueil
                    </a>
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
    <main class="max-w-5xl mx-auto px-4 py-12">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Tabs -->
            <div class="flex border-b">
                <a href="?tab=apps" id="infoTab-apps"
                   class="flex-1 flex items-center justify-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition <?= $activeTab === 'apps' ? 'border-indigo-500 text-indigo-700 bg-indigo-50/50' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Nos applications
                </a>
                <a href="?tab=legal" id="infoTab-legal"
                   class="flex-1 flex items-center justify-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition <?= $activeTab === 'legal' ? 'border-indigo-500 text-indigo-700 bg-indigo-50/50' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Mentions legales & Vie privee
                </a>
            </div>

            <?php if ($activeTab === 'apps'): ?>
            <!-- Tab: Applications -->
            <div class="p-6">
                <p class="text-gray-600 mb-5">
                    Ces applications pedagogiques sont concues pour accompagner vos formations, ateliers et projets collaboratifs. Chaque outil est autonome et peut etre utilise independamment selon vos besoins.
                </p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php
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

            <?php else: ?>
            <!-- Tab: Legal -->
            <div class="p-6">
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
            <?php endif; ?>
        </div>

        <div class="mt-8 text-center">
            <a href="index.php" class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Retour a l'accueil
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-400 mt-16 py-8">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p><?= t('home.footer') ?></p>
        </div>
    </footer>

    <?= renderLanguageScript() ?>
</body>
</html>
