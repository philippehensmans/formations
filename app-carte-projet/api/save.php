<?php
/**
 * API Sauvegarde - Carte d'identite du projet
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Donnees invalides']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$sessionId = validateCurrentSession($db);

if (!$user || !$sessionId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session invalide']);
    exit;
}

// Verifier que la carte existe
$stmt = $db->prepare("SELECT id, is_submitted FROM cartes_projet WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$carte = $stmt->fetch();

if (!$carte) {
    echo json_encode(['success' => false, 'error' => 'Carte projet non trouvee']);
    exit;
}

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
    WHERE user_id = ? AND session_id = ?");

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
    $user['id'],
    $sessionId
]);

echo json_encode(['success' => true, 'completion' => $completion]);
