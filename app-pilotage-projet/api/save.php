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

$nomProjet = $data['nom_projet'] ?? '';
$description = $data['description_projet'] ?? '';
$contexte = $data['contexte'] ?? '';
$contraintes = $data['contraintes'] ?? '';
$objectifs = $data['objectifs_data'] ?? [];
$phases = $data['phases_data'] ?? [];
$checkpoints = $data['checkpoints_data'] ?? [];
$lessons = $data['lessons_data'] ?? [];
$synthese = $data['synthese'] ?? '';
$notes = $data['notes'] ?? '';

$completion = calculateCompletion($nomProjet, $description, $contexte, $objectifs, $phases, $checkpoints, $synthese);

$stmt = $db->prepare("SELECT id FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("UPDATE analyses SET nom_projet = ?, description_projet = ?, contexte = ?, contraintes = ?, objectifs_data = ?, phases_data = ?, checkpoints_data = ?, lessons_data = ?, synthese = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$nomProjet, $description, $contexte, $contraintes, json_encode($objectifs), json_encode($phases), json_encode($checkpoints), json_encode($lessons), $synthese, $notes, $userId, $sessionId]);
} else {
    $stmt = $db->prepare("INSERT INTO analyses (user_id, session_id, nom_projet, description_projet, contexte, contraintes, objectifs_data, phases_data, checkpoints_data, lessons_data, synthese, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $nomProjet, $description, $contexte, $contraintes, json_encode($objectifs), json_encode($phases), json_encode($checkpoints), json_encode($lessons), $synthese, $notes]);
}

echo json_encode(['success' => true, 'completion' => $completion]);

function calculateCompletion($nomProjet, $description, $contexte, $objectifs, $phases, $checkpoints, $synthese = '') {
    $total = 0; $filled = 0;

    // Nom du projet (10pts)
    $total += 10;
    if (!empty(trim($nomProjet))) $filled += 10;

    // Description (10pts)
    $total += 10;
    if (!empty(trim($description))) $filled += 10;

    // Contexte (5pts)
    $total += 5;
    if (!empty(trim($contexte))) $filled += 5;

    // Objectifs (20pts)
    $total += 20;
    $validObj = 0;
    if (is_array($objectifs)) {
        foreach ($objectifs as $o) {
            if (!empty(trim($o['titre'] ?? ''))) $validObj++;
        }
    }
    if ($validObj >= 3) $filled += 20;
    elseif ($validObj >= 2) $filled += 15;
    elseif ($validObj >= 1) $filled += 8;

    // Phases (25pts)
    $total += 25;
    $validPhases = 0;
    $totalTasks = 0;
    if (is_array($phases)) {
        foreach ($phases as $p) {
            if (!empty(trim($p['nom'] ?? ''))) $validPhases++;
            if (is_array($p['taches'] ?? null)) {
                foreach ($p['taches'] as $t) {
                    if (!empty(trim($t['titre'] ?? ''))) $totalTasks++;
                }
            }
        }
    }
    if ($validPhases >= 3 && $totalTasks >= 5) $filled += 25;
    elseif ($validPhases >= 2 && $totalTasks >= 3) $filled += 18;
    elseif ($validPhases >= 1) $filled += 10;

    // Checkpoints (15pts)
    $total += 15;
    $validCP = 0;
    if (is_array($checkpoints)) {
        foreach ($checkpoints as $cp) {
            if (!empty(trim($cp['description'] ?? '')) || !empty(trim($cp['type'] ?? ''))) $validCP++;
        }
    }
    if ($validCP >= 3) $filled += 15;
    elseif ($validCP >= 2) $filled += 10;
    elseif ($validCP >= 1) $filled += 5;

    // Synthese (15pts)
    $total += 15;
    if (!empty(trim($synthese))) $filled += 15;

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}
?>
