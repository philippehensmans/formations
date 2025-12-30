<?php
/**
 * API Chargement - Stop Start Continue
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$user = getLoggedUser();

$stmt = $db->prepare("SELECT * FROM retrospectives WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$retro = $stmt->fetch();

if (!$retro) {
    echo json_encode(['success' => false, 'error' => 'Retrospective non trouvee']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'projet_nom' => $retro['projet_nom'] ?? '',
        'projet_contexte' => $retro['projet_contexte'] ?? '',
        'items_cesser' => json_decode($retro['stop_items'] ?? '[]', true) ?: [],
        'items_commencer' => json_decode($retro['start_items'] ?? '[]', true) ?: [],
        'items_continuer' => json_decode($retro['continue_items'] ?? '[]', true) ?: [],
        'notes' => $retro['notes'] ?? '',
        'completion_percent' => $retro['completion_percent'] ?? 0,
        'is_submitted' => ($retro['is_shared'] ?? 0) == 1
    ]
]);
