<?php
/**
 * Administration de la base shared-auth
 * Gestion des utilisateurs
 */
require_once __DIR__ . '/config.php';

// Verifier admin
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
            <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">Administration</h1>
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

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_user':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $prenom = trim($_POST['prenom'] ?? '');
            $nom = trim($_POST['nom'] ?? '');
            $organisation = trim($_POST['organisation'] ?? '');
            $is_formateur = isset($_POST['is_formateur']) ? 1 : 0;
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;

            if (empty($username) || empty($password)) {
                $error = 'Username et mot de passe requis.';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password, prenom, nom, organisation, is_formateur, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hash, $prenom, $nom, $organisation, $is_formateur, $is_admin]);
                    $success = "Utilisateur '$username' cree.";
                } catch (PDOException $e) {
                    $error = "Erreur: " . ($e->getCode() == 23000 ? "Username deja utilise." : $e->getMessage());
                }
            }
            break;

        case 'update_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            $prenom = trim($_POST['prenom'] ?? '');
            $nom = trim($_POST['nom'] ?? '');
            $organisation = trim($_POST['organisation'] ?? '');
            $is_formateur = isset($_POST['is_formateur']) ? 1 : 0;
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $newPassword = $_POST['new_password'] ?? '';

            if ($newPassword) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET prenom = ?, nom = ?, organisation = ?, is_formateur = ?, is_admin = ?, password = ? WHERE id = ?");
                $stmt->execute([$prenom, $nom, $organisation, $is_formateur, $is_admin, $hash, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET prenom = ?, nom = ?, organisation = ?, is_formateur = ?, is_admin = ? WHERE id = ?");
                $stmt->execute([$prenom, $nom, $organisation, $is_formateur, $is_admin, $userId]);
            }
            $success = "Utilisateur mis a jour.";
            break;

        case 'delete_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            $currentUser = getLoggedUser();
            if ($userId == $currentUser['id']) {
                $error = "Impossible de supprimer votre propre compte.";
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $success = "Utilisateur supprime.";
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
$currentUser = getLoggedUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Shared Auth</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100">
    <header class="bg-gray-800 text-white p-4">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold">Administration Shared-Auth</h1>
                <p class="text-sm text-gray-400">Connecte: <?= h($currentUser['username']) ?></p>
            </div>
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600">Deconnexion</button>
            </form>
        </div>
    </header>

    <main class="max-w-6xl mx-auto p-6">
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= h($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- Creer utilisateur -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Creer un utilisateur</h2>
            <form method="POST" class="grid md:grid-cols-6 gap-4">
                <input type="hidden" name="action" value="create_user">
                <input type="text" name="username" placeholder="Username *" required class="px-3 py-2 border rounded-lg">
                <input type="password" name="password" placeholder="Mot de passe *" required class="px-3 py-2 border rounded-lg">
                <input type="text" name="prenom" placeholder="Prenom" class="px-3 py-2 border rounded-lg">
                <input type="text" name="nom" placeholder="Nom" class="px-3 py-2 border rounded-lg">
                <input type="text" name="organisation" placeholder="Organisation" class="px-3 py-2 border rounded-lg">
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-1 text-sm">
                        <input type="checkbox" name="is_formateur"> Formateur
                    </label>
                    <label class="flex items-center gap-1 text-sm">
                        <input type="checkbox" name="is_admin"> Admin
                    </label>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Creer</button>
                </div>
            </form>
        </div>

        <!-- Liste utilisateurs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="bg-gray-50 p-4 border-b font-semibold text-gray-700">
                Utilisateurs (<?= count($users) ?>)
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left p-3">ID</th>
                            <th class="text-left p-3">Username</th>
                            <th class="text-left p-3">Prenom</th>
                            <th class="text-left p-3">Nom</th>
                            <th class="text-left p-3">Organisation</th>
                            <th class="text-center p-3">Formateur</th>
                            <th class="text-center p-3">Admin</th>
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
                                <td class="p-3 text-gray-500"><?= $user['id'] ?></td>
                                <td class="p-3 font-medium"><?= h($user['username']) ?></td>
                                <td class="p-3"><input type="text" name="prenom" value="<?= h($user['prenom'] ?? '') ?>" class="w-full px-2 py-1 border rounded text-sm"></td>
                                <td class="p-3"><input type="text" name="nom" value="<?= h($user['nom'] ?? '') ?>" class="w-full px-2 py-1 border rounded text-sm"></td>
                                <td class="p-3"><input type="text" name="organisation" value="<?= h($user['organisation'] ?? '') ?>" class="w-full px-2 py-1 border rounded text-sm"></td>
                                <td class="p-3 text-center"><input type="checkbox" name="is_formateur" <?= $user['is_formateur'] ? 'checked' : '' ?>></td>
                                <td class="p-3 text-center"><input type="checkbox" name="is_admin" <?= $user['is_admin'] ? 'checked' : '' ?>></td>
                                <td class="p-3 text-gray-500 text-xs"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                <td class="p-3 text-center">
                                    <div class="flex gap-1 justify-center">
                                        <input type="password" name="new_password" placeholder="Nouveau mdp" class="w-24 px-2 py-1 border rounded text-xs">
                                        <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">Sauver</button>
                            </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet utilisateur?')">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700" <?= $user['id'] == $currentUser['id'] ? 'disabled title="Impossible de supprimer votre compte"' : '' ?>>Suppr</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info base de donnees -->
        <div class="mt-6 text-sm text-gray-500">
            <p>Base de donnees: <?= realpath(__DIR__ . '/data/users.sqlite') ?></p>
        </div>
    </main>
</body>
</html>
