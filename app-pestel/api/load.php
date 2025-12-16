<?php
/**
 * API de chargement de l'analyse PESTEL
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

$stmt = $db->prepare("SELECT * FROM analyse_pestel WHERE participant_id = ?");
$stmt->execute([$participantId]);
$analyse = $stmt->fetch();

if ($analyse) {
    echo json_encode([
        'success' => true,
        'data' => [
            'nom_projet' => $analyse['nom_projet'],
            'participants_analyse' => $analyse['participants_analyse'],
            'zone' => $analyse['zone'],
            'pestel_data' => json_decode($analyse['pestel_data'], true),
            'synthese' => $analyse['synthese'],
            'notes' => $analyse['notes'],
            'completion' => $analyse['completion_percent'],
            'is_submitted' => (bool)$analyse['is_submitted'],
            'updated_at' => $analyse['updated_at']
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'nom_projet' => '',
            'participants_analyse' => '',
            'zone' => '',
            'pestel_data' => getEmptyPestel(),
            'synthese' => '',
            'notes' => '',
            'completion' => 0,
            'is_submitted' => false,
            'updated_at' => null
        ]
    ]);
}
?>
