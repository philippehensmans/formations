<?php
/**
 * API de sauvegarde de l'analyse PESTEL
 */
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

// Calculer completion
$completion = calculateCompletion($data);

// Mettre a jour ou inserer
$stmt = $db->prepare("SELECT id FROM analyse_pestel WHERE participant_id = ?");
$stmt->execute([$participantId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("
        UPDATE analyse_pestel SET
            nom_projet = ?,
            participants_analyse = ?,
            zone = ?,
            pestel_data = ?,
            synthese = ?,
            notes = ?,
            completion_percent = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE participant_id = ?
    ");
    $stmt->execute([
        $data['nom_projet'] ?? '',
        $data['participants_analyse'] ?? '',
        $data['zone'] ?? '',
        json_encode($data['pestel_data'] ?? getEmptyPestel()),
        $data['synthese'] ?? '',
        $data['notes'] ?? '',
        $completion,
        $participantId
    ]);
} else {
    $stmt = $db->prepare("
        INSERT INTO analyse_pestel (participant_id, session_id, nom_projet, participants_analyse, zone, pestel_data, synthese, notes, completion_percent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $participantId,
        $sessionId,
        $data['nom_projet'] ?? '',
        $data['participants_analyse'] ?? '',
        $data['zone'] ?? '',
        json_encode($data['pestel_data'] ?? getEmptyPestel()),
        $data['synthese'] ?? '',
        $data['notes'] ?? '',
        $completion
    ]);
}

echo json_encode([
    'success' => true,
    'completion' => $completion
]);
?>
