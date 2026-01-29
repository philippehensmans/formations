<?php
/**
 * API pour mettre a jour les informations d'une session (formateur uniquement)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isFormateur()) {
    echo json_encode(['success' => false, 'error' => 'Non autorise']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Session ID manquant']);
    exit;
}

$sessionId = (int)$data['session_id'];
$appKey = 'app-six-chapeaux';

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $sessionId)) {
    echo json_encode(['success' => false, 'error' => 'Acces refuse a cette session']);
    exit;
}

$db = getDB();

try {
    // Mettre a jour le sujet de la session
    if (isset($data['sujet'])) {
        $stmt = $db->prepare("UPDATE sessions SET sujet = ? WHERE id = ?");
        $stmt->execute([trim($data['sujet']), $sessionId]);
    }

    // Mettre a jour le nom de la session
    if (isset($data['nom']) && !empty(trim($data['nom']))) {
        $stmt = $db->prepare("UPDATE sessions SET nom = ? WHERE id = ?");
        $stmt->execute([trim($data['nom']), $sessionId]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
