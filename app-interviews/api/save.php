<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401); echo json_encode(['error' => 'Non authentifié']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400); echo json_encode(['error' => 'Données invalides']); exit;
}

$db = getDB();
$userId = getLoggedUser()['id'];
$sessionId = (int)$_SESSION['current_session_id'];

$stmt = $db->prepare("SELECT id, is_submitted FROM fiches WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing && $existing['is_submitted']) {
    http_response_code(403); echo json_encode(['error' => 'Fiche déjà soumise']); exit;
}

$sujet    = mb_substr(trim($data['sujet']    ?? ''), 0, 2000);
$message1 = mb_substr(trim($data['message1'] ?? ''), 0, 1000);
$message2 = mb_substr(trim($data['message2'] ?? ''), 0, 1000);
$message3 = mb_substr(trim($data['message3'] ?? ''), 0, 1000);
$anecdote = mb_substr(trim($data['anecdote'] ?? ''), 0, 2000);
$a_eviter = mb_substr(trim($data['a_eviter'] ?? ''), 0, 1000);
$submit   = !empty($data['submit']) ? 1 : 0;

if ($existing) {
    $db->prepare("UPDATE fiches SET sujet=?, message1=?, message2=?, message3=?, anecdote=?, a_eviter=?, is_submitted=?, updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND session_id=?")
       ->execute([$sujet, $message1, $message2, $message3, $anecdote, $a_eviter, $submit, $userId, $sessionId]);
} else {
    $db->prepare("INSERT INTO fiches (user_id, session_id, sujet, message1, message2, message3, anecdote, a_eviter, is_submitted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([$userId, $sessionId, $sujet, $message1, $message2, $message3, $anecdote, $a_eviter, $submit]);
}

echo json_encode(['success' => true]);
