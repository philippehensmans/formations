<?php
/**
 * Configuration - Tableau Blanc Collaboratif
 * Utilise le systeme d'authentification partage
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Tableau Blanc');
define('APP_COLOR', 'indigo');

/**
 * Connexion a la base de donnees locale
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $dbPath = __DIR__ . '/data/whiteboard.sqlite';

        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        initDB($db);
    }
    return $db;
}

function initDB($db) {
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
        UNIQUE(session_id, user_id)
    )");

    // Table des tableaux blancs (un par session)
    $db->exec("CREATE TABLE IF NOT EXISTS whiteboards (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL UNIQUE,
        title VARCHAR(255) DEFAULT 'Tableau Blanc',
        background VARCHAR(20) DEFAULT 'white',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
    )");

    // Table des elements (formes, textes, post-its, dessins)
    $db->exec("CREATE TABLE IF NOT EXISTS elements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        whiteboard_id INTEGER NOT NULL,
        type VARCHAR(20) NOT NULL,
        x REAL DEFAULT 0,
        y REAL DEFAULT 0,
        width REAL DEFAULT 100,
        height REAL DEFAULT 100,
        rotation REAL DEFAULT 0,
        content TEXT DEFAULT NULL,
        color VARCHAR(20) DEFAULT 'yellow',
        stroke_color VARCHAR(20) DEFAULT 'black',
        stroke_width INTEGER DEFAULT 2,
        font_size INTEGER DEFAULT 16,
        z_index INTEGER DEFAULT 0,
        locked INTEGER DEFAULT 0,
        created_by INTEGER,
        updated_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (whiteboard_id) REFERENCES whiteboards(id) ON DELETE CASCADE
    )");

    // Table pour les tracés libres (paths SVG)
    $db->exec("CREATE TABLE IF NOT EXISTS paths (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        whiteboard_id INTEGER NOT NULL,
        points TEXT NOT NULL,
        color VARCHAR(20) DEFAULT 'black',
        stroke_width INTEGER DEFAULT 2,
        z_index INTEGER DEFAULT 0,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (whiteboard_id) REFERENCES whiteboards(id) ON DELETE CASCADE
    )");

    // Index pour performances
    $db->exec("CREATE INDEX IF NOT EXISTS idx_elements_whiteboard ON elements(whiteboard_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_paths_whiteboard ON paths(whiteboard_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_participants_session ON participants(session_id)");
}

/**
 * Recuperer ou creer le tableau blanc d'une session
 */
function getOrCreateWhiteboard($sessionId) {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM whiteboards WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $whiteboard = $stmt->fetch();

    if (!$whiteboard) {
        // Creer le tableau blanc vide
        $db->prepare("INSERT INTO whiteboards (session_id) VALUES (?)")->execute([$sessionId]);
        $whiteboardId = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM whiteboards WHERE id = ?");
        $stmt->execute([$whiteboardId]);
        $whiteboard = $stmt->fetch();
    }

    return $whiteboard;
}

/**
 * Recuperer tous les elements d'un tableau blanc
 */
function getElements($whiteboardId) {
    $db = getDB();
    $sharedDb = getSharedDB();

    $stmt = $db->prepare("SELECT * FROM elements WHERE whiteboard_id = ? ORDER BY z_index ASC, id ASC");
    $stmt->execute([$whiteboardId]);
    $elements = $stmt->fetchAll();

    // Ajouter les infos des utilisateurs
    foreach ($elements as &$element) {
        if ($element['created_by']) {
            $userStmt = $sharedDb->prepare("SELECT prenom, nom FROM users WHERE id = ?");
            $userStmt->execute([$element['created_by']]);
            $user = $userStmt->fetch();
            $element['created_by_name'] = $user ? $user['prenom'] . ' ' . substr($user['nom'], 0, 1) . '.' : '';
        }
    }

    return $elements;
}

/**
 * Recuperer tous les traces d'un tableau blanc
 */
function getPaths($whiteboardId) {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM paths WHERE whiteboard_id = ? ORDER BY z_index ASC, id ASC");
    $stmt->execute([$whiteboardId]);
    return $stmt->fetchAll();
}

/**
 * Couleurs disponibles pour les elements
 */
function getColors() {
    return [
        'yellow' => ['bg' => 'bg-yellow-200', 'text' => 'text-gray-800', 'hex' => '#fef08a'],
        'pink' => ['bg' => 'bg-pink-200', 'text' => 'text-gray-800', 'hex' => '#fbcfe8'],
        'blue' => ['bg' => 'bg-blue-200', 'text' => 'text-gray-800', 'hex' => '#bfdbfe'],
        'green' => ['bg' => 'bg-green-200', 'text' => 'text-gray-800', 'hex' => '#bbf7d0'],
        'orange' => ['bg' => 'bg-orange-200', 'text' => 'text-gray-800', 'hex' => '#fed7aa'],
        'purple' => ['bg' => 'bg-purple-200', 'text' => 'text-gray-800', 'hex' => '#e9d5ff'],
        'white' => ['bg' => 'bg-white', 'text' => 'text-gray-800', 'hex' => '#ffffff'],
        'gray' => ['bg' => 'bg-gray-200', 'text' => 'text-gray-800', 'hex' => '#e5e7eb'],
    ];
}

/**
 * Types d'elements disponibles
 */
function getElementTypes() {
    return [
        'postit' => ['label' => 'Post-it', 'icon' => '📝'],
        'text' => ['label' => 'Texte', 'icon' => 'T'],
        'rect' => ['label' => 'Rectangle', 'icon' => '▢'],
        'circle' => ['label' => 'Cercle', 'icon' => '○'],
        'arrow' => ['label' => 'Fleche', 'icon' => '→'],
        'line' => ['label' => 'Ligne', 'icon' => '─'],
    ];
}
