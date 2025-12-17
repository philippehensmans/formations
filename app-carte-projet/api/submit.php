<?php
/**
 * API Soumission - Carte d'identite du projet
 */
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isParticipantLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT id, is_submitted FROM cartes_projet WHERE participant_id = ?");
$stmt->execute([$_SESSION['participant_id']]);
$carte = $stmt->fetch();

if (!$carte) {
    echo json_encode(['success' => false, 'error' => 'Carte projet non trouvee']);
    exit;
}

if ($carte['is_submitted']) {
    echo json_encode(['success' => false, 'error' => 'Deja soumis']);
    exit;
}

$stmt = $db->prepare("UPDATE cartes_projet SET is_submitted = 1, updated_at = CURRENT_TIMESTAMP WHERE participant_id = ?");
$stmt->execute([$_SESSION['participant_id']]);

echo json_encode(['success' => true]);
