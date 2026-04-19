<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401); echo json_encode(['error' => 'Non authentifié']); exit;
}

$db = getDB();
$userId = getLoggedUser()['id'];
$sessionId = $_SESSION['current_session_id'];

$stmt = $db->prepare("UPDATE evaluations SET is_submitted = 1, submitted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);

if ($stmt->rowCount() === 0) {
    http_response_code(400); echo json_encode(['error' => 'Aucune évaluation à soumettre']); exit;
}

echo json_encode(['success' => true]);
