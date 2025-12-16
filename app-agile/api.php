<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Non autorise']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

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
        }
        break;

    case 'view':
        if (isAdmin()) {
            viewProject($db, $_GET['id'] ?? 0);
        }
        break;

    case 'delete':
        if (isAdmin()) {
            deleteProject($db, $_GET['id'] ?? 0);
        }
        break;

    case 'deleteAll':
        if (isAdmin()) {
            deleteAllProjects($db, isset($_GET['users']));
        }
        break;

    default:
        echo json_encode(['error' => 'Action non reconnue']);
}

function saveProject($db, $userId) {
    $projectName = $_POST['project_name'] ?? '';
    $teamName = $_POST['team_name'] ?? '';
    $cards = $_POST['cards'] ?? '[]';
    $userStories = $_POST['user_stories'] ?? '[]';
    $retrospective = $_POST['retrospective'] ?? '{"good":[],"improve":[],"actions":[]}';
    $sprint = $_POST['sprint'] ?? '{"number":1,"start":"","end":"","goal":""}';

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
    } else {
        $stmt = $db->prepare("
            INSERT INTO projects (user_id, project_name, team_name, cards, user_stories, retrospective, sprint)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $projectName, $teamName, $cards, $userStories, $retrospective, $sprint]);
    }

    echo json_encode(['success' => true]);
}

function loadProject($db, $userId) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ?");
    $stmt->execute([$userId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {
        echo json_encode([
            'success' => true,
            'data' => [
                'project_name' => $project['project_name'],
                'team_name' => $project['team_name'],
                'cards' => json_decode($project['cards']),
                'user_stories' => json_decode($project['user_stories']),
                'retrospective' => json_decode($project['retrospective']),
                'sprint' => json_decode($project['sprint']),
                'is_shared' => $project['is_shared']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Projet non trouve']);
    }
}

function toggleShare($db, $userId) {
    $isShared = $_POST['is_shared'] ?? '0';

    $stmt = $db->prepare("UPDATE projects SET is_shared = ? WHERE user_id = ?");
    $stmt->execute([$isShared, $userId]);

    echo json_encode(['success' => true, 'is_shared' => $isShared]);
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

    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {
        echo json_encode(['success' => true, 'project' => $project]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Projet non trouve']);
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
