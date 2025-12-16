<?php
/**
 * API de soumission finale du cadre logique
 */
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isParticipantLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$db = getDB();
$participantId = $_SESSION['participant_id'];

// Verifier que le cadre existe
$stmt = $db->prepare("SELECT * FROM cadre_logique WHERE participant_id = ?");
$stmt->execute([$participantId]);
$cadre = $stmt->fetch();

if (!$cadre) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucun cadre logique trouve']);
    exit;
}

// Verifier completion minimale
if ($cadre['completion_percent'] < 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Completez au moins 50% du cadre logique avant de soumettre']);
    exit;
}

// Marquer comme soumis
$stmt = $db->prepare("UPDATE cadre_logique SET is_submitted = 1, submitted_at = CURRENT_TIMESTAMP WHERE participant_id = ?");
$stmt->execute([$participantId]);

echo json_encode([
    'success' => true,
    'message' => 'Cadre logique soumis avec succes'
]);
?>
