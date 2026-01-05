<?php
require_once __DIR__ . '/config.php';

// Handle export (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export') {
    if (!isLoggedIn()) {
        http_response_code(401);
        exit('Non autorisé');
    }

    $sessionId = intval($_GET['session_id'] ?? 0);
    $activites = getActivites($sessionId);
    $categories = getCategories();
    $frequences = getFrequences();
    $priorites = getPriorites();
    $stats = getStatistiques($sessionId);

    // Get session info
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventaire-activites-' . ($session['code'] ?? 'export') . '.html"');

    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inventaire des Activités - ' . htmlspecialchars($session['nom'] ?? '') . '</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; }
        h1 { color: #0d9488; border-bottom: 2px solid #0d9488; padding-bottom: 10px; }
        h2 { color: #374151; margin-top: 30px; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat { background: #f3f4f6; padding: 15px; border-radius: 8px; text-align: center; flex: 1; }
        .stat-value { font-size: 24px; font-weight: bold; color: #0d9488; }
        .stat-label { font-size: 12px; color: #6b7280; }
        .activity { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin: 10px 0; }
        .activity-header { display: flex; justify-content: space-between; align-items: center; }
        .activity-name { font-weight: bold; font-size: 16px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 5px; }
        .badge-ia { background: #d1fae5; color: #065f46; }
        .badge-cat { background: #e0e7ff; color: #3730a3; }
        .badge-freq { background: #f3f4f6; color: #374151; }
        .activity-desc { color: #6b7280; margin: 8px 0; font-size: 14px; }
        .activity-notes { background: #ecfdf5; padding: 10px; border-radius: 4px; margin-top: 10px; font-size: 13px; color: #065f46; }
        .category-section { margin-top: 30px; }
        .category-title { background: #f3f4f6; padding: 10px 15px; border-radius: 8px; font-weight: bold; }
        @media print { body { max-width: 100%; } }
    </style>
</head>
<body>
    <h1>📋 Inventaire des Activités</h1>
    <p><strong>Session:</strong> ' . htmlspecialchars($session['nom'] ?? '') . ' (' . htmlspecialchars($session['code'] ?? '') . ')</p>
    <p><strong>Date d\'export:</strong> ' . date('d/m/Y H:i') . '</p>

    <div class="stats">
        <div class="stat">
            <div class="stat-value">' . $stats['total'] . '</div>
            <div class="stat-label">Activités</div>
        </div>
        <div class="stat">
            <div class="stat-value">' . $stats['avec_potentiel_ia'] . '</div>
            <div class="stat-label">Potentiel IA</div>
        </div>
        <div class="stat">
            <div class="stat-value">' . ($stats['total'] > 0 ? round(($stats['avec_potentiel_ia'] / $stats['total']) * 100) : 0) . '%</div>
            <div class="stat-label">% IA</div>
        </div>
    </div>';

    // Group by category
    $byCategory = [];
    foreach ($activites as $act) {
        $cat = $act['categorie'];
        if (!isset($byCategory[$cat])) $byCategory[$cat] = [];
        $byCategory[$cat][] = $act;
    }

    foreach ($byCategory as $catKey => $acts) {
        $cat = $categories[$catKey] ?? $categories['autre'];
        echo '<div class="category-section">
            <div class="category-title">' . $cat['icon'] . ' ' . $cat['label'] . ' (' . count($acts) . ')</div>';

        foreach ($acts as $act) {
            echo '<div class="activity">
                <div class="activity-header">
                    <span class="activity-name">' . htmlspecialchars($act['nom']);
            if ($act['potentiel_ia']) {
                echo ' <span class="badge badge-ia">🤖 Potentiel IA</span>';
            }
            echo '</span>
                    <span>
                        <span class="badge badge-freq">' . ($frequences[$act['frequence']] ?? $act['frequence']) . '</span>
                    </span>
                </div>';
            if ($act['description']) {
                echo '<div class="activity-desc">' . htmlspecialchars($act['description']) . '</div>';
            }
            if ($act['temps_estime']) {
                echo '<div style="font-size:12px;color:#6b7280;">⏱️ Temps estimé: ' . htmlspecialchars($act['temps_estime']) . '</div>';
            }
            if ($act['notes_ia']) {
                echo '<div class="activity-notes">💡 <strong>Comment l\'IA peut aider:</strong> ' . htmlspecialchars($act['notes_ia']) . '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    echo '</body></html>';
    exit;
}

// Handle JSON API requests
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user = getLoggedUser();
$db = getDB();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'create':
        try {
            $nom = trim($input['nom'] ?? '');
            if (empty($nom)) {
                echo json_encode(['success' => false, 'error' => 'Nom requis']);
                exit;
            }
            $stmt = $db->prepare("
                INSERT INTO activites (session_id, nom, description, categorie, frequence, temps_estime, priorite, potentiel_ia, notes_ia, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                intval($input['session_id']),
                $nom,
                $input['description'] ?? '',
                $input['categorie'] ?? 'autre',
                $input['frequence'] ?? 'ponctuelle',
                $input['temps_estime'] ?? '',
                intval($input['priorite'] ?? 2),
                intval($input['potentiel_ia'] ?? 0),
                $input['notes_ia'] ?? '',
                $user['id'],
                $user['id']
            ]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'update':
        try {
            $nom = trim($input['nom'] ?? '');
            if (empty($nom)) {
                echo json_encode(['success' => false, 'error' => 'Nom requis']);
                exit;
            }
            $stmt = $db->prepare("
                UPDATE activites
                SET nom = ?, description = ?, categorie = ?, frequence = ?, temps_estime = ?, priorite = ?, potentiel_ia = ?, notes_ia = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $nom,
                $input['description'] ?? '',
                $input['categorie'] ?? 'autre',
                $input['frequence'] ?? 'ponctuelle',
                $input['temps_estime'] ?? '',
                intval($input['priorite'] ?? 2),
                intval($input['potentiel_ia'] ?? 0),
                $input['notes_ia'] ?? '',
                $user['id'],
                intval($input['id'])
            ]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete':
        $stmt = $db->prepare("DELETE FROM activites WHERE id = ?");
        $stmt->execute([intval($input['id'])]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
