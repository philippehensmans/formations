<?php
/**
 * API pour sauvegarder et charger les données de l'arbre à problèmes
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Vérifier l'authentification
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'load':
        loadData($db, $userId);
        break;

    case 'save':
        saveData($db, $userId);
        break;

    case 'share':
        toggleShare($db, $userId);
        break;

    case 'list':
        // Pour l'admin: lister tous les arbres partagés
        if (isAdmin()) {
            listSharedArbres($db);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
        }
        break;

    case 'view':
        // Pour l'admin: voir un arbre spécifique
        if (isAdmin()) {
            viewArbre($db, $_GET['id'] ?? 0);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action non reconnue']);
}

function loadData($db, $userId) {
    $stmt = $db->prepare("SELECT * FROM arbres WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$userId]);
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
        echo json_encode([
            'success' => true,
            'data' => null
        ]);
    }
}

function saveData($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides']);
        return;
    }

    // Vérifier si un arbre existe déjà pour cet utilisateur
    $stmt = $db->prepare("SELECT id FROM arbres WHERE user_id = ?");
    $stmt->execute([$userId]);
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
        // Mettre à jour
        $stmt = $db->prepare("UPDATE arbres SET
            nom_projet = ?,
            participants = ?,
            probleme_central = ?,
            objectif_central = ?,
            consequences = ?,
            causes = ?,
            objectifs = ?,
            moyens = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?");
        $stmt->execute([
            $nomProjet, $participants, $problemeCentral, $objectifCentral,
            $consequences, $causes, $objectifs, $moyens,
            $existing['id']
        ]);
        $arbreId = $existing['id'];
    } else {
        // Créer
        $stmt = $db->prepare("INSERT INTO arbres
            (user_id, nom_projet, participants, probleme_central, objectif_central, consequences, causes, objectifs, moyens)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $nomProjet, $participants, $problemeCentral, $objectifCentral,
            $consequences, $causes, $objectifs, $moyens
        ]);
        $arbreId = $db->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'id' => $arbreId,
        'message' => 'Données sauvegardées'
    ]);
}

function toggleShare($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $shared = $input['shared'] ?? false;

    $stmt = $db->prepare("UPDATE arbres SET is_shared = ? WHERE user_id = ?");
    $stmt->execute([$shared ? 1 : 0, $userId]);

    echo json_encode([
        'success' => true,
        'shared' => $shared
    ]);
}

function listSharedArbres($db) {
    $stmt = $db->query("
        SELECT a.*, u.username
        FROM arbres a
        JOIN users u ON a.user_id = u.id
        WHERE a.is_shared = 1
        ORDER BY a.updated_at DESC
    ");
    $arbres = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'arbres' => array_map(function($a) {
            return [
                'id' => $a['id'],
                'username' => $a['username'],
                'nomProjet' => $a['nom_projet'],
                'participants' => $a['participants'],
                'problemeCentral' => $a['probleme_central'],
                'updatedAt' => $a['updated_at']
            ];
        }, $arbres)
    ]);
}

function viewArbre($db, $arbreId) {
    $stmt = $db->prepare("
        SELECT a.*, u.username
        FROM arbres a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$arbreId]);
    $arbre = $stmt->fetch();

    if (!$arbre) {
        http_response_code(404);
        echo json_encode(['error' => 'Arbre non trouvé']);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $arbre['id'],
            'username' => $arbre['username'],
            'nomProjet' => $arbre['nom_projet'],
            'participants' => $arbre['participants'],
            'problemeCentral' => $arbre['probleme_central'],
            'objectifCentral' => $arbre['objectif_central'],
            'consequences' => json_decode($arbre['consequences'] ?? '[]'),
            'causes' => json_decode($arbre['causes'] ?? '[]'),
            'objectifs' => json_decode($arbre['objectifs'] ?? '[]'),
            'moyens' => json_decode($arbre['moyens'] ?? '[]'),
            'isShared' => (bool)$arbre['is_shared'],
            'updatedAt' => $arbre['updated_at']
        ]
    ]);
}
