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
            'nom_projet' => $analyse['nom_projet'],
            'description_projet' => $analyse['description_projet'],
            'contexte' => $analyse['contexte'],
            'contraintes' => $analyse['contraintes'],
            'objectifs_data' => json_decode($analyse['objectifs_data'], true),
            'phases_data' => json_decode($analyse['phases_data'], true),
            'checkpoints_data' => json_decode($analyse['checkpoints_data'], true),
            'lessons_data' => json_decode($analyse['lessons_data'], true),
            'synthese' => $analyse['synthese'],
            'notes' => $analyse['notes'],
            'is_submitted' => (bool)$analyse['is_shared'],
            'updated_at' => $analyse['updated_at']
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'nom_projet' => '', 'description_projet' => '',
            'contexte' => '', 'contraintes' => '',
            'objectifs_data' => [], 'phases_data' => [],
            'checkpoints_data' => [], 'lessons_data' => [],
            'synthese' => '', 'notes' => '',
            'is_submitted' => false, 'updated_at' => null
        ]
    ]);
}
?>
