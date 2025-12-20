<?php
/**
 * Page Formateur - Mesure d'Impact Social
 * Utilise l'authentification partagee avec tableau de bord specifique
 */

$appName = 'Mesure d\'Impact Social';
$appColor = 'indigo';

require_once __DIR__ . '/config/database.php';
$db = getDB();

$error = '';
$success = '';

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
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                               placeholder="formateur">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                               placeholder="Formation2024!">
                    </div>
                    <button type="submit" class="w-full py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Connexion
                    </button>
                </form>
            </div>

            <div class="mt-4 text-center">
                <a href="login.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
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

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_session':
                $nom = trim($_POST['nom'] ?? '');
                if (!empty($nom)) {
                    $code = generateSessionCode();
                    $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)");
                    $stmt->execute([$code, $nom, $user['id']]);
                    $success = "Session creee avec le code: $code";
                }
                break;

            case 'toggle_session':
                $sessionId = (int)($_POST['session_id'] ?? 0);
                toggleSession($db, $sessionId);
                $success = "Statut de la session modifie.";
                break;

            case 'delete_session':
                $sessionId = (int)($_POST['session_id'] ?? 0);
                deleteSession($db, $sessionId);
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

// Recuperer les donnees de la session selectionnee
$selectedSession = null;
$participants = [];
$stats = [];

if (isset($_GET['session'])) {
    $selectedSession = getSessionById($db, (int)$_GET['session']);
    if ($selectedSession) {
        $sharedDb = getSharedDB();

        // Recuperer les participants avec leurs donnees
        $stmt = $db->prepare("
            SELECT p.*, m.etape_courante, m.etape1_classification, m.etape2_theorie_changement,
                   m.etape3_indicateurs, m.etape4_plan_collecte, m.etape5_synthese,
                   m.completion_percent, m.is_submitted, m.updated_at
            FROM participants p
            LEFT JOIN mesure_impact m ON p.id = m.participant_id
            WHERE p.session_id = ?
        ");
        $stmt->execute([$selectedSession['id']]);
        $participantsRaw = $stmt->fetchAll();

        // Ajouter les infos utilisateur
        foreach ($participantsRaw as $p) {
            $userStmt = $sharedDb->prepare("SELECT username, prenom, nom, organisation FROM users WHERE id = ?");
            $userStmt->execute([$p['user_id']]);
            $userData = $userStmt->fetch();
            if ($userData) {
                $p['prenom'] = $userData['prenom'] ?? '';
                $p['nom'] = $userData['nom'] ?? '';
                $p['organisation'] = $userData['organisation'] ?? '';
                $p['username'] = $userData['username'];
            }
            $participants[] = $p;
        }

        // Calculer les statistiques
        $stats = [
            'total' => count($participants),
            'etapes' => [0, 0, 0, 0, 0],
            'submitted' => 0,
            'score_etape1' => [],
            'erreurs_etape1' => [],
            'methodes_collecte' => [],
            'outcomes_mots' => []
        ];

        foreach ($participants as $p) {
            $etape = $p['etape_courante'] ?? 1;
            if ($etape >= 1 && $etape <= 5) {
                $stats['etapes'][$etape - 1]++;
            }
            if ($p['is_submitted']) {
                $stats['submitted']++;
            }

            // Analyser etape 1
            if ($p['etape1_classification']) {
                $e1 = json_decode($p['etape1_classification'], true);
                if (isset($e1['score'])) {
                    $stats['score_etape1'][] = $e1['score'];
                }
                if (isset($e1['reponses'])) {
                    foreach ($e1['reponses'] as $rep) {
                        if (!$rep['correct']) {
                            $key = $rep['enonce_id'];
                            if (!isset($stats['erreurs_etape1'][$key])) {
                                $stats['erreurs_etape1'][$key] = 0;
                            }
                            $stats['erreurs_etape1'][$key]++;
                        }
                    }
                }
            }

            // Analyser etape 4 (methodes de collecte)
            if ($p['etape4_plan_collecte']) {
                $e4 = json_decode($p['etape4_plan_collecte'], true);
                if (isset($e4['plan'])) {
                    foreach ($e4['plan'] as $plan) {
                        if (!empty($plan['methode'])) {
                            if (!isset($stats['methodes_collecte'][$plan['methode']])) {
                                $stats['methodes_collecte'][$plan['methode']] = 0;
                            }
                            $stats['methodes_collecte'][$plan['methode']]++;
                        }
                    }
                }
            }
        }
    }
}

$enonces = getEnonces($selectedSession['id'] ?? null);
$enoncesById = [];
foreach ($enonces as $e) {
    $enoncesById[$e['id']] = $e;
}
$methodes = getMethodesCollecte();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formateur - <?= h($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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

        <!-- Creer une session -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Creer une nouvelle session</h2>
            <form method="POST" class="flex gap-4">
                <input type="hidden" name="action" value="create_session">
                <input type="text" name="nom" placeholder="Nom de la session (ex: Formation Impact Mars 2025)" required
                       class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
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
                                    <div class="font-mono font-bold text-indigo-600"><?= h($session['code']) ?></div>
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
                                        <button type="submit" class="p-1 text-gray-400 hover:text-gray-600">
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

            <!-- Dashboard de la session selectionnee -->
            <div class="md:col-span-2">
                <?php if ($selectedSession): ?>
                    <!-- Stats -->
                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-4">
                            <div class="text-2xl font-bold text-indigo-600"><?= $stats['total'] ?></div>
                            <div class="text-xs text-gray-600">Participants</div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-4">
                            <div class="text-2xl font-bold text-green-600"><?= $stats['submitted'] ?></div>
                            <div class="text-xs text-gray-600">Termines</div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-4">
                            <div class="text-2xl font-bold text-blue-600">
                                <?= count($stats['score_etape1']) ? round(array_sum($stats['score_etape1']) / count($stats['score_etape1']), 1) : '-' ?>
                            </div>
                            <div class="text-xs text-gray-600">Score moyen E1</div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-4">
                            <div class="text-2xl font-bold text-purple-600"><?= count($enonces) ?></div>
                            <div class="text-xs text-gray-600">Enonces</div>
                        </div>
                    </div>

                    <!-- Participants -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-semibold">Participants - Session <?= h($selectedSession['code']) ?></h3>
                            <button onclick="exportExcel()" class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                                📥 Export Excel
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-2">Nom</th>
                                        <th class="text-left py-2">Organisation</th>
                                        <th class="text-center py-2">Etape</th>
                                        <th class="text-center py-2">Score E1</th>
                                        <th class="text-center py-2">Statut</th>
                                        <th class="text-center py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $p):
                                        $e1 = json_decode($p['etape1_classification'] ?: '{}', true);
                                        $score = isset($e1['score']) ? $e1['score'] . '/' . ($e1['score_max'] ?? count($enonces)) : '-';
                                    ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-2"><?= h(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? '')) ?></td>
                                        <td class="py-2 text-gray-600"><?= h($p['organisation'] ?? '-') ?></td>
                                        <td class="py-2 text-center">
                                            <span class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs">
                                                <?= $p['etape_courante'] ?? 1 ?>/5
                                            </span>
                                        </td>
                                        <td class="py-2 text-center"><?= $score ?></td>
                                        <td class="py-2 text-center">
                                            <?php if ($p['is_submitted']): ?>
                                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Termine</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 bg-amber-100 text-amber-700 rounded-full text-xs">En cours</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 text-center">
                                            <a href="view.php?id=<?= $p['id'] ?>"
                                               class="inline-block px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-xs"
                                               target="_blank">
                                                Voir
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($participants)): ?>
                                    <tr><td colspan="6" class="py-8 text-center text-gray-500">Aucun participant</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm p-8 flex items-center justify-center h-64 text-gray-400">
                        Selectionnez une session pour voir les details
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const participants = <?= json_encode($participants) ?>;
        function exportExcel() {
            const data = [['Nom', 'Prenom', 'Organisation', 'Etape', 'Score E1', 'Statut']];
            participants.forEach(p => {
                const e1 = JSON.parse(p.etape1_classification || '{}');
                data.push([
                    p.nom || '', p.prenom || '', p.organisation || '',
                    (p.etape_courante || 1) + '/5',
                    e1.score ? e1.score + '/' + (e1.score_max || 12) : '-',
                    p.is_submitted ? 'Termine' : 'En cours'
                ]);
            });
            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Participants');
            XLSX.writeFile(wb, 'mesure-impact-export.xlsx');
        }
    </script>
</body>
</html>
