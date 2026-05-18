<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

$stmt = $db->prepare("SELECT id FROM canevas WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
if (!$stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucun canevas trouvé']);
    exit;
}

$stmt = $db->prepare("UPDATE canevas SET is_shared = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);

echo json_encode(['success' => true, 'message' => 'Canevas soumis avec succès']);
