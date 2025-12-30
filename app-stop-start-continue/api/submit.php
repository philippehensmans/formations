<?php
/**
 * API Soumission - Stop Start Continue
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$user = getLoggedUser();

$stmt = $db->prepare("SELECT id, is_shared FROM retrospectives WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$retro = $stmt->fetch();

if (!$retro) {
    echo json_encode(['success' => false, 'error' => 'Retrospective non trouvee']);
    exit;
}

if ($retro['is_shared']) {
    echo json_encode(['success' => false, 'error' => 'Deja soumis']);
    exit;
}

$stmt = $db->prepare("UPDATE retrospectives SET is_shared = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);

echo json_encode(['success' => true]);
