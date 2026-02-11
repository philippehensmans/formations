<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$analyse = $stmt->fetch();

if ($analyse) {
    echo json_encode([
        'success' => true,
        'data' => [
            'nom_organisation' => $analyse['nom_organisation'],
            'action_communiquer' => $analyse['action_communiquer'],
            'objectif_smart' => $analyse['objectif_smart'],
            'public_prioritaire' => $analyse['public_prioritaire'],
            'message_cle' => $analyse['message_cle'],
            'canaux_data' => json_decode($analyse['canaux_data'], true),
            'calendrier_data' => json_decode($analyse['calendrier_data'], true),
            'ressources_data' => json_decode($analyse['ressources_data'], true),
            'notes' => $analyse['notes'],
            'is_submitted' => (bool)$analyse['is_shared'],
            'updated_at' => $analyse['updated_at']
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'nom_organisation' => '', 'action_communiquer' => '',
            'objectif_smart' => '', 'public_prioritaire' => '',
            'message_cle' => '', 'canaux_data' => [],
            'calendrier_data' => [], 'ressources_data' => [],
            'notes' => '', 'is_submitted' => false, 'updated_at' => null
        ]
    ]);
}
?>
