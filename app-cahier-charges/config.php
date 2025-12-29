<?php
/**
 * Configuration Cahier des Charges
 * Utilise le systeme d'authentification partage
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Cahier des Charges Associatif');
define('APP_COLOR', 'blue');
define('DB_PATH', __DIR__ . '/data/cahier_charges.db');

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

    // Table des cahiers des charges
    $db->exec("CREATE TABLE IF NOT EXISTS cahiers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        titre_projet VARCHAR(255),
        date_debut DATE,
        date_fin DATE,
        chef_projet VARCHAR(100),
        sponsor VARCHAR(100),
        groupe_travail VARCHAR(255),
        benevoles VARCHAR(255),
        autres_acteurs TEXT,
        objectif_strategique TEXT,
        inclusivite TEXT,
        aspect_digital TEXT,
        evolution TEXT,
        description_projet TEXT,
        objectif_projet TEXT,
        logique_projet TEXT,
        objectif_global TEXT,
        objectifs_specifiques TEXT,
        resultats TEXT,
        contraintes TEXT,
        strategies TEXT,
        budget VARCHAR(255),
        ressources_humaines TEXT,
        ressources_materielles TEXT,
        etapes TEXT,
        communication TEXT,
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: ajouter session_id si la colonne n'existe pas
    try {
        $db->exec("ALTER TABLE cahiers ADD COLUMN session_id INTEGER");
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
 * Exiger connexion avec session (version specifique a l'app)
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
