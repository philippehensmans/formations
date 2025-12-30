<?php
/**
 * API Chargement - Atelier IA
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$user = getLoggedUser();

$stmt = $db->prepare("SELECT * FROM ateliers WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$atelier = $stmt->fetch();

if (!$atelier) {
    echo json_encode(['success' => false, 'error' => 'Atelier non trouve']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'association_nom' => $atelier['association_nom'] ?? '',
        'association_mission' => $atelier['association_mission'] ?? '',
        'post_its' => json_decode($atelier['post_its'] ?? '[]', true) ?: [],
        'themes' => json_decode($atelier['themes'] ?? '[]', true) ?: [],
        'interactions' => json_decode($atelier['interactions'] ?? '[]', true) ?: [],
        'conditions_reussite' => json_decode($atelier['conditions_reussite'] ?? '[]', true) ?: [],
        'notes' => $atelier['notes'] ?? '',
        'completion_percent' => $atelier['completion_percent'] ?? 0,
        'is_submitted' => ($atelier['is_shared'] ?? 0) == 1
    ]
]);
