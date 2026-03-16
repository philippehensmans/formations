<?php
/**
 * API pour soumettre l'analyse (marquer comme terminée)
 */
require_once __DIR__ . '/../../shared-auth/config.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

// Vérifier l'authentification via shared-auth
if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

// S'assurer que le participant existe dans la base locale
$db_check = getDB();
$sessionId = validateCurrentSession($db_check);
if ($sessionId) {
    ensureParticipant($db_check, $sessionId, getLoggedUser());
}

if (!isset($_SESSION['participant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$participantId = $_SESSION['participant_id'];

try {
    $db = getDB();

    // Vérifier que l'analyse existe
    $stmt = $db->prepare("SELECT id FROM analyses WHERE participant_id = ?");
    $stmt->execute([$participantId]);

    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucune analyse a soumettre']);
        exit;
    }

    // Marquer comme soumise
    $stmt = $db->prepare("
        UPDATE analyses
        SET submitted = 1, submitted_at = datetime('now')
        WHERE participant_id = ?
    ");
    $stmt->execute([$participantId]);

    echo json_encode(['success' => true, 'message' => 'Analyse soumise avec succes']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
