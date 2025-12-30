<?php
/**
 * Systeme d'authentification partage pour toutes les applications de formation
 *
 * Usage: require_once __DIR__ . '/../shared-auth/config.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SHARED_AUTH_PATH', __DIR__);
define('SHARED_DB_PATH', __DIR__ . '/data/users.sqlite');
define('ADMIN_PASSWORD', 'Formation2024!');
define('NOTIFY_EMAIL', 'philippe@hensmans.org');

/**
 * Connexion a la base utilisateurs partagee
 */
function getSharedDB() {
    static $db = null;
    if ($db === null) {
        $dir = dirname(SHARED_DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $db = new PDO('sqlite:' . SHARED_DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        initSharedDB($db);
    }
    return $db;
}

/**
 * Initialisation des tables utilisateurs
 */
function initSharedDB($db) {
    // Table des utilisateurs
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        prenom VARCHAR(100),
        nom VARCHAR(100),
        organisation VARCHAR(255),
        is_admin INTEGER DEFAULT 0,
        is_formateur INTEGER DEFAULT 0,
        is_super_admin INTEGER DEFAULT 0,
        email_consent INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME
    )");

    // Table d'affectation formateurs aux sessions
    $db->exec("CREATE TABLE IF NOT EXISTS formateur_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        formateur_id INTEGER NOT NULL,
        app_name VARCHAR(100) NOT NULL,
        session_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(formateur_id, app_name, session_id)
    )");

    // Migrations pour ajouter les nouvelles colonnes
    try {
        $db->exec("ALTER TABLE users ADD COLUMN is_super_admin INTEGER DEFAULT 0");
    } catch (Exception $e) { /* Colonne existe deja */ }

    try {
        $db->exec("ALTER TABLE users ADD COLUMN email_consent INTEGER DEFAULT 0");
    } catch (Exception $e) { /* Colonne existe deja */ }

    // Colonnes pour la reinitialisation du mot de passe
    try {
        $db->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64)");
    } catch (Exception $e) { /* Colonne existe deja */ }

    try {
        $db->exec("ALTER TABLE users ADD COLUMN reset_token_expires DATETIME");
    } catch (Exception $e) { /* Colonne existe deja */ }

    // Creer le compte super-admin par defaut s'il n'existe pas
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE username = 'formateur'");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, is_admin, is_formateur, is_super_admin, prenom, nom)
                   VALUES ('formateur', '$hash', 1, 1, 1, 'Formateur', 'Principal')");
    }

    // Mettre a jour l'utilisateur formateur existant pour qu'il soit super_admin
    $db->exec("UPDATE users SET is_super_admin = 1 WHERE username = 'formateur'");
}

/**
 * Authentification d'un utilisateur
 */
function authenticateUser($username, $password) {
    $db = getSharedDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Mettre a jour last_login
        $stmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$user['id']]);
        return $user;
    }
    return false;
}

/**
 * Inscription d'un nouvel utilisateur
 */
function registerUser($username, $password, $prenom = '', $nom = '', $organisation = '', $email = '', $emailConsent = 0) {
    $db = getSharedDB();

    // Verifier si l'utilisateur existe deja
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Ce nom d\'utilisateur existe deja'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password, prenom, nom, organisation, email, email_consent) VALUES (?, ?, ?, ?, ?, ?, ?)");

    try {
        $stmt->execute([$username, $hash, $prenom, $nom, $organisation, $email, $emailConsent ? 1 : 0]);
        $userId = $db->lastInsertId();

        // Envoyer notification par email
        notifyNewRegistration($username, $prenom, $nom, $organisation, $email);

        return ['success' => true, 'user_id' => $userId];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envoyer une notification email lors d'une nouvelle inscription
 */
function notifyNewRegistration($username, $prenom, $nom, $organisation, $email) {
    $notifyEmail = defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : '';
    if (empty($notifyEmail)) {
        return false;
    }

    $appName = defined('APP_NAME') ? APP_NAME : 'Formation';
    $subject = "[{$appName}] Nouvelle inscription : {$username}";

    $message = "Bonjour,\n\n";
    $message .= "Une nouvelle personne s'est inscrite.\n\n";
    $message .= "Details :\n";
    $message .= "- Identifiant : {$username}\n";
    $message .= "- Prenom : " . ($prenom ?: '(non renseigne)') . "\n";
    $message .= "- Nom : " . ($nom ?: '(non renseigne)') . "\n";
    $message .= "- Organisation : " . ($organisation ?: '(non renseigne)') . "\n";
    $message .= "- Email : " . ($email ?: '(non renseigne)') . "\n";
    $message .= "- Date : " . date('d/m/Y H:i') . "\n\n";
    $message .= "Cordialement,\n";
    $message .= "Le systeme de formation";

    $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'k1m.be') . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($notifyEmail, $subject, $message, $headers);
}

/**
 * Recuperer l'utilisateur connecte
 */
function getLoggedUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $db = getSharedDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Verifier si l'utilisateur est connecte
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Verifier si l'utilisateur est formateur/admin
 */
function isFormateur() {
    $user = getLoggedUser();
    return $user && ($user['is_formateur'] || $user['is_admin']);
}

/**
 * Verifier si l'utilisateur est admin
 */
function isAdmin() {
    $user = getLoggedUser();
    return $user && $user['is_admin'];
}

/**
 * Deconnexion
 */
function logout() {
    $_SESSION = [];
    session_destroy();
}

/**
 * Login et creation de session
 */
function login($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['is_admin'] = $user['is_admin'];
    $_SESSION['is_formateur'] = $user['is_formateur'];
}

/**
 * Protection de page - redirige si non connecte
 */
function requireLogin($redirectUrl = 'login.php') {
    if (!isLoggedIn()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Protection de page formateur
 */
function requireFormateur($redirectUrl = 'login.php') {
    if (!isFormateur()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Lister tous les utilisateurs (pour admin)
 */
function getAllUsers() {
    $db = getSharedDB();
    return $db->query("SELECT id, username, prenom, nom, organisation, is_admin, is_formateur, created_at, last_login FROM users ORDER BY created_at DESC")->fetchAll();
}

/**
 * Supprimer un utilisateur
 */
function deleteUser($userId) {
    $db = getSharedDB();
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
    return $stmt->execute([$userId]);
}

/**
 * Echapper les donnees pour l'affichage HTML
 */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Verifier si l'utilisateur est super-admin
 */
function isSuperAdmin() {
    $user = getLoggedUser();
    return $user && $user['is_super_admin'];
}

/**
 * Protection de page super-admin
 */
function requireSuperAdmin($redirectUrl = 'login.php') {
    if (!isSuperAdmin()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Affecter un formateur a une session
 */
function assignFormateurToSession($formateurId, $appName, $sessionId) {
    $db = getSharedDB();
    try {
        $stmt = $db->prepare("INSERT OR IGNORE INTO formateur_sessions (formateur_id, app_name, session_id) VALUES (?, ?, ?)");
        $stmt->execute([$formateurId, $appName, $sessionId]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Retirer un formateur d'une session
 */
function removeFormateurFromSession($formateurId, $appName, $sessionId) {
    $db = getSharedDB();
    $stmt = $db->prepare("DELETE FROM formateur_sessions WHERE formateur_id = ? AND app_name = ? AND session_id = ?");
    return $stmt->execute([$formateurId, $appName, $sessionId]);
}

/**
 * Verifier si un formateur a acces a une session specifique
 * Les super-admins ont acces a toutes les sessions
 */
function canAccessSession($appName, $sessionId) {
    $user = getLoggedUser();
    if (!$user) return false;

    // Super-admin a acces a tout
    if ($user['is_super_admin']) return true;

    // Formateur sans restriction = acces a tout (mode legacy)
    $db = getSharedDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM formateur_sessions WHERE formateur_id = ?");
    $stmt->execute([$user['id']]);
    $hasRestrictions = $stmt->fetchColumn() > 0;

    if (!$hasRestrictions && $user['is_formateur']) {
        return true; // Formateur sans affectation = acces total (mode compatibilite)
    }

    // Verifier l'affectation specifique
    $stmt = $db->prepare("SELECT COUNT(*) FROM formateur_sessions WHERE formateur_id = ? AND app_name = ? AND session_id = ?");
    $stmt->execute([$user['id'], $appName, $sessionId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Recuperer les sessions auxquelles un formateur a acces
 * Retourne null si acces a toutes les sessions (super-admin ou formateur sans restriction)
 */
function getFormateurSessionIds($appName) {
    $user = getLoggedUser();
    if (!$user) return [];

    // Super-admin = acces a tout
    if ($user['is_super_admin']) return null;

    // Verifier si le formateur a des restrictions
    $db = getSharedDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM formateur_sessions WHERE formateur_id = ?");
    $stmt->execute([$user['id']]);
    $hasRestrictions = $stmt->fetchColumn() > 0;

    if (!$hasRestrictions && $user['is_formateur']) {
        return null; // Pas de restriction
    }

    // Retourner les IDs de sessions autorisees
    $stmt = $db->prepare("SELECT session_id FROM formateur_sessions WHERE formateur_id = ? AND app_name = ?");
    $stmt->execute([$user['id'], $appName]);
    return array_column($stmt->fetchAll(), 'session_id');
}

/**
 * Recuperer tous les formateurs (pour affectation)
 */
function getAllFormateurs() {
    $db = getSharedDB();
    return $db->query("SELECT id, username, prenom, nom, organisation, is_formateur, is_super_admin FROM users WHERE is_formateur = 1 ORDER BY nom, prenom")->fetchAll();
}

/**
 * Recuperer les sessions affectees a un formateur
 */
function getFormateurAssignments($formateurId) {
    $db = getSharedDB();
    $stmt = $db->prepare("SELECT app_name, session_id FROM formateur_sessions WHERE formateur_id = ?");
    $stmt->execute([$formateurId]);
    return $stmt->fetchAll();
}

/**
 * Generer un token de reinitialisation de mot de passe
 */
function generatePasswordResetToken($emailOrUsername) {
    $db = getSharedDB();

    // Chercher l'utilisateur par email ou username
    $stmt = $db->prepare("SELECT id, email, username, prenom, nom FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$emailOrUsername, $emailOrUsername]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'error' => 'user_not_found'];
    }

    if (empty($user['email'])) {
        return ['success' => false, 'error' => 'no_email'];
    }

    // Generer un token securise
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Sauvegarder le token
    $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $user['id']]);

    // Envoyer l'email
    $sent = sendPasswordResetEmail($user['email'], $token, $user['prenom'], $user['nom'], $user['username']);

    if ($sent) {
        return ['success' => true, 'email' => maskEmail($user['email'])];
    } else {
        return ['success' => false, 'error' => 'email_failed'];
    }
}

/**
 * Envoyer l'email de reinitialisation
 */
function sendPasswordResetEmail($email, $token, $prenom, $nom, $username) {
    $host = $_SERVER['HTTP_HOST'] ?? 'k1m.be';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    // Determiner le chemin de base
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $basePath = dirname(dirname($requestUri)); // Remonter d'un niveau depuis shared-auth ou app
    $resetUrl = "{$protocol}://{$host}{$basePath}/shared-auth/reset-password.php?token={$token}";

    $appName = defined('APP_NAME') ? APP_NAME : 'Formation';
    $subject = "[{$appName}] Reinitialisation de votre mot de passe";

    $nomComplet = trim(($prenom ?? '') . ' ' . ($nom ?? ''));
    if (empty($nomComplet)) {
        $nomComplet = $username;
    }

    $message = "Bonjour {$nomComplet},\n\n";
    $message .= "Vous avez demande la reinitialisation de votre mot de passe.\n\n";
    $message .= "Cliquez sur le lien ci-dessous pour definir un nouveau mot de passe :\n";
    $message .= $resetUrl . "\n\n";
    $message .= "Ce lien est valable pendant 1 heure.\n\n";
    $message .= "Si vous n'avez pas demande cette reinitialisation, ignorez simplement ce message.\n\n";
    $message .= "Cordialement,\n";
    $message .= "L'equipe de formation";

    $headers = "From: noreply@{$host}\r\n";
    $headers .= "Reply-To: noreply@{$host}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, $subject, $message, $headers);
}

/**
 * Masquer partiellement une adresse email
 */
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return '***@***.***';

    $name = $parts[0];
    $domain = $parts[1];

    $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
    $domainParts = explode('.', $domain);
    $maskedDomain = substr($domainParts[0], 0, 1) . '***.' . end($domainParts);

    return $maskedName . '@' . $maskedDomain;
}

/**
 * Valider un token de reinitialisation
 */
function validatePasswordResetToken($token) {
    if (empty($token) || strlen($token) !== 64) {
        return false;
    }

    $db = getSharedDB();
    $stmt = $db->prepare("SELECT id, username, prenom, nom FROM users WHERE reset_token = ? AND reset_token_expires > CURRENT_TIMESTAMP");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

/**
 * Reinitialiser le mot de passe avec un token
 */
function resetPasswordWithToken($token, $newPassword) {
    $user = validatePasswordResetToken($token);
    if (!$user) {
        return ['success' => false, 'error' => 'invalid_token'];
    }

    if (strlen($newPassword) < 4) {
        return ['success' => false, 'error' => 'password_too_short'];
    }

    $db = getSharedDB();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
    $stmt->execute([$hash, $user['id']]);

    return ['success' => true];
}
