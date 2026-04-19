<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401); echo json_encode(['error' => 'Non authentifié']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400); echo json_encode(['error' => 'Données invalides']); exit;
}

$db = getDB();
$userId = getLoggedUser()['id'];
$sessionId = (int)$_SESSION['current_session_id'];
$type = $data['type'] ?? 'fiche';
$submit = !empty($data['submit']) ? 1 : 0;

switch ($type) {

    case 'fiche':
        $stmt = $db->prepare("SELECT id, is_submitted FROM fiches WHERE user_id=? AND session_id=?");
        $stmt->execute([$userId, $sessionId]);
        $row = $stmt->fetch();
        if ($row && $row['is_submitted']) { http_response_code(403); echo json_encode(['error' => 'Déjà soumis']); exit; }

        $sujet    = mb_substr(trim($data['sujet']    ?? ''), 0, 2000);
        $message1 = mb_substr(trim($data['message1'] ?? ''), 0, 1000);
        $message2 = mb_substr(trim($data['message2'] ?? ''), 0, 1000);
        $message3 = mb_substr(trim($data['message3'] ?? ''), 0, 1000);
        $anecdote = mb_substr(trim($data['anecdote'] ?? ''), 0, 2000);
        $a_eviter = mb_substr(trim($data['a_eviter'] ?? ''), 0, 1000);

        if ($row) {
            $db->prepare("UPDATE fiches SET sujet=?,message1=?,message2=?,message3=?,anecdote=?,a_eviter=?,is_submitted=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND session_id=?")
               ->execute([$sujet,$message1,$message2,$message3,$anecdote,$a_eviter,$submit,$userId,$sessionId]);
        } else {
            $db->prepare("INSERT INTO fiches (user_id,session_id,sujet,message1,message2,message3,anecdote,a_eviter,is_submitted) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$userId,$sessionId,$sujet,$message1,$message2,$message3,$anecdote,$a_eviter,$submit]);
        }
        break;

    case 'lignes':
        $stmt = $db->prepare("SELECT id, is_submitted FROM lignes_reponse WHERE user_id=? AND session_id=?");
        $stmt->execute([$userId, $sessionId]);
        $row = $stmt->fetch();
        if ($row && $row['is_submitted']) { http_response_code(403); echo json_encode(['error' => 'Déjà soumis']); exit; }

        $qr = $data['qr_data'] ?? [];
        $el = $data['elements_data'] ?? [];
        if (!is_array($qr)) $qr = [];
        if (!is_array($el)) $el = [];

        $cleanQr = array_values(array_map(fn($r) => [
            'question' => mb_substr(trim($r['question'] ?? ''), 0, 500),
            'reponse'  => mb_substr(trim($r['reponse']  ?? ''), 0, 1000),
        ], array_filter($qr, fn($r) => is_array($r))));

        $cleanEl = array_values(array_map(fn($r) => [
            'situation'  => mb_substr(trim($r['situation']  ?? ''), 0, 500),
            'formulation'=> mb_substr(trim($r['formulation']?? ''), 0, 1000),
        ], array_filter($el, fn($r) => is_array($r))));

        if ($row) {
            $db->prepare("UPDATE lignes_reponse SET qr_data=?,elements_data=?,is_submitted=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND session_id=?")
               ->execute([json_encode($cleanQr),json_encode($cleanEl),$submit,$userId,$sessionId]);
        } else {
            $db->prepare("INSERT INTO lignes_reponse (user_id,session_id,qr_data,elements_data,is_submitted) VALUES (?,?,?,?,?)")
               ->execute([$userId,$sessionId,json_encode($cleanQr),json_encode($cleanEl),$submit]);
        }
        break;

    case 'communique':
        $stmt = $db->prepare("SELECT id, is_submitted FROM communiques WHERE user_id=? AND session_id=?");
        $stmt->execute([$userId, $sessionId]);
        $row = $stmt->fetch();
        if ($row && $row['is_submitted']) { http_response_code(403); echo json_encode(['error' => 'Déjà soumis']); exit; }

        $fields = ['titre','chapeau','paragraphe1','paragraphe2','paragraphe3','citation','citation_source','contact_nom','contact_titre','contact_email','contact_tel'];
        $vals = [];
        foreach ($fields as $f) $vals[$f] = mb_substr(trim($data[$f] ?? ''), 0, 2000);

        if ($row) {
            $db->prepare("UPDATE communiques SET titre=?,chapeau=?,paragraphe1=?,paragraphe2=?,paragraphe3=?,citation=?,citation_source=?,contact_nom=?,contact_titre=?,contact_email=?,contact_tel=?,is_submitted=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND session_id=?")
               ->execute([...array_values($vals),$submit,$userId,$sessionId]);
        } else {
            $db->prepare("INSERT INTO communiques (user_id,session_id,titre,chapeau,paragraphe1,paragraphe2,paragraphe3,citation,citation_source,contact_nom,contact_titre,contact_email,contact_tel,is_submitted) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$userId,$sessionId,...array_values($vals),$submit]);
        }
        break;

    default:
        http_response_code(400); echo json_encode(['error' => 'Type inconnu']); exit;
}

echo json_encode(['success' => true]);
