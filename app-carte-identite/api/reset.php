<?php
/**
 * API Reset - Reinitialiser le statut de partage d'une fiche
 * Accessible uniquement aux formateurs
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isFormateur()) {
    echo json_encode(['success' => false, 'error' => 'Non autorise']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ficheId = (int)($input['fiche_id'] ?? 0);

if (!$ficheId) {
    echo json_encode(['success' => false, 'error' => 'ID fiche manquant']);
    exit;
}

$db = getDB();

// Verifier que la fiche existe et recuperer la session
$stmt = $db->prepare("SELECT f.*, p.session_id FROM fiches f
    JOIN participants p ON f.user_id = p.user_id AND f.session_id = p.session_id
    WHERE f.id = ?");
$stmt->execute([$ficheId]);
$fiche = $stmt->fetch();

if (!$fiche) {
    echo json_encode(['success' => false, 'error' => 'Fiche non trouvee']);
    exit;
}

// Verifier l'acces a cette session
if (!canAccessSession('app-carte-identite', $fiche['session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Acces refuse']);
    exit;
}

// Reinitialiser le statut de partage
$stmt = $db->prepare("UPDATE fiches SET is_shared = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$ficheId]);

echo json_encode(['success' => true]);
