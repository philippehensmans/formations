<?php
/**
 * API pour recuperer tous les avis d'une session (formateur uniquement)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isFormateur()) {
    echo json_encode(['success' => false, 'error' => 'Non autorise']);
    exit;
}

$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$sessionId) {
    echo json_encode(['success' => false, 'error' => 'Session ID manquant']);
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();
$appKey = 'app-six-chapeaux';

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $sessionId)) {
    echo json_encode(['success' => false, 'error' => 'Acces refuse a cette session']);
    exit;
}

try {
    // Recuperer tous les avis partages de la session
    $stmt = $db->prepare("SELECT * FROM avis WHERE session_id = ? AND is_shared = 1 ORDER BY chapeau, created_at DESC");
    $stmt->execute([$sessionId]);
    $avis = $stmt->fetchAll();

    // Enrichir avec les infos utilisateur
    foreach ($avis as &$a) {
        $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
        $userStmt->execute([$a['user_id']]);
        $userInfo = $userStmt->fetch();
        $a['user_prenom'] = $userInfo['prenom'] ?? '';
        $a['user_nom'] = $userInfo['nom'] ?? '';
        $a['user_organisation'] = $userInfo['organisation'] ?? '';
    }

    // Statistiques par chapeau
    $stats = getStatsSession($sessionId);

    echo json_encode([
        'success' => true,
        'avis' => $avis,
        'stats' => $stats
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
