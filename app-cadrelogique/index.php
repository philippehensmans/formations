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

// Traitement du formulaire participant
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
                // Chercher ou creer le participant
                $stmt = $db->prepare("SELECT id FROM participants WHERE session_id = ? AND prenom = ? AND nom = ?");
                $stmt->execute([$session['id'], $prenom, $nom]);
                $participant = $stmt->fetch();

                if ($participant) {
                    $participantId = $participant['id'];
                    // Mettre a jour l'organisation si fournie
                    if ($organisation) {
                        $stmt = $db->prepare("UPDATE participants SET organisation = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$organisation, $participantId]);
                    }
                } else {
                    // Creer le participant
                    $stmt = $db->prepare("INSERT INTO participants (session_id, prenom, nom, organisation) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$session['id'], $prenom, $nom, $organisation]);
                    $participantId = $db->lastInsertId();

                    // Creer son cadre logique vide
                    $stmt = $db->prepare("INSERT INTO cadre_logique (participant_id, session_id, matrice_data) VALUES (?, ?, ?)");
                    $stmt->execute([$participantId, $session['id'], json_encode(getEmptyMatrice())]);
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
            $error = 'Veuillez entrer le code de session';
            $mode = 'formateur';
        } else {
            $stmt = $db->prepare("SELECT * FROM sessions WHERE code = ?");
            $stmt->execute([$code]);
            $session = $stmt->fetch();

            if (!$session) {
                $error = 'Code de session invalide';
                $mode = 'formateur';
            } elseif ($session['formateur_password'] && !password_verify($password, $session['formateur_password'])) {
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
    <title>Cadre Logique - Identification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-active { border-bottom: 3px solid #3b82f6; color: #3b82f6; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <!-- En-tete -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-6 text-center">
            <h1 class="text-2xl font-bold">Cadre Logique</h1>
            <p class="text-blue-100 mt-1">Outil de planification de projet</p>
        </div>

        <!-- Onglets -->
        <div class="flex border-b">
            <a href="?mode=participant" class="flex-1 py-3 text-center font-medium transition <?= $mode === 'participant' ? 'tab-active' : 'text-gray-500 hover:text-gray-700' ?>">
                Participant
            </a>
            <a href="?mode=formateur" class="flex-1 py-3 text-center font-medium transition <?= $mode === 'formateur' ? 'tab-active' : 'text-gray-500 hover:text-gray-700' ?>">
                Formateur
            </a>
        </div>

        <div class="p-6">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'participant'): ?>
            <!-- Formulaire Participant -->
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="participant">

                <div>
                    <label class="block text-gray-700 font-medium mb-1">Session *</label>
                    <select name="code" required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none text-lg">
                        <option value="">-- Choisir une session --</option>
                        <?php foreach ($sessions as $s): ?>
                            <option value="<?= sanitize($s['code']) ?>" <?= (($_POST['code'] ?? '') === $s['code']) ? 'selected' : '' ?>>
                                <?= sanitize($s['code']) ?> - <?= sanitize($s['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Prenom *</label>
                        <input type="text" name="prenom" required
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none"
                            value="<?= sanitize($_POST['prenom'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Nom *</label>
                        <input type="text" name="nom" required
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none"
                            value="<?= sanitize($_POST['nom'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 font-medium mb-1">Organisation</label>
                    <input type="text" name="organisation"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none"
                        placeholder="Optionnel"
                        value="<?= sanitize($_POST['organisation'] ?? '') ?>">
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-blue-700 hover:to-indigo-700 transition shadow-lg">
                    Acceder a l'exercice
                </button>
            </form>

            <?php else: ?>
            <!-- Formulaire Formateur -->
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="formateur">

                <div>
                    <label class="block text-gray-700 font-medium mb-1">Session</label>
                    <select name="code" required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none text-lg">
                        <option value="">-- Choisir une session --</option>
                        <?php foreach ($sessions as $s): ?>
                            <option value="<?= sanitize($s['code']) ?>" <?= (($_POST['code'] ?? '') === $s['code']) ? 'selected' : '' ?>>
                                <?= sanitize($s['code']) ?> - <?= sanitize($s['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 font-medium mb-1">Mot de passe (si requis)</label>
                    <input type="password" name="password"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none"
                        placeholder="Laisser vide si pas de mot de passe">
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3 rounded-lg font-semibold hover:from-indigo-700 hover:to-purple-700 transition shadow-lg">
                    Acceder au tableau de bord
                </button>
            </form>

            <div class="mt-6 pt-4 border-t text-center">
                <a href="admin_sessions.php" class="text-sm text-gray-500 hover:text-gray-700">
                    Gestion des sessions
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
