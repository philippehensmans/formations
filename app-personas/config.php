<?php
/**
 * Configuration Publics & Personas
 * Utilise le systeme d'authentification partage
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Publics & Personas');
define('APP_COLOR', 'rose');
define('DB_PATH', __DIR__ . '/data/personas.db');

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
        contexte TEXT DEFAULT '',
        stakeholders_data TEXT DEFAULT '[]',
        personas_data TEXT DEFAULT '[]',
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
 * Familles de publics
 */
function getPublicFamilies() {
    return [
        'beneficiaires' => [
            'label' => 'Beneficiaires',
            'description' => 'Participants, usagers, public de vos activites',
            'color' => 'blue',
            'icon' => "\xF0\x9F\x91\xA5"
        ],
        'partenaires' => [
            'label' => 'Partenaires',
            'description' => 'Associations, reseaux, federations, plateformes',
            'color' => 'green',
            'icon' => "\xF0\x9F\xA4\x9D"
        ],
        'pouvoirs_subsidiants' => [
            'label' => 'Pouvoirs subsidiants',
            'description' => 'Commune, province, FWB, Region, fondations, fonds europeens',
            'color' => 'purple',
            'icon' => "\xF0\x9F\x8F\x9B\xEF\xB8\x8F"
        ],
        'medias' => [
            'label' => 'Medias locaux',
            'description' => 'Presse regionale, TV locales, radios, sites d\'info',
            'color' => 'orange',
            'icon' => "\xF0\x9F\x93\xB0"
        ],
        'grand_public' => [
            'label' => 'Grand public',
            'description' => 'Habitants du quartier, de la commune, inconnus',
            'color' => 'cyan',
            'icon' => "\xF0\x9F\x8C\x8D"
        ],
        'benevoles' => [
            'label' => 'Benevoles',
            'description' => 'Benevoles actuels et potentiels',
            'color' => 'amber',
            'icon' => "\xE2\x9D\xA4\xEF\xB8\x8F"
        ],
        'elus' => [
            'label' => 'Elu-es locaux',
            'description' => 'Echevins, bourgmestres, conseillers communaux',
            'color' => 'red',
            'icon' => "\xF0\x9F\x8E\xAF"
        ],
        'autre' => [
            'label' => 'Autre',
            'description' => 'Autre type de public',
            'color' => 'gray',
            'icon' => "\xE2\x9E\x95"
        ]
    ];
}
