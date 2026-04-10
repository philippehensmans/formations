<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$analyse = $stmt->fetch();

if ($analyse) {
    echo json_encode([
        'success' => true,
        'data' => [
            'nom_organisation' => $analyse['nom_organisation'],
            'section1_data' => json_decode($analyse['section1_data'], true),
            'section2_data' => json_decode($analyse['section2_data'], true),
            'section3_data' => json_decode($analyse['section3_data'], true),
            'section4_data' => json_decode($analyse['section4_data'], true),
            'section5_data' => json_decode($analyse['section5_data'], true),
            'is_submitted' => (bool)$analyse['is_shared'],
            'updated_at' => $analyse['updated_at']
        ]
    ]);
} else {
    $defaults = getDefaultData();
    $defaults['is_submitted'] = false;
    $defaults['updated_at'] = null;
    echo json_encode(['success' => true, 'data' => $defaults]);
}
