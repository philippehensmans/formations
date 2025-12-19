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

if ($carto) {
    echo json_encode([
        'success' => true,
        'data' => [
            'titre_projet' => $carto['titre_projet'],
            'stakeholders_data' => json_decode($carto['stakeholders_data'], true),
            'notes' => $carto['notes'],
            'completion' => $carto['completion_percent'],
            'is_submitted' => (bool)$carto['is_submitted'],
            'updated_at' => $carto['updated_at']
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'titre_projet' => '',
            'stakeholders_data' => [],
            'notes' => '',
            'completion' => 0,
            'is_submitted' => false,
            'updated_at' => null
        ]
    ]);
}
?>
