<?php
/**
 * Configuration Guide de Prompting
 * Utilise le systeme d'authentification partage
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Guide Prompting');
define('APP_COLOR', 'indigo');
define('DB_PATH', __DIR__ . '/data/guide_prompting.db');

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
        prenom VARCHAR(100),
        nom VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id),
        UNIQUE(session_id, user_id)
    )");

    // Migrations pour ajouter les colonnes manquantes aux participants
    $participantMigrations = [
        "ALTER TABLE participants ADD COLUMN prenom VARCHAR(100)",
        "ALTER TABLE participants ADD COLUMN nom VARCHAR(100)"
    ];
    foreach ($participantMigrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* Colonne existe deja */ }
    }

    // Table des guides de prompting
    $db->exec("CREATE TABLE IF NOT EXISTS guides (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        organisation_nom TEXT DEFAULT '',
        organisation_mission TEXT DEFAULT '',
        current_step INTEGER DEFAULT 1,
        tasks TEXT DEFAULT '[]',
        experimentations TEXT DEFAULT '[]',
        templates TEXT DEFAULT '[]',
        guide_intro TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        is_shared INTEGER DEFAULT 0,
        completion_percent INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrations
    $migrations = [
        "ALTER TABLE guides ADD COLUMN session_id INTEGER",
        "ALTER TABLE guides ADD COLUMN is_shared INTEGER DEFAULT 0",
        "ALTER TABLE guides ADD COLUMN current_step INTEGER DEFAULT 1",
        "ALTER TABLE guides ADD COLUMN notes TEXT DEFAULT ''",
        "ALTER TABLE guides ADD COLUMN completion_percent INTEGER DEFAULT 0"
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* Colonne existe deja */ }
    }
}

/**
 * Fonction de securisation (alias de h() pour compatibilite)
 */
function sanitize($input) {
    return h($input);
}

/**
 * Verification admin (utilise isFormateur du shared-auth)
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
