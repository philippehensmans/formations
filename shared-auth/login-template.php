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

$error = '';
$showRegister = $showRegister ?? true;
$appColor = $appColor ?? 'blue';
$redirectAfterLogin = $redirectAfterLogin ?? 'index.php';

// Si deja connecte, rediriger
if (isLoggedIn() && isset($_SESSION['current_session_id'])) {
    header('Location: ' . $redirectAfterLogin);
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $sessionCode = strtoupper(trim($_POST['session_code'] ?? ''));

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (empty($sessionCode)) {
        $error = 'Veuillez selectionner une session.';
    } else {
        // Verifier la session
        $session = getSessionByCode($db, $sessionCode);
        if (!$session) {
            $error = 'Session invalide ou inactive.';
        } else {
            // Authentifier l'utilisateur
            $user = authenticateUser($username, $password);
            if ($user) {
                login($user);
                $_SESSION['current_session_id'] = $session['id'];
                $_SESSION['current_session_code'] = $session['code'];
                $_SESSION['current_session_nom'] = $session['nom'];

                // Enregistrer le participant dans la session si necessaire
                $stmt = $db->prepare("SELECT id FROM participants WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$session['id'], $user['id']]);
                $participant = $stmt->fetch();

                if (!$participant) {
                    $stmt = $db->prepare("INSERT INTO participants (session_id, user_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
                    $stmt->execute([$session['id'], $user['id']]);
                    $_SESSION['participant_id'] = $db->lastInsertId();
                } else {
                    $_SESSION['participant_id'] = $participant['id'];
                }

                header('Location: ' . $redirectAfterLogin);
                exit;
            } else {
                $error = 'Identifiants incorrects.';
            }
        }
    }
}

$sessions = getActiveSessions($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= h($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-<?= $appColor ?>-600 to-<?= $appColor ?>-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white mb-2"><?= h($appName) ?></h1>
            <p class="text-<?= $appColor ?>-200">Formation interactive</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6 text-center">Connexion</h2>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($sessions)): ?>
                <div class="mb-4 p-4 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg text-sm text-center">
                    <p class="font-semibold">Aucune session disponible</p>
                    <p class="text-xs mt-1">Le formateur doit d'abord creer une session.</p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Session de formation</label>
                    <?= renderSessionDropdown($db, $_POST['session_code'] ?? '') ?>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom d'utilisateur</label>
                    <input type="text" name="username" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500 focus:ring-2 focus:ring-<?= $appColor ?>-200"
                           placeholder="Votre identifiant"
                           value="<?= h($_POST['username'] ?? '') ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-<?= $appColor ?>-500 focus:ring-2 focus:ring-<?= $appColor ?>-200"
                           placeholder="Votre mot de passe">
                </div>

                <button type="submit" <?= empty($sessions) ? 'disabled' : '' ?>
                        class="w-full py-3 px-4 bg-<?= $appColor ?>-600 hover:bg-<?= $appColor ?>-700 disabled:bg-gray-400 text-white font-semibold rounded-lg transition-colors">
                    Se connecter
                </button>
            </form>

            <?php if ($showRegister): ?>
            <div class="mt-6 text-center text-sm text-gray-600">
                Pas encore de compte ?
                <a href="register.php" class="text-<?= $appColor ?>-600 hover:text-<?= $appColor ?>-800 font-medium">
                    S'inscrire
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-6 text-center">
            <a href="formateur.php" class="text-<?= $appColor ?>-200 hover:text-white text-sm">
                Acces formateur
            </a>
        </div>
    </div>
</body>
</html>
