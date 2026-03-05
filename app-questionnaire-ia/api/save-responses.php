<?php
/**
 * API pour sauvegarder les reponses du questionnaire
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifie']);
    exit;
}

$user = getLoggedUser();
$db = getDB();
$sessionId = $_SESSION['current_session_id'];

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['responses'])) {
    echo json_encode(['success' => false, 'error' => 'Donnees manquantes']);
    exit;
}

$share = !empty($data['share']);

try {
    $db->beginTransaction();

    foreach ($data['responses'] as $r) {
        $questionId = (int)($r['question_id'] ?? 0);
        $contenu = $r['contenu'] ?? '';

        if (!$questionId) continue;

        // Verifier que la question appartient bien a cette session
        $stmt = $db->prepare("SELECT id FROM questions WHERE id = ? AND session_id = ?");
        $stmt->execute([$questionId, $sessionId]);
        if (!$stmt->fetch()) continue;

        // Upsert : mettre a jour si existe, sinon inserer
        $stmt = $db->prepare("SELECT id FROM reponses WHERE user_id = ? AND session_id = ? AND question_id = ?");
        $stmt->execute([$user['id'], $sessionId, $questionId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("UPDATE reponses SET contenu = ?, is_shared = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$contenu, $share ? 1 : 0, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO reponses (user_id, session_id, question_id, contenu, is_shared) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $sessionId, $questionId, $contenu, $share ? 1 : 0]);
        }
    }

    // Si partage, marquer toutes les reponses comme partagees
    if ($share) {
        $stmt = $db->prepare("UPDATE reponses SET is_shared = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$user['id'], $sessionId]);
    }

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erreur base de donnees: ' . $e->getMessage()]);
}
