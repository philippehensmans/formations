<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

// Si déjà connecté, rediriger vers l'application
if (isset($_SESSION['participant_id'])) {
    header('Location: app.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionCode = strtoupper(trim($_POST['session_code'] ?? ''));
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $organisation = trim($_POST['organisation'] ?? '');

    if (empty($sessionCode) || empty($prenom) || empty($nom)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        $db = getDB();

        // Vérifier que la session existe et est active
        $stmt = $db->prepare("SELECT * FROM sessions WHERE code = ? AND active = 1");
        $stmt->execute([$sessionCode]);
        $session = $stmt->fetch();

        if (!$session) {
            $error = "Code de session invalide ou session inactive.";
        } else {
            // Chercher ou créer le participant
            $stmt = $db->prepare("SELECT * FROM participants WHERE session_id = ? AND prenom = ? AND nom = ?");
            $stmt->execute([$session['id'], $prenom, $nom]);
            $participant = $stmt->fetch();

            if (!$participant) {
                // Créer le participant
                $stmt = $db->prepare("INSERT INTO participants (session_id, prenom, nom, organisation) VALUES (?, ?, ?, ?)");
                $stmt->execute([$session['id'], $prenom, $nom, $organisation]);
                $participantId = $db->lastInsertId();
            } else {
                $participantId = $participant['id'];
                // Mettre à jour l'organisation si fournie
                if (!empty($organisation) && $organisation !== $participant['organisation']) {
                    $stmt = $db->prepare("UPDATE participants SET organisation = ? WHERE id = ?");
                    $stmt->execute([$organisation, $participantId]);
                }
            }

            // Mettre à jour last_login
            $stmt = $db->prepare("UPDATE participants SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$participantId]);

            // Créer la session PHP
            $_SESSION['participant_id'] = $participantId;
            $_SESSION['session_id'] = $session['id'];

            header('Location: app.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesure d'Impact Social - Identification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-900 via-purple-900 to-indigo-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo et titre -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white/10 backdrop-blur rounded-2xl mb-4">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Mesure d'Impact Social</h1>
            <p class="text-indigo-200">Formation a l'evaluation de l'impact</p>
        </div>

        <!-- Formulaire -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6 text-center">Rejoindre la formation</h2>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Code de session <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="session_code" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-center text-2xl font-mono tracking-widest uppercase"
                           placeholder="ABC123"
                           maxlength="10"
                           value="<?= htmlspecialchars($_POST['session_code'] ?? '') ?>">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Prenom <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="prenom" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Marie"
                               value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nom <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nom" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Dupont"
                               value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Organisation
                    </label>
                    <input type="text" name="organisation"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Nom de votre organisation"
                           value="<?= htmlspecialchars($_POST['organisation'] ?? '') ?>">
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors">
                    Commencer la formation
                </button>
            </form>
        </div>

        <!-- Liens -->
        <div class="mt-6 text-center">
            <a href="formateur.php" class="text-indigo-200 hover:text-white text-sm transition-colors">
                Acces formateur
            </a>
        </div>

        <!-- Info -->
        <div class="mt-8 text-center text-indigo-200 text-sm">
            <p>La chaine de resultats : INPUTS → ACTIVITES → OUTPUTS → OUTCOMES → IMPACT</p>
        </div>
    </div>
</body>
</html>
