<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = getLoggedUser();
$sessionId = $user['session_id'];
$db = getDB();

header('Content-Type: application/json');

// Gérer GET et POST
$action = $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $action;
}

switch ($action) {
    case 'vote':
        handleVote($db, $user, $input);
        break;

    case 'my_votes':
        handleMyVotes($db, $user, $_GET['scenario_id'] ?? null);
        break;

    case 'poll':
        handlePoll($db, $sessionId, $_GET['scenario_id'] ?? null);
        break;

    default:
        echo json_encode(['error' => 'Action non reconnue']);
}

function handleVote($db, $user, $input) {
    $scenarioId = $input['scenario_id'] ?? null;
    $optionNumber = $input['option_number'] ?? null;
    $impact = max(0, min(3, intval($input['impact'] ?? 0)));
    $qualite = max(0, min(5, intval($input['qualite'] ?? 0)));
    $temps = max(0, min(3, intval($input['temps'] ?? 0)));

    if (!$scenarioId || !$optionNumber) {
        echo json_encode(['error' => 'Paramètres manquants']);
        return;
    }

    // Vérifier que le scénario appartient à la session de l'utilisateur
    $stmt = $db->prepare("SELECT id FROM scenarios WHERE id = ? AND session_id = ?");
    $stmt->execute([$scenarioId, $user['session_id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Scénario non trouvé']);
        return;
    }

    // Insérer ou mettre à jour le vote
    $stmt = $db->prepare("
        INSERT INTO votes (scenario_id, participant_id, option_number, impact, qualite, temps, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(scenario_id, participant_id, option_number)
        DO UPDATE SET impact = ?, qualite = ?, temps = ?, updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $scenarioId, $user['id'], $optionNumber, $impact, $qualite, $temps,
        $impact, $qualite, $temps
    ]);

    // Retourner les résultats mis à jour
    $results = calculateAverages($db, $scenarioId);

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
}

function handleMyVotes($db, $user, $scenarioId) {
    if (!$scenarioId) {
        echo json_encode(['error' => 'Scénario non spécifié']);
        return;
    }

    $stmt = $db->prepare("SELECT option_number, impact, qualite, temps FROM votes WHERE scenario_id = ? AND participant_id = ?");
    $stmt->execute([$scenarioId, $user['id']]);
    $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = calculateAverages($db, $scenarioId);

    echo json_encode([
        'votes' => $votes,
        'results' => $results
    ]);
}

function handlePoll($db, $sessionId, $scenarioId) {
    // Vérifier si le scénario existe toujours et est actif
    if ($scenarioId) {
        $stmt = $db->prepare("SELECT id, is_active FROM scenarios WHERE id = ? AND session_id = ?");
        $stmt->execute([$scenarioId, $sessionId]);
        $scenario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$scenario || !$scenario['is_active']) {
            // Le scénario a été désactivé ou supprimé, recharger la page
            echo json_encode(['reload' => true]);
            return;
        }

        $results = calculateAverages($db, $scenarioId);

        echo json_encode([
            'results' => $results,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        // Pas de scénario, vérifier s'il y en a un nouveau
        $stmt = $db->prepare("SELECT id FROM scenarios WHERE session_id = ? AND is_active = 1");
        $stmt->execute([$sessionId]);
        if ($stmt->fetch()) {
            echo json_encode(['reload' => true]);
            return;
        }

        echo json_encode(['waiting' => true]);
    }
}
