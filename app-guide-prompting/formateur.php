<?php
/**
 * Page formateur - Guide de Prompting
 * Avec affichage des activites des participants (taches, prompts, completion)
 */

$appName = 'Guide Prompting';
$appColor = 'indigo';
$appKey = 'app-guide-prompting';

// Charger la config locale
require_once __DIR__ . '/config.php';
$db = getDB();

// Charger les dependances
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

$error = '';
$success = '';
$lang = getCurrentLanguage();

// Verifier si formateur connecte
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
            $error = t('auth.login_error_trainer');
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= t('trainer.title') ?> - <?= h($appName) ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <img src="../logo.png" alt="Logo" class="h-16 mx-auto mb-4">
                <h1 class="text-2xl font-bold text-gray-800"><?= t('trainer.title') ?></h1>
                <p class="text-gray-600"><?= h($appName) ?></p>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex justify-end mb-4">
                    <?= renderLanguageSelector('text-sm border rounded px-2 py-1') ?>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.username') ?></label>
                        <input type="text" name="username" required
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-<?= $appColor ?>-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('auth.password') ?></label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-<?= $appColor ?>-500">
                    </div>
                    <button type="submit" class="w-full py-2 bg-<?= $appColor ?>-600 text-white rounded-lg hover:bg-<?= $appColor ?>-700">
                        <?= t('auth.login') ?>
                    </button>
                </form>
            </div>

            <div class="mt-4 text-center">
                <a href="login.php" class="text-<?= $appColor ?>-600 hover:text-<?= $appColor ?>-800 text-sm">
                    <?= t('auth.back_to_app') ?>
                </a>
            </div>
        </div>
        <?= renderLanguageScript() ?>
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

// Recuperer les IDs des sessions autorisees pour ce formateur
$allowedSessionIds = getFormateurSessionIds($appKey);
$canCreateSessions = ($allowedSessionIds === null);

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_session' && $canCreateSessions) {
        $nom = trim($_POST['nom'] ?? '');
        if (!empty($nom)) {
            $code = generateSessionCode();
            $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)");
            $stmt->execute([$code, $nom, $user['id']]);
            $success = t('trainer.session_created', ['code' => $code]);
        }
    } elseif ($action === 'toggle_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if (canAccessSession($appKey, $sessionId)) {
            toggleSession($db, $sessionId);
            $success = t('trainer.session_status_changed');
        } else {
            $error = t('trainer.access_denied');
        }
    } elseif ($action === 'delete_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if (canAccessSession($appKey, $sessionId)) {
            deleteSession($db, $sessionId);
            $success = t('trainer.session_deleted');
        } else {
            $error = t('trainer.access_denied');
        }
    } elseif ($action === 'logout') {
        logout();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Recuperer les sessions
if ($allowedSessionIds === null) {
    $sessions = $db->query("SELECT * FROM sessions ORDER BY created_at DESC")->fetchAll();
} elseif (empty($allowedSessionIds)) {
    $sessions = [];
} else {
    $placeholders = implode(',', array_fill(0, count($allowedSessionIds), '?'));
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id IN ($placeholders) ORDER BY created_at DESC");
    $stmt->execute($allowedSessionIds);
    $sessions = $stmt->fetchAll();
}

// Recuperer les participants si une session est selectionnee
$selectedSession = null;
$participants = [];
$debugInfo = ''; // Pour le debogage

if (isset($_GET['session'])) {
    $sessionId = (int)$_GET['session'];
    $debugInfo .= "Session ID demandee: $sessionId. ";

    // Verifier l'acces a cette session
    if (!canAccessSession($appKey, $sessionId)) {
        $error = t('trainer.access_denied');
        $debugInfo .= "Acces refuse par canAccessSession. ";
    } else {
        $selectedSession = getSessionById($db, $sessionId);
        $debugInfo .= "Acces autorise. ";

        if ($selectedSession) {
            $debugInfo .= "Session trouvee: " . $selectedSession['code'] . ". ";

            // Recuperer les participants - combiner participants table ET guides table
            // Car certains utilisateurs peuvent avoir un guide sans etre dans la table participants
            $stmt = $db->prepare("
                SELECT DISTINCT user_id, MIN(created_at) as created_at
                FROM (
                    SELECT user_id, created_at FROM participants WHERE session_id = ?
                    UNION
                    SELECT user_id, created_at FROM guides WHERE session_id = ?
                ) AS combined
                GROUP BY user_id
            ");
            $stmt->execute([$selectedSession['id'], $selectedSession['id']]);
            $localParticipants = $stmt->fetchAll();
            $debugInfo .= "Participants (participants+guides): " . count($localParticipants) . ". ";

            // Enrichir avec les donnees utilisateur de la base partagee
            $sharedDb = getSharedDB();
            foreach ($localParticipants as $p) {
                $userStmt = $sharedDb->prepare("SELECT username, prenom, nom, organisation FROM users WHERE id = ?");
                $userStmt->execute([$p['user_id']]);
                $userData = $userStmt->fetch();

                if ($userData) {
                    // Recuperer les donnees du guide pour ce participant
                    $guideStmt = $db->prepare("SELECT tasks, templates, completion_percent, is_shared, organisation_nom FROM guides WHERE user_id = ? AND session_id = ?");
                    $guideStmt->execute([$p['user_id'], $selectedSession['id']]);
                    $guideData = $guideStmt->fetch();

                    // Compter les taches et templates
                    $taskCount = 0;
                    $templateCount = 0;
                    if ($guideData) {
                        $tasks = json_decode($guideData['tasks'] ?? '[]', true);
                        $templates = json_decode($guideData['templates'] ?? '[]', true);
                        $taskCount = is_array($tasks) ? count($tasks) : 0;
                        $templateCount = is_array($templates) ? count($templates) : 0;
                    }

                    // Ajouter le participant avec toutes ses donnees
                    $participants[] = [
                        'user_id' => $p['user_id'],
                        'session_id' => $selectedSession['id'],
                        'created_at' => $p['created_at'] ?? date('Y-m-d H:i:s'),
                        'username' => $userData['username'],
                        'prenom' => $userData['prenom'],
                        'nom' => $userData['nom'],
                        'organisation' => $userData['organisation'] ?? '',
                        'guide_exists' => $guideData ? true : false,
                        'task_count' => $taskCount,
                        'template_count' => $templateCount,
                        'completion_percent' => $guideData ? (int)($guideData['completion_percent'] ?? 0) : 0,
                        'is_shared' => $guideData ? (int)($guideData['is_shared'] ?? 0) : 0,
                        'guide_organisation' => $guideData ? ($guideData['organisation_nom'] ?? '') : ''
                    ];
                    $debugInfo .= "Participant ajoute: " . $userData['username'] . ". ";
                } else {
                    $debugInfo .= "User ID " . $p['user_id'] . " non trouve dans shared DB. ";
                }
            }
            $debugInfo .= "Total participants enrichis: " . count($participants) . ". ";
        } else {
            $debugInfo .= "Session non trouvee dans la DB. ";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('trainer.title') ?> - <?= h($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100">
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900"><?= t('trainer.title') ?></h1>
                    <p class="text-sm text-gray-500"><?= h($appName) ?> - <?= h($user['username']) ?></p>
                </div>
                <div class="flex gap-3 items-center">
                    <?= renderLanguageSelector('text-sm border rounded px-2 py-1') ?>
                    <a href="login.php" class="px-4 py-2 text-gray-600 hover:text-gray-800"><?= t('trainer.application') ?></a>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            <?= t('auth.logout') ?>
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

        <?php if ($debugInfo): ?>
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg text-sm">
                <strong>Debug:</strong> <?= h($debugInfo) ?>
            </div>
        <?php endif; ?>

        <?php if ($canCreateSessions): ?>
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4"><?= t('trainer.create_new_session') ?></h2>
            <form method="POST" class="flex gap-4">
                <input type="hidden" name="action" value="create_session">
                <input type="text" name="nom" placeholder="<?= t('trainer.session_name_placeholder') ?>" required
                       class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-<?= $appColor ?>-500">
                <button type="submit" class="px-6 py-2 bg-<?= $appColor ?>-600 text-white rounded-lg hover:bg-<?= $appColor ?>-700">
                    <?= t('trainer.create') ?>
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
            <p class="text-yellow-800 text-sm">
                <span class="font-medium"><?= t('trainer.restricted_access') ?>:</span>
                <?= t('trainer.restricted_access_msg') ?>
            </p>
        </div>
        <?php endif; ?>

        <div class="grid md:grid-cols-3 gap-6">
            <!-- Liste des sessions -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gray-50 p-4 border-b font-semibold text-gray-700">
                    <?= t('trainer.sessions') ?> (<?= count($sessions) ?>)
                </div>
                <div class="divide-y max-h-96 overflow-y-auto">
                    <?php foreach ($sessions as $session): ?>
                        <?php $isSelected = ($selectedSession && $selectedSession['id'] == $session['id']); ?>
                        <div class="p-4 hover:bg-gray-50 <?= $isSelected ? 'bg-indigo-50 border-l-4 border-indigo-600' : '' ?>">
                            <div class="flex justify-between items-start">
                                <a href="?session=<?= $session['id'] ?>" class="flex-1">
                                    <div class="font-mono font-bold text-<?= $appColor ?>-600"><?= h($session['code']) ?></div>
                                    <div class="text-sm text-gray-600"><?= h($session['nom']) ?></div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        <?= date('d/m/Y', strtotime($session['created_at'])) ?>
                                        <?php if (!$session['is_active']): ?>
                                            <span class="ml-2 px-2 py-0.5 bg-gray-200 text-gray-600 rounded"><?= t('trainer.inactive') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <div class="flex gap-1">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_session">
                                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                        <button type="submit" class="p-1 text-gray-400 hover:text-gray-600" title="<?= $session['is_active'] ? t('trainer.deactivate') : t('trainer.activate') ?>">
                                            <?= $session['is_active'] ? '⏸' : '▶' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('<?= t('trainer.delete_confirm') ?>')">
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
                            <?= t('trainer.no_sessions') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Participants de la session selectionnee -->
            <div class="md:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
                <?php if ($selectedSession): ?>
                    <div class="bg-gray-50 p-4 border-b flex justify-between items-center">
                        <div>
                            <span class="font-semibold text-gray-700"><?= t('trainer.participants') ?></span>
                            <span class="text-gray-500">- Session <?= h($selectedSession['code']) ?></span>
                            <span class="ml-2 px-2 py-1 bg-<?= $appColor ?>-100 text-<?= $appColor ?>-700 rounded text-sm"><?= count($participants) ?></span>
                        </div>
                        <a href="api/export-session.php?session_id=<?= $selectedSession['id'] ?>"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <?= t('gp.export_all_prompts') ?>
                        </a>
                    </div>
                    <div class="p-4">
                        <?php if (empty($participants)): ?>
                            <p class="text-gray-500 text-center py-8"><?= t('trainer.no_participant_in_session') ?></p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b bg-gray-50">
                                            <th class="text-left py-2 px-2"><?= t('trainer.participant') ?></th>
                                            <th class="text-left py-2 px-2"><?= t('auth.organisation') ?></th>
                                            <th class="text-center py-2 px-2"><?= t('gp.tasks') ?></th>
                                            <th class="text-center py-2 px-2"><?= t('gp.prompts') ?></th>
                                            <th class="text-center py-2 px-2"><?= t('app.completion') ?></th>
                                            <th class="text-center py-2 px-2"><?= t('common.status') ?></th>
                                            <th class="text-center py-2 px-2"><?= t('common.actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($participants as $p): ?>
                                            <tr class="border-b hover:bg-gray-50 <?= $p['is_shared'] ? 'bg-green-50' : '' ?>">
                                                <td class="py-2 px-2">
                                                    <div class="font-medium"><?= h($p['prenom'] . ' ' . $p['nom']) ?></div>
                                                    <div class="text-xs text-gray-500"><?= h($p['username']) ?></div>
                                                </td>
                                                <td class="py-2 px-2 text-gray-600">
                                                    <?= h($p['guide_organisation'] ?: ($p['organisation'] ?: '-')) ?>
                                                </td>
                                                <td class="py-2 px-2 text-center">
                                                    <?php if ($p['guide_exists']): ?>
                                                        <span class="inline-block px-2 py-1 bg-indigo-100 text-indigo-700 rounded font-medium">
                                                            <?= $p['task_count'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-2 px-2 text-center">
                                                    <?php if ($p['guide_exists']): ?>
                                                        <span class="inline-block px-2 py-1 bg-purple-100 text-purple-700 rounded font-medium">
                                                            <?= $p['template_count'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-2 px-2 text-center">
                                                    <?php if ($p['guide_exists']): ?>
                                                        <div class="flex items-center justify-center gap-1">
                                                            <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                                <?php
                                                                $pct = $p['completion_percent'];
                                                                $color = $pct >= 80 ? 'green' : ($pct >= 40 ? 'yellow' : 'red');
                                                                ?>
                                                                <div class="h-full bg-<?= $color ?>-500" style="width: <?= $pct ?>%"></div>
                                                            </div>
                                                            <span class="text-xs font-medium"><?= $pct ?>%</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-xs"><?= t('trainer.not_started') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-2 px-2 text-center">
                                                    <?php if (!$p['guide_exists']): ?>
                                                        <span class="inline-block px-2 py-0.5 bg-gray-200 text-gray-600 rounded text-xs">-</span>
                                                    <?php elseif ($p['is_shared']): ?>
                                                        <span class="inline-block px-2 py-0.5 bg-green-200 text-green-700 rounded text-xs"><?= t('app.submitted') ?></span>
                                                    <?php else: ?>
                                                        <span class="inline-block px-2 py-0.5 bg-yellow-200 text-yellow-700 rounded text-xs"><?= t('app.draft') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-2 px-2 text-center">
                                                    <?php if ($p['guide_exists']): ?>
                                                        <a href="view.php?user_id=<?= $p['user_id'] ?>&session_id=<?= $p['session_id'] ?>"
                                                           class="inline-block px-3 py-1 bg-<?= $appColor ?>-600 text-white rounded hover:bg-<?= $appColor ?>-700 text-xs"
                                                           target="_blank">
                                                            <?= t('common.view') ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-xs">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="flex items-center justify-center h-64 text-gray-400">
                        <?= t('trainer.select_session_view') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <?= renderLanguageScript() ?>
</body>
</html>
