<?php
/**
 * Page de demande de reinitialisation de mot de passe
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lang.php';

$message = '';
$messageType = '';
$lang = getCurrentLanguage();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrUsername = trim($_POST['email_or_username'] ?? '');

    if (empty($emailOrUsername)) {
        $message = t('reset.enter_email_or_username');
        $messageType = 'error';
    } else {
        $result = generatePasswordResetToken($emailOrUsername);

        if ($result['success']) {
            $message = t('reset.email_sent') . ' ' . $result['email'];
            $messageType = 'success';
        } else {
            switch ($result['error']) {
                case 'user_not_found':
                    $message = t('reset.user_not_found');
                    break;
                case 'no_email':
                    $message = t('reset.no_email_registered');
                    break;
                case 'email_failed':
                    $message = t('reset.email_error');
                    break;
                default:
                    $message = t('reset.error_occurred');
            }
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
    <title><?= t('reset.forgot_password') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <img src="../logo.png" alt="Logo" class="h-16 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-white"><?= t('reset.forgot_password') ?></h1>
            <p class="text-blue-200 mt-2"><?= t('reset.forgot_password_desc') ?></p>
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

            <?php if ($messageType !== 'success'): ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('reset.email_or_username') ?></label>
                    <input type="text" name="email_or_username" required autofocus
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                           placeholder="<?= t('reset.email_or_username_placeholder') ?>"
                           value="<?= h($_POST['email_or_username'] ?? '') ?>">
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                    <?= t('reset.send_reset_link') ?>
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-6 text-center text-sm text-gray-600">
                <a href="javascript:history.back()" class="text-blue-600 hover:text-blue-800 font-medium">
                    <?= t('reset.back_to_login') ?>
                </a>
            </div>
        </div>
    </div>
    <?= renderLanguageScript() ?>
</body>
</html>
