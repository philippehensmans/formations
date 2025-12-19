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
        <style>body { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }</style>
    </head>
    <body class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <h1 class="text-2xl font-bold mb-6 text-center">Administration</h1>
            <?php if (isset($loginError)): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $loginError ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Mot de passe admin</label>
                    <input type="password" name="admin_password" required class="w-full p-3 border rounded focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="submit" class="w-full bg-emerald-600 text-white p-3 rounded hover:bg-emerald-700">Connexion</button>
            </form>
            <div class="mt-4 text-center">
                <a href="index.php" class="text-emerald-600 hover:underline">Retour</a>
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create_session':
            $nom = trim($_POST['nom'] ?? '');
            $formateurPassword = trim($_POST['formateur_password'] ?? '');
            if ($nom && $formateurPassword) {
                $code = generateSessionCode();
                $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_password) VALUES (?, ?, ?)");
                $stmt->execute([$code, $nom, $formateurPassword]);
                $message = "Session creee avec le code: $code";
            }
            break;
        case 'delete_session':
            $sessionId = $_POST['session_id'] ?? 0;
            $db->prepare("DELETE FROM objectifs_smart WHERE session_id = ?")->execute([$sessionId]);
            $db->prepare("DELETE FROM participants WHERE session_id = ?")->execute([$sessionId]);
            $db->prepare("DELETE FROM sessions WHERE id = ?")->execute([$sessionId]);
            $message = "Session supprimee";
            break;
        case 'reset_session':
            $sessionId = $_POST['session_id'] ?? 0;
            $db->prepare("DELETE FROM objectifs_smart WHERE session_id = ?")->execute([$sessionId]);
            $db->prepare("DELETE FROM participants WHERE session_id = ?")->execute([$sessionId]);
            $message = "Session reinitalisee";
            break;
    }
}

$sessions = $db->query("SELECT * FROM sessions ORDER BY created_at DESC")->fetchAll();
foreach ($sessions as &$s) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM participants WHERE session_id = ?");
    $stmt->execute([$s['id']]);
    $s['participants'] = $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) FROM objectifs_smart WHERE session_id = ? AND is_submitted = 1");
    $stmt->execute([$s['id']]);
    $s['submitted'] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Objectifs SMART</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background: linear-gradient(135deg, #10b981 0%, #047857 100%); min-height: 100vh; }</style>
</head>
<body class="p-4">
    <div class="max-w-6xl mx-auto">
        <div class="bg-emerald-700 text-white p-4 rounded-t-lg flex justify-between items-center">
            <h1 class="text-xl font-bold">Administration - Objectifs SMART</h1>
            <div class="flex gap-4">
                <a href="index.php" class="bg-emerald-600 hover:bg-emerald-500 px-4 py-2 rounded">Accueil</a>
                <a href="?admin_logout=1" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded">Deconnexion</a>
            </div>
        </div>

        <div class="bg-white rounded-b-lg p-6">
            <?php if ($message): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded mb-6"><?= sanitize($message) ?></div>
            <?php endif; ?>

            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">Nouvelle session</h2>
                <form method="POST" class="flex flex-wrap gap-4">
                    <input type="hidden" name="action" value="create_session">
                    <input type="text" name="nom" placeholder="Nom de la session" required class="flex-1 min-w-[200px] p-3 border rounded">
                    <input type="text" name="formateur_password" placeholder="Mot de passe formateur" required class="flex-1 min-w-[200px] p-3 border rounded">
                    <button type="submit" class="bg-emerald-600 text-white px-6 py-3 rounded hover:bg-emerald-700">Creer</button>
                </form>
            </div>

            <div class="border rounded-lg">
                <div class="p-4 border-b bg-gray-50">
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
                                    <th class="text-left p-4">MDP</th>
                                    <th class="text-center p-4">Participants</th>
                                    <th class="text-center p-4">Termines</th>
                                    <th class="text-right p-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($sessions as $s): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-4 font-mono font-bold text-emerald-700"><?= sanitize($s['code']) ?></td>
                                        <td class="p-4"><?= sanitize($s['nom']) ?></td>
                                        <td class="p-4 text-gray-500"><?= sanitize($s['formateur_password']) ?></td>
                                        <td class="p-4 text-center"><?= $s['participants'] ?></td>
                                        <td class="p-4 text-center"><span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm"><?= $s['submitted'] ?></span></td>
                                        <td class="p-4 text-right">
                                            <form method="POST" class="inline" onsubmit="return confirm('Reset?')">
                                                <input type="hidden" name="action" value="reset_session">
                                                <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                                <button class="text-orange-600 hover:text-orange-800 mr-3">Reset</button>
                                            </form>
                                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer?')">
                                                <input type="hidden" name="action" value="delete_session">
                                                <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                                <button class="text-red-600 hover:text-red-800">Supprimer</button>
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
    </div>
</body>
</html>
