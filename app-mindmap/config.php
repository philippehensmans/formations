<?php
/**
 * Configuration - Carte Mentale Collaborative
 * Utilise le systeme d'authentification partage
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';

define('APP_NAME', 'Carte Mentale');
define('APP_COLOR', 'violet');

/**
 * Connexion a la base de donnees locale
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $dbPath = __DIR__ . '/data/mindmap.sqlite';

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

    // Table des cartes mentales (une par session)
    $db->exec("CREATE TABLE IF NOT EXISTS mindmaps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL UNIQUE,
        title VARCHAR(255) DEFAULT 'Carte Mentale',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
    )");

    // Table des noeuds
    $db->exec("CREATE TABLE IF NOT EXISTS nodes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        mindmap_id INTEGER NOT NULL,
        parent_id INTEGER DEFAULT NULL,
        text VARCHAR(255) NOT NULL,
        note TEXT DEFAULT NULL,
        file_url VARCHAR(500) DEFAULT NULL,
        color VARCHAR(20) DEFAULT 'blue',
        icon VARCHAR(50) DEFAULT NULL,
        pos_x REAL DEFAULT 0,
        pos_y REAL DEFAULT 0,
        is_root INTEGER DEFAULT 0,
        created_by INTEGER,
        updated_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (mindmap_id) REFERENCES mindmaps(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES nodes(id) ON DELETE CASCADE
    )");

    // Migration: ajouter note et file_url si elles n'existent pas
    try { $db->exec("ALTER TABLE nodes ADD COLUMN note TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE nodes ADD COLUMN file_url VARCHAR(500) DEFAULT NULL"); } catch (Exception $e) {}

    // Index pour performances
    $db->exec("CREATE INDEX IF NOT EXISTS idx_nodes_mindmap ON nodes(mindmap_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_nodes_parent ON nodes(parent_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_participants_session ON participants(session_id)");
}

/**
 * Recuperer ou creer la carte mentale d'une session
 */
function getOrCreateMindmap($sessionId) {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM mindmaps WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $mindmap = $stmt->fetch();

    if (!$mindmap) {
        // Creer la carte avec un noeud racine
        $db->prepare("INSERT INTO mindmaps (session_id) VALUES (?)")->execute([$sessionId]);
        $mindmapId = $db->lastInsertId();

        // Creer le noeud central
        $db->prepare("INSERT INTO nodes (mindmap_id, text, color, is_root, pos_x, pos_y) VALUES (?, 'Idee centrale', 'violet', 1, 400, 300)")
           ->execute([$mindmapId]);

        $stmt = $db->prepare("SELECT * FROM mindmaps WHERE id = ?");
        $stmt->execute([$mindmapId]);
        $mindmap = $stmt->fetch();
    }

    return $mindmap;
}

/**
 * Recuperer tous les noeuds d'une carte
 */
function getNodes($mindmapId) {
    $db = getDB();
    $sharedDb = getSharedDB();

    $stmt = $db->prepare("SELECT * FROM nodes WHERE mindmap_id = ? ORDER BY is_root DESC, id ASC");
    $stmt->execute([$mindmapId]);
    $nodes = $stmt->fetchAll();

    // Ajouter les infos des utilisateurs
    foreach ($nodes as &$node) {
        if ($node['updated_by']) {
            $userStmt = $sharedDb->prepare("SELECT prenom, nom FROM users WHERE id = ?");
            $userStmt->execute([$node['updated_by']]);
            $user = $userStmt->fetch();
            $node['updated_by_name'] = $user ? $user['prenom'] . ' ' . substr($user['nom'], 0, 1) . '.' : '';
        }
    }

    return $nodes;
}

/**
 * Couleurs disponibles pour les noeuds
 */
function getColors() {
    return [
        'violet' => ['bg' => 'bg-violet-500', 'text' => 'text-white', 'border' => 'border-violet-600'],
        'blue' => ['bg' => 'bg-blue-500', 'text' => 'text-white', 'border' => 'border-blue-600'],
        'green' => ['bg' => 'bg-green-500', 'text' => 'text-white', 'border' => 'border-green-600'],
        'yellow' => ['bg' => 'bg-yellow-400', 'text' => 'text-gray-800', 'border' => 'border-yellow-500'],
        'orange' => ['bg' => 'bg-orange-500', 'text' => 'text-white', 'border' => 'border-orange-600'],
        'red' => ['bg' => 'bg-red-500', 'text' => 'text-white', 'border' => 'border-red-600'],
        'pink' => ['bg' => 'bg-pink-500', 'text' => 'text-white', 'border' => 'border-pink-600'],
        'gray' => ['bg' => 'bg-gray-500', 'text' => 'text-white', 'border' => 'border-gray-600'],
    ];
}

/**
 * Icones disponibles
 */
function getIcons() {
    return [
        'idea' => ['label' => 'Idee', 'emoji' => '💡'],
        'question' => ['label' => 'Question', 'emoji' => '❓'],
        'check' => ['label' => 'Valide', 'emoji' => '✅'],
        'warning' => ['label' => 'Attention', 'emoji' => '⚠️'],
        'star' => ['label' => 'Important', 'emoji' => '⭐'],
        'target' => ['label' => 'Objectif', 'emoji' => '🎯'],
        'people' => ['label' => 'Personnes', 'emoji' => '👥'],
        'tools' => ['label' => 'Outils', 'emoji' => '🔧'],
        'calendar' => ['label' => 'Date', 'emoji' => '📅'],
        'money' => ['label' => 'Budget', 'emoji' => '💰'],
    ];
}
