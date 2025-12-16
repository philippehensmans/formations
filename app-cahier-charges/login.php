<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];

            header('Location: ' . ($user['is_admin'] ? 'admin.php' : 'index.php'));
            exit;
        } else {
            $error = 'Identifiant ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md">
        <h1 class="text-2xl font-bold text-center text-gray-800 mb-2">Cahier des Charges</h1>
        <p class="text-center text-gray-600 mb-6">Connectez-vous pour accéder à votre espace</p>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-center"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Identifiant</label>
                <input type="text" name="username" value="<?= sanitize($_POST['username'] ?? '') ?>"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                       placeholder="Votre identifiant" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Mot de passe</label>
                <input type="password" name="password"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                       placeholder="Votre mot de passe" required>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">
                Se connecter
            </button>
        </form>

        <p class="text-center mt-6 text-gray-600">
            Pas encore de compte ? <a href="register.php" class="text-blue-600 font-semibold hover:underline">S'inscrire</a>
        </p>
    </div>
</body>
</html>
