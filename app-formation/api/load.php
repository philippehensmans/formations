<?php
/**
 * API de chargement du cadre logique
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

$stmt = $db->prepare("SELECT * FROM cadre_logique WHERE participant_id = ?");
$stmt->execute([$participantId]);
$cadre = $stmt->fetch();

if ($cadre) {
    echo json_encode([
        'success' => true,
        'data' => [
            'titre_projet' => $cadre['titre_projet'],
            'organisation' => $cadre['organisation'],
            'zone_geo' => $cadre['zone_geo'],
            'duree' => $cadre['duree'],
            'matrice_data' => json_decode($cadre['matrice_data'], true),
            'completion' => $cadre['completion_percent'],
            'is_submitted' => (bool)$cadre['is_submitted'],
            'updated_at' => $cadre['updated_at']
        ]
    ]);
} else {
    // Retourner une structure vide
    echo json_encode([
        'success' => true,
        'data' => [
            'titre_projet' => '',
            'organisation' => '',
            'zone_geo' => '',
            'duree' => '',
            'matrice_data' => getEmptyMatrice(),
            'completion' => 0,
            'is_submitted' => false,
            'updated_at' => null
        ]
    ]);
}
?>
