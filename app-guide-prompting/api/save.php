<?php
/**
 * API Sauvegarde - Guide de Prompting
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

$stmt = $db->prepare("SELECT id FROM guides WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$existing = $stmt->fetch();

$data = [
    'organisation_nom' => $input['organisation_nom'] ?? '',
    'organisation_mission' => $input['organisation_mission'] ?? '',
    'current_step' => (int)($input['current_step'] ?? 1),
    'tasks' => json_encode($input['tasks'] ?? []),
    'experimentations' => json_encode($input['experimentations'] ?? []),
    'templates' => json_encode($input['templates'] ?? []),
    'guide_intro' => $input['guide_intro'] ?? ''
];

// Calculer completion
$completion = 0;
$taskCount = count($input['tasks'] ?? []);
if ($taskCount > 0) $completion += 25;
if (!empty($input['experimentations'])) $completion += 25;
if (!empty($input['templates'])) $completion += 25;
if (!empty($input['guide_intro'])) $completion += 25;
$data['completion_percent'] = $completion;

if ($existing) {
    $stmt = $db->prepare("UPDATE guides SET
        organisation_nom = ?, organisation_mission = ?, current_step = ?,
        tasks = ?, experimentations = ?, templates = ?, guide_intro = ?,
        completion_percent = ?, updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND session_id = ?");
    $stmt->execute([
        $data['organisation_nom'], $data['organisation_mission'], $data['current_step'],
        $data['tasks'], $data['experimentations'], $data['templates'], $data['guide_intro'],
        $data['completion_percent'], $user['id'], $_SESSION['current_session_id']
    ]);
} else {
    $stmt = $db->prepare("INSERT INTO guides (user_id, session_id, organisation_nom, organisation_mission, current_step, tasks, experimentations, templates, guide_intro, completion_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user['id'], $_SESSION['current_session_id'],
        $data['organisation_nom'], $data['organisation_mission'], $data['current_step'],
        $data['tasks'], $data['experimentations'], $data['templates'], $data['guide_intro'],
        $data['completion_percent']
    ]);
}

echo json_encode(['success' => true]);
