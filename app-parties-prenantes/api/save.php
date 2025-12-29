<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Donnees invalides']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

$completion = calculateCompletion($data);

$stmt = $db->prepare("SELECT id FROM cartographie WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("
        UPDATE cartographie SET
            titre_projet = ?,
            stakeholders_data = ?,
            notes = ?,
            completion_percent = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND session_id = ?
    ");
    $stmt->execute([
        $data['titre_projet'] ?? '',
        json_encode($data['stakeholders_data'] ?? []),
        $data['notes'] ?? '',
        $completion,
        $userId,
        $sessionId
    ]);
} else {
    $stmt = $db->prepare("
        INSERT INTO cartographie (user_id, session_id, titre_projet, stakeholders_data, notes, completion_percent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $sessionId,
        $data['titre_projet'] ?? '',
        json_encode($data['stakeholders_data'] ?? []),
        $data['notes'] ?? '',
        $completion
    ]);
}

echo json_encode([
    'success' => true,
    'completion' => $completion
]);

/**
 * Calcule le pourcentage de completion
 */
function calculateCompletion($data) {
    $stakeholders = $data['stakeholders_data'] ?? [];
    if (empty($stakeholders)) return 0;

    $filled = 0;
    $total = count($stakeholders) * 4; // nom, interet, influence, strategie

    foreach ($stakeholders as $s) {
        if (!empty($s['nom'])) $filled++;
        if (isset($s['interet']) && $s['interet'] !== '') $filled++;
        if (isset($s['influence']) && $s['influence'] !== '') $filled++;
        if (!empty($s['strategie'])) $filled++;
    }

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}
?>
