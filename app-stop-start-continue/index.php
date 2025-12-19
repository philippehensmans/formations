<?php
/**
 * Page d'identification - Participant ou Formateur
 */
require_once 'config/database.php';

$error = '';
$mode = $_GET['mode'] ?? 'participant';

// Si deja connecte, rediriger
if (isParticipantLoggedIn()) {
    header('Location: app.php');
    exit;
}
if (isFormateurLoggedIn()) {
    header('Location: formateur.php');
    exit;
}

// Charger la liste des sessions actives
$db = getDB();
$sessions = $db->query("SELECT code, nom FROM sessions WHERE is_active = 1 ORDER BY nom")->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'participant') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $organisation = trim($_POST['organisation'] ?? '');

        if (empty($code) || empty($prenom) || empty($nom)) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            $session = getSession($code);
            if (!$session) {
                $error = 'Code de session invalide ou session inactive';
            } else {
                $stmt = $db->prepare("SELECT id FROM participants WHERE session_id = ? AND prenom = ? AND nom = ?");
                $stmt->execute([$session['id'], $prenom, $nom]);
                $participant = $stmt->fetch();

                if ($participant) {
                    $participantId = $participant['id'];
                    if ($organisation) {
                        $stmt = $db->prepare("UPDATE participants SET organisation = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$organisation, $participantId]);
                    }
                } else {
                    $stmt = $db->prepare("INSERT INTO participants (session_id, prenom, nom, organisation) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$session['id'], $prenom, $nom, $organisation]);
                    $participantId = $db->lastInsertId();

                    $stmt = $db->prepare("INSERT INTO retrospectives (participant_id, session_id) VALUES (?, ?)");
                    $stmt->execute([$participantId, $session['id']]);
                }

                $_SESSION['participant_id'] = $participantId;
                $_SESSION['session_id'] = $session['id'];
                header('Location: app.php');
                exit;
            }
        }
    }

    if ($_POST['action'] === 'formateur') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($code)) {
            $error = 'Veuillez selectionner une session';
            $mode = 'formateur';
        } else {
            $stmt = $db->prepare("SELECT * FROM sessions WHERE code = ?");
            $stmt->execute([$code]);
            $session = $stmt->fetch();

            if (!$session) {
                $error = 'Code de session invalide';
                $mode = 'formateur';
            } elseif ($session['formateur_password'] !== $password) {
                $error = 'Mot de passe incorrect';
                $mode = 'formateur';
            } else {
                $_SESSION['formateur_session_id'] = $session['id'];
                $_SESSION['formateur_session_code'] = $session['code'];
                $_SESSION['formateur_session_nom'] = $session['nom'];
                header('Location: formateur.php');
                exit;
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
    <title>Stop Start Continue - Connexion</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-active { border-bottom: 3px solid #1e40af; color: #1e40af; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <!-- Header -->
        <div class="bg-blue-900 text-white p-6 text-center">
            <h1 class="text-2xl font-bold">Stop Start Continue</h1>
            <p class="text-blue-200 mt-1">Retrospective & Planification</p>
        </div>

        <!-- Tabs -->
        <div class="flex border-b">
            <a href="?mode=participant"
               class="flex-1 py-3 text-center font-medium <?= $mode === 'participant' ? 'tab-active' : 'text-gray-500 hover:text-gray-700' ?>">
                Participant
            </a>
            <a href="?mode=formateur"
               class="flex-1 py-3 text-center font-medium <?= $mode === 'formateur' ? 'tab-active' : 'text-gray-500 hover:text-gray-700' ?>">
                Formateur
            </a>
        </div>

        <div class="p-6">
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm">
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'participant'): ?>
                <!-- Formulaire Participant -->
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="participant">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Code Session *</label>
                        <?php if (count($sessions) > 0): ?>
                            <select name="code" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- Selectionnez --</option>
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?= sanitize($s['code']) ?>"><?= sanitize($s['code']) ?> - <?= sanitize($s['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="code" required maxlength="6" placeholder="Ex: ABC123"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 uppercase">
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prenom *</label>
                            <input type="text" name="prenom" required
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
                            <input type="text" name="nom" required
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Organisation</label>
                        <input type="text" name="organisation"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <button type="submit" class="w-full bg-blue-900 text-white py-3 rounded-lg font-medium hover:bg-blue-800 transition">
                        Rejoindre la session
                    </button>
                </form>

            <?php else: ?>
                <!-- Formulaire Formateur -->
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="formateur">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Session</label>
                        <?php if (count($sessions) > 0): ?>
                            <select name="code" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Selectionnez --</option>
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?= sanitize($s['code']) ?>"><?= sanitize($s['code']) ?> - <?= sanitize($s['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="code" required maxlength="6" placeholder="Code session"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 uppercase">
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input type="password" name="password" required
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <button type="submit" class="w-full bg-blue-900 text-white py-3 rounded-lg font-medium hover:bg-blue-800 transition">
                        Connexion Formateur
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-6 text-center">
                <a href="admin_sessions.php" class="text-sm text-gray-500 hover:text-gray-700">
                    Administration
                </a>
            </div>
        </div>
    </div>
</body>
</html>
