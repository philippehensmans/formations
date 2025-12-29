<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$user = getLoggedUser();
if (!$user || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$db = getDB();
$sessionId = $_SESSION['current_session_id'];
$userId = $user['id'];

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'add_calcul':
        $useCaseId = $input['use_case_id'] ?? '';
        $frequence = $input['frequence'] ?? 'ponctuel';
        $quantite = max(1, min(100, intval($input['quantite'] ?? 1)));

        if (empty($useCaseId)) {
            echo json_encode(['success' => false, 'error' => 'Cas d\'usage requis']);
            exit;
        }

        $co2Total = calculerCO2($useCaseId, $frequence, $quantite);

        $stmt = $db->prepare("INSERT INTO calculs (session_id, user_id, use_case_id, frequence, quantite, co2_total) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sessionId, $userId, $useCaseId, $frequence, $quantite, $co2Total]);

        echo json_encode([
            'success' => true,
            'calcul_id' => $db->lastInsertId(),
            'co2_total' => $co2Total
        ]);
        break;

    case 'delete_calcul':
        $calculId = intval($input['calcul_id'] ?? 0);

        $stmt = $db->prepare("DELETE FROM calculs WHERE id = ? AND session_id = ? AND user_id = ?");
        $stmt->execute([$calculId, $sessionId, $userId]);

        echo json_encode(['success' => true]);
        break;

    case 'get_stats':
        // Stats pour le formateur
        $stmt = $db->prepare("
            SELECT
                use_case_id,
                COUNT(*) as nb_utilisations,
                SUM(co2_total) as total_co2
            FROM calculs
            WHERE session_id = ?
            GROUP BY use_case_id
            ORDER BY total_co2 DESC
        ");
        $stmt->execute([$sessionId]);
        $byUseCase = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT
                u.id,
                u.prenom,
                u.username,
                SUM(c.co2_total) as total_co2,
                COUNT(c.id) as nb_calculs
            FROM calculs c
            JOIN users u ON c.user_id = u.id
            WHERE c.session_id = ?
            GROUP BY u.id
            ORDER BY total_co2 DESC
        ");
        $stmt->execute([$sessionId]);
        $byUser = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT SUM(co2_total) as total FROM calculs WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $total = $stmt->fetch()['total'] ?? 0;

        echo json_encode([
            'success' => true,
            'by_use_case' => $byUseCase,
            'by_user' => $byUser,
            'total_session' => $total
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
}
