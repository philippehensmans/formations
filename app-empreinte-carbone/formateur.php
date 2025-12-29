<?php
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/config.php';

$appName = 'Empreinte Carbone IA';
$appColor = 'green';
$appKey = 'app-empreinte-carbone';
$db = getDB();

$error = '';
$success = '';

// Vérifier connexion formateur
if (!isLoggedIn()) {
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
    <body class="min-h-screen bg-gradient-to-br from-green-800 to-green-600 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <div class="text-6xl mb-4">🌱</div>
                <h1 class="text-2xl font-bold text-white">Espace Formateur</h1>
                <p class="text-green-200"><?= h($appName) ?></p>
            </div>
            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($error) ?></div>
            <?php endif; ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Identifiant</label>
                        <input type="text" name="username" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    <button type="submit" class="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Connexion</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$user = getLoggedUser();
$allowedSessionIds = getFormateurSessionIds($appKey);
$canCreateSessions = ($allowedSessionIds === null);

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create_session':
            if (!$canCreateSessions) {
                $error = "Vous n'avez pas les droits pour créer des sessions.";
                break;
            }
            $nom = trim($_POST['nom'] ?? '');
            if (!empty($nom)) {
                $code = generateSessionCode();
                $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)");
                $stmt->execute([$code, $nom, $user['id']]);
                $success = "Session créée avec le code: $code";
            }
            break;

        case 'create_scenario':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            if (!canAccessSession($appKey, $sessionId)) {
                $error = "Accès refusé.";
                break;
            }
            // Désactiver les anciens scénarios
            $stmt = $db->prepare("UPDATE scenarios SET is_active = 0 WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            // Créer le nouveau
            $stmt = $db->prepare("INSERT INTO scenarios (session_id, title, description, option1_name, option1_desc, option2_name, option2_desc, option3_name, option3_desc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $sessionId,
                trim($_POST['title'] ?? 'Nouveau scénario'),
                trim($_POST['description'] ?? ''),
                trim($_POST['option1_name'] ?? '🚀 IA Puissante (Cloud)'),
                trim($_POST['option1_desc'] ?? ''),
                trim($_POST['option2_name'] ?? '⚖️ IA Légère (Locale)'),
                trim($_POST['option2_desc'] ?? ''),
                trim($_POST['option3_name'] ?? '👥 Sans IA (Humain)'),
                trim($_POST['option3_desc'] ?? '')
            ]);
            $success = "Scénario créé et activé !";
            break;

        case 'stop_scenario':
            $scenarioId = (int)($_POST['scenario_id'] ?? 0);
            $stmt = $db->prepare("UPDATE scenarios SET is_active = 0 WHERE id = ?");
            $stmt->execute([$scenarioId]);
            $success = "Scénario arrêté.";
            break;

        case 'reset_votes':
            $scenarioId = (int)($_POST['scenario_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM votes WHERE scenario_id = ?");
            $stmt->execute([$scenarioId]);
            $success = "Votes réinitialisés.";
            break;

        case 'logout':
            logout();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
    }
}

// Récupérer les sessions
if ($allowedSessionIds === null) {
    $sessions = $db->query("SELECT * FROM sessions ORDER BY created_at DESC")->fetchAll();
} else {
    if (empty($allowedSessionIds)) {
        $sessions = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($allowedSessionIds), '?'));
        $stmt = $db->prepare("SELECT * FROM sessions WHERE id IN ($placeholders) ORDER BY created_at DESC");
        $stmt->execute($allowedSessionIds);
        $sessions = $stmt->fetchAll();
    }
}

// Session sélectionnée
$selectedSession = null;
$scenario = null;
$results = null;

if (isset($_GET['session'])) {
    $sessionId = (int)$_GET['session'];
    if (canAccessSession($appKey, $sessionId)) {
        $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $selectedSession = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedSession) {
            $scenario = getActiveScenario($db, $sessionId);
            if ($scenario) {
                $results = calculateAverages($db, $scenario['id']);
            }
        }
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
                    <h1 class="text-xl font-bold text-gray-900">🌱 Espace Formateur</h1>
                    <p class="text-sm text-gray-500"><?= h($appName) ?> - <?= h($user['username']) ?></p>
                </div>
                <div class="flex gap-3">
                    <a href="login.php" class="px-4 py-2 text-gray-600 hover:text-gray-800">Application</a>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Déconnexion</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= h($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Liste des sessions -->
            <div class="lg:col-span-1">
                <?php if ($canCreateSessions): ?>
                <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
                    <h2 class="font-semibold text-gray-800 mb-3">Nouvelle session</h2>
                    <form method="POST" class="flex gap-2">
                        <input type="hidden" name="action" value="create_session">
                        <input type="text" name="nom" placeholder="Nom de la session" required class="flex-1 px-3 py-2 border rounded-lg text-sm">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">Créer</button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="bg-white rounded-xl shadow-sm p-4">
                    <h2 class="font-semibold text-gray-800 mb-3">Sessions</h2>
                    <?php if (empty($sessions)): ?>
                        <p class="text-gray-500 text-sm">Aucune session.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($sessions as $s): ?>
                            <a href="?session=<?= $s['id'] ?>"
                               class="block p-3 rounded-lg border-2 transition-all <?= ($selectedSession && $selectedSession['id'] == $s['id']) ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-green-300' ?>">
                                <div class="font-medium text-gray-800"><?= h($s['nom']) ?></div>
                                <div class="text-sm text-gray-500">Code: <?= h($s['code']) ?></div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Détail de la session -->
            <div class="lg:col-span-2">
                <?php if ($selectedSession): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800"><?= h($selectedSession['nom']) ?></h2>
                            <p class="text-gray-500">Code d'accès: <span class="font-mono font-bold text-green-600"><?= h($selectedSession['code']) ?></span></p>
                        </div>
                        <?php if ($scenario): ?>
                        <a href="view.php?session=<?= $selectedSession['id'] ?>" target="_blank" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                            👁️ Voir les résultats
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($scenario): ?>
                    <!-- Scénario actif -->
                    <div class="bg-green-50 border-2 border-green-200 rounded-lg p-4 mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-green-700 font-medium">✅ Scénario actif</span>
                            <div class="flex gap-2">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="reset_votes">
                                    <input type="hidden" name="scenario_id" value="<?= $scenario['id'] ?>">
                                    <button type="submit" onclick="return confirm('Réinitialiser tous les votes ?')" class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 text-sm">🔄 Réinitialiser</button>
                                </form>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="stop_scenario">
                                    <input type="hidden" name="scenario_id" value="<?= $scenario['id'] ?>">
                                    <button type="submit" class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm">⏹️ Arrêter</button>
                                </form>
                            </div>
                        </div>
                        <h3 class="font-bold text-gray-800"><?= h($scenario['title']) ?></h3>
                        <p class="text-gray-600 text-sm"><?= h($scenario['description']) ?></p>
                    </div>

                    <!-- Résultats -->
                    <?php if ($results): ?>
                    <div class="grid md:grid-cols-3 gap-4 mb-4">
                        <?php
                        $maxScore = 0;
                        $bestOption = 0;
                        for ($i = 1; $i <= 3; $i++):
                            if ($results[$i]['score_global'] > $maxScore) {
                                $maxScore = $results[$i]['score_global'];
                                $bestOption = $i;
                            }
                        ?>
                        <div class="border rounded-lg p-4 <?= ($bestOption == $i && $maxScore > 0) ? 'border-green-500 bg-green-50' : '' ?>">
                            <h4 class="font-medium text-sm mb-2"><?= h($scenario["option{$i}_name"]) ?></h4>
                            <div class="text-xs text-gray-500 space-y-1">
                                <div>🌍 Impact: <?= $results[$i]['impact'] ?: '-' ?></div>
                                <div>⭐ Qualité: <?= $results[$i]['qualite'] ?: '-' ?></div>
                                <div>⏱️ Temps: <?= $results[$i]['temps'] ?: '-' ?></div>
                            </div>
                            <div class="mt-2 pt-2 border-t">
                                <span class="text-lg font-bold text-green-600"><?= $results[$i]['score_global'] ?: '-' ?></span>
                                <span class="text-xs text-gray-500">/100</span>
                                <div class="text-xs text-gray-400"><?= $results[$i]['voters'] ?> vote(s)</div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <!-- Pas de scénario actif, formulaire de création -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <h3 class="font-bold text-yellow-800 mb-4">🆕 Lancer un nouveau scénario</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="create_scenario">
                            <input type="hidden" name="session_id" value="<?= $selectedSession['id'] ?>">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Titre du scénario</label>
                                <input type="text" name="title" required placeholder="Ex: Génération de réponses FAQ" class="w-full px-3 py-2 border rounded-lg">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description du besoin</label>
                                <textarea name="description" rows="3" placeholder="Décrivez le besoin à évaluer..." class="w-full px-3 py-2 border rounded-lg"></textarea>
                            </div>

                            <div class="grid md:grid-cols-3 gap-4">
                                <div class="bg-blue-50 p-3 rounded-lg">
                                    <label class="block text-sm font-medium text-blue-800 mb-1">🚀 Option 1</label>
                                    <input type="text" name="option1_name" value="🚀 IA Puissante (Cloud)" class="w-full px-2 py-1 border rounded text-sm mb-2">
                                    <textarea name="option1_desc" rows="2" placeholder="Description..." class="w-full px-2 py-1 border rounded text-sm">Utilisation d'un grand modèle d'IA via API cloud (GPT-4, Claude, etc.)</textarea>
                                </div>
                                <div class="bg-purple-50 p-3 rounded-lg">
                                    <label class="block text-sm font-medium text-purple-800 mb-1">⚖️ Option 2</label>
                                    <input type="text" name="option2_name" value="⚖️ IA Légère (Locale)" class="w-full px-2 py-1 border rounded text-sm mb-2">
                                    <textarea name="option2_desc" rows="2" placeholder="Description..." class="w-full px-2 py-1 border rounded text-sm">Modèle d'IA plus petit, exécuté localement ou solution hybride</textarea>
                                </div>
                                <div class="bg-orange-50 p-3 rounded-lg">
                                    <label class="block text-sm font-medium text-orange-800 mb-1">👥 Option 3</label>
                                    <input type="text" name="option3_name" value="👥 Sans IA (Humain)" class="w-full px-2 py-1 border rounded text-sm mb-2">
                                    <textarea name="option3_desc" rows="2" placeholder="Description..." class="w-full px-2 py-1 border rounded text-sm">Approche traditionnelle sans intelligence artificielle</textarea>
                                </div>
                            </div>

                            <button type="submit" class="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                                🚀 Lancer le scénario
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <div class="text-6xl mb-4">👈</div>
                    <p class="text-gray-500">Sélectionnez une session pour gérer les scénarios d'évaluation.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php if ($scenario): ?>
    <script>
        // Auto-refresh des résultats
        setInterval(() => {
            fetch('api.php?action=poll&scenario_id=<?= $scenario['id'] ?>')
                .then(r => r.json())
                .then(data => {
                    if (data.results) {
                        location.reload();
                    }
                });
        }, 5000);
    </script>
    <?php endif; ?>
</body>
</html>
