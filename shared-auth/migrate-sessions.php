<?php
/**
 * Script de migration unique : consolider les sessions existantes dans la shared DB
 *
 * A executer une seule fois apres le deploiement de la centralisation des sessions.
 * Ce script :
 * 1. Collecte toutes les sessions de toutes les bases locales des apps
 * 2. Les insere dans la shared DB (sans doublons, en se basant sur le code)
 * 3. Met a jour les IDs dans les bases locales pour correspondre aux IDs de la shared DB
 *
 * Usage : php migrate-sessions.php
 *    ou : acceder via navigateur (necessite acces super-admin)
 */

require_once __DIR__ . '/config.php';

// Si acces via navigateur, verifier super-admin
if (php_sapi_name() !== 'cli') {
    if (!isLoggedIn() || !isSuperAdmin()) {
        die('Acces refuse. Super-admin requis.');
    }
    echo '<pre>';
}

$sdb = getSharedDB();
$baseDir = dirname(__DIR__);
$appDirs = glob($baseDir . '/app-*', GLOB_ONLYDIR);

echo "=== Migration des sessions vers la shared DB ===\n\n";

// Phase 1 : Collecter toutes les sessions uniques (par code) depuis les bases locales
$allSessions = [];
$appDatabases = [];

foreach ($appDirs as $appDir) {
    $appName = basename($appDir);
    $dataDir = $appDir . '/data';
    if (!is_dir($dataDir)) continue;

    $dbFiles = array_merge(
        glob($dataDir . '/*.db') ?: [],
        glob($dataDir . '/*.sqlite') ?: []
    );
    if (empty($dbFiles)) continue;

    foreach ($dbFiles as $dbPath) {
        try {
            $appDb = new PDO('sqlite:' . $dbPath);
            $appDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $appDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Verifier que la table sessions existe
            $check = $appDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sessions'");
            if (!$check->fetch()) continue;

            $sessions = $appDb->query("SELECT * FROM sessions")->fetchAll();
            echo "[$appName] " . basename($dbPath) . " : " . count($sessions) . " session(s)\n";

            $appDatabases[] = ['name' => $appName, 'db' => $appDb, 'path' => $dbPath];

            foreach ($sessions as $s) {
                $code = strtoupper($s['code'] ?? '');
                if (empty($code)) continue;

                if (!isset($allSessions[$code])) {
                    $allSessions[$code] = $s;
                }
            }
        } catch (PDOException $e) {
            echo "[$appName] Erreur: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nTotal sessions uniques (par code) : " . count($allSessions) . "\n\n";

// Phase 2 : Inserer dans la shared DB
$inserted = 0;
$skipped = 0;

foreach ($allSessions as $code => $s) {
    $stmt = $sdb->prepare("SELECT id FROM sessions WHERE code = ?");
    $stmt->execute([$code]);
    if ($stmt->fetch()) {
        $skipped++;
        continue;
    }

    $stmt = $sdb->prepare("INSERT INTO sessions (code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $code,
        $s['nom'] ?? $s['name'] ?? 'Session ' . $code,
        $s['formateur_id'] ?? null,
        $s['is_active'] ?? $s['active'] ?? 1,
        $s['created_at'] ?? date('Y-m-d H:i:s')
    ]);
    $inserted++;
    echo "  Inseree: $code - " . ($s['nom'] ?? $s['name'] ?? '?') . "\n";
}

echo "\nPhase 2 terminee : $inserted inseree(s), $skipped deja presente(s)\n\n";

// Phase 3 : Mettre a jour les IDs dans les bases locales pour correspondre a la shared DB
echo "=== Phase 3 : Mise a jour des IDs locaux ===\n\n";

// Construire la map code -> shared ID
$sharedSessions = $sdb->query("SELECT id, code FROM sessions")->fetchAll();
$codeToSharedId = [];
foreach ($sharedSessions as $s) {
    $codeToSharedId[$s['code']] = $s['id'];
}

foreach ($appDatabases as $appInfo) {
    $appDb = $appInfo['db'];
    $appName = $appInfo['name'];

    echo "[$appName]\n";

    $localSessions = $appDb->query("SELECT id, code FROM sessions")->fetchAll();

    // Trouver quelles tables reference session_id
    $tables = $appDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('sessions', 'sqlite_sequence')")->fetchAll(PDO::FETCH_COLUMN);
    $refTables = [];
    foreach ($tables as $table) {
        $cols = array_column($appDb->query("PRAGMA table_info(" . $table . ")")->fetchAll(), 'name');
        if (in_array('session_id', $cols)) {
            $refTables[] = $table;
        }
    }

    foreach ($localSessions as $local) {
        $code = strtoupper($local['code'] ?? '');
        if (empty($code) || !isset($codeToSharedId[$code])) continue;

        $sharedId = $codeToSharedId[$code];
        $localId = $local['id'];

        if ($localId == $sharedId) continue; // Deja le bon ID

        echo "  $code : local ID $localId -> shared ID $sharedId\n";

        // Mettre a jour les references dans toutes les tables
        foreach ($refTables as $table) {
            try {
                $stmt = $appDb->prepare("UPDATE " . $table . " SET session_id = ? WHERE session_id = ?");
                $stmt->execute([$sharedId, $localId]);
                $affected = $stmt->rowCount();
                if ($affected > 0) {
                    echo "    $table : $affected ligne(s) mise(s) a jour\n";
                }
            } catch (PDOException $e) {
                echo "    $table : Erreur - " . $e->getMessage() . "\n";
            }
        }

        // Mettre a jour la session elle-meme
        try {
            $appDb->prepare("DELETE FROM sessions WHERE id = ?")->execute([$localId]);
            $appDb->prepare("INSERT OR REPLACE INTO sessions (id, code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$sharedId, $code, $local['nom'] ?? $local['name'] ?? '', $local['formateur_id'] ?? null, $local['is_active'] ?? $local['active'] ?? 1, $local['created_at'] ?? date('Y-m-d H:i:s')]);
        } catch (PDOException $e) {
            echo "    sessions : Erreur - " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Migration terminee ===\n";

if (php_sapi_name() !== 'cli') {
    echo '</pre>';
}
