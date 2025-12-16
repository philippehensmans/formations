<?php
/**
 * API de soumission finale de l'analyse PESTEL
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

// Verifier que l'analyse existe
$stmt = $db->prepare("SELECT * FROM analyse_pestel WHERE participant_id = ?");
$stmt->execute([$participantId]);
$analyse = $stmt->fetch();

if (!$analyse) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune analyse PESTEL trouvee']);
    exit;
}

// Verifier completion minimale
if ($analyse['completion_percent'] < 30) {
    http_response_code(400);
    echo json_encode(['error' => 'Completez au moins 30% de l\'analyse avant de soumettre']);
    exit;
}

// Marquer comme soumis
$stmt = $db->prepare("UPDATE analyse_pestel SET is_submitted = 1, submitted_at = CURRENT_TIMESTAMP WHERE participant_id = ?");
$stmt->execute([$participantId]);

echo json_encode([
    'success' => true,
    'message' => 'Analyse PESTEL soumise avec succes'
]);
?>
