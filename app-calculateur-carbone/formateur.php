<?php
require_once __DIR__ . '/config.php';

$user = getLoggedUser();
$db = getDB();

$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'sessions';

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        // Login formateur
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = authenticateUser($username, $password);
        if ($user && $user['role'] === 'formateur') {
            login($user);
            header('Location: formateur.php');
            exit;
        } else {
            $error = 'Acces reserve aux formateurs.';
            $user = null;
        }
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_session') {
            $nom = trim($_POST['nom'] ?? '');
            $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            if (!empty($nom)) {
                $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_id) VALUES (?, ?, ?)");
                $stmt->execute([$code, $nom, $user['id']]);
                $success = "Session creee avec le code: $code";
            }
        } elseif ($action === 'toggle_session') {
            $sessionId = intval($_POST['session_id'] ?? 0);
            $stmt = $db->prepare("UPDATE sessions SET is_active = NOT is_active WHERE id = ? AND formateur_id = ?");
            $stmt->execute([$sessionId, $user['id']]);
        } elseif ($action === 'update_ecologits') {
            // Lancer le script de mise a jour
            $output = [];
            $returnCode = 0;
            exec('php ' . __DIR__ . '/update_ecologits.php 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                $success = "Mise a jour depuis EcoLogits effectuee.";
            } else {
                $error = "Erreur lors de la mise a jour: " . implode("\n", $output);
            }
        }
    }
}

// Si non connecte, afficher login
if (!$user || $user['role'] !== 'formateur') {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Formateur - <?= h(APP_NAME) ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>body { font-family: 'Inter', sans-serif; }</style>
    </head>
    <body class="min-h-screen bg-gradient-to-br from-emerald-600 to-emerald-800 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white mb-2">Espace Formateur</h1>
                <p class="text-emerald-200"><?= h(APP_NAME) ?></p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8">
                <?php if ($error): ?>
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Identifiant</label>
                        <input type="text" name="username" required
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-emerald-500">
                    </div>
                    <button type="submit"
                            class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg">
                        Connexion
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <a href="login.php" class="text-emerald-600 hover:text-emerald-800 text-sm">Retour participant</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Charger les sessions du formateur
$stmt = $db->prepare("SELECT * FROM sessions WHERE formateur_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Charger les estimations
$estimations = getEstimations();
$categories = $estimations['categories'] ?? [];
$useCases = $estimations['use_cases'] ?? [];
$metadata = $estimations['_metadata'] ?? [];

// Stats session selectionnee
$selectedSessionId = intval($_GET['session'] ?? ($sessions[0]['id'] ?? 0));
$sessionStats = null;
if ($selectedSessionId) {
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND formateur_id = ?");
    $stmt->execute([$selectedSessionId, $user['id']]);
    $selectedSession = $stmt->fetch();

    if ($selectedSession) {
        // Participants
        $stmt = $db->prepare("
            SELECT u.id, u.prenom, u.username,
                   COALESCE(SUM(c.co2_total), 0) as total_co2,
                   COUNT(c.id) as nb_calculs
            FROM participants p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN calculs c ON c.user_id = u.id AND c.session_id = p.session_id
            WHERE p.session_id = ?
            GROUP BY u.id
            ORDER BY total_co2 DESC
        ");
        $stmt->execute([$selectedSessionId]);
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total session
        $stmt = $db->prepare("SELECT SUM(co2_total) as total FROM calculs WHERE session_id = ?");
        $stmt->execute([$selectedSessionId]);
        $totalSession = $stmt->fetch()['total'] ?? 0;

        // Top use cases
        $stmt = $db->prepare("
            SELECT use_case_id, COUNT(*) as nb, SUM(co2_total) as total_co2
            FROM calculs WHERE session_id = ?
            GROUP BY use_case_id ORDER BY total_co2 DESC LIMIT 10
        ");
        $stmt->execute([$selectedSessionId]);
        $topUseCases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sessionStats = [
            'session' => $selectedSession,
            'participants' => $participants,
            'total' => $totalSession,
            'top_use_cases' => $topUseCases
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formateur - <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-emerald-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold"><?= h(APP_NAME) ?> - Formateur</h1>
                    <p class="text-emerald-200 text-sm">Bienvenue, <?= h($user['prenom'] ?? $user['username']) ?></p>
                </div>
                <a href="logout.php" class="bg-emerald-800 hover:bg-emerald-900 px-4 py-2 rounded">
                    Deconnexion
                </a>
            </div>
        </div>
    </header>

    <!-- Tabs -->
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4">
            <nav class="flex gap-4">
                <a href="?tab=sessions" class="py-4 px-2 border-b-2 <?= $activeTab === 'sessions' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-600 hover:text-emerald-600' ?>">
                    Sessions
                </a>
                <a href="?tab=stats&session=<?= $selectedSessionId ?>" class="py-4 px-2 border-b-2 <?= $activeTab === 'stats' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-600 hover:text-emerald-600' ?>">
                    Statistiques
                </a>
                <a href="?tab=estimations" class="py-4 px-2 border-b-2 <?= $activeTab === 'estimations' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-600 hover:text-emerald-600' ?>">
                    Estimations CO2
                </a>
            </nav>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= h($success) ?></div>
        <?php endif; ?>

        <?php if ($activeTab === 'sessions'): ?>
        <!-- ONGLET SESSIONS -->
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Creer session -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Creer une session</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create_session">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom de la session</label>
                        <input type="text" name="nom" required placeholder="Ex: Formation IA Durable - Janvier 2025"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-emerald-500">
                    </div>
                    <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">
                        Creer la session
                    </button>
                </form>
            </div>

            <!-- Liste sessions -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Mes sessions</h2>
                <div class="space-y-2">
                    <?php foreach ($sessions as $session): ?>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="font-medium"><?= h($session['nom']) ?></p>
                            <p class="text-sm text-gray-500">
                                Code: <span class="font-mono font-bold text-emerald-600"><?= h($session['code']) ?></span>
                                <?php if (!$session['is_active']): ?>
                                    <span class="text-red-500">(inactive)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a href="?tab=stats&session=<?= $session['id'] ?>" class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded text-sm hover:bg-emerald-200">
                                Stats
                            </a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_session">
                                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                <button type="submit" class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300">
                                    <?= $session['is_active'] ? 'Desactiver' : 'Activer' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($sessions)): ?>
                        <p class="text-gray-500 text-center py-4">Aucune session creee</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($activeTab === 'stats' && $sessionStats): ?>
        <!-- ONGLET STATS -->
        <div class="space-y-6">
            <!-- Header session -->
            <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 rounded-xl p-6 text-white">
                <h2 class="text-2xl font-bold"><?= h($sessionStats['session']['nom']) ?></h2>
                <p class="text-emerald-200">Code: <?= h($sessionStats['session']['code']) ?></p>
                <div class="mt-4 grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-4xl font-bold"><?= count($sessionStats['participants']) ?></p>
                        <p class="text-emerald-200">Participants</p>
                    </div>
                    <div>
                        <p class="text-4xl font-bold"><?= number_format($sessionStats['total']/1000, 1, ',', ' ') ?></p>
                        <p class="text-emerald-200">kg CO2 total</p>
                    </div>
                    <div>
                        <p class="text-4xl font-bold"><?= round($sessionStats['total']/1000/0.21, 0) ?></p>
                        <p class="text-emerald-200">km voiture eq.</p>
                    </div>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Classement participants -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Classement participants</h3>
                    <div class="space-y-2">
                        <?php foreach ($sessionStats['participants'] as $i => $p): ?>
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-bold">
                                    <?= $i + 1 ?>
                                </span>
                                <span><?= h($p['prenom'] ?: $p['username']) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="font-semibold text-emerald-600"><?= number_format($p['total_co2'], 0, ',', ' ') ?>g</span>
                                <span class="text-xs text-gray-500 block"><?= $p['nb_calculs'] ?> usages</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($sessionStats['participants'])): ?>
                            <p class="text-gray-500 text-center py-4">Aucun participant</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top use cases -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Top cas d'usage</h3>
                    <div class="space-y-2">
                        <?php foreach ($sessionStats['top_use_cases'] as $uc):
                            $ucData = $useCases[$uc['use_case_id']] ?? null;
                        ?>
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <div>
                                <p class="font-medium"><?= h($ucData['nom'] ?? $uc['use_case_id']) ?></p>
                                <p class="text-xs text-gray-500"><?= $uc['nb'] ?> utilisations</p>
                            </div>
                            <span class="font-semibold text-emerald-600"><?= number_format($uc['total_co2'], 0, ',', ' ') ?>g</span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($sessionStats['top_use_cases'])): ?>
                            <p class="text-gray-500 text-center py-4">Aucun usage enregistre</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($activeTab === 'estimations'): ?>
        <!-- ONGLET ESTIMATIONS -->
        <div class="space-y-6">
            <!-- Metadata et mise a jour -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Base d'estimations CO2</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Version: <?= h($metadata['version'] ?? '1.0') ?> |
                            Derniere mise a jour: <?= h($metadata['last_updated'] ?? 'Inconnue') ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-1"><?= h($metadata['source'] ?? '') ?></p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_ecologits">
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Mettre a jour depuis EcoLogits
                        </button>
                    </form>
                </div>
            </div>

            <!-- Liste par categorie -->
            <?php foreach ($categories as $catId => $cat):
                $catUseCases = array_filter($useCases, fn($uc) => $uc['categorie'] === $catId);
            ?>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <?= $cat['icon'] ?> <?= h($cat['nom']) ?>
                    <span class="text-sm font-normal text-gray-500">(<?= count($catUseCases) ?> cas)</span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-2">Cas d'usage</th>
                                <th class="text-left p-2">Modele</th>
                                <th class="text-right p-2">Tokens</th>
                                <th class="text-right p-2">CO2 (g)</th>
                                <th class="text-left p-2">Equivalent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($catUseCases as $ucId => $uc): ?>
                            <tr class="border-t">
                                <td class="p-2">
                                    <p class="font-medium"><?= h($uc['nom']) ?></p>
                                    <p class="text-xs text-gray-500"><?= h($uc['description']) ?></p>
                                </td>
                                <td class="p-2 text-gray-600"><?= h($uc['modele_type']) ?></td>
                                <td class="p-2 text-right"><?= number_format($uc['tokens_estimes'], 0, ',', ' ') ?></td>
                                <td class="p-2 text-right font-semibold text-emerald-600"><?= $uc['co2_grammes'] ?></td>
                                <td class="p-2 text-gray-500 text-xs"><?= h($uc['equivalent']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
