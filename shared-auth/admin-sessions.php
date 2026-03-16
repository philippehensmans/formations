<?php
/**
 * Administration des sessions de formation
 * Page dediee a la gestion centralisee des sessions
 * Accessible uniquement par les super-admins
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sessions.php';

// Verifier super-admin (ou admin pour login initial)
if (!isLoggedIn() || !isAdmin()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = authenticateUser($username, $password);
        if ($user && $user['is_admin']) {
            login($user);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        $error = 'Acces refuse. Compte admin requis.';
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Sessions</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-gray-900 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-6">
                <img src="../logo.png" alt="Logo" class="h-16 mx-auto mb-4">
                <h1 class="text-2xl font-bold text-gray-800">Admin Sessions</h1>
            </div>
            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Identifiant admin</label>
                    <input type="text" name="username" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                    <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <button type="submit" class="w-full py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-700">Connexion</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$db = getSharedDB();
$success = '';
$error = '';
$currentUser = getLoggedUser();
$isSuperAdmin = isSuperAdmin();

// Liste des applications
$apps = [
    'app-activites' => 'Inventaire des Activites',
    'app-agile' => 'Gestion Agile',
    'app-arbreproblemes' => 'Arbre a Problemes',
    'app-atelier-ia' => 'Atelier IA',
    'app-cadrelogique' => 'Cadre Logique',
    'app-cahier-charges' => 'Cahier des Charges',
    'app-calculateur-carbone' => 'Calculateur Carbone IA',
    'app-carte-identite' => 'Carte d\'Identite du Projet',
    'app-carte-projet' => 'Carte Projet',
    'app-empreinte-carbone' => 'Empreinte Carbone',
    'app-guide-prompting' => 'Guide Prompting',
    'app-mesure-impact' => 'Mesure d\'Impact',
    'app-mindmap' => 'Carte Mentale',
    'app-objectifs-smart' => 'Objectifs SMART',
    'app-parties-prenantes' => 'Parties Prenantes',
    'app-pestel' => 'Analyse PESTEL',
    'app-prompt-jeunes' => 'Prompt Engineering',
    'app-six-chapeaux' => 'Six Chapeaux',
    'app-stop-start-continue' => 'Stop Start Continue',
    'app-swot' => 'Analyse SWOT',
    'app-whiteboard' => 'Tableau Blanc',
    'app-journey-mapping' => 'Journey Mapping',
    'app-personas' => 'Publics & Personas',
    'app-comm-plan' => 'Mini-Plan de Communication',
    'app-pilotage-projet' => 'Pilotage de Projet'
];

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_session':
            if ($isSuperAdmin) {
                $nom = trim($_POST['nom'] ?? '');
                $formateurId = (int)($_POST['formateur_id'] ?? 0) ?: null;
                if (!empty($nom)) {
                    $code = generateSessionCode();
                    $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)");
                    $stmt->execute([$code, $nom, $formateurId]);
                    $success = "Session creee : <strong>$code</strong> - " . h($nom);
                } else {
                    $error = "Le nom de la session est requis.";
                }
            }
            break;

        case 'update_session':
            if ($isSuperAdmin) {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $nom = trim($_POST['nom'] ?? '');
                $formateurId = (int)($_POST['formateur_id'] ?? 0) ?: null;
                if ($sessionId && !empty($nom)) {
                    $stmt = $db->prepare("UPDATE sessions SET nom = ?, formateur_id = ? WHERE id = ?");
                    $stmt->execute([$nom, $formateurId, $sessionId]);
                    $success = "Session mise a jour.";
                }
            }
            break;

        case 'toggle_session':
            if ($isSuperAdmin) {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                if ($sessionId) {
                    $db->prepare("UPDATE sessions SET is_active = NOT is_active WHERE id = ?")
                        ->execute([$sessionId]);
                    $success = "Statut de la session modifie.";
                }
            }
            break;

        case 'delete_session':
            if ($isSuperAdmin) {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                if ($sessionId) {
                    $db->prepare("DELETE FROM formateur_sessions WHERE session_id = ?")
                        ->execute([$sessionId]);
                    $db->prepare("DELETE FROM sessions WHERE id = ?")
                        ->execute([$sessionId]);
                    $success = "Session supprimee.";
                }
            }
            break;

        case 'assign_formateur':
            if ($isSuperAdmin) {
                $formateurId = (int)($_POST['formateur_id'] ?? 0);
                $appNames = $_POST['app_names'] ?? [];
                $sessionId = (int)($_POST['session_id'] ?? 0);
                if ($formateurId && !empty($appNames) && $sessionId) {
                    $assigned = 0;
                    foreach ($appNames as $appName) {
                        if (assignFormateurToSession($formateurId, $appName, $sessionId)) {
                            $assigned++;
                        }
                    }
                    $success = "$assigned application(s) affectee(s).";
                }
            }
            break;

        case 'remove_assignment':
            if ($isSuperAdmin) {
                $formateurId = (int)($_POST['formateur_id'] ?? 0);
                $appName = $_POST['app_name'] ?? '';
                $sessionId = (int)($_POST['session_id'] ?? 0);
                if (removeFormateurFromSession($formateurId, $appName, $sessionId)) {
                    $success = "Affectation retiree.";
                }
            }
            break;

        case 'logout':
            logout();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
    }
}

// Recuperer les sessions
$allSessions = $db->query("SELECT s.*, u.username as formateur_username, u.prenom as formateur_prenom, u.nom as formateur_nom
    FROM sessions s LEFT JOIN users u ON s.formateur_id = u.id
    ORDER BY s.is_active DESC, s.created_at DESC")->fetchAll();

// Recuperer les formateurs
$formateurs = $db->query("SELECT id, username, prenom, nom, is_super_admin FROM users WHERE is_formateur = 1 ORDER BY nom, prenom")->fetchAll();

// Recuperer les affectations formateurs
$allAssignments = $db->query("SELECT * FROM formateur_sessions ORDER BY session_id, formateur_id, app_name")->fetchAll();
$sessionAssignments = [];
foreach ($allAssignments as $a) {
    $sessionAssignments[$a['session_id']][] = $a;
}

// Compter les participants par session dans chaque app locale
$participantCounts = [];
foreach ($apps as $appKey => $appLabel) {
    $dbPath = __DIR__ . '/../' . $appKey . '/data/database.sqlite';
    if (file_exists($dbPath)) {
        try {
            $appDb = new PDO('sqlite:' . $dbPath);
            $appDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $appDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $rows = $appDb->query("SELECT session_id, COUNT(*) as cnt FROM participants GROUP BY session_id")->fetchAll();
            foreach ($rows as $row) {
                $participantCounts[$row['session_id']][$appKey] = $row['cnt'];
            }
        } catch (Exception $e) { /* app DB not available */ }
    }
}

// Nombre de formateurs par session (via affectations)
$formateursBySession = [];
foreach ($allAssignments as $a) {
    $formateursBySession[$a['session_id']][$a['formateur_id']] = true;
}

// Session en detail (si demandee)
$detailSession = null;
$detailSessionId = (int)($_GET['detail'] ?? 0);
if ($detailSessionId) {
    foreach ($allSessions as $s) {
        if ($s['id'] == $detailSessionId) {
            $detailSession = $s;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sessions - Shared Auth</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100">
    <header class="bg-gray-800 text-white p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <img src="../logo.png" alt="Logo" class="h-10">
                <div>
                    <h1 class="text-xl font-bold">Gestion des Sessions</h1>
                    <p class="text-sm text-gray-400">
                        Connecte: <?= h($currentUser['username']) ?>
                        <?php if ($isSuperAdmin): ?>
                            <span class="ml-2 px-2 py-0.5 bg-purple-600 rounded text-xs">Super-Admin</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="admin.php" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-500 text-sm">Utilisateurs</a>
                <a href="../admin-categories.php" class="px-4 py-2 bg-indigo-600 rounded hover:bg-indigo-500 text-sm">Categories</a>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600">Deconnexion</button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6">
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- Statistiques rapides -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <?php
            $activeSessions = array_filter($allSessions, function($s) { return $s['is_active']; });
            $inactiveSessions = array_filter($allSessions, function($s) { return !$s['is_active']; });
            $totalParticipants = 0;
            foreach ($participantCounts as $sid => $apps_counts) {
                $totalParticipants += max($apps_counts);
            }
            ?>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-2xl font-bold text-gray-800"><?= count($allSessions) ?></p>
                <p class="text-sm text-gray-500">Sessions au total</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <p class="text-2xl font-bold text-green-600"><?= count($activeSessions) ?></p>
                <p class="text-sm text-gray-500">Sessions actives</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-gray-400">
                <p class="text-2xl font-bold text-gray-500"><?= count($inactiveSessions) ?></p>
                <p class="text-sm text-gray-500">Sessions inactives</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                <p class="text-2xl font-bold text-purple-600"><?= count($formateurs) ?></p>
                <p class="text-sm text-gray-500">Formateurs</p>
            </div>
        </div>

        <?php if ($isSuperAdmin): ?>
        <!-- Creer une session -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Creer une nouvelle session</h2>
            <form method="POST" class="flex flex-wrap gap-3 items-end">
                <input type="hidden" name="action" value="create_session">
                <div class="flex-1 min-w-64">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Nom de la session *</label>
                    <input type="text" name="nom" placeholder="Ex: CESEP Mars 2026, Formation ONG Bruxelles..." required
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="w-64">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Formateur responsable</label>
                    <select name="formateur_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Aucun --</option>
                        <?php foreach ($formateurs as $f): ?>
                        <option value="<?= $f['id'] ?>">
                            <?= h($f['prenom'] . ' ' . $f['nom']) ?> (<?= h($f['username']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    Creer la session
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Liste des sessions -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="bg-gray-50 p-4 border-b font-semibold text-gray-700">
                Sessions de formation (<?= count($allSessions) ?>)
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left p-3">Code</th>
                            <th class="text-left p-3">Nom</th>
                            <th class="text-left p-3">Formateur</th>
                            <th class="text-center p-3">Participants</th>
                            <th class="text-center p-3">Formateurs affectes</th>
                            <th class="text-center p-3">Statut</th>
                            <th class="text-left p-3">Creee le</th>
                            <?php if ($isSuperAdmin): ?>
                            <th class="text-center p-3">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($allSessions as $session): ?>
                        <?php
                        $maxParticipants = 0;
                        if (!empty($participantCounts[$session['id']])) {
                            $maxParticipants = max($participantCounts[$session['id']]);
                        }
                        $nbFormateurs = count($formateursBySession[$session['id']] ?? []);
                        ?>
                        <tr class="hover:bg-gray-50 <?= $session['is_active'] ? '' : 'opacity-60' ?>">
                            <td class="p-3">
                                <a href="?detail=<?= $session['id'] ?>" class="font-mono font-bold text-blue-600 hover:text-blue-800 hover:underline">
                                    <?= h($session['code']) ?>
                                </a>
                            </td>
                            <td class="p-3 font-medium"><?= h($session['nom']) ?></td>
                            <td class="p-3 text-gray-600">
                                <?php if ($session['formateur_username']): ?>
                                    <?= h($session['formateur_prenom'] . ' ' . $session['formateur_nom']) ?>
                                    <span class="text-gray-400 text-xs">(<?= h($session['formateur_username']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php if ($maxParticipants > 0): ?>
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-medium"><?= $maxParticipants ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php if ($nbFormateurs > 0): ?>
                                    <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded font-medium"><?= $nbFormateurs ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php if ($session['is_active']): ?>
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Active</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 bg-gray-200 text-gray-600 rounded text-xs font-medium">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-gray-500 text-xs"><?= date('d/m/Y H:i', strtotime($session['created_at'])) ?></td>
                            <?php if ($isSuperAdmin): ?>
                            <td class="p-3 text-center">
                                <div class="flex gap-1 justify-center">
                                    <a href="?detail=<?= $session['id'] ?>" class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200">Detail</a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_session">
                                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                        <button type="submit" class="px-2 py-1 rounded text-xs <?= $session['is_active'] ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200' ?>">
                                            <?= $session['is_active'] ? 'Desactiver' : 'Activer' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer la session <?= h($session['code']) ?> ?\nLes donnees des participants dans chaque app ne seront pas supprimees.')">
                                        <input type="hidden" name="action" value="delete_session">
                                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                        <button type="submit" class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($allSessions)): ?>
                        <tr><td colspan="8" class="p-8 text-center text-gray-500">Aucune session creee.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($detailSession && $isSuperAdmin): ?>
        <!-- Detail d'une session -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6" id="detail">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-3">
                        <span class="font-mono text-blue-600 bg-blue-50 px-3 py-1 rounded"><?= h($detailSession['code']) ?></span>
                        <?= h($detailSession['nom']) ?>
                        <?php if ($detailSession['is_active']): ?>
                            <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Active</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 bg-gray-200 text-gray-600 rounded text-xs font-medium">Inactive</span>
                        <?php endif; ?>
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Creee le <?= date('d/m/Y a H:i', strtotime($detailSession['created_at'])) ?>
                    </p>
                </div>
                <a href="?" class="px-3 py-1 text-gray-500 hover:text-gray-700 text-sm">Fermer le detail</a>
            </div>

            <!-- Modifier la session -->
            <form method="POST" class="bg-gray-50 rounded-lg p-4 mb-6">
                <input type="hidden" name="action" value="update_session">
                <input type="hidden" name="session_id" value="<?= $detailSession['id'] ?>">
                <h3 class="font-medium text-gray-700 mb-3">Modifier la session</h3>
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Nom</label>
                        <input type="text" name="nom" value="<?= h($detailSession['nom']) ?>" required
                               class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div class="w-56">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Formateur responsable</label>
                        <select name="formateur_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">-- Aucun --</option>
                            <?php foreach ($formateurs as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= ($detailSession['formateur_id'] == $f['id']) ? 'selected' : '' ?>>
                                <?= h($f['prenom'] . ' ' . $f['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">Sauver</button>
                </div>
            </form>

            <!-- Participants par application -->
            <div class="mb-6">
                <h3 class="font-medium text-gray-700 mb-3">Participants par application</h3>
                <?php
                $sessionParticipants = $participantCounts[$detailSession['id']] ?? [];
                ?>
                <?php if (empty($sessionParticipants)): ?>
                    <p class="text-sm text-gray-500 italic">Aucun participant enregistre dans cette session.</p>
                <?php else: ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                        <?php foreach ($sessionParticipants as $appKey => $count): ?>
                        <div class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                            <span class="text-sm text-gray-700"><?= h($apps[$appKey] ?? $appKey) ?></span>
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold"><?= $count ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Affectations formateurs pour cette session -->
            <div class="mb-6">
                <h3 class="font-medium text-gray-700 mb-3">Affectations formateurs</h3>
                <?php $assignments = $sessionAssignments[$detailSession['id']] ?? []; ?>
                <?php if (!empty($assignments)): ?>
                <div class="flex flex-wrap gap-2 mb-3">
                    <?php foreach ($assignments as $a): ?>
                    <?php
                    $formateurName = '';
                    foreach ($formateurs as $f) {
                        if ($f['id'] == $a['formateur_id']) {
                            $formateurName = $f['prenom'] . ' ' . $f['nom'];
                            break;
                        }
                    }
                    ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="remove_assignment">
                        <input type="hidden" name="formateur_id" value="<?= $a['formateur_id'] ?>">
                        <input type="hidden" name="app_name" value="<?= h($a['app_name']) ?>">
                        <input type="hidden" name="session_id" value="<?= $a['session_id'] ?>">
                        <button type="submit" class="px-3 py-1.5 bg-blue-100 text-blue-800 rounded-lg text-xs hover:bg-red-100 hover:text-red-800 transition" title="Cliquer pour retirer">
                            <span class="font-medium"><?= h($formateurName) ?></span>
                            <span class="text-gray-500"><?= h($apps[$a['app_name']] ?? $a['app_name']) ?></span>
                            <span class="ml-1 text-red-400">x</span>
                        </button>
                    </form>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-green-600 mb-3">Aucune affectation specifique. Tous les formateurs peuvent voir cette session.</p>
                <?php endif; ?>

                <!-- Ajouter une affectation -->
                <?php
                $nonSuperFormateurs = array_filter($formateurs, function($f) { return !$f['is_super_admin']; });
                ?>
                <?php if (!empty($nonSuperFormateurs)): ?>
                <form method="POST" class="bg-gray-50 rounded-lg p-4">
                    <input type="hidden" name="action" value="assign_formateur">
                    <input type="hidden" name="session_id" value="<?= $detailSession['id'] ?>">
                    <h4 class="text-sm font-medium text-gray-600 mb-2">Ajouter une affectation</h4>
                    <div class="flex flex-wrap gap-3 items-end">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Formateur</label>
                            <select name="formateur_id" required class="px-3 py-2 border rounded-lg text-sm">
                                <option value="">-- Choisir --</option>
                                <?php foreach ($nonSuperFormateurs as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= h($f['prenom'] . ' ' . $f['nom']) ?> (<?= h($f['username']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Applications</label>
                            <select name="app_names[]" multiple required class="px-3 py-2 border rounded-lg text-sm h-24 min-w-48">
                                <?php foreach ($apps as $appKey => $appLabel): ?>
                                <option value="<?= h($appKey) ?>"><?= h($appLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Ctrl+clic pour selectionner plusieurs</p>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Affecter</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info -->
        <div class="text-sm text-gray-500">
            <p>Base de donnees: <?= realpath(SHARED_DB_PATH) ?: SHARED_DB_PATH ?></p>
        </div>
    </main>

    <script>
        // Scroll to detail section if present
        <?php if ($detailSession): ?>
        document.getElementById('detail')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        <?php endif; ?>
    </script>
</body>
</html>
