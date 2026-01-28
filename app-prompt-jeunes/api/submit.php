<?php
/**
 * API Soumission - Prompt Engineering pour Public Jeune
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$user = getLoggedUser();

// Recuperer le numero d'exercice
$input = json_decode(file_get_contents('php://input'), true);
$exerciceNum = isset($input['exercice_num']) ? (int)$input['exercice_num'] : 1;

$stmt = $db->prepare("SELECT id, is_shared FROM travaux WHERE user_id = ? AND session_id = ? AND exercice_num = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id'], $exerciceNum]);
$travail = $stmt->fetch();

if (!$travail) {
    echo json_encode(['success' => false, 'error' => 'Travail non trouve']);
    exit;
}

$stmt = $db->prepare("UPDATE travaux SET is_shared = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ? AND exercice_num = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id'], $exerciceNum]);

echo json_encode(['success' => true]);
