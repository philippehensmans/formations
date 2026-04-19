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
        ensureDefaultScale($db);
    }
    return $db;
}

function ensureDefaultScale($db) {
    $count = (int)$db->query("SELECT COUNT(*) FROM scale_levels")->fetchColumn();
    if ($count > 0) return;
    $stmt = $db->prepare("INSERT INTO scale_levels (niveau, cle, label, description) VALUES (?, ?, ?, ?)");
    $default = [
        [1, 'niveau_1', 'Niveau 1', ''],
        [2, 'niveau_2', 'Niveau 2', ''],
        [3, 'niveau_3', 'Niveau 3', ''],
        [4, 'niveau_4', 'Niveau 4', ''],
    ];
    foreach ($default as $l) $stmt->execute($l);
    if (getConfig('na_enabled', null) === null) {
        setConfigValue($db, 'na_enabled', '1');
        setConfigValue($db, 'na_label', 'Non applicable / Ne sais pas');
        setConfigValue($db, 'na_description', 'À cocher sans pénalisation.');
    }
}

function setConfigValue($db, $key, $value) {
    $db->prepare("INSERT OR REPLACE INTO config (key, value) VALUES (?, ?)")->execute([$key, $value]);
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

/**
 * Importe une config (échelle + N/A + domaines + questions + ancrages) depuis un tableau PHP.
 * Format attendu : JSON original {echelleMaturite:{niveaux:[], optionNonApplicable:{}}, domaines:[...]}
 * OU format plat {scale:[...], na:{...}, domains:[...]}.
 * Retourne ['inserted' => [...], 'errors' => [...]].
 */
function importConfig($data, $replace = false) {
    $db = getDB();
    $result = ['domains' => 0, 'questions' => 0, 'anchors' => 0, 'errors' => []];

    $db->beginTransaction();
    try {
        if ($replace) {
            $db->exec("DELETE FROM anchors");
            $db->exec("DELETE FROM questions");
            $db->exec("DELETE FROM domains");
            $db->exec("DELETE FROM scale_levels");
        }

        // Normaliser l'échelle
        $scale = [];
        if (isset($data['echelleMaturite']['niveaux'])) {
            foreach ($data['echelleMaturite']['niveaux'] as $n) {
                $scale[] = [
                    'niveau' => (int)($n['id'] ?? $n['niveau'] ?? 0),
                    'cle' => $n['cle'] ?? '',
                    'label' => $n['label'] ?? '',
                    'description' => $n['description'] ?? '',
                ];
            }
        } elseif (isset($data['scale'])) {
            foreach ($data['scale'] as $n) {
                $scale[] = [
                    'niveau' => (int)($n['niveau'] ?? $n['id'] ?? 0),
                    'cle' => $n['cle'] ?? '',
                    'label' => $n['label'] ?? '',
                    'description' => $n['description'] ?? '',
                ];
            }
        }
        if ($scale) {
            $db->exec("DELETE FROM scale_levels");
            $stmt = $db->prepare("INSERT INTO scale_levels (niveau, cle, label, description) VALUES (?, ?, ?, ?)");
            foreach ($scale as $s) {
                if ($s['niveau'] > 0) $stmt->execute([$s['niveau'], $s['cle'], $s['label'], $s['description']]);
            }
        }

        // N/A
        $na = $data['echelleMaturite']['optionNonApplicable'] ?? $data['na'] ?? null;
        if ($na) {
            $enabled = $na['activee'] ?? $na['enabled'] ?? true;
            setConfig('na_enabled', $enabled ? '1' : '0');
            setConfig('na_label', $na['label'] ?? 'Non applicable / Ne sais pas');
            setConfig('na_description', $na['description'] ?? '');
        }

        // Meta
        if (isset($data['meta']['titre'])) setConfig('app_title', $data['meta']['titre']);
        if (isset($data['meta']['contexte'])) setConfig('app_subtitle', $data['meta']['contexte']);

        // Domaines
        $domains = $data['domaines'] ?? $data['domains'] ?? [];
        $sd = $db->prepare("INSERT INTO domains (slug, titre, description, ordre) VALUES (?, ?, ?, ?)");
        $sq = $db->prepare("INSERT INTO questions (domain_id, slug, intitule, texte, aide, ordre) VALUES (?, ?, ?, ?, ?, ?)");
        $sa = $db->prepare("INSERT OR REPLACE INTO anchors (question_id, niveau, description) VALUES (?, ?, ?)");

        foreach ($domains as $dIdx => $d) {
            $slug = $d['id'] ?? $d['slug'] ?? slugify($d['titre'] ?? $d['title'] ?? ('domain-' . ($dIdx + 1)));
            $slug = uniqueSlug('domains', $slug);
            $titre = $d['titre'] ?? $d['title'] ?? '';
            $desc = $d['description'] ?? '';
            $ordre = (int)($d['ordre'] ?? $d['order'] ?? ($dIdx + 1));
            $sd->execute([$slug, $titre, $desc, $ordre]);
            $domainId = (int)$db->lastInsertId();
            $result['domains']++;

            foreach (($d['questions'] ?? []) as $qIdx => $q) {
                $qSlug = $q['id'] ?? $q['slug'] ?? slugify($q['intitule'] ?? ('q-' . ($qIdx + 1)));
                $qSlug = uniqueSlug('questions', $qSlug);
                $intitule = $q['intitule'] ?? '';
                $texte = $q['question'] ?? $q['texte'] ?? '';
                $aide = $q['aide'] ?? null;
                $qOrdre = (int)($q['ordre'] ?? $q['order'] ?? ($qIdx + 1));
                $sq->execute([$domainId, $qSlug, $intitule, $texte, $aide, $qOrdre]);
                $questionId = (int)$db->lastInsertId();
                $result['questions']++;

                foreach (($q['ancrages'] ?? []) as $niveau => $anchorDesc) {
                    $niveau = (int)$niveau;
                    if ($niveau > 0) {
                        $sa->execute([$questionId, $niveau, (string)$anchorDesc]);
                        $result['anchors']++;
                    }
                }
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $result['errors'][] = $e->getMessage();
    }
    return $result;
}

function exportConfig() {
    $scale = getScaleLevels();
    $na = getNaSettings();
    $domains = getAllDomains();
    $out = [
        'meta' => [
            'titre' => getConfig('app_title', APP_NAME),
            'contexte' => getConfig('app_subtitle', ''),
            'exportedAt' => date('c'),
        ],
        'echelleMaturite' => [
            'niveaux' => array_map(fn($s) => [
                'id' => (int)$s['niveau'],
                'cle' => $s['cle'],
                'label' => $s['label'],
                'description' => $s['description'],
            ], $scale),
            'optionNonApplicable' => [
                'activee' => $na['enabled'],
                'label' => $na['label'],
                'description' => $na['description'],
            ],
        ],
        'domaines' => array_map(function($d) {
            return [
                'id' => $d['slug'],
                'titre' => $d['titre'],
                'description' => $d['description'],
                'ordre' => (int)$d['ordre'],
                'questions' => array_map(function($q) {
                    $out = [
                        'id' => $q['slug'],
                        'intitule' => $q['intitule'],
                        'question' => $q['texte'],
                    ];
                    if (!empty($q['aide'])) $out['aide'] = $q['aide'];
                    $out['ancrages'] = array_map('strval', $q['ancrages'] ?? []);
                    return $out;
                }, $d['questions']),
            ];
        }, $domains),
    ];
    return $out;
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
