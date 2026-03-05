<?php
/**
 * API pour sauvegarder les questions (formateur uniquement)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isFormateur()) {
    echo json_encode(['success' => false, 'error' => 'Non autorise']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['session_id']) || !isset($data['questions'])) {
    echo json_encode(['success' => false, 'error' => 'Donnees manquantes']);
    exit;
}

$sessionId = (int)$data['session_id'];
$appKey = 'app-questionnaire-ia';

if (!canAccessSession($appKey, $sessionId)) {
    echo json_encode(['success' => false, 'error' => 'Acces refuse a cette session']);
    exit;
}

$db = getDB();

try {
    $db->beginTransaction();

    // Supprimer les anciennes questions (et leurs reponses en cascade)
    $stmt = $db->prepare("DELETE FROM reponses WHERE session_id = ? AND question_id NOT IN (SELECT id FROM questions WHERE session_id = ?)");
    $stmt->execute([$sessionId, $sessionId]);

    // Supprimer toutes les anciennes questions
    $stmt = $db->prepare("DELETE FROM questions WHERE session_id = ?");
    $stmt->execute([$sessionId]);

    // Inserer les nouvelles questions
    $insert = $db->prepare("INSERT INTO questions (session_id, type, label, options, ordre, obligatoire) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($data['questions'] as $i => $q) {
        $type = in_array($q['type'] ?? '', ['radio', 'text', 'textarea']) ? $q['type'] : 'text';
        $label = trim($q['label'] ?? '');
        if (empty($label)) continue;

        $options = '';
        if ($type === 'radio' && !empty($q['options'])) {
            $options = is_array($q['options']) ? json_encode($q['options']) : $q['options'];
        }

        $insert->execute([
            $sessionId,
            $type,
            $label,
            $options,
            $i + 1,
            !empty($q['obligatoire']) ? 1 : 0
        ]);
    }

    $db->commit();

    // Supprimer aussi les reponses orphelines
    $db->exec("DELETE FROM reponses WHERE question_id NOT IN (SELECT id FROM questions)");

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
}
