<?php
/**
 * API pour sauvegarder et charger les données du Cahier des Charges
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

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
        if (isAdmin()) {
            listSharedCahiers($db);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
        }
        break;
    case 'view':
        if (isAdmin()) {
            viewCahier($db, $_GET['id'] ?? 0);
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
    $stmt = $db->prepare("SELECT * FROM cahiers WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$userId]);
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

function saveData($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides']);
        return;
    }

    $stmt = $db->prepare("SELECT id FROM cahiers WHERE user_id = ?");
    $stmt->execute([$userId]);
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

        $sql = "INSERT INTO cahiers (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $db->prepare($sql);
        $values = array_values($fields);
        $values[] = $userId;
        $stmt->execute($values);
        $cahierId = $db->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'id' => $cahierId,
        'message' => 'Données sauvegardées'
    ]);
}

function toggleShare($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $shared = $input['shared'] ?? false;

    $stmt = $db->prepare("UPDATE cahiers SET is_shared = ? WHERE user_id = ?");
    $stmt->execute([$shared ? 1 : 0, $userId]);

    echo json_encode(['success' => true, 'shared' => $shared]);
}

function listSharedCahiers($db) {
    $stmt = $db->query("
        SELECT c.*, u.username
        FROM cahiers c
        JOIN users u ON c.user_id = u.id
        WHERE c.is_shared = 1
        ORDER BY c.updated_at DESC
    ");
    $cahiers = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'cahiers' => array_map(function($c) {
            return [
                'id' => $c['id'],
                'username' => $c['username'],
                'titreProjet' => $c['titre_projet'],
                'chefProjet' => $c['chef_projet'],
                'dateDebut' => $c['date_debut'],
                'dateFin' => $c['date_fin'],
                'updatedAt' => $c['updated_at']
            ];
        }, $cahiers)
    ]);
}

function viewCahier($db, $cahierId) {
    $stmt = $db->prepare("
        SELECT c.*, u.username
        FROM cahiers c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$cahierId]);
    $cahier = $stmt->fetch();

    if (!$cahier) {
        http_response_code(404);
        echo json_encode(['error' => 'Cahier non trouvé']);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $cahier['id'],
            'username' => $cahier['username'],
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
