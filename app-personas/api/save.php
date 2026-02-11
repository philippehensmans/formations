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

$nomOrg = $data['nom_organisation'] ?? '';
$contexte = $data['contexte'] ?? '';
$stakeholders = $data['stakeholders_data'] ?? [];
$personas = $data['personas_data'] ?? [];
$synthese = $data['synthese'] ?? '';
$notes = $data['notes'] ?? '';

$completion = calculateCompletion($nomOrg, $stakeholders, $personas, $synthese);

$stmt = $db->prepare("SELECT id FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("UPDATE analyses SET nom_organisation = ?, contexte = ?, stakeholders_data = ?, personas_data = ?, synthese = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$nomOrg, $contexte, json_encode($stakeholders), json_encode($personas), $synthese, $notes, $userId, $sessionId]);
} else {
    $stmt = $db->prepare("INSERT INTO analyses (user_id, session_id, nom_organisation, contexte, stakeholders_data, personas_data, synthese, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $nomOrg, $contexte, json_encode($stakeholders), json_encode($personas), $synthese, $notes]);
}

echo json_encode(['success' => true, 'completion' => $completion]);

function calculateCompletion($nomOrg, $stakeholders, $personas, $synthese) {
    $total = 0; $filled = 0;

    $total += 10;
    if (!empty(trim($nomOrg))) $filled += 10;

    $total += 30;
    $validStakeholders = 0;
    if (is_array($stakeholders)) {
        foreach ($stakeholders as $s) {
            if (!empty(trim($s['nom'] ?? ''))) $validStakeholders++;
        }
    }
    if ($validStakeholders >= 5) $filled += 30;
    elseif ($validStakeholders >= 3) $filled += 20;
    elseif ($validStakeholders >= 1) $filled += 10;

    $total += 40;
    $validPersonas = 0;
    if (is_array($personas)) {
        foreach ($personas as $p) {
            if (!empty(trim($p['prenom'] ?? '')) && !empty(trim($p['situation'] ?? ''))) $validPersonas++;
        }
    }
    if ($validPersonas >= 3) $filled += 40;
    elseif ($validPersonas >= 2) $filled += 30;
    elseif ($validPersonas >= 1) $filled += 15;

    $total += 20;
    if (!empty(trim($synthese))) $filled += 20;

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}
?>
