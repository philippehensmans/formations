<?php
/**
 * API Soumission - Carte d'identite du projet
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$sessionId = validateCurrentSession($db);

if (!$user || !$sessionId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session invalide']);
    exit;
}

$stmt = $db->prepare("SELECT id, is_submitted FROM cartes_projet WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$carte = $stmt->fetch();

if (!$carte) {
    echo json_encode(['success' => false, 'error' => 'Carte projet non trouvee']);
    exit;
}

if ($carte['is_submitted']) {
    echo json_encode(['success' => false, 'error' => 'Deja soumis']);
    exit;
}

$stmt = $db->prepare("UPDATE cartes_projet SET is_submitted = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);

echo json_encode(['success' => true]);
