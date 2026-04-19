<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401); echo json_encode(['error' => 'Non authentifié']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !isset($data['responses']) || !is_array($data['responses'])) {
    http_response_code(400); echo json_encode(['error' => 'Données invalides']); exit;
}

$db = getDB();
$userId = getLoggedUser()['id'];
$sessionId = $_SESSION['current_session_id'];
$maxLevel = getMaxLevel();

$clean = [];
foreach ($data['responses'] as $slug => $val) {
    if (!is_string($slug) || !preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) continue;
    if ($val === 'na') { $clean[$slug] = 'na'; continue; }
    if (is_numeric($val)) {
        $v = (int)$val;
        if ($v >= 1 && $v <= $maxLevel) $clean[$slug] = $v;
    }
}

$stmt = $db->prepare("SELECT id, is_submitted FROM evaluations WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$row = $stmt->fetch();

if ($row && $row['is_submitted']) {
    http_response_code(403); echo json_encode(['error' => 'Évaluation déjà soumise']); exit;
}

if ($row) {
    $db->prepare("UPDATE evaluations SET responses = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?")
       ->execute([json_encode($clean), $userId, $sessionId]);
} else {
    $db->prepare("INSERT INTO evaluations (user_id, session_id, responses) VALUES (?, ?, ?)")
       ->execute([$userId, $sessionId, json_encode($clean)]);
}

echo json_encode(['success' => true, 'count' => count($clean)]);
