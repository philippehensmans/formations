<?php
/**
 * API pour sauvegarder et charger les donnees de l'arbre a problemes
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$sessionId = validateCurrentSession($db);

if (!$user || !$sessionId) {
    http_response_code(401);
    echo json_encode(['error' => 'Session invalide']);
    exit;
}

$userId = $user['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'load':
        loadData($db, $userId, $sessionId);
        break;
    case 'save':
        saveData($db, $userId, $sessionId);
        break;
    case 'share':
        toggleShare($db, $userId, $sessionId);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action non reconnue']);
}

function loadData($db, $userId, $sessionId) {
    $stmt = $db->prepare("SELECT * FROM arbres WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$userId, $sessionId]);
    $arbre = $stmt->fetch();

    if ($arbre) {
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $arbre['id'],
                'nomProjet' => $arbre['nom_projet'],
                'participants' => $arbre['participants'],
                'problemeCentral' => $arbre['probleme_central'],
                'objectifCentral' => $arbre['objectif_central'],
                'consequences' => json_decode($arbre['consequences'] ?? '[]'),
                'causes' => json_decode($arbre['causes'] ?? '[]'),
                'objectifs' => json_decode($arbre['objectifs'] ?? '[]'),
                'moyens' => json_decode($arbre['moyens'] ?? '[]'),
                'isShared' => (bool)$arbre['is_shared']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'data' => null]);
    }
}

function saveData($db, $userId, $sessionId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Donnees invalides']);
        return;
    }

    $stmt = $db->prepare("SELECT id FROM arbres WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$userId, $sessionId]);
    $existing = $stmt->fetch();

    $nomProjet = $input['nomProjet'] ?? '';
    $participants = $input['participants'] ?? '';
    $problemeCentral = $input['problemeCentral'] ?? '';
    $objectifCentral = $input['objectifCentral'] ?? '';
    $consequences = json_encode($input['consequences'] ?? []);
    $causes = json_encode($input['causes'] ?? []);
    $objectifs = json_encode($input['objectifs'] ?? []);
    $moyens = json_encode($input['moyens'] ?? []);

    if ($existing) {
        $stmt = $db->prepare("UPDATE arbres SET
            nom_projet = ?, participants = ?, probleme_central = ?, objectif_central = ?,
            consequences = ?, causes = ?, objectifs = ?, moyens = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?");
        $stmt->execute([
            $nomProjet, $participants, $problemeCentral, $objectifCentral,
            $consequences, $causes, $objectifs, $moyens,
            $existing['id']
        ]);
        $arbreId = $existing['id'];
    } else {
        $stmt = $db->prepare("INSERT INTO arbres
            (user_id, session_id, nom_projet, participants, probleme_central, objectif_central, consequences, causes, objectifs, moyens)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $sessionId, $nomProjet, $participants, $problemeCentral, $objectifCentral,
            $consequences, $causes, $objectifs, $moyens
        ]);
        $arbreId = $db->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => $arbreId]);
}

function toggleShare($db, $userId, $sessionId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $shared = $input['shared'] ?? false;

    $stmt = $db->prepare("UPDATE arbres SET is_shared = ? WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$shared ? 1 : 0, $userId, $sessionId]);

    echo json_encode(['success' => true, 'shared' => $shared]);
}
