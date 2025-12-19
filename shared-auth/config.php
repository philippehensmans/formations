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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME
    )");

    // Creer le compte formateur par defaut s'il n'existe pas
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE username = 'formateur'");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, is_admin, is_formateur, prenom, nom)
                   VALUES ('formateur', '$hash', 1, 1, 'Formateur', 'Principal')");
    }
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
function registerUser($username, $password, $prenom = '', $nom = '', $organisation = '', $email = '') {
    $db = getSharedDB();

    // Verifier si l'utilisateur existe deja
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Ce nom d\'utilisateur existe deja'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password, prenom, nom, organisation, email) VALUES (?, ?, ?, ?, ?, ?)");

    try {
        $stmt->execute([$username, $hash, $prenom, $nom, $organisation, $email]);
        return ['success' => true, 'user_id' => $db->lastInsertId()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
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
