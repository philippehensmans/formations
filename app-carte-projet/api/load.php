<?php
/**
 * API Chargement - Carte d'identite du projet
 */
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isParticipantLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT * FROM cartes_projet WHERE participant_id = ?");
$stmt->execute([$_SESSION['participant_id']]);
$carte = $stmt->fetch();

if (!$carte) {
    echo json_encode(['success' => false, 'error' => 'Carte projet non trouvee']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'titre' => $carte['titre'],
        'objectifs' => $carte['objectifs'],
        'public_cible' => $carte['public_cible'],
        'territoire' => $carte['territoire'],
        'partenaires' => json_decode($carte['partenaires'], true) ?: [],
        'ressources_humaines' => $carte['ressources_humaines'],
        'ressources_materielles' => $carte['ressources_materielles'],
        'ressources_financieres' => $carte['ressources_financieres'],
        'calendrier' => $carte['calendrier'],
        'resultats' => $carte['resultats'],
        'notes' => $carte['notes'],
        'completion_percent' => $carte['completion_percent'],
        'is_submitted' => $carte['is_submitted'] == 1
    ]
]);
