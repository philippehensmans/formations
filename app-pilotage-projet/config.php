<?php
/**
 * Configuration Pilotage de Projet
 * Structuration complete d'un projet : objectifs, phases, taches, approbations
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Pilotage de Projet');
define('APP_COLOR', 'emerald');
define('DB_PATH', __DIR__ . '/data/pilotage_projet.db');

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

function initDatabase($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        nom VARCHAR(255) NOT NULL,
        formateur_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

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

    $participantMigrations = [
        "ALTER TABLE participants ADD COLUMN prenom VARCHAR(100)",
        "ALTER TABLE participants ADD COLUMN nom VARCHAR(100)"
    ];
    foreach ($participantMigrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) {}
    }

    $db->exec("CREATE TABLE IF NOT EXISTS analyses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        nom_projet TEXT DEFAULT '',
        description_projet TEXT DEFAULT '',
        contexte TEXT DEFAULT '',
        contraintes TEXT DEFAULT '',
        objectifs_data TEXT DEFAULT '[]',
        phases_data TEXT DEFAULT '[]',
        checkpoints_data TEXT DEFAULT '[]',
        lessons_data TEXT DEFAULT '[]',
        synthese TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try { $db->exec("ALTER TABLE analyses ADD COLUMN session_id INTEGER"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE analyses ADD COLUMN is_shared INTEGER DEFAULT 0"); } catch (Exception $e) {}
}

function sanitize($input) { return h($input); }
function isLocalAdmin() { return isFormateur(); }
function getCurrentUser() { return getLoggedUser(); }

function requireLoginWithSession() {
    if (!isLoggedIn()) { header('Location: login.php'); exit; }
    if (!isset($_SESSION['current_session_id'])) { header('Location: login.php'); exit; }
}

function requireAdmin() { requireFormateur(); }

/**
 * Statuts disponibles pour les taches
 */
function getTaskStatuses() {
    return [
        'todo' => ['label' => 'A faire', 'color' => 'gray', 'icon' => "\xE2\x9C\xA6"],
        'in_progress' => ['label' => 'En cours', 'color' => 'blue', 'icon' => "\xF0\x9F\x94\xB5"],
        'review' => ['label' => 'En validation', 'color' => 'amber', 'icon' => "\xF0\x9F\x9F\xA1"],
        'done' => ['label' => 'Termine', 'color' => 'green', 'icon' => "\xE2\x9C\x85"],
        'blocked' => ['label' => 'Bloque', 'color' => 'red', 'icon' => "\xF0\x9F\x94\xB4"]
    ];
}

/**
 * Types de points de controle
 */
function getCheckpointTypes() {
    return [
        'validation' => ['label' => 'Validation / Go-No Go', 'color' => 'green', 'icon' => "\xE2\x9C\x85"],
        'revue' => ['label' => 'Revue d\'etape', 'color' => 'blue', 'icon' => "\xF0\x9F\x94\x8D"],
        'livraison' => ['label' => 'Livraison / Jalon', 'color' => 'purple', 'icon' => "\xF0\x9F\x8E\xAF"],
        'feedback' => ['label' => 'Feedback / Retour', 'color' => 'amber', 'icon' => "\xF0\x9F\x92\xAC"],
        'decision' => ['label' => 'Point de decision', 'color' => 'red', 'icon' => "\xE2\x9A\xA1"]
    ];
}
