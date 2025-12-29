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

if (!$carto) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune cartographie trouvee']);
    exit;
}

$stmt = $db->prepare("UPDATE cartographie SET is_submitted = 1, submitted_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);

echo json_encode([
    'success' => true,
    'message' => 'Cartographie soumise avec succes'
]);
?>
