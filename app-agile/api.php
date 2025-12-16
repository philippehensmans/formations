<?php
/**
 * API pour sauvegarder et charger les donnees du projet Agile
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'save':
        saveProject($db, $userId);
        break;

    case 'load':
        loadProject($db, $userId);
        break;

    case 'share':
        toggleShare($db, $userId);
        break;

    case 'list':
        if (isAdmin()) {
            listProjects($db, isset($_GET['all']));
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Acces refuse']);
        }
        break;

    case 'view':
        if (isAdmin()) {
            viewProject($db, $_GET['id'] ?? 0);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Acces refuse']);
        }
        break;

    case 'delete':
        if (isAdmin()) {
            deleteProject($db, $_GET['id'] ?? 0);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Acces refuse']);
        }
        break;

    case 'deleteAll':
        if (isAdmin()) {
            deleteAllProjects($db, isset($_GET['users']));
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Acces refuse']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action non reconnue']);
}

function saveProject($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Donnees invalides']);
        return;
    }

    $projectName = $input['project_name'] ?? '';
    $teamName = $input['team_name'] ?? '';
    $cards = json_encode($input['cards'] ?? []);
    $userStories = json_encode($input['user_stories'] ?? []);
    $retrospective = json_encode($input['retrospective'] ?? ['good' => [], 'improve' => [], 'actions' => []]);
    $sprint = json_encode($input['sprint'] ?? ['number' => 1, 'start' => '', 'end' => '', 'goal' => '']);

    $stmt = $db->prepare("SELECT id FROM projects WHERE user_id = ?");
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("
            UPDATE projects SET
                project_name = ?,
                team_name = ?,
                cards = ?,
                user_stories = ?,
                retrospective = ?,
                sprint = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([$projectName, $teamName, $cards, $userStories, $retrospective, $sprint, $userId]);
        $projectId = $existing['id'];
    } else {
        $stmt = $db->prepare("
            INSERT INTO projects (user_id, project_name, team_name, cards, user_stories, retrospective, sprint)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $projectName, $teamName, $cards, $userStories, $retrospective, $sprint]);
        $projectId = $db->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => $projectId]);
}

function loadProject($db, $userId) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ?");
    $stmt->execute([$userId]);
    $project = $stmt->fetch();

    if ($project) {
        echo json_encode([
            'success' => true,
            'data' => [
                'project_name' => $project['project_name'],
                'team_name' => $project['team_name'],
                'cards' => json_decode($project['cards'] ?: '[]'),
                'user_stories' => json_decode($project['user_stories'] ?: '[]'),
                'retrospective' => json_decode($project['retrospective'] ?: '{"good":[],"improve":[],"actions":[]}'),
                'sprint' => json_decode($project['sprint'] ?: '{"number":1,"start":"","end":"","goal":""}'),
                'is_shared' => (bool)$project['is_shared']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'data' => null]);
    }
}

function toggleShare($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $shared = $input['shared'] ?? false;

    $stmt = $db->prepare("UPDATE projects SET is_shared = ? WHERE user_id = ?");
    $stmt->execute([$shared ? 1 : 0, $userId]);

    echo json_encode(['success' => true, 'shared' => $shared]);
}

function listProjects($db, $showAll = false) {
    if ($showAll) {
        $stmt = $db->query("
            SELECT p.*, u.username
            FROM projects p
            JOIN users u ON p.user_id = u.id
            WHERE u.is_admin = 0
            ORDER BY p.updated_at DESC
        ");
    } else {
        $stmt = $db->query("
            SELECT p.*, u.username
            FROM projects p
            JOIN users u ON p.user_id = u.id
            WHERE u.is_admin = 0 AND p.is_shared = 1
            ORDER BY p.updated_at DESC
        ");
    }

    $projects = $stmt->fetchAll();
    echo json_encode(['success' => true, 'projects' => $projects]);
}

function viewProject($db, $projectId) {
    $stmt = $db->prepare("
        SELECT p.*, u.username
        FROM projects p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();

    if ($project) {
        echo json_encode(['success' => true, 'project' => $project]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Projet non trouve']);
    }
}

function deleteProject($db, $projectId) {
    $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    echo json_encode(['success' => true, 'message' => 'Projet supprime']);
}

function deleteAllProjects($db, $deleteUsers = false) {
    $db->exec("DELETE FROM projects WHERE user_id IN (SELECT id FROM users WHERE is_admin = 0)");
    $deleted = ['projects' => true];

    if ($deleteUsers) {
        $db->exec("DELETE FROM users WHERE is_admin = 0");
        $deleted['users'] = true;
    }

    echo json_encode(['success' => true, 'deleted' => $deleted]);
}
?>
