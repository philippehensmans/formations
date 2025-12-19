<?php
/**
 * API pour sauvegarder et charger les donnees du Cahier des Charges
 * Utilise le systeme d'authentification partage
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];
$sessionId = $_SESSION['current_session_id'] ?? null;
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
    case 'list':
        if (isAdmin()) {
            listSharedCahiers($db, $sessionId);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Acces refuse']);
        }
        break;
    case 'view':
        if (isAdmin()) {
            viewCahier($db, $_GET['id'] ?? 0);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Acces refuse']);
        }
        break;
    case 'delete':
        if (isAdmin()) {
            deleteCahier($db, $_GET['id'] ?? 0);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Acces refuse']);
        }
        break;
    case 'deleteAll':
        if (isAdmin()) {
            deleteAllCahiers($db, $sessionId);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Acces refuse']);
        }
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action non reconnue']);
}

function deleteCahier($db, $cahierId) {
    $stmt = $db->prepare("DELETE FROM cahiers WHERE id = ?");
    $stmt->execute([$cahierId]);
    echo json_encode(['success' => true, 'message' => 'Cahier supprime']);
}

function deleteAllCahiers($db, $sessionId) {
    if ($sessionId) {
        $stmt = $db->prepare("DELETE FROM cahiers WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    } else {
        $db->exec("DELETE FROM cahiers");
    }
    echo json_encode(['success' => true, 'deleted' => ['cahiers' => true]]);
}

function loadData($db, $userId, $sessionId) {
    if ($sessionId) {
        $stmt = $db->prepare("SELECT * FROM cahiers WHERE user_id = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$userId, $sessionId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM cahiers WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$userId]);
    }
    $cahier = $stmt->fetch();

    if ($cahier) {
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $cahier['id'],
                'titreProjet' => $cahier['titre_projet'],
                'dateDebut' => $cahier['date_debut'],
                'dateFin' => $cahier['date_fin'],
                'chefProjet' => $cahier['chef_projet'],
                'sponsor' => $cahier['sponsor'],
                'groupeTravail' => $cahier['groupe_travail'],
                'benevoles' => $cahier['benevoles'],
                'autresActeurs' => $cahier['autres_acteurs'],
                'objectifStrategique' => $cahier['objectif_strategique'],
                'inclusivite' => $cahier['inclusivite'],
                'aspectDigital' => $cahier['aspect_digital'],
                'evolution' => $cahier['evolution'],
                'descriptionProjet' => $cahier['description_projet'],
                'objectifProjet' => $cahier['objectif_projet'],
                'logiqueProjet' => $cahier['logique_projet'],
                'objectifGlobal' => $cahier['objectif_global'],
                'objectifsSpecifiques' => json_decode($cahier['objectifs_specifiques'] ?? '[]'),
                'resultats' => json_decode($cahier['resultats'] ?? '[]'),
                'contraintes' => json_decode($cahier['contraintes'] ?? '[]'),
                'strategies' => json_decode($cahier['strategies'] ?? '[]'),
                'budget' => $cahier['budget'],
                'ressourcesHumaines' => $cahier['ressources_humaines'],
                'ressourcesMaterialles' => $cahier['ressources_materielles'],
                'etapes' => json_decode($cahier['etapes'] ?? '[]'),
                'communication' => $cahier['communication'],
                'isShared' => (bool)$cahier['is_shared']
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

    if ($sessionId) {
        $stmt = $db->prepare("SELECT id FROM cahiers WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$userId, $sessionId]);
    } else {
        $stmt = $db->prepare("SELECT id FROM cahiers WHERE user_id = ? AND session_id IS NULL");
        $stmt->execute([$userId]);
    }
    $existing = $stmt->fetch();

    $fields = [
        'titre_projet' => $input['titreProjet'] ?? '',
        'date_debut' => $input['dateDebut'] ?? null,
        'date_fin' => $input['dateFin'] ?? null,
        'chef_projet' => $input['chefProjet'] ?? '',
        'sponsor' => $input['sponsor'] ?? '',
        'groupe_travail' => $input['groupeTravail'] ?? '',
        'benevoles' => $input['benevoles'] ?? '',
        'autres_acteurs' => $input['autresActeurs'] ?? '',
        'objectif_strategique' => $input['objectifStrategique'] ?? '',
        'inclusivite' => $input['inclusivite'] ?? '',
        'aspect_digital' => $input['aspectDigital'] ?? '',
        'evolution' => $input['evolution'] ?? '',
        'description_projet' => $input['descriptionProjet'] ?? '',
        'objectif_projet' => $input['objectifProjet'] ?? '',
        'logique_projet' => $input['logiqueProjet'] ?? '',
        'objectif_global' => $input['objectifGlobal'] ?? '',
        'objectifs_specifiques' => json_encode($input['objectifsSpecifiques'] ?? []),
        'resultats' => json_encode($input['resultats'] ?? []),
        'contraintes' => json_encode($input['contraintes'] ?? []),
        'strategies' => json_encode($input['strategies'] ?? []),
        'budget' => $input['budget'] ?? '',
        'ressources_humaines' => $input['ressourcesHumaines'] ?? '',
        'ressources_materielles' => $input['ressourcesMaterialles'] ?? '',
        'etapes' => json_encode($input['etapes'] ?? []),
        'communication' => $input['communication'] ?? ''
    ];

    if ($existing) {
        $setClauses = [];
        $values = [];
        foreach ($fields as $key => $value) {
            $setClauses[] = "$key = ?";
            $values[] = $value;
        }
        $setClauses[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $existing['id'];

        $sql = "UPDATE cahiers SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        $cahierId = $existing['id'];
    } else {
        $columns = array_keys($fields);
        $placeholders = array_fill(0, count($fields), '?');
        $columns[] = 'user_id';
        $placeholders[] = '?';
        $columns[] = 'session_id';
        $placeholders[] = '?';

        $sql = "INSERT INTO cahiers (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $db->prepare($sql);
        $values = array_values($fields);
        $values[] = $userId;
        $values[] = $sessionId;
        $stmt->execute($values);
        $cahierId = $db->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'id' => $cahierId,
        'message' => 'Donnees sauvegardees'
    ]);
}

function toggleShare($db, $userId, $sessionId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $shared = $input['shared'] ?? false;

    if ($sessionId) {
        $stmt = $db->prepare("UPDATE cahiers SET is_shared = ? WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$shared ? 1 : 0, $userId, $sessionId]);
    } else {
        $stmt = $db->prepare("UPDATE cahiers SET is_shared = ? WHERE user_id = ?");
        $stmt->execute([$shared ? 1 : 0, $userId]);
    }

    echo json_encode(['success' => true, 'shared' => $shared]);
}

function listSharedCahiers($db, $sessionId) {
    $sharedDb = getSharedDB();
    if ($sessionId) {
        $stmt = $db->prepare("
            SELECT c.*, c.user_id
            FROM cahiers c
            WHERE c.session_id = ?
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$sessionId]);
    } else {
        $stmt = $db->query("
            SELECT c.*, c.user_id
            FROM cahiers c
            ORDER BY c.updated_at DESC
        ");
    }
    $cahiers = $stmt->fetchAll();

    // Get usernames from shared DB
    $result = [];
    foreach ($cahiers as $c) {
        $userStmt = $sharedDb->prepare("SELECT username, prenom, nom FROM users WHERE id = ?");
        $userStmt->execute([$c['user_id']]);
        $user = $userStmt->fetch();
        $result[] = [
            'id' => $c['id'],
            'username' => $user['username'] ?? 'Inconnu',
            'prenom' => $user['prenom'] ?? '',
            'nom' => $user['nom'] ?? '',
            'titreProjet' => $c['titre_projet'],
            'chefProjet' => $c['chef_projet'],
            'dateDebut' => $c['date_debut'],
            'dateFin' => $c['date_fin'],
            'updatedAt' => $c['updated_at'],
            'isShared' => (bool)$c['is_shared']
        ];
    }

    echo json_encode(['success' => true, 'cahiers' => $result]);
}

function viewCahier($db, $cahierId) {
    $sharedDb = getSharedDB();
    $stmt = $db->prepare("SELECT * FROM cahiers WHERE id = ?");
    $stmt->execute([$cahierId]);
    $cahier = $stmt->fetch();

    if (!$cahier) {
        http_response_code(404);
        echo json_encode(['error' => 'Cahier non trouve']);
        return;
    }

    $userStmt = $sharedDb->prepare("SELECT username, prenom, nom FROM users WHERE id = ?");
    $userStmt->execute([$cahier['user_id']]);
    $user = $userStmt->fetch();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $cahier['id'],
            'username' => $user['username'] ?? 'Inconnu',
            'prenom' => $user['prenom'] ?? '',
            'nom' => $user['nom'] ?? '',
            'titreProjet' => $cahier['titre_projet'],
            'dateDebut' => $cahier['date_debut'],
            'dateFin' => $cahier['date_fin'],
            'chefProjet' => $cahier['chef_projet'],
            'sponsor' => $cahier['sponsor'],
            'groupeTravail' => $cahier['groupe_travail'],
            'benevoles' => $cahier['benevoles'],
            'autresActeurs' => $cahier['autres_acteurs'],
            'objectifStrategique' => $cahier['objectif_strategique'],
            'inclusivite' => $cahier['inclusivite'],
            'aspectDigital' => $cahier['aspect_digital'],
            'evolution' => $cahier['evolution'],
            'descriptionProjet' => $cahier['description_projet'],
            'objectifProjet' => $cahier['objectif_projet'],
            'logiqueProjet' => $cahier['logique_projet'],
            'objectifGlobal' => $cahier['objectif_global'],
            'objectifsSpecifiques' => json_decode($cahier['objectifs_specifiques'] ?? '[]'),
            'resultats' => json_decode($cahier['resultats'] ?? '[]'),
            'contraintes' => json_decode($cahier['contraintes'] ?? '[]'),
            'strategies' => json_decode($cahier['strategies'] ?? '[]'),
            'budget' => $cahier['budget'],
            'ressourcesHumaines' => $cahier['ressources_humaines'],
            'ressourcesMaterialles' => $cahier['ressources_materielles'],
            'etapes' => json_decode($cahier['etapes'] ?? '[]'),
            'communication' => $cahier['communication'],
            'isShared' => (bool)$cahier['is_shared'],
            'updatedAt' => $cahier['updated_at']
        ]
    ]);
}
