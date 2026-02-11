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
$action = $data['action_communiquer'] ?? '';
$objectif = $data['objectif_smart'] ?? '';
$publicPrio = $data['public_prioritaire'] ?? '';
$messageCle = $data['message_cle'] ?? '';
$canaux = $data['canaux_data'] ?? [];
$calendrier = $data['calendrier_data'] ?? [];
$ressources = $data['ressources_data'] ?? [];
$notes = $data['notes'] ?? '';

$completion = calculateCompletion($nomOrg, $action, $objectif, $publicPrio, $messageCle, $canaux, $calendrier, $ressources);

$stmt = $db->prepare("SELECT id FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("UPDATE analyses SET nom_organisation = ?, action_communiquer = ?, objectif_smart = ?, public_prioritaire = ?, message_cle = ?, canaux_data = ?, calendrier_data = ?, ressources_data = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$nomOrg, $action, $objectif, $publicPrio, $messageCle, json_encode($canaux), json_encode($calendrier), json_encode($ressources), $notes, $userId, $sessionId]);
} else {
    $stmt = $db->prepare("INSERT INTO analyses (user_id, session_id, nom_organisation, action_communiquer, objectif_smart, public_prioritaire, message_cle, canaux_data, calendrier_data, ressources_data, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $nomOrg, $action, $objectif, $publicPrio, $messageCle, json_encode($canaux), json_encode($calendrier), json_encode($ressources), $notes]);
}

echo json_encode(['success' => true, 'completion' => $completion]);

function calculateCompletion($nomOrg, $action, $objectif, $publicPrio, $messageCle, $canaux, $calendrier, $ressources) {
    $total = 0; $filled = 0;

    // Organisation (5pts)
    $total += 5;
    if (!empty(trim($nomOrg))) $filled += 5;

    // Action a communiquer (15pts)
    $total += 15;
    if (!empty(trim($action))) $filled += 15;

    // Objectif SMART (15pts)
    $total += 15;
    if (!empty(trim($objectif))) $filled += 15;

    // Public prioritaire (15pts)
    $total += 15;
    if (!empty(trim($publicPrio))) $filled += 15;

    // Message cle (15pts)
    $total += 15;
    if (!empty(trim($messageCle))) $filled += 15;

    // Canaux (15pts)
    $total += 15;
    $validCanaux = 0;
    if (is_array($canaux)) {
        foreach ($canaux as $c) {
            if (!empty(trim($c['canal'] ?? ''))) $validCanaux++;
        }
    }
    if ($validCanaux >= 3) $filled += 15;
    elseif ($validCanaux >= 2) $filled += 10;
    elseif ($validCanaux >= 1) $filled += 5;

    // Calendrier (10pts)
    $total += 10;
    $validEtapes = 0;
    if (is_array($calendrier)) {
        foreach ($calendrier as $e) {
            if (!empty(trim($e['etape'] ?? ''))) $validEtapes++;
        }
    }
    if ($validEtapes >= 3) $filled += 10;
    elseif ($validEtapes >= 2) $filled += 7;
    elseif ($validEtapes >= 1) $filled += 4;

    // Ressources (10pts)
    $total += 10;
    $validRes = 0;
    if (is_array($ressources)) {
        foreach ($ressources as $r) {
            if (!empty(trim($r['qui'] ?? '')) || !empty(trim($r['quoi'] ?? ''))) $validRes++;
        }
    }
    if ($validRes >= 2) $filled += 10;
    elseif ($validRes >= 1) $filled += 5;

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}
?>
