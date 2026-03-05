<?php
/**
 * API pour charger les reponses d'un participant
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$user = getLoggedUser();
$sessionId = $_SESSION['current_session_id'];

try {
    $reponses = getReponses($user['id'], $sessionId);
    echo json_encode(['success' => true, 'reponses' => $reponses]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
}
