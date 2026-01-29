<?php
/**
 * API pour partager tous les avis avec le formateur
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$user = getLoggedUser();
$db = getDB();

try {
    // Marquer tous les avis de l'utilisateur pour cette session comme partages
    $stmt = $db->prepare("UPDATE avis SET is_shared = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
    $stmt->execute([
        $user['id'],
        $_SESSION['current_session_id']
    ]);

    $count = $stmt->rowCount();
    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
