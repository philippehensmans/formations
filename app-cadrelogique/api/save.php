<?php
/**
 * API de sauvegarde du cadre logique
 */
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isParticipantLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Donnees invalides']);
    exit;
}

$db = getDB();
$participantId = $_SESSION['participant_id'];
$sessionId = $_SESSION['session_id'];

$titreProjet = $input['titre_projet'] ?? '';
$organisation = $input['organisation'] ?? '';
$zoneGeo = $input['zone_geo'] ?? '';
$duree = $input['duree'] ?? '';
$matriceData = json_encode($input['matrice_data'] ?? []);

// Calculer completion
$completionData = $input;
$completionData['matrice_data'] = $input['matrice_data'] ?? [];
$completion = calculateCompletion($completionData);

// Verifier si existe
$stmt = $db->prepare("SELECT id FROM cadre_logique WHERE participant_id = ?");
$stmt->execute([$participantId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("
        UPDATE cadre_logique SET
            titre_projet = ?,
            organisation = ?,
            zone_geo = ?,
            duree = ?,
            matrice_data = ?,
            completion_percent = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE participant_id = ?
    ");
    $stmt->execute([$titreProjet, $organisation, $zoneGeo, $duree, $matriceData, $completion, $participantId]);
} else {
    $stmt = $db->prepare("
        INSERT INTO cadre_logique (participant_id, session_id, titre_projet, organisation, zone_geo, duree, matrice_data, completion_percent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$participantId, $sessionId, $titreProjet, $organisation, $zoneGeo, $duree, $matriceData, $completion]);
}

// Mettre a jour le timestamp du participant
$stmt = $db->prepare("UPDATE participants SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$participantId]);

echo json_encode([
    'success' => true,
    'completion' => $completion
]);
?>
