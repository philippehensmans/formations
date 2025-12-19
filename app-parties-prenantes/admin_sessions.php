<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminPassword = 'Formation2024!';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $loginError = "Mot de passe incorrect";
    }
}

if (isset($_GET['admin_logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: admin_sessions.php');
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin - Connexion</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <h1 class="text-2xl font-bold mb-6 text-center">Administration</h1>
            <?php if (isset($loginError)): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $loginError ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Mot de passe admin</label>
                    <input type="password" name="admin_password" required
                           class="w-full p-3 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="w-full bg-gray-900 text-white p-3 rounded hover:bg-gray-800">
                    Connexion
                </button>
            </form>
            <div class="mt-4 text-center">
                <a href="index.php" class="text-blue-600 hover:underline">Retour</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once 'config/database.php';
$db = getDB();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_session':
                $nom = trim($_POST['nom'] ?? '');
                $formateurPassword = trim($_POST['formateur_password'] ?? '');
                if ($nom && $formateurPassword) {
                    $code = generateSessionCode();
                    $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_password) VALUES (?, ?, ?)");
                    $stmt->execute([$code, $nom, $formateurPassword]);
                    $message = "Session creee avec le code: $code";
                } else {
                    $error = "Nom et mot de passe requis";
                }
                break;

            case 'delete_session':
                $sessionId = $_POST['session_id'] ?? 0;
                $stmt = $db->prepare("DELETE FROM cartographie WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $stmt = $db->prepare("DELETE FROM participants WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
                $stmt->execute([$sessionId]);
                $message = "Session supprimee";
                break;

            case 'reset_session':
                $sessionId = $_POST['session_id'] ?? 0;
                $stmt = $db->prepare("DELETE FROM cartographie WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $stmt = $db->prepare("DELETE FROM participants WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $message = "Donnees de la session reinitalisees";
                break;
        }
    }
}

$sessions = $db->query("SELECT * FROM sessions ORDER BY created_at DESC")->fetchAll();

foreach ($sessions as &$session) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM participants WHERE session_id = ?");
    $stmt->execute([$session['id']]);
    $session['participant_count'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM cartographie WHERE session_id = ? AND is_submitted = 1");
    $stmt->execute([$session['id']]);
    $session['submitted_count'] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration des Sessions - Parties Prenantes</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gray-900 text-white p-4">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Administration - Parties Prenantes</h1>
            <div class="flex gap-4">
                <a href="index.php" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded">Accueil</a>
                <a href="?admin_logout=1" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto p-6">
        <?php if ($message): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded mb-6"><?= sanitize($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <!-- Nouvelle session -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Creer une nouvelle session</h2>
            <form method="POST" class="flex flex-wrap gap-4">
                <input type="hidden" name="action" value="create_session">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" name="nom" placeholder="Nom de la session" required
                           class="w-full p-3 border rounded">
                </div>
                <div class="flex-1 min-w-[200px]">
                    <input type="text" name="formateur_password" placeholder="Mot de passe formateur" required
                           class="w-full p-3 border rounded">
                </div>
                <button type="submit" class="bg-blue-900 text-white px-6 py-3 rounded hover:bg-blue-800">
                    Creer
                </button>
            </form>
        </div>

        <!-- Liste des sessions -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b">
                <h2 class="text-lg font-semibold">Sessions (<?= count($sessions) ?>)</h2>
            </div>
            <?php if (empty($sessions)): ?>
                <div class="p-6 text-gray-500 text-center">Aucune session</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-4">Code</th>
                                <th class="text-left p-4">Nom</th>
                                <th class="text-left p-4">Mot de passe</th>
                                <th class="text-center p-4">Participants</th>
                                <th class="text-center p-4">Soumis</th>
                                <th class="text-left p-4">Creee le</th>
                                <th class="text-right p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($sessions as $session): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-4">
                                        <span class="font-mono font-bold text-blue-900"><?= sanitize($session['code']) ?></span>
                                    </td>
                                    <td class="p-4"><?= sanitize($session['nom']) ?></td>
                                    <td class="p-4 text-gray-500"><?= sanitize($session['formateur_password']) ?></td>
                                    <td class="p-4 text-center"><?= $session['participant_count'] ?></td>
                                    <td class="p-4 text-center">
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
                                            <?= $session['submitted_count'] ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-500"><?= date('d/m/Y H:i', strtotime($session['created_at'])) ?></td>
                                    <td class="p-4 text-right">
                                        <form method="POST" class="inline" onsubmit="return confirm('Reinitialiser toutes les donnees?')">
                                            <input type="hidden" name="action" value="reset_session">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" class="text-orange-600 hover:text-orange-800 mr-3">Reset</button>
                                        </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette session?')">
                                            <input type="hidden" name="action" value="delete_session">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
