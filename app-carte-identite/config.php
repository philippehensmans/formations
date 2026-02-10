<?php
/**
 * Configuration - Carte d'identite du Projet
 * Application de creation de fiches projet
 */

// Charger la configuration partagee
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

// Configuration de l'application
define('APP_NAME', 'Carte d\'identite du Projet');
define('APP_KEY', 'app-carte-identite');
define('APP_COLOR', 'purple');
define('DB_FILE', __DIR__ . '/data/carte_identite.db');

/**
 * Connexion a la base de donnees locale
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        // Creer le dossier data s'il n'existe pas
        $dataDir = dirname(DB_FILE);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        initDB($db);
    }
    return $db;
}

/**
 * Initialisation de la base de donnees
 */
function initDB($db) {
    // Table des sessions
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

    // Table des fiches projet
    $db->exec("CREATE TABLE IF NOT EXISTS fiches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        titre TEXT DEFAULT '',
        objectifs TEXT DEFAULT '',
        public_cible TEXT DEFAULT '',
        territoire TEXT DEFAULT '',
        partenaires TEXT DEFAULT '[]',
        ressources_humaines TEXT DEFAULT '',
        ressources_materielles TEXT DEFAULT '',
        ressources_financieres TEXT DEFAULT '',
        calendrier TEXT DEFAULT '',
        resultats TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        is_shared INTEGER DEFAULT 0,
        completion_percent INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrations
    $migrations = [
        "ALTER TABLE fiches ADD COLUMN session_id INTEGER",
        "ALTER TABLE fiches ADD COLUMN is_shared INTEGER DEFAULT 0",
        "ALTER TABLE fiches ADD COLUMN completion_percent INTEGER DEFAULT 0"
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* Colonne existe deja */ }
    }
}
