<?php
/**
 * Configuration Mini-Plan de Communication
 * Utilise le systeme d'authentification partage
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Mini-Plan de Communication');
define('APP_COLOR', 'indigo');
define('DB_PATH', __DIR__ . '/data/comm_plan.db');

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
        nom_organisation TEXT DEFAULT '',
        action_communiquer TEXT DEFAULT '',
        objectif_smart TEXT DEFAULT '',
        public_prioritaire TEXT DEFAULT '',
        message_cle TEXT DEFAULT '',
        canaux_data TEXT DEFAULT '[]',
        calendrier_data TEXT DEFAULT '[]',
        ressources_data TEXT DEFAULT '[]',
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
 * Canaux de communication disponibles
 */
function getCanaux() {
    return [
        'facebook' => ['label' => 'Facebook', 'icon' => "\xF0\x9F\x93\x98", 'color' => 'blue'],
        'instagram' => ['label' => 'Instagram', 'icon' => "\xF0\x9F\x93\xB7", 'color' => 'pink'],
        'site_web' => ['label' => 'Site web', 'icon' => "\xF0\x9F\x8C\x90", 'color' => 'cyan'],
        'newsletter' => ['label' => 'Newsletter / Email', 'icon' => "\xF0\x9F\x93\xA7", 'color' => 'green'],
        'affichage' => ['label' => 'Affichage / Flyers', 'icon' => "\xF0\x9F\x93\x8C", 'color' => 'amber'],
        'presse' => ['label' => 'Presse locale', 'icon' => "\xF0\x9F\x93\xB0", 'color' => 'gray'],
        'radio' => ['label' => 'Radio', 'icon' => "\xF0\x9F\x93\xBB", 'color' => 'purple'],
        'bouche_a_oreille' => ['label' => 'Bouche-a-oreille / Relais', 'icon' => "\xF0\x9F\x97\xA3\xEF\xB8\x8F", 'color' => 'orange'],
        'evenement' => ['label' => 'Evenement / Stand', 'icon' => "\xF0\x9F\x8E\xAA", 'color' => 'red'],
        'whatsapp' => ['label' => 'WhatsApp / Signal', 'icon' => "\xF0\x9F\x92\xAC", 'color' => 'emerald'],
        'autre' => ['label' => 'Autre', 'icon' => "\xE2\x9E\x95", 'color' => 'gray']
    ];
}
