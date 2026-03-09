<?php
/**
 * Page de connexion globale
 * Permet de se connecter sans passer par une application specifique
 * Apres connexion, l'utilisateur est redirige vers la page d'accueil
 */

require_once __DIR__ . '/shared-auth/config.php';
require_once __DIR__ . '/shared-auth/lang.php';

$error = '';
$lang = getCurrentLanguage();

// Deja connecte ? Rediriger vers l'accueil
if (isLoggedIn() && getLoggedUser()) {
    header('Location: index.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = t('auth.fill_required');
    } else {
        $user = authenticateUser($username, $password);
        if ($user) {
            login($user);
            header('Location: index.php');
            exit;
        } else {
            $error = t('auth.login_error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('auth.login') ?> - Formation Interactive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-600 to-purple-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <img src="logo.png" alt="Logo" class="h-16 mx-auto mb-4">
            <h1 class="text-3xl font-bold text-white mb-2">Formation Interactive</h1>
            <p class="text-indigo-200"><?= t('common.interactive_training') ?></p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="flex justify-end mb-4">
                <?= renderLanguageSelector('text-sm border rounded px-2 py-1') ?>
            </div>

            <h2 class="text-xl font-semibold text-gray-800 mb-6 text-center"><?= t('auth.login') ?></h2>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.username') ?></label>
                    <input type="text" name="username" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                           placeholder="<?= t('auth.your_username') ?>"
                           value="<?= h($_POST['username'] ?? '') ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.password') ?></label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                           placeholder="<?= t('auth.your_password') ?>">
                    <div class="text-right mt-1">
                        <a href="shared-auth/forgot-password.php" class="text-sm text-indigo-600 hover:text-indigo-800">
                            <?= t('auth.forgot_password') ?>
                        </a>
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors">
                    <?= t('auth.connect') ?>
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-600">
                <?= t('auth.no_account') ?>
                <a href="register.php" class="text-indigo-600 hover:text-indigo-800 font-medium">
                    <?= t('auth.sign_up') ?>
                </a>
            </div>
        </div>

        <div class="mt-6 text-center">
            <a href="index.php" class="text-indigo-200 hover:text-white text-sm">
                <?= t('auth.back_to_home') ?? 'Toutes les applications' ?>
            </a>
        </div>
    </div>
    <?= renderLanguageScript() ?>
</body>
</html>
