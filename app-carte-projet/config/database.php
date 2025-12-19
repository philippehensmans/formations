<?php
/**
 * Configuration base de donnees - Carte d'identite du projet
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_PATH', __DIR__ . '/../data/database.sqlite');

/**
 * Initialise et retourne la connexion DB
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Creation des tables
        $db->exec("CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            nom TEXT NOT NULL,
            formateur_password TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS participants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER NOT NULL,
            prenom TEXT NOT NULL,
            nom TEXT NOT NULL,
            organisation TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES sessions(id)
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS cartes_projet (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            participant_id INTEGER NOT NULL UNIQUE,
            session_id INTEGER NOT NULL,
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
            completion_percent INTEGER DEFAULT 0,
            is_submitted INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (participant_id) REFERENCES participants(id),
            FOREIGN KEY (session_id) REFERENCES sessions(id)
        )");

        // Migration: ajouter colonnes si elles n'existent pas
        try {
            $db->exec("ALTER TABLE cartes_projet ADD COLUMN is_submitted INTEGER DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $db->exec("ALTER TABLE cartes_projet ADD COLUMN completion_percent INTEGER DEFAULT 0");
        } catch (PDOException $e) {}
    }
    return $db;
}

/**
 * Genere un code de session unique
 */
function generateSessionCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM sessions WHERE code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() > 0);
    return $code;
}

/**
 * Recupere une session par son code
 */
function getSession($code) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM sessions WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    return $stmt->fetch();
}

/**
 * Echappe les caracteres HTML
 */
function sanitize($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Verifie si un participant est connecte
 */
function isParticipantLoggedIn() {
    return isset($_SESSION['participant_id']) && $_SESSION['participant_id'] > 0;
}

/**
 * Verifie si un formateur est connecte
 */
function isFormateurLoggedIn() {
    return isset($_SESSION['formateur_session_id']) && $_SESSION['formateur_session_id'] > 0;
}

/**
 * Redirige si non connecte comme participant
 */
function requireParticipant() {
    if (!isParticipantLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Redirige si non connecte comme formateur
 */
function requireFormateur() {
    if (!isFormateurLoggedIn()) {
        header('Location: index.php?mode=formateur');
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
 * Calcule le pourcentage de completion
 */
function calculateCompletion($carte) {
    $fields = ['titre', 'objectifs', 'public_cible', 'territoire', 'calendrier', 'resultats'];
    $total = count($fields) + 3; // +3 pour les ressources
    $filled = 0;

    foreach ($fields as $field) {
        if (!empty($carte[$field])) $filled++;
    }

    if (!empty($carte['ressources_humaines'])) $filled++;
    if (!empty($carte['ressources_materielles'])) $filled++;
    if (!empty($carte['ressources_financieres'])) $filled++;

    $partenaires = json_decode($carte['partenaires'] ?? '[]', true);
    if (!empty($partenaires)) $filled++;
    $total++;

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}
