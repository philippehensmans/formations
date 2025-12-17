<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['participant_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$participantId = $_SESSION['participant_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Donnees invalides']);
    exit;
}

try {
    $db = getDB();

    // Vérifier que l'enregistrement existe
    $stmt = $db->prepare("SELECT id FROM mesure_impact WHERE participant_id = ?");
    $stmt->execute([$participantId]);
    $exists = $stmt->fetch();

    if (!$exists) {
        // Créer l'enregistrement
        $stmt = $db->prepare("INSERT INTO mesure_impact (participant_id, session_id) VALUES (?, ?)");
        $stmt->execute([$participantId, $_SESSION['session_id']]);
    }

    // Construire la requête de mise à jour
    $updates = [];
    $params = [];

    if (isset($data['etape_courante'])) {
        $updates[] = "etape_courante = ?";
        $params[] = $data['etape_courante'];
    }

    if (isset($data['etape1_classification'])) {
        $updates[] = "etape1_classification = ?";
        $params[] = $data['etape1_classification'];
    }

    if (isset($data['etape2_theorie_changement'])) {
        $updates[] = "etape2_theorie_changement = ?";
        $params[] = $data['etape2_theorie_changement'];
    }

    if (isset($data['etape3_indicateurs'])) {
        $updates[] = "etape3_indicateurs = ?";
        $params[] = $data['etape3_indicateurs'];
    }

    if (isset($data['etape4_plan_collecte'])) {
        $updates[] = "etape4_plan_collecte = ?";
        $params[] = $data['etape4_plan_collecte'];
    }

    if (isset($data['etape5_synthese'])) {
        $updates[] = "etape5_synthese = ?";
        $params[] = $data['etape5_synthese'];
    }

    if (isset($data['is_submitted'])) {
        $updates[] = "is_submitted = ?";
        $params[] = $data['is_submitted'];
        if ($data['is_submitted']) {
            $updates[] = "submitted_at = CURRENT_TIMESTAMP";
        }
    }

    // Calculer le pourcentage de completion
    $completion = 0;
    if (!empty($data['etape1_classification'])) {
        $e1 = json_decode($data['etape1_classification'], true);
        if (!empty($e1['completed'])) $completion += 20;
    }
    if (!empty($data['etape2_theorie_changement'])) {
        $e2 = json_decode($data['etape2_theorie_changement'], true);
        if (!empty($e2['completed'])) $completion += 20;
    }
    if (!empty($data['etape3_indicateurs'])) {
        $e3 = json_decode($data['etape3_indicateurs'], true);
        if (!empty($e3['completed'])) $completion += 20;
    }
    if (!empty($data['etape4_plan_collecte'])) {
        $e4 = json_decode($data['etape4_plan_collecte'], true);
        if (!empty($e4['completed'])) $completion += 20;
    }
    if (!empty($data['etape5_synthese'])) {
        $e5 = json_decode($data['etape5_synthese'], true);
        if (!empty($e5['completed'])) $completion += 20;
    }

    $updates[] = "completion_percent = ?";
    $params[] = $completion;

    $updates[] = "updated_at = CURRENT_TIMESTAMP";

    $params[] = $participantId;

    $sql = "UPDATE mesure_impact SET " . implode(", ", $updates) . " WHERE participant_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'completion' => $completion]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
