<?php
/**
 * API Soumission - Atelier IA
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$user = getLoggedUser();

$stmt = $db->prepare("SELECT id, is_shared FROM ateliers WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$atelier = $stmt->fetch();

if (!$atelier) {
    echo json_encode(['success' => false, 'error' => 'Atelier non trouve']);
    exit;
}

$stmt = $db->prepare("UPDATE ateliers SET is_shared = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);

echo json_encode(['success' => true]);
