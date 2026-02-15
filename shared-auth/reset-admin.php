<?php
/**
 * Reinitialisation du mot de passe super-admin
 *
 * Securite: necessite le mot de passe maitre (ADMIN_PASSWORD) defini dans config.php
 * Cela prouve que l'utilisateur a acces aux fichiers du serveur.
 *
 * Usage: acceder a /shared-auth/reset-admin.php via le navigateur
 */
require_once __DIR__ . '/config.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $masterPassword = $_POST['master_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($masterPassword !== ADMIN_PASSWORD) {
        $error = 'Mot de passe maitre incorrect.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Le nouveau mot de passe doit contenir au moins 6 caracteres.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $db = getSharedDB();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = 'formateur'");
        $stmt->execute([$hash]);

        if ($stmt->rowCount() > 0) {
            // Verification immediate : relire le hash et tester
            $check = $db->query("SELECT id, username, is_admin, is_super_admin, password FROM users WHERE username = 'formateur'")->fetch();
            if ($check && password_verify($newPassword, $check['password'])) {
                $success = 'Mot de passe du compte "formateur" reinitialise avec succes.'
                    . ' (is_admin=' . $check['is_admin'] . ', is_super_admin=' . $check['is_super_admin'] . ')'
                    . ' Base: ' . realpath(SHARED_DB_PATH);
            } else {
                $error = 'Le mot de passe a ete mis a jour mais la verification echoue. Contactez le support.';
            }
        } else {
            $error = 'Compte "formateur" introuvable dans la base: ' . realpath(SHARED_DB_PATH);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reinitialisation Super-Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-6">
            <img src="../logo.png" alt="Logo" class="h-16 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-gray-800">Reinitialisation Super-Admin</h1>
            <p class="text-sm text-gray-500 mt-2">
                Entrez le mot de passe maitre (ADMIN_PASSWORD de config.php) pour reinitialiser le mot de passe du compte super-admin.
            </p>
        </div>

        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                <?= h($success) ?>
                <div class="mt-2">
                    <a href="admin.php" class="text-green-800 underline font-medium">Aller a l'administration</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe maitre</label>
                <input type="password" name="master_password" required
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                       placeholder="ADMIN_PASSWORD de config.php">
                <p class="text-xs text-gray-400 mt-1">Celui defini dans shared-auth/config.php</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
                <input type="password" name="new_password" required minlength="6"
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                       placeholder="Minimum 6 caracteres">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
                <input type="password" name="confirm_password" required minlength="6"
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>
            <button type="submit" class="w-full py-2 bg-purple-700 text-white rounded-lg hover:bg-purple-600 font-medium">
                Reinitialiser le mot de passe
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="admin.php" class="text-sm text-gray-500 hover:text-gray-700">Retour a l'administration</a>
        </div>
    </div>
</body>
</html>
