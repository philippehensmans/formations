<?php
/**
 * Template de page d'inscription partagee
 *
 * Variables a definir avant d'inclure ce template:
 * - $appName : Nom de l'application
 * - $appColor : Couleur principale (ex: 'blue', 'green', 'purple')
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lang.php';

$error = '';
$success = '';
$appColor = $appColor ?? 'blue';
$lang = getCurrentLanguage();

// Si deja connecte, rediriger
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $organisation = trim($_POST['organisation'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $emailConsent = isset($_POST['email_consent']) ? 1 : 0;

    if (empty($username) || empty($password)) {
        $error = t('auth.fill_required');
    } elseif (strlen($username) < 3) {
        $error = t('auth.username_min_length');
    } elseif (strlen($password) < 4) {
        $error = t('auth.password_min_length');
    } elseif ($password !== $confirm) {
        $error = t('auth.password_mismatch');
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('auth.invalid_email');
    } else {
        $result = registerUser($username, $password, $prenom, $nom, $organisation, $email, $emailConsent);
        if ($result['success']) {
            $success = t('auth.register_success');
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('auth.register') ?> - <?= h($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-<?= $appColor ?>-600 to-<?= $appColor ?>-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <img src="../logo.png" alt="Logo" class="h-16 mx-auto mb-4">
            <h1 class="text-3xl font-bold text-white mb-2"><?= h($appName) ?></h1>
            <p class="text-<?= $appColor ?>-200"><?= t('auth.create_account') ?></p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8">
            <!-- Selecteur de langue -->
            <div class="flex justify-end mb-4">
                <?= renderLanguageSelector('text-sm border rounded px-2 py-1') ?>
            </div>

            <h2 class="text-xl font-semibold text-gray-800 mb-6 text-center"><?= t('auth.register') ?></h2>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                    <?= h($success) ?>
                    <a href="login.php" class="block mt-2 font-semibold underline"><?= t('auth.connect') ?></a>
                </div>
            <?php else: ?>
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.firstname') ?></label>
                        <input type="text" name="prenom"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500"
                               placeholder="Marie"
                               value="<?= h($_POST['prenom'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.lastname') ?></label>
                        <input type="text" name="nom"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500"
                               placeholder="Dupont"
                               value="<?= h($_POST['nom'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.organisation') ?></label>
                    <input type="text" name="organisation"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500"
                           value="<?= h($_POST['organisation'] ?? '') ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.email_address') ?></label>
                    <input type="email" name="email"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500"
                           placeholder="votre@email.com"
                           value="<?= h($_POST['email'] ?? '') ?>">
                    <p class="text-xs text-gray-500 mt-1"><?= t('auth.email_optional') ?></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <?= t('auth.username') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="username" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500"
                           placeholder="<?= t('auth.choose_username') ?>"
                           value="<?= h($_POST['username'] ?? '') ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <?= t('auth.password') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500"
                           placeholder="<?= t('auth.min_4_chars') ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <?= t('auth.confirm_password') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="confirm" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500"
                           placeholder="<?= t('auth.retype_password') ?>">
                </div>

                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="email_consent"
                               class="mt-1 w-4 h-4 text-<?= $appColor ?>-600 border-gray-300 rounded focus:ring-<?= $appColor ?>-500"
                               <?= isset($_POST['email_consent']) ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600">
                            <?= t('auth.email_consent') ?>
                            <span class="block text-xs text-gray-500 mt-1">
                                <?= t('auth.gdpr_notice') ?>
                            </span>
                        </span>
                    </label>
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 bg-<?= $appColor ?>-600 hover:bg-<?= $appColor ?>-700 text-white font-semibold rounded-lg transition-colors">
                    <?= t('auth.create_my_account') ?>
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-6 text-center text-sm text-gray-600">
                <?= t('auth.already_account') ?>
                <a href="login.php" class="text-<?= $appColor ?>-600 hover:text-<?= $appColor ?>-800 font-medium">
                    <?= t('auth.connect') ?>
                </a>
            </div>
        </div>
    </div>
    <?= renderLanguageScript() ?>
</body>
</html>
