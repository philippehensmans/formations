<?php
/**
 * API Sauvegarde - Carte d'identite du Projet
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
$sessionId = $_SESSION['current_session_id'];

// Verifier que l'utilisateur a une fiche
$stmt = $db->prepare("SELECT id, is_shared FROM fiches WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$fiche = $stmt->fetch();

if (!$fiche) {
    echo json_encode(['success' => false, 'error' => 'Fiche non trouvee']);
    exit;
}

// Si deja soumis, on ne peut que marquer comme soumis
if ($fiche['is_shared'] && !isset($input['submit'])) {
    echo json_encode(['success' => false, 'error' => 'Fiche deja soumise']);
    exit;
}

// Si c'est une soumission
if (isset($input['submit']) && $input['submit']) {
    $stmt = $db->prepare("UPDATE fiches SET is_shared = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$fiche['id']]);
    echo json_encode(['success' => true]);
    exit;
}

// Calculer completion
$fields = ['titre', 'objectifs', 'public_cible', 'territoire', 'calendrier', 'resultats'];
$filled = 0;
foreach ($fields as $field) {
    if (!empty($input[$field])) $filled++;
}
if (!empty($input['partenaires']) && count($input['partenaires']) > 0) $filled++;
if (!empty($input['ressources_humaines']) || !empty($input['ressources_materielles']) || !empty($input['ressources_financieres'])) $filled++;

$total = count($fields) + 2; // +2 pour partenaires et ressources
$completion = round(($filled / $total) * 100);

// Mise a jour
$stmt = $db->prepare("UPDATE fiches SET
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
    json_encode($input['partenaires'] ?? []),
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
