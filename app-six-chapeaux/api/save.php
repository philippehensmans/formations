<?php
/**
 * API pour sauvegarder un avis
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['chapeau']) || empty($data['contenu'])) {
    echo json_encode(['success' => false, 'error' => 'Donnees manquantes']);
    exit;
}

$user = getLoggedUser();
$db = getDB();
$chapeaux = getChapeaux();

// Valider le chapeau
if (!isset($chapeaux[$data['chapeau']])) {
    echo json_encode(['success' => false, 'error' => 'Chapeau invalide']);
    exit;
}

try {
    if (!empty($data['id'])) {
        // Mise a jour d'un avis existant
        $stmt = $db->prepare("UPDATE avis SET chapeau = ?, contenu = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND session_id = ?");
        $stmt->execute([
            $data['chapeau'],
            trim($data['contenu']),
            (int)$data['id'],
            $user['id'],
            $_SESSION['current_session_id']
        ]);
        $avisId = (int)$data['id'];
    } else {
        // Nouvel avis
        $stmt = $db->prepare("INSERT INTO avis (user_id, session_id, chapeau, contenu) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $user['id'],
            $_SESSION['current_session_id'],
            $data['chapeau'],
            trim($data['contenu'])
        ]);
        $avisId = $db->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => $avisId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
