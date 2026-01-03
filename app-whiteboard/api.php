<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$user = requireAuth();
$db = getDB();

// Handle GET requests (polling)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'poll') {
        $whiteboardId = intval($_GET['whiteboard_id'] ?? 0);
        $since = floatval($_GET['since'] ?? 0);

        // Get updated elements
        $stmt = $db->prepare("
            SELECT * FROM elements
            WHERE whiteboard_id = ?
            AND (strftime('%s', updated_at) + 0.0) > ?
        ");
        $stmt->execute([$whiteboardId, $since]);
        $elements = $stmt->fetchAll();

        // Get new paths
        $stmt = $db->prepare("
            SELECT * FROM paths
            WHERE whiteboard_id = ?
            AND (strftime('%s', created_at) + 0.0) > ?
        ");
        $stmt->execute([$whiteboardId, $since]);
        $paths = $stmt->fetchAll();

        // Get deleted elements (we'll track this with a separate table in production)
        // For now, return empty array
        $deletedElements = [];

        echo json_encode([
            'success' => true,
            'timestamp' => microtime(true),
            'elements' => $elements,
            'paths' => $paths,
            'deleted_elements' => $deletedElements
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Handle POST requests
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'add_element':
        $whiteboardId = intval($input['whiteboard_id']);
        $type = $input['type'] ?? 'postit';
        $x = floatval($input['x'] ?? 0);
        $y = floatval($input['y'] ?? 0);
        $width = floatval($input['width'] ?? 100);
        $height = floatval($input['height'] ?? 100);
        $color = $input['color'] ?? 'yellow';
        $content = $input['content'] ?? '';

        // Get max z_index
        $stmt = $db->prepare("SELECT MAX(z_index) as max_z FROM elements WHERE whiteboard_id = ?");
        $stmt->execute([$whiteboardId]);
        $maxZ = $stmt->fetch()['max_z'] ?? 0;

        $stmt = $db->prepare("
            INSERT INTO elements (whiteboard_id, type, x, y, width, height, color, content, z_index, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$whiteboardId, $type, $x, $y, $width, $height, $color, $content, $maxZ + 1, $user['id'], $user['id']]);
        $elementId = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM elements WHERE id = ?");
        $stmt->execute([$elementId]);
        $element = $stmt->fetch();

        echo json_encode(['success' => true, 'element' => $element]);
        break;

    case 'update_element':
        $id = intval($input['id']);
        $x = floatval($input['x'] ?? 0);
        $y = floatval($input['y'] ?? 0);
        $width = floatval($input['width'] ?? 100);
        $height = floatval($input['height'] ?? 100);
        $content = $input['content'] ?? null;

        $stmt = $db->prepare("
            UPDATE elements
            SET x = ?, y = ?, width = ?, height = ?, content = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$x, $y, $width, $height, $content, $user['id'], $id]);

        echo json_encode(['success' => true]);
        break;

    case 'update_element_color':
        $id = intval($input['id']);
        $color = $input['color'] ?? 'yellow';

        $stmt = $db->prepare("
            UPDATE elements
            SET color = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$color, $user['id'], $id]);

        echo json_encode(['success' => true]);
        break;

    case 'delete_element':
        $id = intval($input['id']);

        $stmt = $db->prepare("DELETE FROM elements WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
        break;

    case 'add_path':
        $whiteboardId = intval($input['whiteboard_id']);
        $points = $input['points'] ?? '';
        $color = $input['color'] ?? '#000000';
        $strokeWidth = intval($input['stroke_width'] ?? 2);

        // Get max z_index
        $stmt = $db->prepare("SELECT MAX(z_index) as max_z FROM paths WHERE whiteboard_id = ?");
        $stmt->execute([$whiteboardId]);
        $maxZ = $stmt->fetch()['max_z'] ?? 0;

        $stmt = $db->prepare("
            INSERT INTO paths (whiteboard_id, points, color, stroke_width, z_index, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$whiteboardId, $points, $color, $strokeWidth, $maxZ + 1, $user['id']]);

        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'delete_path':
        $id = intval($input['id']);

        $stmt = $db->prepare("DELETE FROM paths WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
        break;

    case 'clear_all':
        $whiteboardId = intval($input['whiteboard_id']);

        $db->prepare("DELETE FROM elements WHERE whiteboard_id = ?")->execute([$whiteboardId]);
        $db->prepare("DELETE FROM paths WHERE whiteboard_id = ?")->execute([$whiteboardId]);

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
