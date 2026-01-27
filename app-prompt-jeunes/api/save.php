<?php
/**
 * API Sauvegarde - Prompt Engineering pour Public Jeune
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

// Verifier si le travail existe
$stmt = $db->prepare("SELECT id FROM travaux WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$existing = $stmt->fetch();

$data = [
    'organisation_nom' => $input['organisation_nom'] ?? '',
    'organisation_type' => $input['organisation_type'] ?? '',
    'cas_choisi' => $input['cas_choisi'] ?? '',
    'cas_description' => $input['cas_description'] ?? '',
    'prompt_initial' => $input['prompt_initial'] ?? '',
    'resultat_initial' => $input['resultat_initial'] ?? '',
    'analyse_resultat' => $input['analyse_resultat'] ?? '',
    'prompt_ameliore' => $input['prompt_ameliore'] ?? '',
    'resultat_ameliore' => $input['resultat_ameliore'] ?? '',
    'ameliorations_notes' => $input['ameliorations_notes'] ?? '',
    'feedback_binome' => $input['feedback_binome'] ?? '',
    'points_forts' => $input['points_forts'] ?? '',
    'points_ameliorer' => $input['points_ameliorer'] ?? '',
    'feedback_ia' => $input['feedback_ia'] ?? '',
    'synthese_cles' => json_encode($input['synthese_cles'] ?? []),
    'notes' => $input['notes'] ?? ''
];

// Calculer le pourcentage de completion
$completionItems = 0;
$totalItems = 6;
if (!empty($input['prompt_initial'])) $completionItems++;
if (!empty($input['resultat_initial'])) $completionItems++;
if (!empty($input['prompt_ameliore'])) $completionItems++;
if (!empty($input['feedback_binome']) || !empty($input['points_forts'])) $completionItems++;
if (!empty($input['feedback_ia'])) $completionItems++;
if (!empty($input['synthese_cles'])) $completionItems++;
$data['completion_percent'] = round(($completionItems / $totalItems) * 100);

if ($existing) {
    $stmt = $db->prepare("UPDATE travaux SET
        organisation_nom = ?,
        organisation_type = ?,
        cas_choisi = ?,
        cas_description = ?,
        prompt_initial = ?,
        resultat_initial = ?,
        analyse_resultat = ?,
        prompt_ameliore = ?,
        resultat_ameliore = ?,
        ameliorations_notes = ?,
        feedback_binome = ?,
        points_forts = ?,
        points_ameliorer = ?,
        feedback_ia = ?,
        synthese_cles = ?,
        notes = ?,
        completion_percent = ?,
        updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND session_id = ?");
    $stmt->execute([
        $data['organisation_nom'],
        $data['organisation_type'],
        $data['cas_choisi'],
        $data['cas_description'],
        $data['prompt_initial'],
        $data['resultat_initial'],
        $data['analyse_resultat'],
        $data['prompt_ameliore'],
        $data['resultat_ameliore'],
        $data['ameliorations_notes'],
        $data['feedback_binome'],
        $data['points_forts'],
        $data['points_ameliorer'],
        $data['feedback_ia'],
        $data['synthese_cles'],
        $data['notes'],
        $data['completion_percent'],
        $user['id'],
        $_SESSION['current_session_id']
    ]);
} else {
    $stmt = $db->prepare("INSERT INTO travaux (user_id, session_id, organisation_nom, organisation_type, cas_choisi, cas_description, prompt_initial, resultat_initial, analyse_resultat, prompt_ameliore, resultat_ameliore, ameliorations_notes, feedback_binome, points_forts, points_ameliorer, feedback_ia, synthese_cles, notes, completion_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user['id'],
        $_SESSION['current_session_id'],
        $data['organisation_nom'],
        $data['organisation_type'],
        $data['cas_choisi'],
        $data['cas_description'],
        $data['prompt_initial'],
        $data['resultat_initial'],
        $data['analyse_resultat'],
        $data['prompt_ameliore'],
        $data['resultat_ameliore'],
        $data['ameliorations_notes'],
        $data['feedback_binome'],
        $data['points_forts'],
        $data['points_ameliorer'],
        $data['feedback_ia'],
        $data['synthese_cles'],
        $data['notes'],
        $data['completion_percent']
    ]);
}

echo json_encode(['success' => true]);
