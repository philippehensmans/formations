<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'L\'identifiant et le mot de passe sont obligatoires.';
    } elseif (strlen($username) < 3) {
        $error = 'L\'identifiant doit contenir au moins 3 caractères.';
    } elseif (strlen($password) < 4) {
        $error = 'Le mot de passe doit contenir au moins 4 caractères.';
    } elseif ($password !== $password_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Cet identifiant est déjà utilisé.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $email]);
            $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md">
        <h1 class="text-2xl font-bold text-center text-gray-800 mb-2">Inscription</h1>
        <p class="text-center text-gray-600 mb-6">Créez votre compte participant</p>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-center"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-center"><?= sanitize($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Identifiant *</label>
                <input type="text" name="username" value="<?= sanitize($_POST['username'] ?? '') ?>"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                       placeholder="Choisissez un identifiant" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Email <span class="text-gray-400 font-normal">(optionnel)</span></label>
                <input type="email" name="email" value="<?= sanitize($_POST['email'] ?? '') ?>"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                       placeholder="votre@email.com">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Mot de passe *</label>
                <input type="password" name="password"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                       placeholder="Minimum 4 caractères" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Confirmer le mot de passe *</label>
                <input type="password" name="password_confirm"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                       placeholder="Répétez le mot de passe" required>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">
                Créer mon compte
            </button>
        </form>

        <p class="text-center mt-6 text-gray-600">
            Déjà inscrit ? <a href="login.php" class="text-blue-600 font-semibold hover:underline">Se connecter</a>
        </p>
    </div>
</body>
</html>
