<?php
/**
 * Administration de la base shared-auth
 * Gestion des utilisateurs et affectation des formateurs aux sessions
 * Accessible uniquement par les super-admins
 */
require_once __DIR__ . '/config.php';

// Verifier super-admin (ou admin pour login initial)
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
        <title>Admin - Shared Auth</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-gray-900 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-6">
                <img src="../logo.png" alt="Logo" class="h-16 mx-auto mb-4">
                <h1 class="text-2xl font-bold text-gray-800">Administration</h1>
            </div>
            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= h($error) ?></div>
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

$db = getSharedDB();
$success = '';
$error = '';
$currentUser = getLoggedUser();
$isSuperAdmin = isSuperAdmin();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_user':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $email = trim($_POST['email'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $nom = trim($_POST['nom'] ?? '');
            $organisation = trim($_POST['organisation'] ?? '');
            $is_formateur = isset($_POST['is_formateur']) ? 1 : 0;
            $is_admin = ($isSuperAdmin && isset($_POST['is_admin'])) ? 1 : 0;

            if (empty($username) || empty($password)) {
                $error = 'Username et mot de passe requis.';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password, email, prenom, nom, organisation, is_formateur, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hash, $email, $prenom, $nom, $organisation, $is_formateur, $is_admin]);
                    $success = "Utilisateur '$username' cree.";
                } catch (PDOException $e) {
                    $error = "Erreur: " . ($e->getCode() == 23000 ? "Username deja utilise." : $e->getMessage());
                }
            }
            break;

        case 'update_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $nom = trim($_POST['nom'] ?? '');
            $organisation = trim($_POST['organisation'] ?? '');
            $is_formateur = isset($_POST['is_formateur']) ? 1 : 0;
            $is_admin = ($isSuperAdmin && isset($_POST['is_admin'])) ? 1 : 0;
            $newPassword = $_POST['new_password'] ?? '';

            // Non super-admin ne peut pas modifier is_admin
            if (!$isSuperAdmin) {
                $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $existingUser = $stmt->fetch();
                $is_admin = $existingUser['is_admin'] ?? 0;
            }

            if ($newPassword) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET email = ?, prenom = ?, nom = ?, organisation = ?, is_formateur = ?, is_admin = ?, password = ? WHERE id = ?");
                $stmt->execute([$email, $prenom, $nom, $organisation, $is_formateur, $is_admin, $hash, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET email = ?, prenom = ?, nom = ?, organisation = ?, is_formateur = ?, is_admin = ? WHERE id = ?");
                $stmt->execute([$email, $prenom, $nom, $organisation, $is_formateur, $is_admin, $userId]);
            }
            $success = "Utilisateur mis a jour.";
            break;

        case 'delete_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId == $currentUser['id']) {
                $error = "Impossible de supprimer votre propre compte.";
            } else {
                // Supprimer aussi les affectations de sessions
                $db->prepare("DELETE FROM formateur_sessions WHERE formateur_id = ?")->execute([$userId]);
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $success = "Utilisateur supprime.";
            }
            break;

        case 'assign_session':
            if ($isSuperAdmin) {
                $formateurId = (int)($_POST['formateur_id'] ?? 0);
                $appNames = $_POST['app_names'] ?? [];
                $sessionId = (int)($_POST['session_id'] ?? 0);
                if ($formateurId && !empty($appNames) && $sessionId) {
                    $assigned = 0;
                    foreach ($appNames as $appName) {
                        if (assignFormateurToSession($formateurId, $appName, $sessionId)) {
                            $assigned++;
                        }
                    }
                    if ($assigned > 0) {
                        $success = "$assigned application(s) affectee(s).";
                    } else {
                        $error = "Erreur lors de l'affectation.";
                    }
                }
            }
            break;

        case 'remove_session':
            if ($isSuperAdmin) {
                $formateurId = (int)($_POST['formateur_id'] ?? 0);
                $appName = $_POST['app_name'] ?? '';
                $sessionId = (int)($_POST['session_id'] ?? 0);
                if (removeFormateurFromSession($formateurId, $appName, $sessionId)) {
                    $success = "Affectation retiree.";
                }
            }
            break;

        case 'grant_app_access':
            if ($isSuperAdmin) {
                $appName = $_POST['app_name'] ?? '';
                $userIds = $_POST['user_ids'] ?? [];
                if ($appName && !empty($userIds)) {
                    $granted = 0;
                    foreach ($userIds as $uid) {
                        if (grantAppAccess($appName, (int)$uid, $currentUser['id'])) {
                            $granted++;
                        }
                    }
                    $success = "$granted acces accorde(s) pour " . (getRestrictedApps()[$appName] ?? $appName) . ".";
                }
            }
            break;

        case 'revoke_app_access':
            if ($isSuperAdmin) {
                $appName = $_POST['app_name'] ?? '';
                $userId = (int)($_POST['user_id'] ?? 0);
                if ($appName && $userId) {
                    revokeAppAccess($appName, $userId);
                    $success = "Acces revoque.";
                }
            }
            break;

        case 'logout':
            logout();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
    }
}

// Recuperer les utilisateurs
$users = $db->query("SELECT * FROM users ORDER BY username")->fetchAll();

// Liste des applications
$apps = [
    'app-activites' => 'Inventaire des Activités',
    'app-agile' => 'Gestion Agile',
    'app-arbreproblemes' => 'Arbre à Problèmes',
    'app-atelier-ia' => 'Atelier IA',
    'app-cadrelogique' => 'Cadre Logique',
    'app-cahier-charges' => 'Cahier des Charges',
    'app-calculateur-carbone' => 'Calculateur Carbone IA',
    'app-carte-identite' => 'Carte d\'Identité du Projet',
    'app-carte-projet' => 'Carte Projet',
    'app-empreinte-carbone' => 'Empreinte Carbone',
    'app-guide-prompting' => 'Guide Prompting',
    'app-mesure-impact' => 'Mesure d\'Impact',
    'app-mindmap' => 'Carte Mentale',
    'app-objectifs-smart' => 'Objectifs SMART',
    'app-parties-prenantes' => 'Parties Prenantes',
    'app-pestel' => 'Analyse PESTEL',
    'app-prompt-jeunes' => 'Prompt Engineering',
    'app-six-chapeaux' => 'Six Chapeaux',
    'app-stop-start-continue' => 'Stop Start Continue',
    'app-swot' => 'Analyse SWOT',
    'app-whiteboard' => 'Tableau Blanc',
    'app-journey-mapping' => 'Journey Mapping',
    'app-personas' => 'Publics & Personas',
    'app-comm-plan' => 'Mini-Plan de Communication',
    'app-pilotage-projet' => 'Pilotage de Projet'
];

// Recuperer les affectations de sessions pour l'affichage
$formateurAssignments = [];
if ($isSuperAdmin) {
    $allAssignments = $db->query("SELECT * FROM formateur_sessions ORDER BY formateur_id, app_name, session_id")->fetchAll();
    foreach ($allAssignments as $a) {
        $formateurAssignments[$a['formateur_id']][] = $a;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Shared Auth</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body class="min-h-screen bg-gray-100">
    <header class="bg-gray-800 text-white p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <img src="../logo.png" alt="Logo" class="h-10">
                <div>
                    <h1 class="text-xl font-bold">Administration Shared-Auth</h1>
                    <p class="text-sm text-gray-400">
                        Connecte: <?= h($currentUser['username']) ?>
                        <?php if ($isSuperAdmin): ?>
                            <span class="ml-2 px-2 py-0.5 bg-purple-600 rounded text-xs">Super-Admin</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="../admin-categories.php" class="px-4 py-2 bg-indigo-600 rounded hover:bg-indigo-500 text-sm">Gestion Categories</a>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600">Deconnexion</button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6">
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= h($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- Creer utilisateur -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Creer un utilisateur</h2>
            <form method="POST" class="grid md:grid-cols-7 gap-4 items-end">
                <input type="hidden" name="action" value="create_user">
                <input type="text" name="username" placeholder="Username *" required class="px-3 py-2 border rounded-lg">
                <input type="password" name="password" placeholder="Mot de passe *" required class="px-3 py-2 border rounded-lg">
                <input type="email" name="email" placeholder="Email" class="px-3 py-2 border rounded-lg">
                <input type="text" name="prenom" placeholder="Prenom" class="px-3 py-2 border rounded-lg">
                <input type="text" name="nom" placeholder="Nom" class="px-3 py-2 border rounded-lg">
                <input type="text" name="organisation" placeholder="Organisation" class="px-3 py-2 border rounded-lg">
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-1 text-sm">
                        <input type="checkbox" name="is_formateur"> Formateur
                    </label>
                    <?php if ($isSuperAdmin): ?>
                    <label class="flex items-center gap-1 text-sm">
                        <input type="checkbox" name="is_admin"> Admin
                    </label>
                    <?php endif; ?>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Creer</button>
                </div>
            </form>
        </div>

        <!-- Liste utilisateurs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="bg-gray-50 p-4 border-b font-semibold text-gray-700 flex justify-between items-center">
                <span>Utilisateurs (<?= count($users) ?>)</span>
                <button onclick="exportUsersToExcel()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm flex items-center gap-2">
                    <span>📊</span> Exporter Excel
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left p-3">ID</th>
                            <th class="text-left p-3">Username</th>
                            <th class="text-left p-3">Email</th>
                            <th class="text-left p-3">Prenom</th>
                            <th class="text-left p-3">Nom</th>
                            <th class="text-left p-3">Organisation</th>
                            <th class="text-center p-3">Formateur</th>
                            <?php if ($isSuperAdmin): ?>
                            <th class="text-center p-3">Admin</th>
                            <?php endif; ?>
                            <th class="text-center p-3">Consent</th>
                            <th class="text-left p-3">Cree le</th>
                            <th class="text-center p-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-50" id="user-<?= $user['id'] ?>">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <td class="p-3 text-gray-500">
                                    <?= $user['id'] ?>
                                    <?php if (!empty($user['is_super_admin'])): ?>
                                        <span class="ml-1 text-purple-600" title="Super-Admin">SA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 font-medium"><?= h($user['username']) ?></td>
                                <td class="p-3"><input type="email" name="email" value="<?= h($user['email'] ?? '') ?>" class="w-full px-2 py-1 border rounded text-sm" placeholder="email@..."></td>
                                <td class="p-3"><input type="text" name="prenom" value="<?= h($user['prenom'] ?? '') ?>" class="w-full px-2 py-1 border rounded text-sm"></td>
                                <td class="p-3"><input type="text" name="nom" value="<?= h($user['nom'] ?? '') ?>" class="w-full px-2 py-1 border rounded text-sm"></td>
                                <td class="p-3"><input type="text" name="organisation" value="<?= h($user['organisation'] ?? '') ?>" class="w-full px-2 py-1 border rounded text-sm"></td>
                                <td class="p-3 text-center"><input type="checkbox" name="is_formateur" <?= $user['is_formateur'] ? 'checked' : '' ?>></td>
                                <?php if ($isSuperAdmin): ?>
                                <td class="p-3 text-center">
                                    <input type="checkbox" name="is_admin" <?= $user['is_admin'] ? 'checked' : '' ?> <?= $user['is_super_admin'] ? 'disabled title="Super-admin"' : '' ?>>
                                </td>
                                <?php endif; ?>
                                <td class="p-3 text-center">
                                    <?php if (!empty($user['email_consent'])): ?>
                                        <span class="text-green-600" title="Consentement email">Oui</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">Non</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-gray-500 text-xs"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                <td class="p-3 text-center">
                                    <div class="flex gap-1 justify-center flex-wrap">
                                        <input type="password" name="new_password" placeholder="Nouveau mdp" class="w-20 px-2 py-1 border rounded text-xs">
                                        <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">Sauver</button>
                            </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet utilisateur?')">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700" <?= ($user['id'] == $currentUser['id'] || $user['is_super_admin']) ? 'disabled title="Impossible"' : '' ?>>Suppr</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($isSuperAdmin): ?>
        <!-- Affectation des formateurs aux sessions -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Affectation Formateurs aux Sessions</h2>
            <p class="text-sm text-gray-600 mb-4">
                Par defaut, les formateurs ont acces a toutes les sessions. En leur affectant des sessions specifiques, ils ne verront que celles-ci.
            </p>

            <?php
            $formateurs = array_filter($users, function($u) { return $u['is_formateur'] && !$u['is_super_admin']; });
            ?>

            <?php if (empty($formateurs)): ?>
                <p class="text-gray-500 italic">Aucun formateur a configurer.</p>
            <?php else: ?>
                <?php foreach ($formateurs as $formateur): ?>
                <div class="border rounded-lg p-4 mb-4">
                    <h3 class="font-medium text-gray-800 mb-2">
                        <?= h($formateur['prenom'] . ' ' . $formateur['nom']) ?>
                        <span class="text-gray-500 font-normal">(<?= h($formateur['username']) ?>)</span>
                    </h3>

                    <!-- Affectations actuelles -->
                    <?php if (!empty($formateurAssignments[$formateur['id']])): ?>
                    <div class="mb-3">
                        <span class="text-sm text-gray-600">Sessions affectees:</span>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <?php foreach ($formateurAssignments[$formateur['id']] as $assignment): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="remove_session">
                                <input type="hidden" name="formateur_id" value="<?= $formateur['id'] ?>">
                                <input type="hidden" name="app_name" value="<?= h($assignment['app_name']) ?>">
                                <input type="hidden" name="session_id" value="<?= $assignment['session_id'] ?>">
                                <button type="submit" class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-red-100 hover:text-red-800" title="Cliquer pour retirer">
                                    <?= h($apps[$assignment['app_name']] ?? $assignment['app_name']) ?> #<?= $assignment['session_id'] ?>
                                    <span class="ml-1">x</span>
                                </button>
                            </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-sm text-green-600 mb-3">Acces a toutes les sessions (aucune restriction)</p>
                    <?php endif; ?>

                    <!-- Ajouter une affectation -->
                    <form method="POST" class="space-y-2">
                        <input type="hidden" name="action" value="assign_session">
                        <input type="hidden" name="formateur_id" value="<?= $formateur['id'] ?>">
                        <div class="flex gap-2 items-center">
                            <select name="app_names[]" multiple class="px-3 py-1 border rounded text-sm min-w-48 h-32">
                                <?php foreach ($apps as $appKey => $appLabel): ?>
                                <option value="<?= h($appKey) ?>"><?= h($appLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="flex flex-col gap-1">
                                <input type="number" name="session_id" placeholder="ID Session" required class="w-24 px-2 py-1 border rounded text-sm">
                                <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Affecter</button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">Ctrl+clic pour selectionner plusieurs apps</p>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($isSuperAdmin): ?>
        <!-- Controle d'acces aux applications restreintes (IA) -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-2">Controle d'acces - Applications IA</h2>
            <p class="text-sm text-gray-600 mb-4">
                Les applications utilisant l'API Claude sont restreintes pour eviter l'abus d'usage.
                Les <strong>formateurs</strong> et <strong>super-admins</strong> y ont toujours acces.
                Autorisez ici les participants qui peuvent les utiliser.
            </p>

            <?php
            $restrictedApps = getRestrictedApps();
            $regularUsers = array_filter($users, function($u) { return !$u['is_formateur'] && !$u['is_super_admin']; });
            ?>

            <?php foreach ($restrictedApps as $rAppKey => $rAppLabel): ?>
            <div class="border rounded-lg p-4 mb-4">
                <h3 class="font-medium text-gray-800 mb-3 flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded text-xs font-bold">RESTREINT</span>
                    <?= h($rAppLabel) ?>
                </h3>

                <!-- Utilisateurs autorises actuellement -->
                <?php $accessList = getAppAccessList($rAppKey); ?>
                <?php if (!empty($accessList)): ?>
                <div class="mb-3">
                    <span class="text-sm text-gray-600 font-medium">Participants autorises:</span>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <?php foreach ($accessList as $access): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="revoke_app_access">
                            <input type="hidden" name="app_name" value="<?= h($rAppKey) ?>">
                            <input type="hidden" name="user_id" value="<?= $access['user_id'] ?>">
                            <button type="submit" class="px-3 py-1.5 bg-green-100 text-green-800 rounded-lg text-sm hover:bg-red-100 hover:text-red-800 transition flex items-center gap-1" title="Cliquer pour revoquer">
                                <span class="font-medium"><?= h($access['prenom'] . ' ' . $access['nom']) ?></span>
                                <span class="text-xs text-gray-500">(<?= h($access['username']) ?>)</span>
                                <span class="ml-1 text-red-400">x</span>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-sm text-amber-600 mb-3">Aucun participant autorise. Seuls les formateurs et super-admins ont acces.</p>
                <?php endif; ?>

                <!-- Ajouter des acces -->
                <?php if (!empty($regularUsers)): ?>
                <form method="POST" class="mt-3 pt-3 border-t">
                    <input type="hidden" name="action" value="grant_app_access">
                    <input type="hidden" name="app_name" value="<?= h($rAppKey) ?>">
                    <div class="flex gap-3 items-end">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Autoriser des participants</label>
                            <select name="user_ids[]" multiple class="w-full px-3 py-2 border rounded-lg text-sm h-28">
                                <?php
                                $existingIds = array_column($accessList, 'user_id');
                                foreach ($regularUsers as $ru):
                                    if (in_array($ru['id'], $existingIds)) continue;
                                ?>
                                <option value="<?= $ru['id'] ?>">
                                    <?= h($ru['prenom'] . ' ' . $ru['nom']) ?> (<?= h($ru['username']) ?>)
                                    <?= $ru['organisation'] ? '- ' . h($ru['organisation']) : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Ctrl+clic pour selectionner plusieurs</p>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                            Autoriser
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <p class="text-xs text-gray-400 mt-2">Aucun participant enregistre. Les utilisateurs doivent d'abord creer un compte.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Info base de donnees -->
        <div class="text-sm text-gray-500">
            <p>Base de donnees: <?= realpath(__DIR__ . '/data/users.sqlite') ?: __DIR__ . '/data/users.sqlite' ?></p>
        </div>
    </main>

    <script>
        // Donnees utilisateurs pour export
        const usersData = <?= json_encode(array_map(function($u) {
            return [
                'id' => $u['id'],
                'username' => $u['username'],
                'email' => $u['email'] ?? '',
                'prenom' => $u['prenom'] ?? '',
                'nom' => $u['nom'] ?? '',
                'organisation' => $u['organisation'] ?? '',
                'is_formateur' => $u['is_formateur'] ? 'Oui' : 'Non',
                'is_admin' => $u['is_admin'] ? 'Oui' : 'Non',
                'is_super_admin' => $u['is_super_admin'] ? 'Oui' : 'Non',
                'email_consent' => !empty($u['email_consent']) ? 'Oui' : 'Non',
                'created_at' => $u['created_at'] ?? '',
                'last_login' => $u['last_login'] ?? ''
            ];
        }, $users)) ?>;

        function exportUsersToExcel() {
            const wb = XLSX.utils.book_new();

            // Feuille des utilisateurs
            const headers = [
                ['EXPORT UTILISATEURS - SHARED AUTH'],
                ['Date export: ' + new Date().toLocaleDateString('fr-FR')],
                [],
                ['ID', 'Username', 'Email', 'Prenom', 'Nom', 'Organisation', 'Formateur', 'Admin', 'Super-Admin', 'Consent Email', 'Cree le', 'Derniere connexion']
            ];

            const data = usersData.map(u => [
                u.id,
                u.username,
                u.email,
                u.prenom,
                u.nom,
                u.organisation,
                u.is_formateur,
                u.is_admin,
                u.is_super_admin,
                u.email_consent,
                u.created_at,
                u.last_login
            ]);

            const wsData = [...headers, ...data];
            const ws = XLSX.utils.aoa_to_sheet(wsData);

            // Ajuster la largeur des colonnes
            ws['!cols'] = [
                { wch: 5 },   // ID
                { wch: 20 },  // Username
                { wch: 30 },  // Email
                { wch: 15 },  // Prenom
                { wch: 15 },  // Nom
                { wch: 25 },  // Organisation
                { wch: 10 },  // Formateur
                { wch: 8 },   // Admin
                { wch: 12 },  // Super-Admin
                { wch: 12 },  // Consent
                { wch: 18 },  // Cree le
                { wch: 18 }   // Derniere connexion
            ];

            XLSX.utils.book_append_sheet(wb, ws, 'Utilisateurs');

            // Telecharger
            const filename = `utilisateurs_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, filename);
        }
    </script>
</body>
</html>
