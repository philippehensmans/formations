<?php
/**
 * API de chargement - Évaluation de gouvernance
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

$stmt = $db->prepare("SELECT * FROM evaluations WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$evaluation = $stmt->fetch();

if ($evaluation) {
    echo json_encode([
        'success' => true,
        'data' => [
            'responses' => json_decode($evaluation['responses'], true) ?: (object)[],
            'is_submitted' => (bool)$evaluation['is_submitted'],
            'submitted_at' => $evaluation['submitted_at'],
            'updated_at' => $evaluation['updated_at'],
        ],
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'responses' => (object)[],
            'is_submitted' => false,
            'submitted_at' => null,
            'updated_at' => null,
        ],
    ]);
}
