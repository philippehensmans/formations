<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

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
        if ($user && ($user['is_formateur'] || $user['is_admin'])) {
            login($user);
            session_write_close();
            header('Location: formateur.php');
            exit;
        } else {
            $error = t('carbon.access_trainers_only');
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
                $success = t('carbon.session_created_with_code') . ": $code";
            }
        } elseif ($action === 'toggle_session') {
            $sessionId = intval($_POST['session_id'] ?? 0);
            // Verifier les droits
            $canModify = $user['is_admin'] || $user['is_super_admin'];
            if (!$canModify && $user['is_formateur']) {
                $sharedDb = getSharedDB();
                $stmt = $sharedDb->prepare("SELECT COUNT(*) FROM formateur_sessions WHERE formateur_id = ?");
                $stmt->execute([$user['id']]);
                $canModify = ($stmt->fetchColumn() == 0); // Pas de restriction
            }
            if ($canModify) {
                $stmt = $db->prepare("UPDATE sessions SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$sessionId]);
            } else {
                $stmt = $db->prepare("UPDATE sessions SET is_active = NOT is_active WHERE id = ? AND formateur_id = ?");
                $stmt->execute([$sessionId, $user['id']]);
            }
        } elseif ($action === 'delete_session') {
            $sessionId = intval($_POST['session_id'] ?? 0);
            // Verifier les droits
            $canModify = $user['is_admin'] || $user['is_super_admin'];
            if (!$canModify && $user['is_formateur']) {
                $sharedDb = getSharedDB();
                $stmt = $sharedDb->prepare("SELECT COUNT(*) FROM formateur_sessions WHERE formateur_id = ?");
                $stmt->execute([$user['id']]);
                $canModify = ($stmt->fetchColumn() == 0);
            }
            // Supprimer les calculs lies
            $stmt = $db->prepare("DELETE FROM calculs WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            // Supprimer les participants lies
            $stmt = $db->prepare("DELETE FROM participants WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            // Supprimer la session
            if ($canModify) {
                $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
                $stmt->execute([$sessionId]);
            } else {
                $stmt = $db->prepare("DELETE FROM sessions WHERE id = ? AND formateur_id = ?");
                $stmt->execute([$sessionId, $user['id']]);
            }
            $success = t('carbon.session_deleted');
        } elseif ($action === 'update_ecologits') {
            // Inclure et executer le script de mise a jour
            ob_start();
            include __DIR__ . '/update_ecologits.php';
            $output = ob_get_clean();
            $success = t('carbon.update_done');
        }
    }
}

// Si non connecte, afficher login
$currentLang = getCurrentLanguage();
if (!$user || (!$user['is_formateur'] && !$user['is_admin'])) {
    ?>
    <!DOCTYPE html>
    <html lang="<?= $currentLang ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= t('carbon.trainer') ?> - <?= h(APP_NAME) ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>body { font-family: 'Inter', sans-serif; }</style>
    </head>
    <body class="min-h-screen bg-gradient-to-br from-emerald-600 to-emerald-800 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <?= renderLanguageSelector('text-sm border border-emerald-400 rounded px-2 py-1 bg-emerald-700 text-white') ?>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2"><?= t('carbon.trainer_area') ?></h1>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('carbon.identifier') ?></label>
                        <input type="text" name="username" required
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('carbon.password') ?></label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-emerald-500">
                    </div>
                    <button type="submit"
                            class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg">
                        <?= t('carbon.login') ?>
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <a href="login.php" class="text-emerald-600 hover:text-emerald-800 text-sm"><?= t('carbon.back_participant') ?></a>
                </div>
            </div>
        </div>
        <?= renderLanguageScript() ?>
    </body>
    </html>
    <?php
    exit;
}

// Verifier si le formateur a des restrictions d'acces
$canSeeAllSessions = false;
if ($user['is_admin'] || $user['is_super_admin']) {
    $canSeeAllSessions = true;
} elseif ($user['is_formateur']) {
    // Verifier si ce formateur a des restrictions specifiques
    $sharedDb = getSharedDB();
    $stmt = $sharedDb->prepare("SELECT COUNT(*) FROM formateur_sessions WHERE formateur_id = ?");
    $stmt->execute([$user['id']]);
    $hasRestrictions = $stmt->fetchColumn() > 0;

    if (!$hasRestrictions) {
        $canSeeAllSessions = true; // Pas de restriction = acces a tout
    }
}

// Charger les sessions
if ($canSeeAllSessions) {
    $stmt = $db->query("SELECT * FROM sessions ORDER BY created_at DESC");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare("SELECT * FROM sessions WHERE formateur_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Charger les estimations
$estimations = getEstimations();
$categories = $estimations['categories'] ?? [];
$useCases = $estimations['use_cases'] ?? [];
$metadata = $estimations['_metadata'] ?? [];

// Stats session selectionnee
$selectedSessionId = intval($_GET['session'] ?? ($sessions[0]['id'] ?? 0));
$sessionStats = null;
$debugInfo = null; // Initialiser debug

// DEBUG au tout début
error_log("DEBUG formateur.php: session_id=$selectedSessionId, nb_sessions=" . count($sessions));

if ($selectedSessionId) {
    if ($canSeeAllSessions) {
        $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
        $stmt->execute([$selectedSessionId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND formateur_id = ?");
        $stmt->execute([$selectedSessionId, $user['id']]);
    }
    $selectedSession = $stmt->fetch();

    if ($selectedSession) {
        // DEBUG: Compter les sources de participants
        $stmtDebugParticipants = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM participants WHERE session_id = ?");
        $stmtDebugParticipants->execute([$selectedSessionId]);
        $debugCountParticipants = $stmtDebugParticipants->fetchColumn();

        $stmtDebugCalculs = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM calculs WHERE session_id = ?");
        $stmtDebugCalculs->execute([$selectedSessionId]);
        $debugCountCalculs = $stmtDebugCalculs->fetchColumn();

        // Participants - requete locale puis enrichissement depuis shared DB
        // Inclure tous les utilisateurs qui sont soit dans participants, soit dans calculs
        // Calcul des occurrences = multiplicateur de frequence × quantite
        $stmt = $db->prepare("
            SELECT all_users.user_id,
                   COALESCE(SUM(c.co2_total), 0) as total_co2,
                   COUNT(c.id) as nb_calculs,
                   COALESCE(SUM(
                       CASE c.frequence
                           WHEN 'quotidien' THEN 250
                           WHEN 'hebdomadaire' THEN 52
                           WHEN 'mensuel' THEN 12
                           WHEN 'trimestriel' THEN 4
                           ELSE 1
                       END * c.quantite
                   ), 0) as nb_occurrences
            FROM (
                SELECT DISTINCT user_id FROM participants WHERE session_id = ?
                UNION
                SELECT DISTINCT user_id FROM calculs WHERE session_id = ?
            ) AS all_users
            LEFT JOIN calculs c ON c.user_id = all_users.user_id AND c.session_id = ?
            GROUP BY all_users.user_id
            ORDER BY total_co2 DESC
        ");
        $stmt->execute([$selectedSessionId, $selectedSessionId, $selectedSessionId]);
        $participantsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debugCountUnion = count($participantsData);

        // Enrichir avec les infos utilisateur depuis la base partagee
        $sharedDb = getSharedDB();
        $participants = [];
        $debugMissingUsers = [];
        foreach ($participantsData as $p) {
            $stmtUser = $sharedDb->prepare("SELECT id, prenom, username FROM users WHERE id = ?");
            $stmtUser->execute([$p['user_id']]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($userData) {
                $participants[] = array_merge($userData, [
                    'total_co2' => $p['total_co2'],
                    'nb_calculs' => $p['nb_calculs'],
                    'nb_occurrences' => $p['nb_occurrences']
                ]);
            } else {
                $debugMissingUsers[] = $p['user_id'];
            }
        }

        // DEBUG info
        $debugInfo = [
            'participants_table' => $debugCountParticipants,
            'calculs_table' => $debugCountCalculs,
            'union_result' => $debugCountUnion,
            'found_in_users' => count($participants),
            'missing_user_ids' => $debugMissingUsers
        ];

        // Total session
        $stmt = $db->prepare("SELECT SUM(co2_total) as total FROM calculs WHERE session_id = ?");
        $stmt->execute([$selectedSessionId]);
        $totalSession = $stmt->fetch()['total'] ?? 0;

        // Top use cases avec occurrences
        $stmt = $db->prepare("
            SELECT use_case_id,
                   COUNT(*) as nb,
                   SUM(co2_total) as total_co2,
                   SUM(
                       CASE frequence
                           WHEN 'quotidien' THEN 250
                           WHEN 'hebdomadaire' THEN 52
                           WHEN 'mensuel' THEN 12
                           WHEN 'trimestriel' THEN 4
                           ELSE 1
                       END * quantite
                   ) as nb_occurrences
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
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('carbon.trainer') ?> - <?= h(APP_NAME) ?></title>
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
                    <h1 class="text-xl font-bold"><?= h(APP_NAME) ?> - <?= t('carbon.trainer') ?></h1>
                    <p class="text-emerald-200 text-sm"><?= t('carbon.welcome') ?>, <?= h($user['prenom'] ?? $user['username']) ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?= renderLanguageSelector('text-sm border border-emerald-400 rounded px-2 py-1 bg-emerald-800') ?>
                    <a href="logout.php" class="bg-emerald-800 hover:bg-emerald-900 px-4 py-2 rounded">
                        <?= t('carbon.logout') ?>
                    </a>
                </div>
            </div>
        </div>
    </header>
    <?= renderLanguageScript() ?>

    <!-- Tabs -->
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4">
            <nav class="flex gap-4">
                <a href="?tab=sessions" class="py-4 px-2 border-b-2 <?= $activeTab === 'sessions' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-600 hover:text-emerald-600' ?>">
                    <?= t('carbon.tab_sessions') ?>
                </a>
                <a href="?tab=stats&session=<?= $selectedSessionId ?>" class="py-4 px-2 border-b-2 <?= $activeTab === 'stats' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-600 hover:text-emerald-600' ?>">
                    <?= t('carbon.tab_stats') ?>
                </a>
                <a href="?tab=estimations" class="py-4 px-2 border-b-2 <?= $activeTab === 'estimations' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-600 hover:text-emerald-600' ?>">
                    <?= t('carbon.tab_estimations') ?>
                </a>
            </nav>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <!-- DEBUG GLOBAL -->
        <div class="mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg text-sm">
            <strong>DEBUG:</strong>
            Sessions trouvées: <?= count($sessions) ?> |
            Session sélectionnée: <?= $selectedSessionId ?> |
            Tab actif: <?= h($activeTab) ?> |
            sessionStats: <?= $sessionStats ? 'OUI' : 'NON' ?> |
            debugInfo: <?= $debugInfo ? 'OUI' : 'NON' ?>
            <?php if ($debugInfo): ?>
            <br>participants_table: <?= $debugInfo['participants_table'] ?> |
            calculs_table: <?= $debugInfo['calculs_table'] ?> |
            union: <?= $debugInfo['union_result'] ?> |
            found: <?= $debugInfo['found_in_users'] ?>
            <?php endif; ?>
        </div>

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
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= t('carbon.create_session') ?></h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create_session">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('carbon.session_name') ?></label>
                        <input type="text" name="nom" required placeholder="<?= t('carbon.session_name_placeholder') ?>"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-emerald-500">
                    </div>
                    <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">
                        <?= t('carbon.create_session_btn') ?>
                    </button>
                </form>
            </div>

            <!-- Liste sessions -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= t('carbon.my_sessions') ?></h2>
                <div class="space-y-2">
                    <?php foreach ($sessions as $session): ?>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="font-medium"><?= h($session['nom']) ?></p>
                            <p class="text-sm text-gray-500">
                                <?= t('carbon.code') ?>: <span class="font-mono font-bold text-emerald-600"><?= h($session['code']) ?></span>
                                <?php if (!$session['is_active']): ?>
                                    <span class="text-red-500">(<?= t('carbon.inactive') ?>)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a href="?tab=stats&session=<?= $session['id'] ?>" class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded text-sm hover:bg-emerald-200">
                                <?= t('carbon.stats') ?>
                            </a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_session">
                                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                <button type="submit" class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300">
                                    <?= $session['is_active'] ? t('carbon.deactivate') : t('carbon.activate') ?>
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('<?= t('carbon.confirm_delete_session') ?>');">
                                <input type="hidden" name="action" value="delete_session">
                                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                <button type="submit" class="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">
                                    <?= t('carbon.delete_session') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($sessions)): ?>
                        <p class="text-gray-500 text-center py-4"><?= t('carbon.no_session_created') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($activeTab === 'stats' && $sessionStats): ?>
        <!-- ONGLET STATS -->
        <div class="space-y-6">
            <!-- DEBUG INFO -->
            <?php if (isset($debugInfo)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm">
                <h4 class="font-bold text-yellow-800 mb-2">DEBUG - Sources de participants:</h4>
                <ul class="text-yellow-700 space-y-1">
                    <li>Table participants: <strong><?= $debugInfo['participants_table'] ?></strong> utilisateurs</li>
                    <li>Table calculs: <strong><?= $debugInfo['calculs_table'] ?></strong> utilisateurs distincts</li>
                    <li>Résultat UNION: <strong><?= $debugInfo['union_result'] ?></strong> utilisateurs</li>
                    <li>Trouvés dans base users: <strong><?= $debugInfo['found_in_users'] ?></strong> utilisateurs</li>
                    <?php if (!empty($debugInfo['missing_user_ids'])): ?>
                    <li class="text-red-600">IDs manquants dans base users: <?= implode(', ', $debugInfo['missing_user_ids']) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Header session -->
            <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 rounded-xl p-6 text-white">
                <h2 class="text-2xl font-bold"><?= h($sessionStats['session']['nom']) ?></h2>
                <p class="text-emerald-200"><?= t('carbon.code') ?>: <?= h($sessionStats['session']['code']) ?></p>
                <div class="mt-4 grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-4xl font-bold"><?= count($sessionStats['participants']) ?></p>
                        <p class="text-emerald-200"><?= t('carbon.participants') ?></p>
                    </div>
                    <div>
                        <p class="text-4xl font-bold"><?= number_format($sessionStats['total']/1000, 1, ',', ' ') ?></p>
                        <p class="text-emerald-200"><?= t('carbon.kg_co2_total') ?></p>
                    </div>
                    <div>
                        <p class="text-4xl font-bold"><?= round($sessionStats['total']/1000/0.21, 0) ?></p>
                        <p class="text-emerald-200"><?= t('carbon.km_car_equiv') ?></p>
                    </div>
                </div>
                <a href="export.php?type=session&session=<?= $selectedSessionId ?>" class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <?= t('carbon.export_session') ?>
                </a>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Classement participants -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= t('carbon.participant_ranking') ?></h3>
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
                                <span class="text-xs text-gray-500 block"><?= $p['nb_occurrences'] ?> <?= t('carbon.occurrences_year') ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($sessionStats['participants'])): ?>
                            <p class="text-gray-500 text-center py-4"><?= t('carbon.no_participant') ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top use cases -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= t('carbon.top_use_cases') ?></h3>
                    <div class="space-y-2">
                        <?php foreach ($sessionStats['top_use_cases'] as $uc):
                            $ucData = $useCases[$uc['use_case_id']] ?? null;
                        ?>
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <div>
                                <p class="font-medium"><?= h($ucData['nom'] ?? $uc['use_case_id']) ?></p>
                                <p class="text-xs text-gray-500"><?= $uc['nb_occurrences'] ?> <?= t('carbon.occurrences_year') ?></p>
                            </div>
                            <span class="font-semibold text-emerald-600"><?= number_format($uc['total_co2'], 0, ',', ' ') ?>g</span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($sessionStats['top_use_cases'])): ?>
                            <p class="text-gray-500 text-center py-4"><?= t('carbon.no_usage_recorded') ?></p>
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
                        <h2 class="text-lg font-semibold text-gray-800"><?= t('carbon.co2_estimations_base') ?></h2>
                        <p class="text-sm text-gray-500 mt-1">
                            <?= t('carbon.version') ?>: <?= h($metadata['version'] ?? '1.0') ?> |
                            <?= t('carbon.last_updated') ?>: <?= h($metadata['last_updated'] ?? t('carbon.unknown')) ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-1"><?= h($metadata['source'] ?? '') ?></p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_ecologits">
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <?= t('carbon.update_from_ecologits') ?>
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
                    <span class="text-sm font-normal text-gray-500">(<?= count($catUseCases) ?> <?= t('carbon.cases') ?>)</span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-2"><?= t('carbon.use_case') ?></th>
                                <th class="text-left p-2"><?= t('carbon.model') ?></th>
                                <th class="text-right p-2"><?= t('carbon.tokens') ?></th>
                                <th class="text-right p-2"><?= t('carbon.co2_g') ?></th>
                                <th class="text-left p-2"><?= t('carbon.equivalent') ?></th>
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
