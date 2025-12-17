<?php
session_start();
require_once 'config/database.php';

$db = getDB();
$error = '';
$success = '';
$isAdmin = false;

// Vérification admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === 'Formation2024!') {
        $_SESSION['is_admin'] = true;
    } else {
        $error = "Mot de passe incorrect.";
    }
}

if (isset($_POST['logout_admin'])) {
    unset($_SESSION['is_admin']);
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// Actions admin
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_session'])) {
        $sessionId = (int)$_POST['session_id'];
        $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $success = "Session supprimee.";
    }

    if (isset($_POST['toggle_session'])) {
        $sessionId = (int)$_POST['session_id'];
        $stmt = $db->prepare("UPDATE sessions SET active = NOT active WHERE id = ?");
        $stmt->execute([$sessionId]);
        $success = "Statut de la session modifie.";
    }

    if (isset($_POST['create_session'])) {
        $nom = trim($_POST['nom'] ?? '');
        $formateur = trim($_POST['formateur_nom'] ?? '');
        $password = $_POST['mot_de_passe'] ?? 'Formation2024!';

        if (!empty($nom)) {
            $code = generateSessionCode();
            $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_nom, mot_de_passe) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $nom, $formateur, $password]);
            $success = "Session creee avec le code: $code";
        }
    }
}

// Récupérer toutes les sessions
$sessions = [];
if ($isAdmin) {
    $stmt = $db->query("
        SELECT s.*,
               (SELECT COUNT(*) FROM participants WHERE session_id = s.id) as nb_participants,
               (SELECT COUNT(*) FROM mesure_impact WHERE session_id = s.id AND is_submitted = 1) as nb_submitted
        FROM sessions s
        ORDER BY s.created_at DESC
    ");
    $sessions = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Mesure d'Impact Social</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gray-100">
    <?php if (!$isAdmin): ?>
    <!-- Page de connexion admin -->
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Administration</h1>
                <p class="text-gray-600">Mesure d'Impact Social</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe administrateur</label>
                        <input type="password" name="admin_password" required
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <button type="submit" class="w-full py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Connexion
                    </button>
                </form>
            </div>

            <div class="mt-4 text-center">
                <a href="formateur.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                    ← Retour espace formateur
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Interface admin -->
    <header class="bg-white shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Administration des sessions</h1>
                    <p class="text-sm text-gray-500">Mesure d'Impact Social</p>
                </div>
                <form method="POST" class="inline">
                    <input type="hidden" name="logout_admin" value="1">
                    <button type="submit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
                        Deconnexion
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Créer une session -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Creer une nouvelle session</h2>
            <form method="POST" class="grid md:grid-cols-4 gap-4">
                <input type="text" name="nom" placeholder="Nom de la session" required
                       class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                <input type="text" name="formateur_nom" placeholder="Nom du formateur"
                       class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                <input type="text" name="mot_de_passe" placeholder="Mot de passe" value="Formation2024!"
                       class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                <button type="submit" name="create_session" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Creer
                </button>
            </form>
        </div>

        <!-- Liste des sessions -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="font-semibold text-gray-800 mb-4">Sessions existantes</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-2">Code</th>
                            <th class="text-left py-3 px-2">Nom</th>
                            <th class="text-left py-3 px-2">Formateur</th>
                            <th class="text-center py-3 px-2">Participants</th>
                            <th class="text-center py-3 px-2">Termines</th>
                            <th class="text-center py-3 px-2">Statut</th>
                            <th class="text-center py-3 px-2">Cree le</th>
                            <th class="text-center py-3 px-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-2 font-mono font-bold text-indigo-600"><?= $session['code'] ?></td>
                            <td class="py-3 px-2"><?= htmlspecialchars($session['nom']) ?></td>
                            <td class="py-3 px-2 text-gray-600"><?= htmlspecialchars($session['formateur_nom'] ?? '-') ?></td>
                            <td class="py-3 px-2 text-center"><?= $session['nb_participants'] ?></td>
                            <td class="py-3 px-2 text-center"><?= $session['nb_submitted'] ?></td>
                            <td class="py-3 px-2 text-center">
                                <?php if ($session['active']): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-2 text-center text-gray-500">
                                <?= date('d/m/Y', strtotime($session['created_at'])) ?>
                            </td>
                            <td class="py-3 px-2 text-center">
                                <form method="POST" class="inline-flex gap-2">
                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                    <button type="submit" name="toggle_session"
                                            class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-xs hover:bg-amber-200">
                                        <?= $session['active'] ? 'Desactiver' : 'Activer' ?>
                                    </button>
                                    <button type="submit" name="delete_session"
                                            onclick="return confirm('Supprimer cette session et toutes ses donnees?')"
                                            class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">
                                        Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-500">
                                Aucune session
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 text-center">
            <a href="formateur.php" class="text-indigo-600 hover:text-indigo-800">
                ← Retour espace formateur
            </a>
        </div>
    </main>
    <?php endif; ?>
</body>
</html>
