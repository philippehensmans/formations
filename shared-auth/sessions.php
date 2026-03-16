<?php
/**
 * Gestion centralisee des sessions de formation
 *
 * Les sessions sont stockees dans la base partagee (shared DB) comme source de verite.
 * Une copie miroir est maintenue dans la base locale de chaque app pour permettre
 * les JOINs SQL avec les tables locales (participants, analyses, etc.).
 *
 * Le parametre $db (base locale) est conserve dans les signatures pour :
 * - la compatibilite avec le code existant
 * - les operations sur les participants (qui restent locaux)
 * - le miroir local des sessions
 */

require_once __DIR__ . '/config.php';

/**
 * Synchroniser les sessions de la base partagee vers la base locale
 * Remplace importMissingSessions() et les sync* functions
 * Appelee une fois par page pour garder le miroir local a jour
 */
function syncLocalSessions($db) {
    static $synced = [];
    $dbPath = _getDbPath($db);
    if (isset($synced[$dbPath])) return;
    $synced[$dbPath] = true;

    $sdb = getSharedDB();

    // Verifier que la table sessions existe localement
    try {
        $db->query("SELECT 1 FROM sessions LIMIT 0");
    } catch (Exception $e) {
        return; // pas de table sessions locale
    }

    // Detecter les colonnes de la table sessions locale
    $localCols = array_column($db->query("PRAGMA table_info(sessions)")->fetchAll(), 'name');
    $hasFormateurId = in_array('formateur_id', $localCols);

    $sessions = $sdb->query("SELECT id, code, nom, formateur_id, is_active, created_at FROM sessions")->fetchAll();
    foreach ($sessions as $s) {
        try {
            // INSERT OR IGNORE pour ne pas ecraser les colonnes app-specifiques (sujet, etc.)
            if ($hasFormateurId) {
                $stmt = $db->prepare("INSERT OR IGNORE INTO sessions (id, code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$s['id'], $s['code'], $s['nom'], $s['formateur_id'], $s['is_active'], $s['created_at']]);
                $stmt = $db->prepare("UPDATE sessions SET code = ?, nom = ?, formateur_id = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$s['code'], $s['nom'], $s['formateur_id'], $s['is_active'], $s['id']]);
            } else {
                $stmt = $db->prepare("INSERT OR IGNORE INTO sessions (id, code, nom, is_active, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$s['id'], $s['code'], $s['nom'], $s['is_active'], $s['created_at']]);
                $stmt = $db->prepare("UPDATE sessions SET code = ?, nom = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$s['code'], $s['nom'], $s['is_active'], $s['id']]);
            }
        } catch (Exception $e) { continue; }
    }

    // Supprimer les sessions locales qui n'existent plus dans la shared DB
    $sharedIds = array_column($sessions, 'id');
    if (!empty($sharedIds)) {
        $placeholders = implode(',', array_fill(0, count($sharedIds), '?'));
        try {
            $db->prepare("DELETE FROM sessions WHERE id NOT IN ($placeholders)")->execute($sharedIds);
        } catch (Exception $e) { /* ignore */ }
    } elseif (empty($sessions)) {
        try {
            $db->exec("DELETE FROM sessions");
        } catch (Exception $e) { /* ignore */ }
    }
}

/**
 * Creer une nouvelle session de formation
 * Cree dans la shared DB puis miroir local
 */
function createSession($db, $code, $nom, $formateurId = null) {
    $sdb = getSharedDB();
    $stmt = $sdb->prepare("INSERT INTO sessions (code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)");
    $stmt->execute([$code, $nom, $formateurId]);
    $id = $sdb->lastInsertId();

    // Miroir local (INSERT OR IGNORE pour preserver les colonnes app-specifiques)
    try {
        $localCols = array_column($db->query("PRAGMA table_info(sessions)")->fetchAll(), 'name');
        if (in_array('formateur_id', $localCols)) {
            $stmt = $db->prepare("INSERT OR IGNORE INTO sessions (id, code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP)");
            $stmt->execute([$id, $code, $nom, $formateurId]);
        } else {
            $stmt = $db->prepare("INSERT OR IGNORE INTO sessions (id, code, nom, is_active, created_at) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)");
            $stmt->execute([$id, $code, $nom]);
        }
    } catch (Exception $e) { /* miroir best-effort */ }

    return $id;
}

/**
 * Generer un code de session unique
 */
if (!function_exists('generateSessionCode')) {
    function generateSessionCode() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
}

/**
 * Recuperer toutes les sessions actives (depuis la shared DB)
 */
function getActiveSessions($db = null) {
    $sdb = getSharedDB();
    return $sdb->query("SELECT * FROM sessions WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();
}

/**
 * Recuperer une session par son code (depuis la shared DB)
 */
function getSessionByCode($db, $code) {
    $sdb = getSharedDB();
    $stmt = $sdb->prepare("SELECT * FROM sessions WHERE code = ? AND is_active = 1");
    $stmt->execute([strtoupper($code)]);
    return $stmt->fetch();
}

/**
 * Recuperer une session par son ID (depuis la shared DB)
 */
function getSessionById($db, $id) {
    $sdb = getSharedDB();
    $stmt = $sdb->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Generer le HTML du dropdown des sessions
 */
function renderSessionDropdown($db, $selectedCode = '', $fieldName = 'session_code', $required = true) {
    $sessions = getActiveSessions($db);
    $html = '<select name="' . h($fieldName) . '" id="' . h($fieldName) . '" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"';
    if ($required) $html .= ' required';
    $html .= '>';
    $html .= '<option value="">-- Choisir une session --</option>';

    foreach ($sessions as $session) {
        $selected = ($session['code'] === $selectedCode) ? ' selected' : '';
        $html .= '<option value="' . h($session['code']) . '"' . $selected . '>';
        $html .= h($session['code']) . ' - ' . h($session['nom']);
        $html .= '</option>';
    }

    $html .= '</select>';
    return $html;
}

/**
 * Compter les participants d'une session (depuis la base locale)
 */
function countSessionParticipants($db, $sessionId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM participants WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    return $stmt->fetchColumn();
}

/**
 * Activer/Desactiver une session
 * Modifie la shared DB puis miroir local
 */
function toggleSession($db, $sessionId) {
    $sdb = getSharedDB();
    $stmt = $sdb->prepare("UPDATE sessions SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$sessionId]);

    // Miroir local
    $session = getSessionById($db, $sessionId);
    if ($session) {
        try {
            $stmt = $db->prepare("UPDATE sessions SET is_active = ? WHERE id = ?");
            $stmt->execute([$session['is_active'], $sessionId]);
        } catch (Exception $e) { /* miroir best-effort */ }
    }

    return true;
}

/**
 * Supprimer une session et ses participants locaux
 * Supprime de la shared DB puis du miroir local
 */
function deleteSession($db, $sessionId) {
    // Supprimer les participants locaux
    try {
        $stmt = $db->prepare("DELETE FROM participants WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    } catch (Exception $e) { /* pas de table participants ou autre */ }

    // Supprimer le miroir local
    try {
        $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
    } catch (Exception $e) { /* miroir best-effort */ }

    // Supprimer de la shared DB (source de verite)
    $sdb = getSharedDB();
    $stmt = $sdb->prepare("DELETE FROM sessions WHERE id = ?");
    return $stmt->execute([$sessionId]);
}

/**
 * Valider que la session courante existe
 * Simplifie car les IDs sont maintenant globaux (shared DB)
 */
function validateCurrentSession($db) {
    $sessionId = $_SESSION['current_session_id'] ?? null;
    if (!$sessionId) return false;

    $session = getSessionById($db, $sessionId);

    if (!$session) {
        unset($_SESSION['current_session_id'], $_SESSION['current_session_code'],
              $_SESSION['current_session_nom'], $_SESSION['participant_id']);
        return false;
    }

    // Mettre a jour les infos en session PHP si necessaire
    $_SESSION['current_session_code'] = $session['code'];
    $_SESSION['current_session_nom'] = $session['nom'];

    return $session['id'];
}

/**
 * S'assurer qu'un participant existe dans la base locale de l'app
 */
function ensureParticipant($db, $sessionId, $user) {
    $userId = $user['id'];
    $prenom = $user['prenom'] ?? $user['username'] ?? '';
    $nom = $user['nom'] ?? '';

    // Verifier si le participant existe deja
    try {
        $stmt = $db->prepare("SELECT id FROM participants WHERE session_id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $userId]);
        $participant = $stmt->fetch();
        if ($participant) {
            $_SESSION['participant_id'] = $participant['id'];
            return $participant['id'];
        }
    } catch (PDOException $e) {
        // Colonne user_id n'existe peut-etre pas
    }

    // Chercher par prenom/nom (certaines apps n'ont pas ces colonnes)
    try {
        $stmt = $db->prepare("SELECT id FROM participants WHERE session_id = ? AND prenom = ? AND nom = ?");
        $stmt->execute([$sessionId, $prenom, $nom]);
        $participant = $stmt->fetch();
        if ($participant) {
            $_SESSION['participant_id'] = $participant['id'];
            return $participant['id'];
        }
    } catch (PDOException $e) {
        // Colonnes prenom/nom n'existent pas dans cette app
    }

    // Creer le participant
    try {
        $stmt = $db->prepare("INSERT INTO participants (session_id, user_id, prenom, nom, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$sessionId, $userId, $prenom, $nom]);
        $id = $db->lastInsertId();
        $_SESSION['participant_id'] = $id;
        return $id;
    } catch (PDOException $e) {
        try {
            $stmt = $db->prepare("INSERT INTO participants (session_id, prenom, nom, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$sessionId, $prenom, $nom]);
            $id = $db->lastInsertId();
            $_SESSION['participant_id'] = $id;
            return $id;
        } catch (PDOException $e2) {
            return null;
        }
    }
}

// =========================================
// FONCTIONS LEGACY (compatibilite)
// =========================================

/**
 * @deprecated Remplace par syncLocalSessions()
 */
function importMissingSessions($db) {
    syncLocalSessions($db);
}

/**
 * @deprecated Plus necessaire avec sessions centralisees
 */
function syncCreateSession($db, $code, $nom, $formateurId) {
    // No-op: la creation passe deja par la shared DB
}

/**
 * @deprecated Plus necessaire avec sessions centralisees
 */
function syncToggleSession($db, $sessionCode) {
    // No-op: le toggle passe deja par la shared DB
}

/**
 * @deprecated Plus necessaire avec sessions centralisees
 */
function syncDeleteSession($db, $sessionCode) {
    // No-op: la suppression passe deja par la shared DB
}

/**
 * Determiner le chemin de la base de donnees d'une connexion PDO SQLite
 */
function _getDbPath($db) {
    try {
        $result = $db->query("PRAGMA database_list")->fetch();
        return $result['file'] ?? null;
    } catch (PDOException $e) {
        return null;
    }
}
