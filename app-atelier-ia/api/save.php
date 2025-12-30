<?php
/**
 * API Sauvegarde - Atelier IA
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

// Verifier si l'atelier existe
$stmt = $db->prepare("SELECT id FROM ateliers WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$existing = $stmt->fetch();

$data = [
    'association_nom' => $input['association_nom'] ?? '',
    'association_mission' => $input['association_mission'] ?? '',
    'post_its' => json_encode($input['post_its'] ?? []),
    'themes' => json_encode($input['themes'] ?? []),
    'interactions' => json_encode($input['interactions'] ?? []),
    'conditions_reussite' => json_encode($input['conditions_reussite'] ?? []),
    'notes' => $input['notes'] ?? ''
];

// Calculer le pourcentage de completion
$completionItems = 0;
$totalItems = 4;
if (!empty($input['post_its'])) $completionItems++;
if (!empty($input['themes'])) $completionItems++;
if (!empty($input['interactions'])) $completionItems++;
if (!empty($input['conditions_reussite'])) $completionItems++;
$data['completion_percent'] = round(($completionItems / $totalItems) * 100);

if ($existing) {
    $stmt = $db->prepare("UPDATE ateliers SET
        association_nom = ?,
        association_mission = ?,
        post_its = ?,
        themes = ?,
        interactions = ?,
        conditions_reussite = ?,
        notes = ?,
        completion_percent = ?,
        updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND session_id = ?");
    $stmt->execute([
        $data['association_nom'],
        $data['association_mission'],
        $data['post_its'],
        $data['themes'],
        $data['interactions'],
        $data['conditions_reussite'],
        $data['notes'],
        $data['completion_percent'],
        $user['id'],
        $_SESSION['current_session_id']
    ]);
} else {
    $stmt = $db->prepare("INSERT INTO ateliers (user_id, session_id, association_nom, association_mission, post_its, themes, interactions, conditions_reussite, notes, completion_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user['id'],
        $_SESSION['current_session_id'],
        $data['association_nom'],
        $data['association_mission'],
        $data['post_its'],
        $data['themes'],
        $data['interactions'],
        $data['conditions_reussite'],
        $data['notes'],
        $data['completion_percent']
    ]);
}

echo json_encode(['success' => true]);
