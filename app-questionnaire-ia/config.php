<?php
/**
 * Configuration Questionnaire IA
 * Application de questionnaire configurable pour les formations IA
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Questionnaire IA');
define('APP_COLOR', 'sky');
define('DB_PATH', __DIR__ . '/data/questionnaire_ia.db');

/**
 * Questions par defaut pour l'initialisation
 */
function getDefaultQuestions() {
    return [
        [
            'type' => 'radio',
            'label' => "J'utilise l'IA :",
            'options' => json_encode(['Jamais', 'Rarement', 'Parfois', 'Souvent', 'Tous les jours']),
            'ordre' => 1,
            'obligatoire' => 1
        ],
        [
            'type' => 'text',
            'label' => "Les outils que j'utilise ou ai essaye :",
            'options' => '',
            'ordre' => 2,
            'obligatoire' => 0
        ],
        [
            'type' => 'textarea',
            'label' => "Un usage concret ou l'IA m'a aide (ou pourrait m'aider) :",
            'options' => '',
            'ordre' => 3,
            'obligatoire' => 0
        ],
        [
            'type' => 'textarea',
            'label' => "Ce qui me questionne ou m'inquiete :",
            'options' => '',
            'ordre' => 4,
            'obligatoire' => 0
        ],
        [
            'type' => 'textarea',
            'label' => "Ce que j'aimerais savoir faire a la fin de cette journee :",
            'options' => '',
            'ordre' => 5,
            'obligatoire' => 0
        ]
    ];
}

/**
 * Connexion a la base de donnees locale
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
 * Initialisation des tables
 */
function initDatabase($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        nom VARCHAR(255) NOT NULL,
        sujet TEXT DEFAULT '',
        formateur_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

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

    // Questions configurables par session
    $db->exec("CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'text',
        label TEXT NOT NULL,
        options TEXT DEFAULT '',
        ordre INTEGER DEFAULT 0,
        obligatoire INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id)
    )");

    // Reponses des participants
    $db->exec("CREATE TABLE IF NOT EXISTS reponses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        contenu TEXT NOT NULL DEFAULT '',
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id),
        FOREIGN KEY (question_id) REFERENCES questions(id)
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
        try { $db->exec($sql); } catch (Exception $e) {}
    }

    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_participants_session_user ON participants(session_id, user_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_reponses_session ON reponses(session_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_reponses_user_session ON reponses(user_id, session_id)");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_reponses_unique ON reponses(user_id, session_id, question_id)");
    } catch (Exception $e) {}
}

/**
 * Initialiser les questions par defaut pour une session
 */
function initDefaultQuestions($sessionId) {
    $db = getDB();
    // Verifier si des questions existent deja
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM questions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    if ($stmt->fetch()['c'] > 0) return;

    $defaults = getDefaultQuestions();
    $insert = $db->prepare("INSERT INTO questions (session_id, type, label, options, ordre, obligatoire) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($defaults as $q) {
        $insert->execute([$sessionId, $q['type'], $q['label'], $q['options'], $q['ordre'], $q['obligatoire']]);
    }
}

/**
 * Recuperer les questions d'une session (ordonnees)
 */
function getQuestions($sessionId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM questions WHERE session_id = ? ORDER BY ordre ASC, id ASC");
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
}

/**
 * Recuperer les reponses d'un participant pour une session
 */
function getReponses($userId, $sessionId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT r.*, q.label as question_label, q.type as question_type, q.options as question_options
                          FROM reponses r
                          JOIN questions q ON r.question_id = q.id
                          WHERE r.user_id = ? AND r.session_id = ?
                          ORDER BY q.ordre ASC");
    $stmt->execute([$userId, $sessionId]);
    return $stmt->fetchAll();
}

/**
 * Fonctions utilitaires
 */
function requireLoginWithSession() {
    if (!isLoggedIn()) { header('Location: login.php'); exit; }
    if (!isset($_SESSION['current_session_id'])) { header('Location: login.php'); exit; }
}
