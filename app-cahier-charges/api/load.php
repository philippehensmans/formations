<?php
/**
 * API pour charger le cahier des charges d'un participant pour la session courante
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$user = getLoggedUser();
$db = getDB();
$sessionId = $_SESSION['current_session_id'];

try {
    $stmt = $db->prepare("SELECT * FROM cahiers WHERE user_id = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$user['id'], $sessionId]);
    $cahier = $stmt->fetch();

    if (!$cahier) {
        echo json_encode(['success' => true, 'data' => null]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $cahier['id'],
            'titreProjet' => $cahier['titre_projet'],
            'dateDebut' => $cahier['date_debut'],
            'dateFin' => $cahier['date_fin'],
            'chefProjet' => $cahier['chef_projet'],
            'sponsor' => $cahier['sponsor'],
            'groupeTravail' => $cahier['groupe_travail'],
            'benevoles' => $cahier['benevoles'],
            'autresActeurs' => $cahier['autres_acteurs'],
            'objectifStrategique' => $cahier['objectif_strategique'],
            'inclusivite' => $cahier['inclusivite'],
            'aspectDigital' => $cahier['aspect_digital'],
            'evolution' => $cahier['evolution'],
            'descriptionProjet' => $cahier['description_projet'],
            'objectifProjet' => $cahier['objectif_projet'],
            'logiqueProjet' => $cahier['logique_projet'],
            'objectifGlobal' => $cahier['objectif_global'],
            'objectifsSpecifiques' => json_decode($cahier['objectifs_specifiques'] ?? '[]'),
            'resultats' => json_decode($cahier['resultats'] ?? '[]'),
            'contraintes' => json_decode($cahier['contraintes'] ?? '[]'),
            'strategies' => json_decode($cahier['strategies'] ?? '[]'),
            'budget' => $cahier['budget'],
            'ressourcesHumaines' => $cahier['ressources_humaines'],
            'ressourcesMaterialles' => $cahier['ressources_materielles'],
            'etapes' => json_decode($cahier['etapes'] ?? '[]'),
            'communication' => $cahier['communication'],
            'isShared' => (bool)$cahier['is_shared']
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
