<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isParticipantLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM objectifs_smart WHERE participant_id = ?");
$stmt->execute([$_SESSION['participant_id']]);
$data = $stmt->fetch();

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Donnees non trouvees']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'etape_courante' => $data['etape_courante'],
        'etape1_analyses' => json_decode($data['etape1_analyses'], true) ?: [],
        'etape2_reformulations' => json_decode($data['etape2_reformulations'], true) ?: [],
        'etape3_creations' => json_decode($data['etape3_creations'], true) ?: [],
        'completion_percent' => $data['completion_percent'],
        'is_submitted' => $data['is_submitted'] == 1
    ]
]);
