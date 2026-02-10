<?php
/**
 * API de sauvegarde de l'analyse Journey Mapping
 */
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

$nomOrganisation = $data['nom_organisation'] ?? '';
$objectifAudit = $data['objectif_audit'] ?? '';
$publicCible = $data['public_cible'] ?? '';
$journeyData = $data['journey_data'] ?? [];
$synthese = $data['synthese'] ?? '';
$recommandations = $data['recommandations'] ?? '';
$notes = $data['notes'] ?? '';

// Calculer completion
$completion = calculateCompletion($nomOrganisation, $objectifAudit, $publicCible, $journeyData, $synthese, $recommandations);

// Mettre a jour ou inserer
$stmt = $db->prepare("SELECT id FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("
        UPDATE analyses SET
            nom_organisation = ?,
            objectif_audit = ?,
            public_cible = ?,
            journey_data = ?,
            synthese = ?,
            recommandations = ?,
            notes = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND session_id = ?
    ");
    $stmt->execute([
        $nomOrganisation,
        $objectifAudit,
        $publicCible,
        json_encode($journeyData),
        $synthese,
        $recommandations,
        $notes,
        $userId,
        $sessionId
    ]);
} else {
    $stmt = $db->prepare("
        INSERT INTO analyses (user_id, session_id, nom_organisation, objectif_audit, public_cible, journey_data, synthese, recommandations, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $sessionId,
        $nomOrganisation,
        $objectifAudit,
        $publicCible,
        json_encode($journeyData),
        $synthese,
        $recommandations,
        $notes
    ]);
}

echo json_encode([
    'success' => true,
    'completion' => $completion
]);

/**
 * Calcule le pourcentage de completion
 */
function calculateCompletion($nomOrg, $objectif, $public, $steps, $synthese, $recommandations) {
    $total = 0;
    $filled = 0;

    // Champs metadonnees (5 points chacun)
    $total += 5;
    if (!empty(trim($nomOrg))) $filled += 5;

    $total += 5;
    if (!empty(trim($objectif))) $filled += 5;

    $total += 5;
    if (!empty(trim($public))) $filled += 5;

    // Etapes du parcours (50 points si au moins 3 etapes avec contenu)
    $total += 50;
    $filledSteps = 0;
    if (is_array($steps)) {
        foreach ($steps as $step) {
            if (!empty(trim($step['titre'] ?? ''))) {
                $filledSteps++;
            }
        }
    }
    if ($filledSteps >= 5) $filled += 50;
    elseif ($filledSteps >= 3) $filled += 35;
    elseif ($filledSteps >= 1) $filled += 20;

    // Synthese (20 points)
    $total += 20;
    if (!empty(trim($synthese))) $filled += 20;

    // Recommandations (15 points)
    $total += 15;
    if (!empty(trim($recommandations))) $filled += 15;

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}
?>
