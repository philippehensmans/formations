<?php
/**
 * Page Formateur - Cadre Logique
 * Page personnalisee (n'utilise pas le template partage car schema different)
 */
require_once 'config.php';
require_once 'config/database.php';

$appName = 'Cadre Logique';
$appColor = 'indigo';
$error = '';
$success = '';

// Verifier si formateur connecte (via shared-auth)
if (!isLoggedIn()) {
    // Formulaire de connexion formateur
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = authenticateUser($username, $password);
        if ($user && ($user['is_formateur'] || $user['is_admin'])) {
            login($user);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = 'Identifiants incorrects ou compte non formateur.';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Formateur - <?= h($appName) ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Espace Formateur</h1>
                <p class="text-gray-600"><?= h($appName) ?></p>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Identifiant</label>
                        <input type="text" name="username" required
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-<?= $appColor ?>-500"
                               placeholder="formateur">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-<?= $appColor ?>-500">
                    </div>
                    <button type="submit" class="w-full py-2 bg-<?= $appColor ?>-600 text-white rounded-lg hover:bg-<?= $appColor ?>-700">
                        Connexion
                    </button>
                </form>
            </div>

            <div class="mt-4 text-center">
                <a href="login.php" class="text-<?= $appColor ?>-600 hover:text-<?= $appColor ?>-800 text-sm">
                    Retour a l'application
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Verifier les droits formateur
if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$user = getLoggedUser();
$db = getDB();

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_session':
                $nom = trim($_POST['nom'] ?? '');
                if (!empty($nom)) {
                    $code = generateSessionCode();
                    $stmt = $db->prepare("INSERT INTO sessions (code, nom, is_active, created_at) VALUES (?, ?, 1, CURRENT_TIMESTAMP)");
                    $stmt->execute([$code, $nom]);
                    $success = "Session creee avec le code: $code";
                }
                break;

            case 'toggle_session':
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $stmt = $db->prepare("UPDATE sessions SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$sessionId]);
                $success = "Statut de la session modifie.";
                break;

            case 'delete_session':
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $db->prepare("DELETE FROM cadre_logique WHERE session_id = ?")->execute([$sessionId]);
                $db->prepare("DELETE FROM participants WHERE session_id = ?")->execute([$sessionId]);
                $db->prepare("DELETE FROM sessions WHERE id = ?")->execute([$sessionId]);
                $success = "Session supprimee.";
                break;

            case 'logout':
                logout();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
        }
    }
}

// Recuperer les sessions
$sessions = $db->query("SELECT * FROM sessions ORDER BY created_at DESC")->fetchAll();

// Recuperer les participants si une session est selectionnee
$selectedSession = null;
$participants = [];
if (isset($_GET['session'])) {
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([(int)$_GET['session']]);
    $selectedSession = $stmt->fetch();

    if ($selectedSession) {
        $stmt = $db->prepare("
            SELECT p.*, c.completion_percent, c.is_submitted, c.titre_projet
            FROM participants p
            LEFT JOIN cadre_logique c ON p.id = c.participant_id
            WHERE p.session_id = ?
            ORDER BY p.nom, p.prenom
        ");
        $stmt->execute([$selectedSession['id']]);
        $participants = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formateur - <?= h($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100">
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Espace Formateur</h1>
                    <p class="text-sm text-gray-500"><?= h($appName) ?> - <?= h($user['username']) ?></p>
                </div>
                <div class="flex gap-3">
                    <a href="login.php" class="px-4 py-2 text-gray-600 hover:text-gray-800">Application</a>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            Deconnexion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">
                <?= h($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Creer une session -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Creer une nouvelle session</h2>
            <form method="POST" class="flex gap-4">
                <input type="hidden" name="action" value="create_session">
                <input type="text" name="nom" placeholder="Nom de la session (ex: Formation Mars 2025)" required
                       class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-<?= $appColor ?>-500">
                <button type="submit" class="px-6 py-2 bg-<?= $appColor ?>-600 text-white rounded-lg hover:bg-<?= $appColor ?>-700">
                    Creer
                </button>
            </form>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <!-- Liste des sessions -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gray-50 p-4 border-b font-semibold text-gray-700">
                    Sessions (<?= count($sessions) ?>)
                </div>
                <div class="divide-y max-h-96 overflow-y-auto">
                    <?php foreach ($sessions as $session): ?>
                        <div class="p-4 hover:bg-gray-50 <?= ($selectedSession && $selectedSession['id'] == $session['id']) ? 'bg-indigo-50 border-l-4 border-indigo-600' : '' ?>">
                            <div class="flex justify-between items-start">
                                <a href="?session=<?= $session['id'] ?>" class="flex-1">
                                    <div class="font-mono font-bold text-<?= $appColor ?>-600"><?= h($session['code']) ?></div>
                                    <div class="text-sm text-gray-600"><?= h($session['nom']) ?></div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        <?= date('d/m/Y', strtotime($session['created_at'])) ?>
                                        <?php if (!$session['is_active']): ?>
                                            <span class="ml-2 px-2 py-0.5 bg-gray-200 text-gray-600 rounded">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <div class="flex gap-1">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_session">
                                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                        <button type="submit" class="p-1 text-gray-400 hover:text-gray-600" title="<?= $session['is_active'] ? 'Desactiver' : 'Activer' ?>">
                                            <?= $session['is_active'] ? '⏸' : '▶' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette session?')">
                                        <input type="hidden" name="action" value="delete_session">
                                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                        <button type="submit" class="p-1 text-red-400 hover:text-red-600">🗑</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($sessions)): ?>
                        <div class="p-8 text-center text-gray-500">
                            Aucune session
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Participants de la session selectionnee -->
            <div class="md:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
                <?php if ($selectedSession): ?>
                    <div class="bg-gray-50 p-4 border-b">
                        <span class="font-semibold text-gray-700">Participants</span>
                        <span class="text-gray-500">- Session <?= h($selectedSession['code']) ?></span>
                        <span class="ml-2 px-2 py-1 bg-<?= $appColor ?>-100 text-<?= $appColor ?>-700 rounded text-sm"><?= count($participants) ?></span>
                    </div>
                    <div class="p-4">
                        <?php if (empty($participants)): ?>
                            <p class="text-gray-500 text-center py-8">Aucun participant dans cette session</p>
                        <?php else: ?>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-2">Participant</th>
                                        <th class="text-left py-2">Projet</th>
                                        <th class="text-center py-2">Completion</th>
                                        <th class="text-center py-2">Statut</th>
                                        <th class="text-center py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $p): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-2">
                                                <div class="font-medium"><?= h($p['prenom'] . ' ' . $p['nom']) ?></div>
                                                <div class="text-xs text-gray-500"><?= h($p['organisation'] ?? '-') ?></div>
                                            </td>
                                            <td class="py-2 text-gray-600 max-w-xs truncate">
                                                <?= h($p['titre_projet'] ?? '-') ?>
                                            </td>
                                            <td class="py-2 text-center">
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-<?= $appColor ?>-600 h-2 rounded-full" style="width: <?= $p['completion_percent'] ?? 0 ?>%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500"><?= $p['completion_percent'] ?? 0 ?>%</span>
                                            </td>
                                            <td class="py-2 text-center">
                                                <?php if ($p['is_submitted']): ?>
                                                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Soumis</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">Brouillon</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-2 text-center">
                                                <a href="view.php?id=<?= $p['id'] ?>"
                                                   class="inline-block px-3 py-1 bg-<?= $appColor ?>-600 text-white rounded hover:bg-<?= $appColor ?>-700 text-xs"
                                                   target="_blank">
                                                    Voir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="flex items-center justify-center h-64 text-gray-400">
                        Selectionnez une session pour voir les participants
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
