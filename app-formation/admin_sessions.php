<?php
require_once 'config/database.php';

if (!isFormateurLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nom = trim($_POST['nom'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($nom) {
            $code = generateSessionCode();
            $passwordHash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

            $stmt = $db->prepare("INSERT INTO sessions (code, nom, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$code, $nom, $passwordHash]);

            $message = "Session creee avec le code: $code";
        } else {
            $error = "Le nom de la session est requis";
        }
    }

    if ($action === 'toggle') {
        $sessionId = $_POST['session_id'] ?? 0;
        $stmt = $db->prepare("UPDATE sessions SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$sessionId]);
        $message = "Statut de la session modifie";
    }

    if ($action === 'delete') {
        $sessionId = $_POST['session_id'] ?? 0;
        // Supprimer les cadres logiques, participants, puis la session
        $stmt = $db->prepare("DELETE FROM cadre_logique WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $stmt = $db->prepare("DELETE FROM participants WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $message = "Session supprimee";
    }
}

// Recuperer toutes les sessions
$sessions = $db->query("
    SELECT s.*,
        (SELECT COUNT(*) FROM participants WHERE session_id = s.id) as participant_count,
        (SELECT COUNT(*) FROM cadre_logique WHERE session_id = s.id AND is_submitted = 1) as submitted_count,
        (SELECT AVG(completion_percent) FROM cadre_logique WHERE session_id = s.id) as avg_completion
    FROM sessions s
    ORDER BY created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Sessions - Cadre Logique</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Gestion des Sessions</h1>
                    <p class="text-purple-200">Administration des formations</p>
                </div>
                <div class="flex items-center gap-4">
                    <a href="formateur.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition">
                        ← Retour Dashboard
                    </a>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition">
                        Deconnexion
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= sanitize($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Formulaire de creation -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">Nouvelle Session</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create">

                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Nom de la session *</label>
                            <input type="text" name="nom" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                placeholder="Ex: Formation Mars 2024">
                        </div>

                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Mot de passe formateur</label>
                            <input type="password" name="password"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                placeholder="Optionnel">
                            <p class="text-xs text-gray-500 mt-1">Si defini, le formateur devra entrer ce mot de passe pour acceder au dashboard</p>
                        </div>

                        <button type="submit"
                            class="w-full bg-purple-600 text-white py-3 rounded-lg font-semibold hover:bg-purple-700 transition">
                            Creer la session
                        </button>
                    </form>
                </div>
            </div>

            <!-- Liste des sessions -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow">
                    <div class="p-4 border-b">
                        <h2 class="text-lg font-semibold">Sessions existantes (<?= count($sessions) ?>)</h2>
                    </div>

                    <?php if (empty($sessions)): ?>
                        <div class="p-8 text-center text-gray-500">
                            Aucune session creee pour le moment
                        </div>
                    <?php else: ?>
                        <div class="divide-y">
                            <?php foreach ($sessions as $s): ?>
                                <div class="p-4">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3">
                                                <span class="font-mono bg-purple-100 text-purple-800 px-3 py-1 rounded font-bold">
                                                    <?= sanitize($s['code']) ?>
                                                </span>
                                                <span class="font-medium text-lg"><?= sanitize($s['nom']) ?></span>
                                                <?php if ($s['is_active']): ?>
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Active</span>
                                                <?php else: ?>
                                                    <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">Inactive</span>
                                                <?php endif; ?>
                                                <?php if ($s['password_hash']): ?>
                                                    <span class="text-gray-400" title="Protegee par mot de passe">🔒</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-2 text-sm text-gray-500 flex items-center gap-4">
                                                <span><?= $s['participant_count'] ?> participants</span>
                                                <span><?= $s['submitted_count'] ?? 0 ?> soumis</span>
                                                <span>Moy: <?= round($s['avg_completion'] ?? 0) ?>%</span>
                                                <span>Creee le <?= date('d/m/Y', strtotime($s['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <!-- Acceder au dashboard -->
                                            <form method="POST" action="index.php" class="inline">
                                                <input type="hidden" name="mode" value="formateur">
                                                <input type="hidden" name="code" value="<?= sanitize($s['code']) ?>">
                                                <button type="submit"
                                                    class="bg-purple-100 text-purple-700 px-3 py-1 rounded hover:bg-purple-200 transition text-sm">
                                                    Ouvrir
                                                </button>
                                            </form>

                                            <!-- Toggle active -->
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                                <button type="submit"
                                                    class="bg-gray-100 text-gray-700 px-3 py-1 rounded hover:bg-gray-200 transition text-sm">
                                                    <?= $s['is_active'] ? 'Desactiver' : 'Activer' ?>
                                                </button>
                                            </form>

                                            <!-- Supprimer -->
                                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette session et tous ses participants ?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                                <button type="submit"
                                                    class="bg-red-100 text-red-700 px-3 py-1 rounded hover:bg-red-200 transition text-sm">
                                                    Supprimer
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="mt-8 bg-white rounded-xl shadow p-6">
            <h3 class="font-semibold mb-4">Comment utiliser les sessions</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm text-gray-600">
                <div>
                    <div class="font-medium text-gray-800 mb-2">1. Creer une session</div>
                    <p>Creez une nouvelle session avec un nom descriptif. Un code unique sera genere automatiquement.</p>
                </div>
                <div>
                    <div class="font-medium text-gray-800 mb-2">2. Partager le code</div>
                    <p>Communiquez le code de session (ex: ABC123) aux participants. Ils l'utiliseront pour se connecter.</p>
                </div>
                <div>
                    <div class="font-medium text-gray-800 mb-2">3. Suivre les progres</div>
                    <p>Utilisez le dashboard formateur pour suivre l'avancement des participants en temps reel.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
