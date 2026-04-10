<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Donnees invalides']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

$nomOrg = $data['nom_organisation'] ?? '';
$s1 = $data['section1_data'] ?? [];
$s2 = $data['section2_data'] ?? [];
$s3 = $data['section3_data'] ?? [];
$s4 = $data['section4_data'] ?? [];
$s5 = $data['section5_data'] ?? [];

$completion = calculateCompletion($data);

$stmt = $db->prepare("SELECT id FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("UPDATE analyses SET nom_organisation = ?, section1_data = ?, section2_data = ?, section3_data = ?, section4_data = ?, section5_data = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$nomOrg, json_encode($s1), json_encode($s2), json_encode($s3), json_encode($s4), json_encode($s5), $userId, $sessionId]);
} else {
    $stmt = $db->prepare("INSERT INTO analyses (user_id, session_id, nom_organisation, section1_data, section2_data, section3_data, section4_data, section5_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $nomOrg, json_encode($s1), json_encode($s2), json_encode($s3), json_encode($s4), json_encode($s5)]);
}

echo json_encode(['success' => true, 'completion' => $completion]);
