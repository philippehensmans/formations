<?php
/**
 * API Chargement - Stop Start Continue
 */
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isParticipantLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT * FROM retrospectives WHERE participant_id = ?");
$stmt->execute([$_SESSION['participant_id']]);
$retro = $stmt->fetch();

if (!$retro) {
    echo json_encode(['success' => false, 'error' => 'Retrospective non trouvee']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'projet_nom' => $retro['projet_nom'],
        'projet_contexte' => $retro['projet_contexte'],
        'items_cesser' => json_decode($retro['items_cesser'], true) ?: [],
        'items_commencer' => json_decode($retro['items_commencer'], true) ?: [],
        'items_continuer' => json_decode($retro['items_continuer'], true) ?: [],
        'notes' => $retro['notes'],
        'completion_percent' => $retro['completion_percent'],
        'is_submitted' => $retro['is_submitted'] == 1
    ]
]);
