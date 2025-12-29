<?php
/**
 * API de soumission finale de l'analyse PESTEL
 */
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

// Verifier que l'analyse existe
$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$analyse = $stmt->fetch();

if (!$analyse) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune analyse PESTEL trouvee']);
    exit;
}

// Marquer comme soumis (is_shared = 1)
$stmt = $db->prepare("UPDATE analyses SET is_shared = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);

echo json_encode([
    'success' => true,
    'message' => 'Analyse PESTEL soumise avec succes'
]);
?>
