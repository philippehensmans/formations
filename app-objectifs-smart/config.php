<?php
/**
 * Configuration Objectifs SMART
 * Utilise le systeme d'authentification partage
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';

define('APP_NAME', 'Objectifs SMART');
define('APP_COLOR', 'cyan');
define('DB_PATH', __DIR__ . '/data/objectifs_smart.db');

/**
 * Connexion a la base de donnees locale de l'application
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            initDatabase($db);
        } catch (PDOException $e) {
            die("Erreur de connexion a la base de donnees: " . $e->getMessage());
        }
    }
    return $db;
}

/**
 * Initialisation des tables locales
 */
function initDatabase($db) {
    // Table des sessions de formation
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        nom VARCHAR(255) NOT NULL,
        formateur_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des participants aux sessions
    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id),
        UNIQUE(session_id, user_id)
    )");

    // Table des objectifs SMART
    $db->exec("CREATE TABLE IF NOT EXISTS objectifs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        titre_projet TEXT,
        objectifs_data TEXT DEFAULT '[]',
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: ajouter session_id si la colonne n'existe pas
    try {
        $db->exec("ALTER TABLE objectifs ADD COLUMN session_id INTEGER");
    } catch (Exception $e) {
        // Colonne existe deja
    }
    try {
        $db->exec("ALTER TABLE objectifs ADD COLUMN is_shared INTEGER DEFAULT 0");
    } catch (Exception $e) {
        // Colonne existe deja
    }
}

/**
 * Fonction de securisation (alias de h() pour compatibilite)
 */
function sanitize($input) {
    return h($input);
}

/**
 * Verification admin local (utilise isFormateur du shared-auth)
 */
function isLocalAdmin() {
    return isFormateur();
}

/**
 * Recuperer l'utilisateur courant (wrapper pour compatibilite)
 */
function getCurrentUser() {
    return getLoggedUser();
}

/**
 * Exiger connexion avec session
 */
function requireLoginWithSession() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    if (!isset($_SESSION['current_session_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Exiger droits admin/formateur
 */
function requireAdmin() {
    requireFormateur();
}
