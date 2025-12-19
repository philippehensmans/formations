<?php
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isParticipantLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$db = getDB();
$participantId = $_SESSION['participant_id'];

$stmt = $db->prepare("SELECT * FROM cartographie WHERE participant_id = ?");
$stmt->execute([$participantId]);
$carto = $stmt->fetch();

if (!$carto) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune cartographie trouvee']);
    exit;
}

if ($carto['completion_percent'] < 30) {
    http_response_code(400);
    echo json_encode(['error' => 'Ajoutez au moins une partie prenante avant de soumettre']);
    exit;
}

$stmt = $db->prepare("UPDATE cartographie SET is_submitted = 1, submitted_at = CURRENT_TIMESTAMP WHERE participant_id = ?");
$stmt->execute([$participantId]);

echo json_encode([
    'success' => true,
    'message' => 'Cartographie soumise avec succes'
]);
?>
