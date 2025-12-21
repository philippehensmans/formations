<?php
/**
 * API Carte Mentale - Operations CRUD et synchronisation
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Verifier authentification
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$user = getLoggedUser();
$db = getDB();

// Gestion GET (polling)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $mindmapId = (int)($_GET['mindmap_id'] ?? 0);

    if ($action === 'poll' && $mindmapId) {
        $since = $_GET['since'] ?? '';

        // Verifier si mise a jour
        $stmt = $db->prepare("SELECT updated_at FROM mindmaps WHERE id = ?");
        $stmt->execute([$mindmapId]);
        $mindmap = $stmt->fetch();

        if ($mindmap && $mindmap['updated_at'] > $since) {
            echo json_encode([
                'updated' => true,
                'nodes' => getNodes($mindmapId),
                'updated_at' => $mindmap['updated_at']
            ]);
        } else {
            echo json_encode(['updated' => false]);
        }
        exit;
    }
}

// Gestion POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $mindmapId = (int)($input['mindmap_id'] ?? 0);

    if (!$mindmapId) {
        echo json_encode(['error' => 'mindmap_id requis']);
        exit;
    }

    try {
        switch ($action) {
            case 'add':
                // Ajouter un noeud
                $parentId = (int)($input['parent_id'] ?? 0);
                $text = trim($input['text'] ?? '');
                $note = trim($input['note'] ?? '');
                $fileUrl = trim($input['file_url'] ?? '');
                $color = $input['color'] ?? 'blue';
                $icon = $input['icon'] ?? null;
                $x = (float)($input['x'] ?? 0);
                $y = (float)($input['y'] ?? 0);

                if (!$text) {
                    echo json_encode(['error' => 'Texte requis']);
                    exit;
                }

                $stmt = $db->prepare("INSERT INTO nodes (mindmap_id, parent_id, text, note, file_url, color, icon, pos_x, pos_y, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$mindmapId, $parentId ?: null, $text, $note ?: null, $fileUrl ?: null, $color, $icon, $x, $y, $user['id'], $user['id']]);

                updateMindmapTimestamp($mindmapId);

                echo json_encode([
                    'success' => true,
                    'node_id' => $db->lastInsertId(),
                    'nodes' => getNodes($mindmapId),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                break;

            case 'update':
                // Modifier un noeud
                $nodeId = (int)($input['id'] ?? 0);
                $text = trim($input['text'] ?? '');
                $note = trim($input['note'] ?? '');
                $fileUrl = trim($input['file_url'] ?? '');
                $color = $input['color'] ?? 'blue';
                $icon = $input['icon'] ?? null;

                if (!$nodeId || !$text) {
                    echo json_encode(['error' => 'ID et texte requis']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE nodes SET text = ?, note = ?, file_url = ?, color = ?, icon = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND mindmap_id = ?");
                $stmt->execute([$text, $note ?: null, $fileUrl ?: null, $color, $icon, $user['id'], $nodeId, $mindmapId]);

                updateMindmapTimestamp($mindmapId);

                echo json_encode([
                    'success' => true,
                    'nodes' => getNodes($mindmapId),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                break;

            case 'move':
                // Deplacer un noeud
                $nodeId = (int)($input['id'] ?? 0);
                $x = (float)($input['x'] ?? 0);
                $y = (float)($input['y'] ?? 0);

                if (!$nodeId) {
                    echo json_encode(['error' => 'ID requis']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE nodes SET pos_x = ?, pos_y = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND mindmap_id = ?");
                $stmt->execute([$x, $y, $user['id'], $nodeId, $mindmapId]);

                updateMindmapTimestamp($mindmapId);

                echo json_encode([
                    'success' => true,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                break;

            case 'delete':
                // Supprimer un noeud (et ses enfants en cascade)
                $nodeId = (int)($input['id'] ?? 0);

                if (!$nodeId) {
                    echo json_encode(['error' => 'ID requis']);
                    exit;
                }

                // Verifier que ce n'est pas la racine
                $stmt = $db->prepare("SELECT is_root FROM nodes WHERE id = ? AND mindmap_id = ?");
                $stmt->execute([$nodeId, $mindmapId]);
                $node = $stmt->fetch();

                if ($node && $node['is_root']) {
                    echo json_encode(['error' => 'Impossible de supprimer le noeud racine']);
                    exit;
                }

                // Supprimer recursivement
                deleteNodeRecursive($db, $nodeId, $mindmapId);

                updateMindmapTimestamp($mindmapId);

                echo json_encode([
                    'success' => true,
                    'nodes' => getNodes($mindmapId),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                break;

            default:
                echo json_encode(['error' => 'Action inconnue']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Fonctions utilitaires
function updateMindmapTimestamp($mindmapId) {
    global $db;
    $db->prepare("UPDATE mindmaps SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$mindmapId]);
}

function deleteNodeRecursive($db, $nodeId, $mindmapId) {
    // D'abord supprimer les enfants
    $stmt = $db->prepare("SELECT id FROM nodes WHERE parent_id = ? AND mindmap_id = ?");
    $stmt->execute([$nodeId, $mindmapId]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($children as $childId) {
        deleteNodeRecursive($db, $childId, $mindmapId);
    }

    // Puis supprimer le noeud
    $db->prepare("DELETE FROM nodes WHERE id = ? AND mindmap_id = ?")->execute([$nodeId, $mindmapId]);
}
