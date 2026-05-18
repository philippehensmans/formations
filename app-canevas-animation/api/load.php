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

$stmt = $db->prepare("SELECT * FROM canevas WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$row = $stmt->fetch();

if ($row) {
    echo json_encode([
        'success' => true,
        'data' => json_decode($row['data'] ?? '{}', true) ?: [],
        'is_submitted' => (bool)$row['is_shared'],
        'updated_at' => $row['updated_at']
    ]);
} else {
    echo json_encode(['success' => true, 'data' => [], 'is_submitted' => false, 'updated_at' => null]);
}
