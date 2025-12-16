<?php
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isParticipantLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Donnees invalides']);
    exit;
}

$db = getDB();
$participantId = $_SESSION['participant_id'];
$sessionId = $_SESSION['session_id'];

$completion = calculateCompletion($data);

$stmt = $db->prepare("SELECT id FROM cartographie WHERE participant_id = ?");
$stmt->execute([$participantId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("
        UPDATE cartographie SET
            titre_projet = ?,
            stakeholders_data = ?,
            notes = ?,
            completion_percent = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE participant_id = ?
    ");
    $stmt->execute([
        $data['titre_projet'] ?? '',
        json_encode($data['stakeholders_data'] ?? []),
        $data['notes'] ?? '',
        $completion,
        $participantId
    ]);
} else {
    $stmt = $db->prepare("
        INSERT INTO cartographie (participant_id, session_id, titre_projet, stakeholders_data, notes, completion_percent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $participantId,
        $sessionId,
        $data['titre_projet'] ?? '',
        json_encode($data['stakeholders_data'] ?? []),
        $data['notes'] ?? '',
        $completion
    ]);
}

echo json_encode([
    'success' => true,
    'completion' => $completion
]);
?>
