<?php
/**
 * Configuration Craintes et Attentes
 * Application pour recueillir les craintes et attentes des participants
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Craintes & Attentes');
define('APP_COLOR', 'teal');
define('DB_PATH', __DIR__ . '/data/craintes_attentes.db');

/**
 * Definition des deux categories : Craintes et Attentes
 */
function getChapeaux() {
    return [
        'craintes' => [
            'nom' => 'Craintes',
            'description' => 'Vos inquietudes, preoccupations, risques percus',
            'color' => 'red',
            'bg' => 'bg-red-50',
            'border' => 'border-red-400',
            'text' => 'text-red-700',
            'icon' => '😟'
        ],
        'attentes' => [
            'nom' => 'Attentes',
            'description' => 'Vos espoirs, souhaits, objectifs esperes',
            'color' => 'green',
            'bg' => 'bg-green-50',
            'border' => 'border-green-400',
            'text' => 'text-green-700',
            'icon' => '🌟'
        ]
    ];
}

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
        sujet TEXT DEFAULT '',
        formateur_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des participants aux sessions
    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        user_id INTEGER,
        prenom VARCHAR(100),
        nom VARCHAR(100),
        organisation VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id)
    )");

    // Table des avis (craintes et attentes)
    $db->exec("CREATE TABLE IF NOT EXISTS avis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER NOT NULL,
        chapeau VARCHAR(20) NOT NULL,
        contenu TEXT NOT NULL,
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id)
    )");

    // Migrations
    $migrations = [
        "ALTER TABLE sessions ADD COLUMN sujet TEXT DEFAULT ''",
        "ALTER TABLE participants ADD COLUMN prenom VARCHAR(100)",
        "ALTER TABLE participants ADD COLUMN nom VARCHAR(100)",
        "ALTER TABLE participants ADD COLUMN organisation VARCHAR(255)",
        "ALTER TABLE participants ADD COLUMN user_id INTEGER"
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* Colonne existe deja */ }
    }

    // Index pour les recherches
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_participants_session_user ON participants(session_id, user_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_avis_session ON avis(session_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_avis_user_session ON avis(user_id, session_id)");
    } catch (Exception $e) { /* Index existe deja */ }
}

/**
 * Recuperer tous les avis d'un participant pour une session
 */
function getAvisParticipant($userId, $sessionId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM avis WHERE user_id = ? AND session_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId, $sessionId]);
    return $stmt->fetchAll();
}

/**
 * Recuperer tous les avis d'une session
 */
function getAvisSession($sessionId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM avis WHERE session_id = ? AND is_shared = 1 ORDER BY chapeau, created_at DESC");
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
}

/**
 * Compter les avis par categorie pour une session
 */
function getStatsSession($sessionId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT chapeau, COUNT(*) as count FROM avis WHERE session_id = ? AND is_shared = 1 GROUP BY chapeau");
    $stmt->execute([$sessionId]);
    $results = $stmt->fetchAll();

    $stats = [];
    foreach ($results as $row) {
        $stats[$row['chapeau']] = $row['count'];
    }
    return $stats;
}

/**
 * Fonction de securisation
 */
function sanitize($input) {
    return h($input);
}

/**
 * Verification admin
 */
function isLocalAdmin() {
    return isFormateur();
}

/**
 * Recuperer l'utilisateur courant
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
