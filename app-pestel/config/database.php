<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Configuration et initialisation de la base de donnees SQLite
 * Application de formation - Analyse PESTEL
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_PATH', __DIR__ . '/../data/pestel.db');
define('ADMIN_PASSWORD', 'Formation2024!');

/**
 * Connexion a la base de donnees avec cache statique
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $dbDir = dirname(DB_PATH);
        if (!file_exists($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            initDatabase($db);
        } catch (PDOException $e) {
            die("Erreur de base de donnees: " . $e->getMessage());
        }
    }
    return $db;
}

/**
 * Initialisation des tables
 */
function initDatabase($db) {
    // Table des sessions de formation
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        nom VARCHAR(255) NOT NULL,
        description TEXT,
        formateur_password VARCHAR(255),
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des participants
    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        nom VARCHAR(100) NOT NULL,
        organisation VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id),
        UNIQUE(session_id, prenom, nom)
    )");

    // Table des analyses PESTEL
    $db->exec("CREATE TABLE IF NOT EXISTS analyse_pestel (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        participant_id INTEGER NOT NULL UNIQUE,
        session_id INTEGER NOT NULL,
        nom_projet TEXT DEFAULT '',
        participants_analyse TEXT DEFAULT '',
        zone TEXT DEFAULT '',
        pestel_data TEXT DEFAULT '{}',
        synthese TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        completion_percent INTEGER DEFAULT 0,
        is_submitted INTEGER DEFAULT 0,
        submitted_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (participant_id) REFERENCES participants(id),
        FOREIGN KEY (session_id) REFERENCES sessions(id)
    )");

    // Creer une session par defaut si aucune n'existe
    $stmt = $db->query("SELECT COUNT(*) as count FROM sessions");
    if ($stmt->fetch()['count'] == 0) {
        $db->exec("INSERT INTO sessions (code, nom, is_active) VALUES ('DEMO01', 'Session Demo', 1)");
    }
}

/**
 * Genere un code de session unique (6 caracteres)
 */
function generateSessionCode() {
    $db = getDB();
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM sessions WHERE code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetch()['count'] > 0;
    } while ($exists);
    return $code;
}

/**
 * Verifie si une session existe et est active
 */
function getSession($code) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM sessions WHERE code = ? AND is_active = 1");
    $stmt->execute([strtoupper($code)]);
    return $stmt->fetch();
}

/**
 * Verifie si le participant est connecte
 */
function isParticipantLoggedIn() {
    return isset($_SESSION['participant_id']);
}

/**
 * Verifie si le formateur est connecte
 */
function isFormateurLoggedIn() {
    return isset($_SESSION['formateur_session_id']);
}

/**
 * Verifie si l'admin est connecte
 */
function isAdminLoggedIn() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Redirige si non connecte
 */
function requireParticipant() {
    if (!isParticipantLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireFormateur() {
    if (!isFormateurLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Obtient les infos du participant connecte
 */
function getCurrentParticipant() {
    if (!isParticipantLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, s.code as session_code, s.nom as session_nom
        FROM participants p
        JOIN sessions s ON p.session_id = s.id
        WHERE p.id = ?
    ");
    $stmt->execute([$_SESSION['participant_id']]);
    return $stmt->fetch();
}

/**
 * Obtient les stats d'une session
 */
function getSessionStats($sessionId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT p.id) as total_participants,
            COUNT(CASE WHEN ap.is_submitted = 1 THEN 1 END) as submitted_count,
            COALESCE(AVG(ap.completion_percent), 0) as avg_completion
        FROM participants p
        LEFT JOIN analyse_pestel ap ON p.id = ap.participant_id
        WHERE p.session_id = ?
    ");
    $stmt->execute([$sessionId]);
    return $stmt->fetch();
}

/**
 * Calcule le pourcentage de completion d'une analyse PESTEL
 */
function calculateCompletion($data) {
    $total = 0;
    $filled = 0;

    // Champs texte (5 points chacun)
    $textFields = ['nom_projet', 'zone', 'synthese'];
    foreach ($textFields as $field) {
        $total += 5;
        if (!empty($data[$field])) $filled += 5;
    }

    // Categories PESTEL (10 points chacune si au moins un element)
    $categories = ['politique', 'economique', 'socioculturel', 'technologique', 'environnemental', 'legal'];
    $pestelData = $data['pestel_data'] ?? [];

    foreach ($categories as $cat) {
        $total += 10;
        if (!empty($pestelData[$cat]) && is_array($pestelData[$cat])) {
            $hasContent = false;
            foreach ($pestelData[$cat] as $item) {
                if (!empty(trim($item))) {
                    $hasContent = true;
                    break;
                }
            }
            if ($hasContent) $filled += 10;
        }
    }

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}

/**
 * Retourne une structure PESTEL vide
 */
function getEmptyPestel() {
    return [
        'politique' => [''],
        'economique' => [''],
        'socioculturel' => [''],
        'technologique' => [''],
        'environnemental' => [''],
        'legal' => ['']
    ];
}

/**
 * Echappe les caracteres HTML
 */
function sanitize($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
