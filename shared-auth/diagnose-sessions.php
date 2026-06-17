<?php
/**
 * Diagnostic des sessions (LECTURE SEULE)
 *
 * Scanne la base locale de chaque application et signale les symptomes
 * d'une eventuelle corruption des session_id (cf. bug de migration naive) :
 *  - sessions locales absentes de la base partagee (promotion en attente)
 *  - sessions a remapper (code present dans le partage mais id different)
 *  - donnees orphelines (session_id inexistant)
 *  - concentration anormale des participants sur une seule session
 *
 * Ce script NE MODIFIE RIEN (uniquement des SELECT).
 *
 * Usage : php shared-auth/diagnose-sessions.php
 *    ou  : acceder via navigateur (necessite acces super-admin)
 */

require_once __DIR__ . '/config.php';

if (php_sapi_name() !== 'cli') {
    if (!isLoggedIn() || !isSuperAdmin()) {
        die('Acces refuse. Super-admin requis.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$sdb = getSharedDB();
$sharedById = [];
$sharedByCode = [];
foreach ($sdb->query("SELECT id, code, nom FROM sessions")->fetchAll() as $s) {
    $sharedById[(int)$s['id']] = $s;
    $sharedByCode[strtoupper($s['code'])] = (int)$s['id'];
}

echo "=== Diagnostic des sessions (lecture seule) ===\n";
echo "Sessions dans la base partagee : " . count($sharedById) . "\n\n";

$baseDir = dirname(__DIR__);
$appDirs = glob($baseDir . '/app-*', GLOB_ONLYDIR);
$suspects = [];

foreach ($appDirs as $appDir) {
    $appName = basename($appDir);
    $dataDir = $appDir . '/data';
    if (!is_dir($dataDir)) continue;

    $dbFiles = array_merge(glob($dataDir . '/*.db') ?: [], glob($dataDir . '/*.sqlite') ?: []);
    foreach ($dbFiles as $dbPath) {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            continue;
        }

        // Table sessions locale ?
        try {
            $localSessions = $db->query("SELECT id, code FROM sessions")->fetchAll();
        } catch (Exception $e) {
            continue;
        }
        if (empty($localSessions)) continue;

        $localIds = [];
        $localOnly = [];
        $mismatched = [];
        foreach ($localSessions as $ls) {
            $code = strtoupper($ls['code'] ?? '');
            $lid = (int)$ls['id'];
            $localIds[$lid] = $code;
            if ($code === '') continue;
            if (!isset($sharedByCode[$code])) {
                $localOnly[] = $code;
            } elseif ($sharedByCode[$code] !== $lid) {
                $mismatched[] = "$code (local#$lid vs partage#{$sharedByCode[$code]})";
            }
        }

        // Tables de donnees referencant session_id
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('sessions','participants','sqlite_sequence')")->fetchAll(PDO::FETCH_COLUMN);
        $report = [];
        $orphans = [];
        foreach ($tables as $t) {
            $cols = array_column($db->query("PRAGMA table_info(" . $t . ")")->fetchAll(), 'name');
            if (!in_array('session_id', $cols)) continue;
            $hasUser = in_array('user_id', $cols);

            $sql = "SELECT session_id, COUNT(*) as n" . ($hasUser ? ", COUNT(DISTINCT user_id) as users" : "") . " FROM " . $t . " GROUP BY session_id ORDER BY n DESC";
            $rows = $db->query($sql)->fetchAll();
            if (empty($rows)) continue;
            foreach ($rows as $r) {
                $sid = (int)$r['session_id'];
                if (!isset($localIds[$sid]) && !isset($sharedById[$sid])) {
                    $orphans[] = "$t:session_id=$sid";
                }
            }
            $report[$t] = $rows;
        }

        echo "----------------------------------------\n";
        echo "[$appName] " . basename($dbPath) . "\n";
        echo "  Sessions locales : " . count($localSessions) . "\n";
        if ($localOnly)   echo "  [i] Absentes du partage (seront promues proprement) : " . implode(', ', $localOnly) . "\n";
        if ($mismatched)  echo "  [i] A remapper (migration en attente, traitee proprement) : " . implode('; ', $mismatched) . "\n";
        if ($orphans)     echo "  [!] Donnees orphelines (session_id inexistant) : " . implode('; ', $orphans) . "\n";

        foreach ($report as $t => $rows) {
            $total = array_sum(array_column($rows, 'n'));
            echo "  Table '$t' : $total ligne(s) repartie(s) sur " . count($rows) . " session(s)\n";
            foreach ($rows as $r) {
                $sid = (int)$r['session_id'];
                $code = $localIds[$sid] ?? ($sharedById[$sid]['code'] ?? '??');
                $u = isset($r['users']) ? " / {$r['users']} participant(s)" : "";
                echo "       session_id=$sid ($code) : {$r['n']} ligne(s)$u\n";
            }
            // Heuristique de concentration : beaucoup de participants concentres sur 1 seule session
            if (isset($rows[0]['users'])) {
                $totalUsers = array_sum(array_column($rows, 'users'));
                if (count($rows) >= 1 && (int)$rows[0]['users'] >= 5 && count($rows) == 1 && count($localSessions) > 1) {
                    $suspects[] = "$appName / $t : " . (int)$rows[0]['users'] . " participants tous sur 1 seule session alors que l'app a " . count($localSessions) . " sessions";
                }
            }
        }
        if ($orphans) {
            $suspects[] = "$appName : donnees orphelines (" . implode(', ', $orphans) . ")";
        }
        echo "\n";
    }
}

echo "=== Synthese ===\n";
if (empty($suspects)) {
    echo "Aucun symptome evident de corruption detecte.\n";
} else {
    echo "Points a investiguer :\n";
    foreach ($suspects as $s) echo "  - $s\n";
}
echo "\nLecture :\n";
echo "- [i] = normal si la migration n'est pas encore passee ; le correctif collision-safe les traite proprement.\n";
echo "- [!] orphelines = a investiguer.\n";
echo "- Si une table concentre anormalement tous les participants sur UNE session alors\n";
echo "  qu'ils etaient repartis sur plusieurs => signe de la corruption (version naive deja passee).\n";
echo "  Comparez la repartition ci-dessus avec ce que vous savez du nombre reel de participants par session.\n";
