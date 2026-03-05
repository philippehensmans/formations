<?php
/**
 * API pour recuperer toutes les reponses d'une session (formateur uniquement)
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

$appKey = 'app-questionnaire-ia';
if (!canAccessSession($appKey, $sessionId)) {
    echo json_encode(['success' => false, 'error' => 'Acces refuse']);
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

try {
    // Recuperer les questions
    $questions = getQuestions($sessionId);

    // Recuperer toutes les reponses partagees
    $stmt = $db->prepare("SELECT r.*, q.label as question_label, q.type as question_type, q.options as question_options, q.ordre
                          FROM reponses r
                          JOIN questions q ON r.question_id = q.id
                          WHERE r.session_id = ? AND r.is_shared = 1
                          ORDER BY r.user_id, q.ordre");
    $stmt->execute([$sessionId]);
    $allReponses = $stmt->fetchAll();

    // Enrichir avec les infos utilisateur
    foreach ($allReponses as &$r) {
        $userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
        $userStmt->execute([$r['user_id']]);
        $userInfo = $userStmt->fetch();
        $r['user_prenom'] = $userInfo['prenom'] ?? '';
        $r['user_nom'] = $userInfo['nom'] ?? '';
        $r['user_organisation'] = $userInfo['organisation'] ?? '';
    }

    // Nombre de participants ayant repondu
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM reponses WHERE session_id = ? AND is_shared = 1");
    $stmt->execute([$sessionId]);
    $participantsCount = $stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'reponses' => $allReponses,
        'participantsCount' => $participantsCount
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
}
