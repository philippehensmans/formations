<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401); echo json_encode(['error' => 'Non authentifié']); exit;
}

$db = getDB();
$userId = getLoggedUser()['id'];
$sessionId = $_SESSION['current_session_id'];

$stmt = $db->prepare("SELECT * FROM evaluations WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$e = $stmt->fetch();

echo json_encode([
    'success' => true,
    'data' => [
        'responses' => $e ? (json_decode($e['responses'], true) ?: (object)[]) : (object)[],
        'is_submitted' => $e ? (bool)$e['is_submitted'] : false,
        'submitted_at' => $e['submitted_at'] ?? null,
        'updated_at' => $e['updated_at'] ?? null,
    ],
]);
