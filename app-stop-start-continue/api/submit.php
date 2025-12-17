<?php
/**
 * API Soumission - Stop Start Continue
 */
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isParticipantLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT id, is_submitted FROM retrospectives WHERE participant_id = ?");
$stmt->execute([$_SESSION['participant_id']]);
$retro = $stmt->fetch();

if (!$retro) {
    echo json_encode(['success' => false, 'error' => 'Retrospective non trouvee']);
    exit;
}

if ($retro['is_submitted']) {
    echo json_encode(['success' => false, 'error' => 'Deja soumis']);
    exit;
}

$stmt = $db->prepare("UPDATE retrospectives SET is_submitted = 1, updated_at = CURRENT_TIMESTAMP WHERE participant_id = ?");
$stmt->execute([$_SESSION['participant_id']]);

echo json_encode(['success' => true]);
