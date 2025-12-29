<?php
/**
 * Configuration Empreinte Carbone IA
 * Utilise le systeme d'authentification partage
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';

define('APP_NAME', 'Empreinte Carbone IA');
define('APP_COLOR', 'green');
define('DB_PATH', __DIR__ . '/data/empreinte.db');

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
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(session_id, user_id)
    )");

    // Table des scenarios (un par session, defini par le formateur)
    $db->exec("CREATE TABLE IF NOT EXISTS scenarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        option1_name VARCHAR(100) DEFAULT '🚀 IA Puissante (Cloud)',
        option1_desc TEXT DEFAULT 'Utilisation d''un grand modele d''IA via API cloud (GPT-4, Claude, etc.)',
        option2_name VARCHAR(100) DEFAULT '⚖️ IA Legere (Locale)',
        option2_desc TEXT DEFAULT 'Modele d''IA plus petit, execute localement ou solution hybride',
        option3_name VARCHAR(100) DEFAULT '👥 Sans IA (Humain)',
        option3_desc TEXT DEFAULT 'Approche traditionnelle sans intelligence artificielle',
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des votes des participants
    $db->exec("CREATE TABLE IF NOT EXISTS votes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        scenario_id INTEGER NOT NULL,
        participant_id INTEGER NOT NULL,
        option_number INTEGER NOT NULL,
        impact INTEGER DEFAULT 0,
        qualite INTEGER DEFAULT 0,
        temps INTEGER DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(scenario_id, participant_id, option_number)
    )");
}

// Obtenir ou creer le scenario actif pour une session
function getActiveScenario($db, $sessionId) {
    $stmt = $db->prepare("SELECT * FROM scenarios WHERE session_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtenir tous les votes pour un scenario
function getVotes($db, $scenarioId) {
    $stmt = $db->prepare("SELECT * FROM votes WHERE scenario_id = ?");
    $stmt->execute([$scenarioId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculer les moyennes des votes par option
function calculateAverages($db, $scenarioId) {
    $results = [];

    for ($opt = 1; $opt <= 3; $opt++) {
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as voters,
                AVG(impact) as avg_impact,
                AVG(qualite) as avg_qualite,
                AVG(temps) as avg_temps
            FROM votes
            WHERE scenario_id = ? AND option_number = ? AND (impact > 0 OR qualite > 0 OR temps > 0)
        ");
        $stmt->execute([$scenarioId, $opt]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $results[$opt] = [
            'voters' => (int)$row['voters'],
            'impact' => round($row['avg_impact'] ?: 0, 1),
            'qualite' => round($row['avg_qualite'] ?: 0, 1),
            'temps' => round($row['avg_temps'] ?: 0, 1),
            'score_env' => round((4 - ($row['avg_impact'] ?: 0)) * 33.33, 0),
            'score_global' => 0
        ];

        if ($row['voters'] > 0) {
            $results[$opt]['score_global'] = round(
                (($row['avg_qualite'] ?: 0) / 5 * 40) +
                (($row['avg_temps'] ?: 0) / 3 * 30) +
                ((4 - ($row['avg_impact'] ?: 0)) / 3 * 30)
            , 0);
        }
    }

    return $results;
}
