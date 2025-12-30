<?php
/**
 * API Sauvegarde - Stop Start Continue
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Donnees invalides']);
    exit;
}

$db = getDB();
$user = getLoggedUser();

// Verifier que l'utilisateur a une retrospective
$stmt = $db->prepare("SELECT id, is_shared FROM retrospectives WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$retro = $stmt->fetch();

if (!$retro) {
    echo json_encode(['success' => false, 'error' => 'Retrospective non trouvee']);
    exit;
}

// Calculer completion
$total = 5;
$filled = 0;
if (!empty($input['projet_nom'])) $filled++;
if (!empty($input['projet_contexte'])) $filled++;
if (!empty($input['items_cesser']) && count($input['items_cesser']) > 0) $filled++;
if (!empty($input['items_commencer']) && count($input['items_commencer']) > 0) $filled++;
if (!empty($input['items_continuer']) && count($input['items_continuer']) > 0) $filled++;
$completion = round(($filled / $total) * 100);

// Mise a jour
$stmt = $db->prepare("UPDATE retrospectives SET
    projet_nom = ?,
    projet_contexte = ?,
    stop_items = ?,
    start_items = ?,
    continue_items = ?,
    notes = ?,
    completion_percent = ?,
    updated_at = CURRENT_TIMESTAMP
    WHERE user_id = ? AND session_id = ?");

$stmt->execute([
    $input['projet_nom'] ?? '',
    $input['projet_contexte'] ?? '',
    json_encode($input['items_cesser'] ?? []),
    json_encode($input['items_commencer'] ?? []),
    json_encode($input['items_continuer'] ?? []),
    $input['notes'] ?? '',
    $completion,
    $user['id'],
    $_SESSION['current_session_id']
]);

echo json_encode(['success' => true, 'completion' => $completion]);
