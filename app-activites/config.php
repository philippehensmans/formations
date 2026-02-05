<?php
/**
 * Configuration - Inventaire des Activites
 * Cartographie des activites d'une association
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Inventaire des Activités');
define('APP_COLOR', 'teal');

/**
 * Connexion a la base de donnees locale
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $dbPath = __DIR__ . '/data/activites.sqlite';

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
        prenom VARCHAR(100),
        nom VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
        UNIQUE(session_id, user_id)
    )");

    // Migrations pour ajouter les colonnes manquantes aux participants
    $participantMigrations = [
        "ALTER TABLE participants ADD COLUMN prenom VARCHAR(100)",
        "ALTER TABLE participants ADD COLUMN nom VARCHAR(100)"
    ];
    foreach ($participantMigrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* Colonne existe deja */ }
    }

    // Table des activites
    $db->exec("CREATE TABLE IF NOT EXISTS activites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        nom VARCHAR(255) NOT NULL,
        description TEXT,
        categorie VARCHAR(50) DEFAULT 'autre',
        frequence VARCHAR(30) DEFAULT 'ponctuelle',
        temps_estime VARCHAR(50),
        priorite INTEGER DEFAULT 2,
        potentiel_ia INTEGER DEFAULT 0,
        notes_ia TEXT,
        created_by INTEGER,
        updated_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
    )");

    // Index pour performances
    $db->exec("CREATE INDEX IF NOT EXISTS idx_activites_session ON activites(session_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_activites_categorie ON activites(categorie)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_participants_session ON participants(session_id)");
}

/**
 * Categories d'activites
 */
function getCategories() {
    return [
        'communication' => ['label' => 'Communication', 'icon' => '📢', 'color' => 'blue'],
        'administration' => ['label' => 'Administration', 'icon' => '📋', 'color' => 'gray'],
        'evenements' => ['label' => 'Événements', 'icon' => '🎉', 'color' => 'purple'],
        'membres' => ['label' => 'Gestion membres', 'icon' => '👥', 'color' => 'green'],
        'comptabilite' => ['label' => 'Comptabilité', 'icon' => '💰', 'color' => 'yellow'],
        'rh' => ['label' => 'RH / Bénévoles', 'icon' => '🤝', 'color' => 'orange'],
        'projets' => ['label' => 'Gestion de projets', 'icon' => '📊', 'color' => 'indigo'],
        'formation' => ['label' => 'Formation', 'icon' => '🎓', 'color' => 'pink'],
        'autre' => ['label' => 'Autre', 'icon' => '📌', 'color' => 'slate'],
    ];
}

/**
 * Frequences possibles
 */
function getFrequences() {
    return [
        'quotidienne' => 'Quotidienne',
        'hebdomadaire' => 'Hebdomadaire',
        'mensuelle' => 'Mensuelle',
        'trimestrielle' => 'Trimestrielle',
        'annuelle' => 'Annuelle',
        'ponctuelle' => 'Ponctuelle',
    ];
}

/**
 * Niveaux de priorite
 */
function getPriorites() {
    return [
        1 => ['label' => 'Faible', 'color' => 'gray'],
        2 => ['label' => 'Moyenne', 'color' => 'yellow'],
        3 => ['label' => 'Haute', 'color' => 'orange'],
        4 => ['label' => 'Critique', 'color' => 'red'],
    ];
}

/**
 * Recuperer toutes les activites d'une session
 */
function getActivites($sessionId) {
    $db = getDB();
    $sharedDb = getSharedDB();

    $stmt = $db->prepare("SELECT * FROM activites WHERE session_id = ? ORDER BY categorie, nom");
    $stmt->execute([$sessionId]);
    $activites = $stmt->fetchAll();

    // Ajouter les infos des utilisateurs
    foreach ($activites as &$activite) {
        if ($activite['created_by']) {
            $userStmt = $sharedDb->prepare("SELECT prenom, nom FROM users WHERE id = ?");
            $userStmt->execute([$activite['created_by']]);
            $user = $userStmt->fetch();
            $activite['created_by_name'] = $user ? $user['prenom'] . ' ' . substr($user['nom'], 0, 1) . '.' : '';
        }
    }

    return $activites;
}

/**
 * Statistiques des activites
 */
function getStatistiques($sessionId) {
    $db = getDB();

    $stats = [
        'total' => 0,
        'par_categorie' => [],
        'par_frequence' => [],
        'avec_potentiel_ia' => 0,
        'par_priorite' => [],
    ];

    // Total
    $stmt = $db->prepare("SELECT COUNT(*) FROM activites WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $stats['total'] = $stmt->fetchColumn();

    // Par categorie
    $stmt = $db->prepare("SELECT categorie, COUNT(*) as count FROM activites WHERE session_id = ? GROUP BY categorie");
    $stmt->execute([$sessionId]);
    $stats['par_categorie'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Par frequence
    $stmt = $db->prepare("SELECT frequence, COUNT(*) as count FROM activites WHERE session_id = ? GROUP BY frequence");
    $stmt->execute([$sessionId]);
    $stats['par_frequence'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Avec potentiel IA
    $stmt = $db->prepare("SELECT COUNT(*) FROM activites WHERE session_id = ? AND potentiel_ia = 1");
    $stmt->execute([$sessionId]);
    $stats['avec_potentiel_ia'] = $stmt->fetchColumn();

    // Par priorite
    $stmt = $db->prepare("SELECT priorite, COUNT(*) as count FROM activites WHERE session_id = ? GROUP BY priorite");
    $stmt->execute([$sessionId]);
    $stats['par_priorite'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    return $stats;
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
