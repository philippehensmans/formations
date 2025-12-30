<?php
/**
 * API Chargement - Guide de Prompting
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$user = getLoggedUser();

$stmt = $db->prepare("SELECT * FROM guides WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$guide = $stmt->fetch();

if (!$guide) {
    echo json_encode(['success' => false, 'error' => 'Guide non trouve']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'organisation_nom' => $guide['organisation_nom'] ?? '',
        'organisation_mission' => $guide['organisation_mission'] ?? '',
        'current_step' => (int)($guide['current_step'] ?? 1),
        'tasks' => json_decode($guide['tasks'] ?? '[]', true) ?: [],
        'experimentations' => json_decode($guide['experimentations'] ?? '[]', true) ?: [],
        'templates' => json_decode($guide['templates'] ?? '[]', true) ?: [],
        'guide_intro' => $guide['guide_intro'] ?? '',
        'is_submitted' => ($guide['is_shared'] ?? 0) == 1
    ]
]);
