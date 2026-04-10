<?php
/**
 * Configuration Auto-Diagnostic Communication
 * Utilise le systeme d'authentification partage
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Auto-Diagnostic Communication');
define('APP_COLOR', 'cyan');
define('DB_PATH', __DIR__ . '/data/comm_diagnostic.db');

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

function initDatabase($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        nom VARCHAR(255) NOT NULL,
        formateur_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        prenom VARCHAR(100),
        nom VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id),
        UNIQUE(session_id, user_id)
    )");

    $participantMigrations = [
        "ALTER TABLE participants ADD COLUMN prenom VARCHAR(100)",
        "ALTER TABLE participants ADD COLUMN nom VARCHAR(100)"
    ];
    foreach ($participantMigrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) {}
    }

    $db->exec("CREATE TABLE IF NOT EXISTS analyses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        nom_organisation TEXT DEFAULT '',
        section1_data TEXT DEFAULT '{}',
        section2_data TEXT DEFAULT '{}',
        section3_data TEXT DEFAULT '{}',
        section4_data TEXT DEFAULT '{}',
        section5_data TEXT DEFAULT '{}',
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try { $db->exec("ALTER TABLE analyses ADD COLUMN session_id INTEGER"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE analyses ADD COLUMN is_shared INTEGER DEFAULT 0"); } catch (Exception $e) {}
}

function sanitize($input) { return h($input); }
function isLocalAdmin() { return isFormateur(); }
function getCurrentUser() { return getLoggedUser(); }

function requireLoginWithSession() {
    if (!isLoggedIn()) { header('Location: login.php'); exit; }
    if (!isset($_SESSION['current_session_id'])) { header('Location: login.php'); exit; }
}

function requireAdmin() { requireFormateur(); }

function getDefaultData() {
    return [
        'nom_organisation' => '',
        'section1_data' => [
            'valeurs' => ['', '', ''],
            'valeurs_scores' => [
                ['score' => 0, 'commentaire' => ''],
                ['score' => 0, 'commentaire' => ''],
                ['score' => 0, 'commentaire' => '']
            ],
            'exemple_positif' => '',
            'exemple_decalage' => ''
        ],
        'section2_data' => [
            'budget' => '',
            'contraintes' => ['', '', ''],
            'atouts' => ['', '', ''],
            'action_efficace' => '',
            'ressources_non_financieres' => [],
            'ressources_autre' => ''
        ],
        'section3_data' => [
            'parties_prenantes' => [
                ['nom' => '', 'engagement' => 0, 'actions' => ''],
                ['nom' => '', 'engagement' => 0, 'actions' => ''],
                ['nom' => '', 'engagement' => 0, 'actions' => ''],
                ['nom' => '', 'engagement' => 0, 'actions' => '']
            ],
            'transformation_score' => 0,
            'obstacles' => ['', '', ''],
            'exemple_mobilisation' => ''
        ],
        'section4_data' => [
            'force_distinctive' => '',
            'defi_prioritaire' => '',
            'articulation' => ''
        ],
        'section5_data' => [
            'piste_valeurs' => '',
            'piste_ressources' => '',
            'piste_mobilisation' => ''
        ]
    ];
}

function calculateCompletion($data) {
    $total = 0;
    $filled = 0;

    // Organisation (5pts)
    $total += 5;
    if (!empty(trim($data['nom_organisation'] ?? ''))) $filled += 5;

    // Section 1: Valeurs et Mission (25pts)
    $s1 = $data['section1_data'] ?? [];
    // Valeurs (9pts - 3 each)
    $total += 9;
    foreach (($s1['valeurs'] ?? []) as $v) {
        if (!empty(trim($v))) $filled += 3;
    }
    // Scores (6pts - 2 each)
    $total += 6;
    foreach (($s1['valeurs_scores'] ?? []) as $vs) {
        if (($vs['score'] ?? 0) > 0) $filled += 2;
    }
    // Exemples (5pts each)
    $total += 10;
    if (!empty(trim($s1['exemple_positif'] ?? ''))) $filled += 5;
    if (!empty(trim($s1['exemple_decalage'] ?? ''))) $filled += 5;

    // Section 2: Contraintes et Ressources (20pts)
    $s2 = $data['section2_data'] ?? [];
    $total += 4;
    if (!empty($s2['budget'] ?? '')) $filled += 4;
    $total += 6;
    foreach (($s2['contraintes'] ?? []) as $c) {
        if (!empty(trim($c))) $filled += 2;
    }
    $total += 6;
    foreach (($s2['atouts'] ?? []) as $a) {
        if (!empty(trim($a))) $filled += 2;
    }
    $total += 4;
    if (!empty(trim($s2['action_efficace'] ?? ''))) $filled += 4;

    // Section 3: Mobilisation (20pts)
    $s3 = $data['section3_data'] ?? [];
    $total += 8;
    $ppCount = 0;
    foreach (($s3['parties_prenantes'] ?? []) as $pp) {
        if (!empty(trim($pp['nom'] ?? '')) && ($pp['engagement'] ?? 0) > 0) $ppCount++;
    }
    if ($ppCount >= 3) $filled += 8;
    elseif ($ppCount >= 2) $filled += 5;
    elseif ($ppCount >= 1) $filled += 3;
    $total += 4;
    if (($s3['transformation_score'] ?? 0) > 0) $filled += 4;
    $total += 4;
    $obsCount = 0;
    foreach (($s3['obstacles'] ?? []) as $o) {
        if (!empty(trim($o))) $obsCount++;
    }
    if ($obsCount >= 2) $filled += 4;
    elseif ($obsCount >= 1) $filled += 2;
    $total += 4;
    if (!empty(trim($s3['exemple_mobilisation'] ?? ''))) $filled += 4;

    // Section 4: Synthese (10pts)
    $s4 = $data['section4_data'] ?? [];
    $total += 10;
    if (!empty(trim($s4['force_distinctive'] ?? ''))) $filled += 3;
    if (!empty(trim($s4['defi_prioritaire'] ?? ''))) $filled += 3;
    if (!empty(trim($s4['articulation'] ?? ''))) $filled += 4;

    // Section 5: Pistes d'action (10pts)
    $s5 = $data['section5_data'] ?? [];
    $total += 10;
    if (!empty(trim($s5['piste_valeurs'] ?? ''))) $filled += 3;
    if (!empty(trim($s5['piste_ressources'] ?? ''))) $filled += 3;
    if (!empty(trim($s5['piste_mobilisation'] ?? ''))) $filled += 4;

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}

function calculateSectionScores($data) {
    $scores = [];

    // Section 1 average score
    $s1 = $data['section1_data'] ?? [];
    $s1Scores = [];
    foreach (($s1['valeurs_scores'] ?? []) as $vs) {
        if (($vs['score'] ?? 0) > 0) $s1Scores[] = (int)$vs['score'];
    }
    $scores['valeurs'] = !empty($s1Scores) ? round(array_sum($s1Scores) / count($s1Scores), 1) : 0;

    // Section 3 engagement average
    $s3 = $data['section3_data'] ?? [];
    $engScores = [];
    foreach (($s3['parties_prenantes'] ?? []) as $pp) {
        if (($pp['engagement'] ?? 0) > 0) $engScores[] = (int)$pp['engagement'];
    }
    $scores['engagement'] = !empty($engScores) ? round(array_sum($engScores) / count($engScores), 1) : 0;
    $scores['transformation'] = (int)($s3['transformation_score'] ?? 0);

    return $scores;
}
