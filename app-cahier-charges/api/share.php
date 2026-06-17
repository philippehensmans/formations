<?php
/**
 * API pour partager / departager le cahier des charges avec le formateur
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$shared = !empty($input['shared']);

$user = getLoggedUser();
$db = getDB();

try {
    $stmt = $db->prepare("UPDATE cahiers SET is_shared = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$shared ? 1 : 0, $user['id'], $_SESSION['current_session_id']]);
    echo json_encode(['success' => true, 'shared' => $shared]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
