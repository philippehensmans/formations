<?php
/**
 * Page de reinitialisation du mot de passe
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lang.php';

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$lang = getCurrentLanguage();

// Valider le token
$user = validatePasswordResetToken($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (empty($password)) {
        $message = t('reset.enter_new_password');
        $messageType = 'error';
    } elseif (strlen($password) < 4) {
        $message = t('auth.password_min_length');
        $messageType = 'error';
    } elseif ($password !== $confirm) {
        $message = t('auth.password_mismatch');
        $messageType = 'error';
    } else {
        $result = resetPasswordWithToken($token, $password);

        if ($result['success']) {
            $message = t('reset.password_changed');
            $messageType = 'success';
        } else {
            $message = t('reset.error_occurred');
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('reset.reset_password') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <img src="../logo.png" alt="Logo" class="h-16 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-white"><?= t('reset.reset_password') ?></h1>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8">
            <!-- Selecteur de langue -->
            <div class="flex justify-end mb-4">
                <?= renderLanguageSelector('text-sm border rounded px-2 py-1') ?>
            </div>

            <?php if ($message): ?>
                <div class="mb-4 p-4 <?= $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?> rounded-lg text-sm">
                    <?= h($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!$user && $messageType !== 'success'): ?>
                <!-- Token invalide ou expire -->
                <div class="text-center">
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">
                        <?= t('reset.invalid_or_expired_link') ?>
                    </div>
                    <a href="forgot-password.php" class="text-blue-600 hover:text-blue-800 font-medium">
                        <?= t('reset.request_new_link') ?>
                    </a>
                </div>
            <?php elseif ($messageType === 'success'): ?>
                <!-- Mot de passe change avec succes -->
                <div class="text-center">
                    <div class="mb-4">
                        <svg class="w-16 h-16 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <p class="text-gray-700 mb-4"><?= t('reset.can_now_login') ?></p>
                    <a href="javascript:history.go(-2)" class="inline-block py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                        <?= t('reset.go_to_login') ?>
                    </a>
                </div>
            <?php else: ?>
                <!-- Formulaire de nouveau mot de passe -->
                <p class="text-gray-600 text-sm mb-4 text-center">
                    <?= t('reset.choose_new_password') ?>
                </p>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('reset.new_password') ?></label>
                        <input type="password" name="password" required autofocus
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                               placeholder="<?= t('auth.min_4_chars') ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.confirm_password') ?></label>
                        <input type="password" name="confirm" required
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                               placeholder="<?= t('auth.retype_password') ?>">
                    </div>

                    <button type="submit"
                            class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                        <?= t('reset.change_password') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?= renderLanguageScript() ?>
</body>
</html>
