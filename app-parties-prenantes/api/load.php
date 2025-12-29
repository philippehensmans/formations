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

$stmt = $db->prepare("SELECT * FROM cartographie WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$carto = $stmt->fetch();

if ($carto) {
    echo json_encode([
        'success' => true,
        'data' => [
            'titre_projet' => $carto['titre_projet'],
            'stakeholders_data' => json_decode($carto['stakeholders_data'], true),
            'notes' => $carto['notes'],
            'completion' => $carto['completion_percent'],
            'is_submitted' => (bool)$carto['is_submitted'],
            'updated_at' => $carto['updated_at']
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'titre_projet' => '',
            'stakeholders_data' => [],
            'notes' => '',
            'completion' => 0,
            'is_submitted' => false,
            'updated_at' => null
        ]
    ]);
}
?>
