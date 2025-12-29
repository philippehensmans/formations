<?php
/**
 * Configuration base de donnees - Stop Start Continue
 * Application de retrospective et planification
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger le systeme de langues partage
require_once __DIR__ . '/../../shared-auth/lang.php';

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

        $db->exec("CREATE TABLE IF NOT EXISTS retrospectives (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            participant_id INTEGER NOT NULL UNIQUE,
            session_id INTEGER NOT NULL,
            projet_nom TEXT DEFAULT '',
            projet_contexte TEXT DEFAULT '',
            items_cesser TEXT DEFAULT '[]',
            items_commencer TEXT DEFAULT '[]',
            items_continuer TEXT DEFAULT '[]',
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
            $db->exec("ALTER TABLE retrospectives ADD COLUMN is_submitted INTEGER DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $db->exec("ALTER TABLE retrospectives ADD COLUMN completion_percent INTEGER DEFAULT 0");
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
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
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
 * Redirige si non connecte comme formateur (local)
 */
function requireLocalFormateur() {
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
function calculateCompletion($retro) {
    $total = 0;
    $filled = 0;

    // Projet nom et contexte (20%)
    $total += 2;
    if (!empty($retro['projet_nom'])) $filled++;
    if (!empty($retro['projet_contexte'])) $filled++;

    // Items dans chaque categorie (80%)
    $cesser = json_decode($retro['items_cesser'], true) ?: [];
    $commencer = json_decode($retro['items_commencer'], true) ?: [];
    $continuer = json_decode($retro['items_continuer'], true) ?: [];

    $total += 3;
    if (count($cesser) > 0) $filled++;
    if (count($commencer) > 0) $filled++;
    if (count($continuer) > 0) $filled++;

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}
