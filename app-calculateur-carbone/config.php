<?php
/**
 * Configuration Calculateur Carbone IA
 * Utilise le systeme d'authentification partage
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';

define('APP_NAME', 'Calculateur Carbone IA');
define('APP_COLOR', 'emerald');
define('DB_PATH', __DIR__ . '/data/calculateur.db');
define('ESTIMATIONS_PATH', __DIR__ . '/data/estimations.json');

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

    // Table des participants
    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        prenom VARCHAR(100),
        nom VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(session_id, user_id)
    )");

    // Migrations pour ajouter les colonnes manquantes
    $migrations = [
        "ALTER TABLE participants ADD COLUMN prenom VARCHAR(100)",
        "ALTER TABLE participants ADD COLUMN nom VARCHAR(100)"
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* Colonne existe deja */ }
    }

    // Table des calculs effectues par les participants
    $db->exec("CREATE TABLE IF NOT EXISTS calculs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        use_case_id VARCHAR(100) NOT NULL,
        frequence VARCHAR(50) DEFAULT 'ponctuel',
        quantite INTEGER DEFAULT 1,
        co2_total REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table pour stocker les mises a jour EcoLogits
    $db->exec("CREATE TABLE IF NOT EXISTS updates_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source VARCHAR(100),
        status VARCHAR(50),
        message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

/**
 * Charger les estimations depuis le fichier JSON
 */
function getEstimations() {
    if (!file_exists(ESTIMATIONS_PATH)) {
        return [];
    }
    $json = file_get_contents(ESTIMATIONS_PATH);
    return json_decode($json, true) ?: [];
}

/**
 * Sauvegarder les estimations dans le fichier JSON
 */
function saveEstimations($data) {
    $dir = dirname(ESTIMATIONS_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return file_put_contents(ESTIMATIONS_PATH, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Obtenir une estimation par son ID
 */
function getEstimationById($id) {
    $estimations = getEstimations();
    return $estimations['use_cases'][$id] ?? null;
}

/**
 * Calculer le CO2 total pour une utilisation
 */
function calculerCO2($useCase, $frequence, $quantite = 1) {
    $estimation = getEstimationById($useCase);
    if (!$estimation) return 0;

    $co2Base = $estimation['co2_grammes'] ?? 0;

    // Multiplicateurs selon la frequence (par an)
    $multiplicateurs = [
        'ponctuel' => 1,
        'quotidien' => 250, // jours ouvrables
        'hebdomadaire' => 52,
        'mensuel' => 12,
        'trimestriel' => 4,
        'annuel' => 1
    ];

    $mult = $multiplicateurs[$frequence] ?? 1;

    return $co2Base * $mult * $quantite;
}

/**
 * Convertir CO2 en equivalents comprehensibles
 */
function co2Equivalents($grammes) {
    $kg = $grammes / 1000;

    return [
        'km_voiture' => round($kg / 0.21, 1), // ~210g CO2/km voiture moyenne
        'emails' => round($grammes / 4, 0), // ~4g CO2/email
        'streaming_heures' => round($grammes / 36, 1), // ~36g CO2/h streaming HD
        'smartphone_charges' => round($grammes / 8.3, 0), // ~8.3g CO2/charge
        'tasses_cafe' => round($grammes / 21, 1), // ~21g CO2/tasse
    ];
}
