<?php
/**
 * Configuration Journey Mapping - Audit de Communication
 * Utilise le systeme d'authentification partage
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Journey Mapping');
define('APP_COLOR', 'cyan');
define('DB_PATH', __DIR__ . '/data/journey_mapping.db');

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

    // Migrations pour ajouter les colonnes manquantes
    $participantMigrations = [
        "ALTER TABLE participants ADD COLUMN prenom VARCHAR(100)",
        "ALTER TABLE participants ADD COLUMN nom VARCHAR(100)"
    ];
    foreach ($participantMigrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* Colonne existe deja */ }
    }

    // Table des analyses Journey Mapping
    $db->exec("CREATE TABLE IF NOT EXISTS analyses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        nom_organisation TEXT DEFAULT '',
        objectif_audit TEXT DEFAULT '',
        public_cible TEXT DEFAULT '',
        journey_data TEXT DEFAULT '[]',
        synthese TEXT DEFAULT '',
        recommandations TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        is_shared INTEGER DEFAULT 0,
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
 * Canaux de communication disponibles
 */
function getChannels() {
    return [
        'site_web' => 'Site web',
        'reseaux_sociaux' => 'Reseaux sociaux',
        'email' => 'Email / Newsletter',
        'telephone' => 'Telephone',
        'accueil_physique' => 'Accueil physique',
        'courrier' => 'Courrier postal',
        'evenement' => 'Evenement / Conference',
        'media' => 'Media (presse, radio, TV)',
        'bouche_a_oreille' => 'Bouche-a-oreille',
        'application_mobile' => 'Application mobile',
        'chat_messagerie' => 'Chat / Messagerie instantanee',
        'autre' => 'Autre'
    ];
}

/**
 * Emotions disponibles
 */
function getEmotions() {
    return [
        'satisfaction' => ['emoji' => "\xF0\x9F\x98\x8A", 'label' => 'Satisfaction'],
        'confusion' => ['emoji' => "\xF0\x9F\x98\x95", 'label' => 'Confusion'],
        'frustration' => ['emoji' => "\xF0\x9F\x98\xA4", 'label' => 'Frustration'],
        'enthousiasme' => ['emoji' => "\xF0\x9F\x98\x8D", 'label' => 'Enthousiasme'],
        'indifference' => ['emoji' => "\xF0\x9F\x98\x90", 'label' => 'Indifference'],
        'inquietude' => ['emoji' => "\xF0\x9F\x98\xB0", 'label' => 'Inquietude'],
        'questionnement' => ['emoji' => "\xF0\x9F\xA4\x94", 'label' => 'Questionnement'],
        'surprise_positive' => ['emoji' => "\xE2\x9C\xA8", 'label' => 'Surprise positive']
    ];
}
