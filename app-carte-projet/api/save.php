<?php
/**
 * API Sauvegarde - Carte d'identite du projet
 */
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isParticipantLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Donnees invalides']);
    exit;
}

$db = getDB();

// Verifier que le participant a une carte
$stmt = $db->prepare("SELECT id, is_submitted FROM cartes_projet WHERE participant_id = ?");
$stmt->execute([$_SESSION['participant_id']]);
$carte = $stmt->fetch();

if (!$carte) {
    echo json_encode(['success' => false, 'error' => 'Carte projet non trouvee']);
    exit;
}

// Permettre les modifications meme apres soumission

// Calculer completion
$fields = ['titre', 'objectifs', 'public_cible', 'territoire', 'calendrier', 'resultats',
           'ressources_humaines', 'ressources_materielles', 'ressources_financieres'];
$total = count($fields) + 1; // +1 pour partenaires
$filled = 0;

foreach ($fields as $field) {
    if (!empty($input[$field])) $filled++;
}

$partenaires = $input['partenaires'] ?? [];
if (!empty($partenaires)) $filled++;

$completion = round(($filled / $total) * 100);

// Mise a jour
$stmt = $db->prepare("UPDATE cartes_projet SET
    titre = ?,
    objectifs = ?,
    public_cible = ?,
    territoire = ?,
    partenaires = ?,
    ressources_humaines = ?,
    ressources_materielles = ?,
    ressources_financieres = ?,
    calendrier = ?,
    resultats = ?,
    notes = ?,
    completion_percent = ?,
    updated_at = CURRENT_TIMESTAMP
    WHERE participant_id = ?");

$stmt->execute([
    $input['titre'] ?? '',
    $input['objectifs'] ?? '',
    $input['public_cible'] ?? '',
    $input['territoire'] ?? '',
    json_encode($partenaires),
    $input['ressources_humaines'] ?? '',
    $input['ressources_materielles'] ?? '',
    $input['ressources_financieres'] ?? '',
    $input['calendrier'] ?? '',
    $input['resultats'] ?? '',
    $input['notes'] ?? '',
    $completion,
    $_SESSION['participant_id']
]);

echo json_encode(['success' => true, 'completion' => $completion]);
