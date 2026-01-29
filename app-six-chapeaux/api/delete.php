<?php
/**
 * API pour supprimer un avis
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID manquant']);
    exit;
}

$user = getLoggedUser();
$db = getDB();

try {
    // Supprimer uniquement si l'avis appartient a l'utilisateur courant
    $stmt = $db->prepare("DELETE FROM avis WHERE id = ? AND user_id = ? AND session_id = ?");
    $stmt->execute([
        (int)$data['id'],
        $user['id'],
        $_SESSION['current_session_id']
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Avis non trouve ou non autorise']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
