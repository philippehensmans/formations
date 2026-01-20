<?php
/**
 * Gestion des sessions de formation par application
 *
 * Chaque application a ses propres sessions stockees dans sa base locale
 * Ce fichier fournit des fonctions communes pour gerer les sessions
 */

require_once __DIR__ . '/config.php';

/**
 * Creer une nouvelle session de formation
 */
function createSession($db, $code, $nom, $formateurId = null) {
    $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_id, is_active, created_at) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)");
    $stmt->execute([$code, $nom, $formateurId]);
    return $db->lastInsertId();
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
 * Recuperer toutes les sessions actives
 */
function getActiveSessions($db) {
    return $db->query("SELECT * FROM sessions WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();
}

/**
 * Recuperer une session par son code
 */
function getSessionByCode($db, $code) {
    $stmt = $db->prepare("SELECT * FROM sessions WHERE code = ? AND is_active = 1");
    $stmt->execute([strtoupper($code)]);
    return $stmt->fetch();
}

/**
 * Recuperer une session par son ID
 */
function getSessionById($db, $id) {
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
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
 * Compter les participants d'une session
 */
function countSessionParticipants($db, $sessionId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM participants WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    return $stmt->fetchColumn();
}

/**
 * Activer/Desactiver une session
 */
function toggleSession($db, $sessionId) {
    $stmt = $db->prepare("UPDATE sessions SET is_active = NOT is_active WHERE id = ?");
    return $stmt->execute([$sessionId]);
}

/**
 * Supprimer une session et ses donnees
 */
function deleteSession($db, $sessionId) {
    // Supprimer d'abord les participants
    $stmt = $db->prepare("DELETE FROM participants WHERE session_id = ?");
    $stmt->execute([$sessionId]);

    // Puis la session
    $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
    return $stmt->execute([$sessionId]);
}
