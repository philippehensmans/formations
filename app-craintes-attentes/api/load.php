<?php
/**
 * API pour charger les avis d'un participant
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$user = getLoggedUser();
$db = getDB();

try {
    $avis = getAvisParticipant($user['id'], $_SESSION['current_session_id']);
    echo json_encode(['success' => true, 'avis' => $avis]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
