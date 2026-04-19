<?php
/**
 * Configuration - Évaluateur de Gouvernance
 *
 * Contenu (domaines, questions, échelle, N/A) stocké en base et éditable par le formateur.
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Évaluateur de Gouvernance');
define('APP_COLOR', 'indigo');
define('DB_PATH', __DIR__ . '/data/gouvernance.db');

function getDB() {
    static $db = null;
    if ($db === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec("PRAGMA foreign_keys = ON");
        initDatabase($db);
        seedIfEmpty($db);
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

    $db->exec("CREATE TABLE IF NOT EXISTS config (
        key VARCHAR(100) PRIMARY KEY,
        value TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS scale_levels (
        niveau INTEGER PRIMARY KEY,
        cle VARCHAR(50),
        label VARCHAR(100) NOT NULL,
        description TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS domains (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug VARCHAR(100) UNIQUE NOT NULL,
        titre VARCHAR(255) NOT NULL,
        description TEXT,
        ordre INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain_id INTEGER NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        intitule VARCHAR(255) NOT NULL,
        texte TEXT NOT NULL,
        aide TEXT,
        ordre INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS anchors (
        question_id INTEGER NOT NULL,
        niveau INTEGER NOT NULL,
        description TEXT,
        PRIMARY KEY (question_id, niveau),
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS evaluations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        responses TEXT DEFAULT '{}',
        is_submitted INTEGER DEFAULT 0,
        submitted_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, session_id)
    )");
}

function seedIfEmpty($db) {
    $count = (int)$db->query("SELECT COUNT(*) FROM domains")->fetchColumn();
    if ($count > 0) return;

    $seed = require __DIR__ . '/seed-data.php';

    // Échelle
    $stmt = $db->prepare("INSERT INTO scale_levels (niveau, cle, label, description) VALUES (?, ?, ?, ?)");
    foreach ($seed['scale'] as $lvl) {
        $stmt->execute([$lvl['niveau'], $lvl['cle'], $lvl['label'], $lvl['description']]);
    }

    // N/A
    setConfig('na_enabled', $seed['na']['enabled'] ? '1' : '0');
    setConfig('na_label', $seed['na']['label']);
    setConfig('na_description', $seed['na']['description']);

    // Titre
    setConfig('app_title', $seed['meta']['title'] ?? APP_NAME);
    setConfig('app_subtitle', $seed['meta']['subtitle'] ?? '');

    // Domaines + questions + ancrages
    $sd = $db->prepare("INSERT INTO domains (slug, titre, description, ordre) VALUES (?, ?, ?, ?)");
    $sq = $db->prepare("INSERT INTO questions (domain_id, slug, intitule, texte, aide, ordre) VALUES (?, ?, ?, ?, ?, ?)");
    $sa = $db->prepare("INSERT INTO anchors (question_id, niveau, description) VALUES (?, ?, ?)");

    foreach ($seed['domains'] as $dIdx => $d) {
        $sd->execute([$d['slug'], $d['titre'], $d['description'] ?? '', $d['ordre'] ?? ($dIdx + 1)]);
        $domainId = (int)$db->lastInsertId();
        foreach ($d['questions'] as $qIdx => $q) {
            $sq->execute([$domainId, $q['slug'], $q['intitule'], $q['texte'], $q['aide'] ?? null, $q['ordre'] ?? ($qIdx + 1)]);
            $questionId = (int)$db->lastInsertId();
            foreach (($q['ancrages'] ?? []) as $niveau => $desc) {
                $sa->execute([$questionId, (int)$niveau, $desc]);
            }
        }
    }
}

// ----- Config (key/value) -----

function getConfig($key, $default = '') {
    $db = getDB();
    $stmt = $db->prepare("SELECT value FROM config WHERE key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val === false ? $default : $val;
}

function setConfig($key, $value) {
    $db = getDB();
    $db->prepare("INSERT OR REPLACE INTO config (key, value) VALUES (?, ?)")->execute([$key, $value]);
}

function getNaSettings() {
    return [
        'enabled' => getConfig('na_enabled', '1') === '1',
        'label' => getConfig('na_label', 'Non applicable / Ne sais pas'),
        'description' => getConfig('na_description', ''),
    ];
}

// ----- Échelle -----

function getScaleLevels() {
    $db = getDB();
    return $db->query("SELECT * FROM scale_levels ORDER BY niveau")->fetchAll();
}

function getMaxLevel() {
    $db = getDB();
    $max = $db->query("SELECT MAX(niveau) FROM scale_levels")->fetchColumn();
    return (int)$max ?: 4;
}

// ----- Domaines / questions / ancrages -----

function getAllDomains() {
    $db = getDB();
    $domains = $db->query("SELECT * FROM domains ORDER BY ordre, id")->fetchAll();
    $questions = $db->query("SELECT * FROM questions ORDER BY domain_id, ordre, id")->fetchAll();
    $anchors = $db->query("SELECT * FROM anchors")->fetchAll();

    $anchorsByQ = [];
    foreach ($anchors as $a) {
        $anchorsByQ[$a['question_id']][(int)$a['niveau']] = $a['description'];
    }

    $qByDomain = [];
    foreach ($questions as $q) {
        $q['ancrages'] = $anchorsByQ[$q['id']] ?? [];
        $qByDomain[$q['domain_id']][] = $q;
    }

    foreach ($domains as &$d) {
        $d['questions'] = $qByDomain[$d['id']] ?? [];
    }
    return $domains;
}

function getDomain($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM domains WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getQuestion($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$id]);
    $q = $stmt->fetch();
    if (!$q) return null;
    $stmt = $db->prepare("SELECT niveau, description FROM anchors WHERE question_id = ?");
    $stmt->execute([$id]);
    $q['ancrages'] = [];
    foreach ($stmt->fetchAll() as $a) {
        $q['ancrages'][(int)$a['niveau']] = $a['description'];
    }
    return $q;
}

// ----- Scoring -----

function questionValue($response, $maxLevel) {
    if ($response === null || $response === 'na') return null;
    $v = (int)$response;
    if ($v < 1 || $v > $maxLevel) return null;
    return $v;
}

function computeDomainScore($domain, $responses, $maxLevel) {
    $sum = 0; $count = 0;
    foreach ($domain['questions'] as $q) {
        $r = $responses[$q['slug']] ?? null;
        $v = questionValue($r, $maxLevel);
        if ($v !== null) { $sum += $v; $count++; }
    }
    return ['score' => $count > 0 ? $sum / $count : null, 'count' => $count];
}

function computeOverallScore($domains, $responses, $maxLevel) {
    $sum = 0; $scored = 0; $answered = 0; $total = 0;
    foreach ($domains as $d) {
        foreach ($d['questions'] as $q) {
            $total++;
            $r = $responses[$q['slug']] ?? null;
            if ($r !== null) {
                $answered++;
                $v = questionValue($r, $maxLevel);
                if ($v !== null) { $sum += $v; $scored++; }
            }
        }
    }
    return [
        'score' => $scored > 0 ? $sum / $scored : null,
        'count' => $answered,
        'total' => $total,
    ];
}

// ----- Slug helper -----

function slugify($str) {
    $s = mb_strtolower(trim($str), 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s);
    $s = trim($s, '-');
    return $s ?: 'item-' . substr(md5((string)microtime(true)), 0, 6);
}

function uniqueSlug($table, $base) {
    $db = getDB();
    $slug = $base;
    $i = 1;
    $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE slug = ?");
    while (true) {
        $stmt->execute([$slug]);
        if ((int)$stmt->fetchColumn() === 0) return $slug;
        $i++;
        $slug = $base . '-' . $i;
    }
}

function sanitize($s) { return h($s); }
