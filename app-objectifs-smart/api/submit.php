<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isParticipantLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("UPDATE objectifs_smart SET is_submitted = 1, updated_at = CURRENT_TIMESTAMP WHERE participant_id = ?");
$stmt->execute([$_SESSION['participant_id']]);

echo json_encode(['success' => true]);
