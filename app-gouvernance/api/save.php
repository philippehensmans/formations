<?php
/**
 * API de sauvegarde - Évaluation de gouvernance
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data) || !isset($data['responses']) || !is_array($data['responses'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

// Valider : 1/2/3 pour scale, 'yes'/'no' pour boolean
$cleanResponses = [];
foreach ($data['responses'] as $qid => $val) {
    if (!is_string($qid) || !preg_match('/^[a-zA-Z0-9_]+$/', $qid)) continue;
    if (is_numeric($val)) {
        $v = (int)$val;
        if ($v >= 1 && $v <= 3) $cleanResponses[$qid] = $v;
    } elseif ($val === 'yes' || $val === 'no') {
        $cleanResponses[$qid] = $val;
    }
}

$stmt = $db->prepare("SELECT id, is_submitted FROM evaluations WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing && $existing['is_submitted']) {
    http_response_code(403);
    echo json_encode(['error' => 'Évaluation déjà soumise']);
    exit;
}

if ($existing) {
    $stmt = $db->prepare("UPDATE evaluations SET responses = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
    $stmt->execute([json_encode($cleanResponses), $userId, $sessionId]);
} else {
    $stmt = $db->prepare("INSERT INTO evaluations (user_id, session_id, responses) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $sessionId, json_encode($cleanResponses)]);
}

echo json_encode(['success' => true, 'count' => count($cleanResponses)]);
