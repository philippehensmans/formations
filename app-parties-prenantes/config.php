<?php
/**
 * Configuration Parties Prenantes
 * Utilise le systeme d'authentification partage
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Parties Prenantes');
define('APP_COLOR', 'purple');
define('DB_PATH', __DIR__ . '/data/parties_prenantes.db');

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

    // Table des analyses parties prenantes
    $db->exec("CREATE TABLE IF NOT EXISTS analyses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        titre_projet TEXT,
        parties_prenantes TEXT DEFAULT '[]',
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des cartographies (format avec stakeholders)
    $db->exec("CREATE TABLE IF NOT EXISTS cartographie (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER NOT NULL,
        titre_projet TEXT DEFAULT '',
        contexte TEXT DEFAULT '',
        stakeholders_data TEXT DEFAULT '[]',
        notes TEXT DEFAULT '',
        completion_percent INTEGER DEFAULT 0,
        is_submitted INTEGER DEFAULT 0,
        submitted_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: ajouter session_id si la colonne n'existe pas
    try {
        $db->exec("ALTER TABLE analyses ADD COLUMN session_id INTEGER");
    } catch (Exception $e) {
        // Colonne existe deja
    }
    try {
        $db->exec("ALTER TABLE analyses ADD COLUMN is_shared INTEGER DEFAULT 0");
    } catch (Exception $e) {
        // Colonne existe deja
    }

    // Migration: ajouter user_id a cartographie si participant_id existe
    try {
        $db->exec("ALTER TABLE cartographie ADD COLUMN user_id INTEGER");
    } catch (Exception $e) {
        // Colonne existe deja
    }

    // Migration: copier participant_id vers user_id si necessaire
    try {
        $result = $db->query("SELECT participant_id FROM cartographie LIMIT 1");
        if ($result) {
            // La colonne participant_id existe, copier les donnees vers user_id
            $db->exec("UPDATE cartographie SET user_id = participant_id WHERE user_id IS NULL");
        }
    } catch (Exception $e) {
        // participant_id n'existe pas, c'est normal pour les nouvelles installations
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

/**
 * Categories de parties prenantes
 */
function getCategories() {
    return [
        'membres' => ['label' => 'Membres', 'color' => '#e74c3c'],
        'beneficiaires' => ['label' => 'Beneficiaires', 'color' => '#3498db'],
        'partenaires' => ['label' => 'Partenaires', 'color' => '#27ae60'],
        'financeurs' => ['label' => 'Financeurs', 'color' => '#f39c12'],
        'autorites' => ['label' => 'Autorites', 'color' => '#9b59b6'],
        'medias' => ['label' => 'Medias', 'color' => '#34495e'],
        'autres' => ['label' => 'Autres', 'color' => '#95a5a6']
    ];
}
