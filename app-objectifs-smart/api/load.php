<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$sessionId = $_SESSION['current_session_id'];

$stmt = $db->prepare("SELECT * FROM objectifs_smart WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
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
        'is_submitted' => ($data['is_submitted'] ?? 0) == 1
    ]
]);
