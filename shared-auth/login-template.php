<?php
/**
 * Template de page de connexion partagee
 *
 * Variables a definir avant d'inclure ce template:
 * - $appName : Nom de l'application
 * - $appColor : Couleur principale (ex: 'blue', 'green', 'purple')
 * - $db : Connexion a la base de l'application (pour les sessions)
 * - $redirectAfterLogin : Page de redirection apres connexion
 * - $showRegister : Afficher le lien d'inscription (optionnel, defaut true)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sessions.php';
require_once __DIR__ . '/lang.php';

$error = '';
$showRegister = $showRegister ?? true;
$appColor = $appColor ?? 'blue';
$redirectAfterLogin = $redirectAfterLogin ?? 'index.php';
$lang = getCurrentLanguage();

// Si deja connecte avec participant_id, rediriger
if (isLoggedIn() && isset($_SESSION['current_session_id']) && isset($_SESSION['participant_id'])) {
    header('Location: ' . $redirectAfterLogin);
    exit;
}

// Si connecte mais sans participant_id, deconnecter pour recommencer
if (isLoggedIn() && !isset($_SESSION['participant_id'])) {
    logout();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $sessionCode = strtoupper(trim($_POST['session_code'] ?? ''));

    if (empty($username) || empty($password)) {
        $error = t('auth.fill_required');
    } elseif (empty($sessionCode)) {
        $error = t('auth.session_error');
    } else {
        // Verifier la session
        $session = getSessionByCode($db, $sessionCode);
        if (!$session) {
            $error = t('auth.session_error');
        } else {
            // Authentifier l'utilisateur
            $user = authenticateUser($username, $password);
            if ($user) {
                login($user);
                $_SESSION['current_session_id'] = $session['id'];
                $_SESSION['current_session_code'] = $session['code'];
                $_SESSION['current_session_nom'] = $session['nom'];

                // Enregistrer le participant dans la session si necessaire
                $participant = null;
                try {
                    // Nouveau schema avec user_id
                    $stmt = $db->prepare("SELECT id FROM participants WHERE session_id = ? AND user_id = ?");
                    $stmt->execute([$session['id'], $user['id']]);
                    $participant = $stmt->fetch();
                } catch (PDOException $e) {
                    // Ancien schema: chercher par prenom/nom
                    $stmt = $db->prepare("SELECT id FROM participants WHERE session_id = ? AND prenom = ? AND nom = ?");
                    $stmt->execute([$session['id'], $user['prenom'] ?? '', $user['nom'] ?? '']);
                    $participant = $stmt->fetch();
                }

                if (!$participant) {
                    // Essayer d'inserer avec user_id (nouveau schema)
                    try {
                        $stmt = $db->prepare("INSERT INTO participants (session_id, user_id, prenom, nom, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
                        $stmt->execute([$session['id'], $user['id'], $user['prenom'] ?? '', $user['nom'] ?? '']);
                    } catch (PDOException $e) {
                        // Fallback: ancien schema sans user_id mais avec prenom/nom NOT NULL
                        $stmt = $db->prepare("INSERT INTO participants (session_id, prenom, nom, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                        $stmt->execute([$session['id'], $user['prenom'] ?? $user['username'], $user['nom'] ?? '']);
                    }
                    $_SESSION['participant_id'] = $db->lastInsertId();
                } else {
                    $_SESSION['participant_id'] = $participant['id'];
                }

                header('Location: ' . $redirectAfterLogin);
                exit;
            } else {
                $error = t('auth.login_error');
            }
        }
    }
}

$sessions = getActiveSessions($db);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('auth.login') ?> - <?= h($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-<?= $appColor ?>-600 to-<?= $appColor ?>-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <img src="../logo.png" alt="Logo" class="h-16 mx-auto mb-4">
            <h1 class="text-3xl font-bold text-white mb-2"><?= h($appName) ?></h1>
            <p class="text-<?= $appColor ?>-200"><?= t('common.interactive_training') ?></p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8">
            <!-- Selecteur de langue -->
            <div class="flex justify-end mb-4">
                <?= renderLanguageSelector('text-sm border rounded px-2 py-1') ?>
            </div>

            <h2 class="text-xl font-semibold text-gray-800 mb-6 text-center"><?= t('auth.login') ?></h2>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($sessions)): ?>
                <div class="mb-4 p-4 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg text-sm text-center">
                    <p class="font-semibold"><?= t('auth.no_session') ?></p>
                    <p class="text-xs mt-1"><?= t('auth.no_session_detail') ?></p>
                    <a href="formateur.php" class="inline-block mt-2 text-<?= $appColor ?>-600 hover:text-<?= $appColor ?>-800 font-medium underline">
                        <?= t('trainer.create_session') ?>
                    </a>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.training_session') ?></label>
                    <?= renderSessionDropdown($db, $_POST['session_code'] ?? '') ?>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.username') ?></label>
                    <input type="text" name="username" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500 focus:ring-2 focus:ring-<?= $appColor ?>-200"
                           placeholder="<?= t('auth.your_username') ?>"
                           value="<?= h($_POST['username'] ?? '') ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.password') ?></label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500 focus:ring-2 focus:ring-<?= $appColor ?>-200"
                           placeholder="<?= t('auth.your_password') ?>">
                    <div class="text-right mt-1">
                        <a href="../shared-auth/forgot-password.php" class="text-sm text-<?= $appColor ?>-600 hover:text-<?= $appColor ?>-800">
                            <?= t('auth.forgot_password') ?>
                        </a>
                    </div>
                </div>

                <button type="submit" <?= empty($sessions) ? 'disabled' : '' ?>
                        class="w-full py-3 px-4 bg-<?= $appColor ?>-600 hover:bg-<?= $appColor ?>-700 disabled:bg-gray-400 text-white font-semibold rounded-lg transition-colors">
                    <?= t('auth.connect') ?>
                </button>
            </form>

            <?php if ($showRegister): ?>
            <div class="mt-6 text-center text-sm text-gray-600">
                <?= t('auth.no_account') ?>
                <a href="register.php" class="text-<?= $appColor ?>-600 hover:text-<?= $appColor ?>-800 font-medium">
                    <?= t('auth.sign_up') ?>
                </a>
            </div>
            <?php endif; ?>

            <div class="mt-4 pt-4 border-t border-gray-200 text-center text-sm text-gray-600">
                <?= t('auth.want_session') ?>
                <a href="https://k1m.be/contact/" target="_blank" class="text-<?= $appColor ?>-600 hover:text-<?= $appColor ?>-800 font-medium">
                    <?= t('auth.contact_us') ?>
                </a>
            </div>
        </div>

        <div class="mt-6 text-center">
            <a href="formateur.php" class="text-<?= $appColor ?>-200 hover:text-white text-sm">
                <?= t('auth.trainer_access') ?>
            </a>
        </div>
    </div>
    <?= renderLanguageScript() ?>
</body>
</html>
