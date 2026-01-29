<?php
/**
 * Configuration Six Chapeaux de Bono
 * Application pour recueillir des avis categorises selon les Six Chapeaux de la reflexion
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Six Chapeaux');
define('APP_COLOR', 'indigo');
define('DB_PATH', __DIR__ . '/data/six_chapeaux.db');

/**
 * Definition des six chapeaux de Bono
 */
function getChapeaux() {
    return [
        'blanc' => [
            'nom' => 'Chapeau Blanc',
            'description' => 'Faits, donnees, informations objectives',
            'color' => 'gray',
            'bg' => 'bg-gray-100',
            'border' => 'border-gray-400',
            'text' => 'text-gray-700',
            'icon' => '⬜'
        ],
        'rouge' => [
            'nom' => 'Chapeau Rouge',
            'description' => 'Emotions, sentiments, intuitions',
            'color' => 'red',
            'bg' => 'bg-red-100',
            'border' => 'border-red-400',
            'text' => 'text-red-700',
            'icon' => '🟥'
        ],
        'noir' => [
            'nom' => 'Chapeau Noir',
            'description' => 'Prudence, critique, risques, problemes',
            'color' => 'slate',
            'bg' => 'bg-slate-800',
            'border' => 'border-slate-900',
            'text' => 'text-white',
            'icon' => '⬛'
        ],
        'jaune' => [
            'nom' => 'Chapeau Jaune',
            'description' => 'Optimisme, avantages, aspects positifs',
            'color' => 'yellow',
            'bg' => 'bg-yellow-100',
            'border' => 'border-yellow-400',
            'text' => 'text-yellow-700',
            'icon' => '🟨'
        ],
        'vert' => [
            'nom' => 'Chapeau Vert',
            'description' => 'Creativite, nouvelles idees, alternatives',
            'color' => 'green',
            'bg' => 'bg-green-100',
            'border' => 'border-green-400',
            'text' => 'text-green-700',
            'icon' => '🟩'
        ],
        'bleu' => [
            'nom' => 'Chapeau Bleu',
            'description' => 'Organisation, controle du processus, synthese',
            'color' => 'blue',
            'bg' => 'bg-blue-100',
            'border' => 'border-blue-400',
            'text' => 'text-blue-700',
            'icon' => '🟦'
        ]
    ];
}

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
        sujet TEXT DEFAULT '',
        formateur_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des participants aux sessions
    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id),
        UNIQUE(session_id, user_id)
    )");

    // Table des avis (multiple par participant)
    $db->exec("CREATE TABLE IF NOT EXISTS avis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER NOT NULL,
        chapeau VARCHAR(20) NOT NULL,
        contenu TEXT NOT NULL,
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id)
    )");

    // Migrations
    $migrations = [
        "ALTER TABLE sessions ADD COLUMN sujet TEXT DEFAULT ''"
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* Colonne existe deja */ }
    }
}

/**
 * Recuperer tous les avis d'un participant pour une session
 */
function getAvisParticipant($userId, $sessionId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM avis WHERE user_id = ? AND session_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId, $sessionId]);
    return $stmt->fetchAll();
}

/**
 * Recuperer tous les avis d'une session
 */
function getAvisSession($sessionId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT a.*, u.prenom, u.nom, u.organisation
                          FROM avis a
                          JOIN participants p ON a.user_id = p.user_id AND a.session_id = p.session_id
                          LEFT JOIN users u ON a.user_id = u.id
                          WHERE a.session_id = ? AND a.is_shared = 1
                          ORDER BY a.chapeau, a.created_at DESC");
    // Note: JOIN with shared DB users won't work directly, we handle this separately
    $stmt = $db->prepare("SELECT * FROM avis WHERE session_id = ? AND is_shared = 1 ORDER BY chapeau, created_at DESC");
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
}

/**
 * Compter les avis par chapeau pour une session
 */
function getStatsSession($sessionId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT chapeau, COUNT(*) as count FROM avis WHERE session_id = ? AND is_shared = 1 GROUP BY chapeau");
    $stmt->execute([$sessionId]);
    $results = $stmt->fetchAll();

    $stats = [];
    foreach ($results as $row) {
        $stats[$row['chapeau']] = $row['count'];
    }
    return $stats;
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
