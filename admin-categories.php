<?php
/**
 * Administration des categories d'applications
 * Accessible uniquement par les super-admins
 *
 * Permet de :
 * - Creer, modifier, supprimer des categories
 * - Affecter/retirer des applications aux categories
 */
require_once __DIR__ . '/shared-auth/config.php';
require_once __DIR__ . '/shared-auth/lang.php';

$jsonPath = __DIR__ . '/categories.json';

// Verifier super-admin
if (!isLoggedIn() || !isAdmin()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = authenticateUser($username, $password);
        if ($user && $user['is_admin']) {
            login($user);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        $error = 'Acces refuse. Compte admin requis.';
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Categories</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-gray-900 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-6">
                <img src="logo.png" alt="Logo" class="h-16 mx-auto mb-4">
                <h1 class="text-2xl font-bold text-gray-800">Admin Categories</h1>
            </div>
            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Identifiant admin</label>
                    <input type="text" name="username" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                    <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <button type="submit" class="w-full py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-700">Connexion</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$currentUser = getLoggedUser();

// Charger les donnees
function loadCategories() {
    global $jsonPath;
    if (file_exists($jsonPath)) {
        $data = json_decode(file_get_contents($jsonPath), true);
        if ($data && isset($data['categories'])) {
            return $data;
        }
    }
    return ['categories' => [], 'apps' => []];
}

function saveCategories($data) {
    global $jsonPath;
    return file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Detecter les applications existantes
function getInstalledApps() {
    $apps = [];
    $dirs = glob(__DIR__ . '/app-*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $appKey = basename($dir);
        if (file_exists($dir . '/login.php') || file_exists($dir . '/app.php')) {
            $apps[] = $appKey;
        }
    }
    sort($apps);
    return $apps;
}

// Couleurs Tailwind disponibles
$availableColors = [
    'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal',
    'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose', 'gray'
];

$success = '';
$error = '';

// Traiter les actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $data = loadCategories();

    switch ($action) {
        case 'add_category':
            $key = trim($_POST['key'] ?? '');
            $label = trim($_POST['label'] ?? '');
            $color = trim($_POST['color'] ?? 'blue');
            $icon = trim($_POST['icon'] ?? '');

            if (empty($key) || empty($label)) {
                $error = 'L\'identifiant et le label sont obligatoires.';
            } elseif (!preg_match('/^[a-z0-9_]+$/', $key)) {
                $error = 'L\'identifiant ne peut contenir que des lettres minuscules, chiffres et underscores.';
            } elseif (isset($data['categories'][$key])) {
                $error = 'Une categorie avec cet identifiant existe deja.';
            } else {
                $data['categories'][$key] = [
                    'label' => $label,
                    'color' => $color,
                    'icon' => $icon ?: "\xF0\x9F\x93\x8C" // default: pushpin
                ];
                if (saveCategories($data)) {
                    $success = "Categorie \"$label\" creee.";
                } else {
                    $error = 'Erreur lors de l\'ecriture du fichier.';
                }
            }
            break;

        case 'update_category':
            $key = $_POST['key'] ?? '';
            $label = trim($_POST['label'] ?? '');
            $color = trim($_POST['color'] ?? 'blue');
            $icon = trim($_POST['icon'] ?? '');

            if (!isset($data['categories'][$key])) {
                $error = 'Categorie introuvable.';
            } elseif (empty($label)) {
                $error = 'Le label est obligatoire.';
            } else {
                $data['categories'][$key]['label'] = $label;
                $data['categories'][$key]['color'] = $color;
                if (!empty($icon)) {
                    $data['categories'][$key]['icon'] = $icon;
                }
                if (saveCategories($data)) {
                    $success = "Categorie \"$label\" mise a jour.";
                } else {
                    $error = 'Erreur lors de l\'ecriture du fichier.';
                }
            }
            break;

        case 'delete_category':
            $key = $_POST['key'] ?? '';
            if (!isset($data['categories'][$key])) {
                $error = 'Categorie introuvable.';
            } else {
                $label = $data['categories'][$key]['label'];
                unset($data['categories'][$key]);
                // Retirer cette categorie de toutes les apps
                foreach ($data['apps'] as $appKey => &$cats) {
                    $cats = array_values(array_filter($cats, function($c) use ($key) { return $c !== $key; }));
                }
                unset($cats);
                if (saveCategories($data)) {
                    $success = "Categorie \"$label\" supprimee.";
                } else {
                    $error = 'Erreur lors de l\'ecriture du fichier.';
                }
            }
            break;

        case 'update_app_categories':
            $appKey = $_POST['app_key'] ?? '';
            $selectedCats = $_POST['categories'] ?? [];
            if (!empty($appKey)) {
                $data['apps'][$appKey] = array_values($selectedCats);
                // Nettoyer les apps sans categories
                if (empty($data['apps'][$appKey])) {
                    unset($data['apps'][$appKey]);
                }
                if (saveCategories($data)) {
                    $success = "Categories de \"$appKey\" mises a jour.";
                } else {
                    $error = 'Erreur lors de l\'ecriture du fichier.';
                }
            }
            break;

        case 'logout':
            logout();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
    }
}

// Recharger les donnees
$data = loadCategories();
$categories = $data['categories'] ?? [];
$appCategories = $data['apps'] ?? [];
$installedApps = getInstalledApps();

// Noms lisibles des apps (depuis les traductions)
function getAppLabel($appKey) {
    $appId = str_replace('app-', '', $appKey);
    $title = t('apps.' . $appId . '.title');
    if ($title === 'apps.' . $appId . '.title') {
        return ucfirst(str_replace('-', ' ', $appId));
    }
    return $title;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gestion des Categories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .color-swatch {
            width: 24px; height: 24px; border-radius: 50%;
            display: inline-block; cursor: pointer;
            transition: transform 0.15s;
            border: 2px solid transparent;
        }
        .color-swatch:hover { transform: scale(1.2); }
        .color-swatch.selected { border-color: #1f2937; transform: scale(1.2); }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
    <header class="bg-gray-800 text-white p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <img src="logo.png" alt="Logo" class="h-10">
                <div>
                    <h1 class="text-xl font-bold">Gestion des Categories</h1>
                    <p class="text-sm text-gray-400">
                        Connecte: <?= htmlspecialchars($currentUser['username']) ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="shared-auth/admin.php" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 text-sm">Admin utilisateurs</a>
                <a href="index.php" class="px-4 py-2 bg-indigo-600 rounded hover:bg-indigo-500 text-sm">Voir la page d'accueil</a>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 text-sm">Deconnexion</button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6">
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-2 gap-6">
            <!-- ============================================ -->
            <!-- SECTION GAUCHE : Gestion des categories      -->
            <!-- ============================================ -->
            <div>
                <!-- Ajouter une categorie -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h2 class="font-semibold text-gray-800 mb-4 text-lg">Nouvelle categorie</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_category">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Identifiant *</label>
                                <input type="text" name="key" required pattern="[a-z0-9_]+"
                                    class="w-full px-3 py-2 border rounded-lg text-sm"
                                    placeholder="ex: gestion_projet">
                                <p class="text-xs text-gray-500 mt-1">Minuscules, chiffres, underscores</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Label *</label>
                                <input type="text" name="label" required
                                    class="w-full px-3 py-2 border rounded-lg text-sm"
                                    placeholder="ex: Gestion de projet">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Icone (emoji)</label>
                                <input type="text" name="icon"
                                    class="w-full px-3 py-2 border rounded-lg text-sm"
                                    placeholder="ex: &#x1F4CB;">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Couleur *</label>
                                <select name="color" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                                    <?php foreach ($availableColors as $c): ?>
                                    <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                            Creer la categorie
                        </button>
                    </form>
                </div>

                <!-- Liste des categories existantes -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-gray-50 p-4 border-b font-semibold text-gray-700">
                        Categories existantes (<?= count($categories) ?>)
                    </div>
                    <?php if (empty($categories)): ?>
                        <div class="p-8 text-center text-gray-500">
                            Aucune categorie definie.
                        </div>
                    <?php else: ?>
                        <div class="divide-y">
                            <?php foreach ($categories as $catKey => $catDef):
                                // Compter les apps dans cette categorie
                                $appCount = 0;
                                foreach ($appCategories as $appKey => $cats) {
                                    if (in_array($catKey, $cats) && in_array($appKey, $installedApps)) $appCount++;
                                }
                            ?>
                            <div class="p-4" id="cat-<?= $catKey ?>">
                                <form method="POST" class="space-y-3">
                                    <input type="hidden" name="action" value="update_category">
                                    <input type="hidden" name="key" value="<?= htmlspecialchars($catKey) ?>">

                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <span class="text-2xl"><?= $catDef['icon'] ?? '' ?></span>
                                            <div>
                                                <span class="font-medium text-gray-800"><?= htmlspecialchars($catDef['label']) ?></span>
                                                <span class="text-xs text-gray-500 ml-2">(<?= htmlspecialchars($catKey) ?>)</span>
                                            </div>
                                            <span class="px-2 py-0.5 bg-<?= $catDef['color'] ?>-100 text-<?= $catDef['color'] ?>-700 rounded text-xs font-medium border border-<?= $catDef['color'] ?>-200">
                                                <?= $appCount ?> app(s)
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-4 gap-3 items-end">
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Label</label>
                                            <input type="text" name="label" value="<?= htmlspecialchars($catDef['label']) ?>" required
                                                class="w-full px-2 py-1 border rounded text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Icone</label>
                                            <input type="text" name="icon" value="<?= htmlspecialchars($catDef['icon'] ?? '') ?>"
                                                class="w-full px-2 py-1 border rounded text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Couleur</label>
                                            <select name="color" class="w-full px-2 py-1 border rounded text-sm bg-white">
                                                <?php foreach ($availableColors as $c): ?>
                                                <option value="<?= $c ?>" <?= $catDef['color'] === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="flex gap-1">
                                            <button type="submit" class="px-3 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">Sauver</button>
                                </form>
                                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer la categorie \'<?= htmlspecialchars(addslashes($catDef['label'])) ?>\' ? Elle sera retiree de toutes les applications.')">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="key" value="<?= htmlspecialchars($catKey) ?>">
                                                <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700">Suppr</button>
                                            </form>
                                        </div>
                                    </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION DROITE : Affectation des apps        -->
            <!-- ============================================ -->
            <div>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-gray-50 p-4 border-b font-semibold text-gray-700">
                        Applications et leurs categories (<?= count($installedApps) ?>)
                    </div>
                    <div class="divide-y">
                        <?php foreach ($installedApps as $appKey):
                            $appLabel = getAppLabel($appKey);
                            $currentCats = $appCategories[$appKey] ?? [];
                        ?>
                        <div class="p-4 hover:bg-gray-50">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_app_categories">
                                <input type="hidden" name="app_key" value="<?= htmlspecialchars($appKey) ?>">

                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($appLabel) ?></span>
                                        <span class="text-xs text-gray-500 ml-1">(<?= htmlspecialchars($appKey) ?>)</span>
                                    </div>
                                    <button type="submit" class="px-3 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700">
                                        Sauver
                                    </button>
                                </div>

                                <?php if (!empty($categories)): ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($categories as $catKey => $catDef): ?>
                                    <label class="inline-flex items-center gap-1 px-2 py-1 rounded border cursor-pointer text-xs
                                        <?= in_array($catKey, $currentCats) ? 'bg-' . $catDef['color'] . '-100 border-' . $catDef['color'] . '-300 text-' . $catDef['color'] . '-800' : 'bg-gray-50 border-gray-200 text-gray-600' ?>">
                                        <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($catKey) ?>"
                                            <?= in_array($catKey, $currentCats) ? 'checked' : '' ?>
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            onchange="this.form.submit()">
                                        <?= $catDef['icon'] ?? '' ?> <?= htmlspecialchars($catDef['label']) ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-sm text-gray-400 italic">Creez d'abord des categories.</p>
                                <?php endif; ?>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info fichier -->
        <div class="mt-6 text-sm text-gray-500">
            <p>Fichier de configuration: <?= realpath($jsonPath) ?: $jsonPath ?></p>
        </div>
    </main>
</body>
</html>
