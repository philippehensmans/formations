<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$sessionId = $_SESSION['current_session_id'];

$stmt = $db->prepare("UPDATE objectifs_smart SET is_submitted = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);

echo json_encode(['success' => true]);
