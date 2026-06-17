<?php
/**
 * API pour sauvegarder le cahier des charges du participant pour la session courante
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Donnees invalides']);
    exit;
}

$user = getLoggedUser();
$db = getDB();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

$fields = [
    'titre_projet' => $input['titreProjet'] ?? '',
    'date_debut' => $input['dateDebut'] ?? null,
    'date_fin' => $input['dateFin'] ?? null,
    'chef_projet' => $input['chefProjet'] ?? '',
    'sponsor' => $input['sponsor'] ?? '',
    'groupe_travail' => $input['groupeTravail'] ?? '',
    'benevoles' => $input['benevoles'] ?? '',
    'autres_acteurs' => $input['autresActeurs'] ?? '',
    'objectif_strategique' => $input['objectifStrategique'] ?? '',
    'inclusivite' => $input['inclusivite'] ?? '',
    'aspect_digital' => $input['aspectDigital'] ?? '',
    'evolution' => $input['evolution'] ?? '',
    'description_projet' => $input['descriptionProjet'] ?? '',
    'objectif_projet' => $input['objectifProjet'] ?? '',
    'logique_projet' => $input['logiqueProjet'] ?? '',
    'objectif_global' => $input['objectifGlobal'] ?? '',
    'objectifs_specifiques' => json_encode($input['objectifsSpecifiques'] ?? []),
    'resultats' => json_encode($input['resultats'] ?? []),
    'contraintes' => json_encode($input['contraintes'] ?? []),
    'strategies' => json_encode($input['strategies'] ?? []),
    'budget' => $input['budget'] ?? '',
    'ressources_humaines' => $input['ressourcesHumaines'] ?? '',
    'ressources_materielles' => $input['ressourcesMaterialles'] ?? '',
    'etapes' => json_encode($input['etapes'] ?? []),
    'communication' => $input['communication'] ?? ''
];

try {
    $stmt = $db->prepare("SELECT id FROM cahiers WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$userId, $sessionId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $setClauses = [];
        $values = [];
        foreach ($fields as $key => $value) {
            $setClauses[] = "$key = ?";
            $values[] = $value;
        }
        $setClauses[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $existing['id'];

        $sql = "UPDATE cahiers SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $db->prepare($sql)->execute($values);
        $cahierId = $existing['id'];
    } else {
        $columns = array_keys($fields);
        $placeholders = array_fill(0, count($fields), '?');
        $columns[] = 'user_id';
        $placeholders[] = '?';
        $columns[] = 'session_id';
        $placeholders[] = '?';

        $sql = "INSERT INTO cahiers (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $values = array_values($fields);
        $values[] = $userId;
        $values[] = $sessionId;
        $db->prepare($sql)->execute($values);
        $cahierId = $db->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => $cahierId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
