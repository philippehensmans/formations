<?php
/**
 * API de sauvegarde de l'analyse PESTEL
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Donnees invalides']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

// Construire les donnees PESTEL completes (avec metadonnees)
$pestelData = [
    'politique' => $data['pestel_data']['politique'] ?? [''],
    'economique' => $data['pestel_data']['economique'] ?? [''],
    'socioculturel' => $data['pestel_data']['socioculturel'] ?? [''],
    'technologique' => $data['pestel_data']['technologique'] ?? [''],
    'environnemental' => $data['pestel_data']['environnemental'] ?? [''],
    'legal' => $data['pestel_data']['legal'] ?? [''],
    'participants_analyse' => $data['participants_analyse'] ?? '',
    'zone' => $data['zone'] ?? '',
    'synthese' => $data['synthese'] ?? '',
    'notes' => $data['notes'] ?? ''
];

// Calculer completion
$completion = calculateCompletion($pestelData);

// Mettre a jour ou inserer
$stmt = $db->prepare("SELECT id FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("
        UPDATE analyses SET
            titre_projet = ?,
            pestel_data = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND session_id = ?
    ");
    $stmt->execute([
        $data['nom_projet'] ?? '',
        json_encode($pestelData),
        $userId,
        $sessionId
    ]);
} else {
    $stmt = $db->prepare("
        INSERT INTO analyses (user_id, session_id, titre_projet, pestel_data)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $sessionId,
        $data['nom_projet'] ?? '',
        json_encode($pestelData)
    ]);
}

echo json_encode([
    'success' => true,
    'completion' => $completion
]);

/**
 * Calcule le pourcentage de completion
 */
function calculateCompletion($data) {
    $total = 0;
    $filled = 0;

    // Champs texte (5 points chacun)
    $textFields = ['zone', 'synthese'];
    foreach ($textFields as $field) {
        $total += 5;
        if (!empty($data[$field])) $filled += 5;
    }

    // Categories PESTEL (10 points chacune si au moins un element)
    $categories = ['politique', 'economique', 'socioculturel', 'technologique', 'environnemental', 'legal'];
    foreach ($categories as $cat) {
        $total += 10;
        if (!empty($data[$cat]) && is_array($data[$cat])) {
            foreach ($data[$cat] as $item) {
                if (!empty(trim($item))) {
                    $filled += 10;
                    break;
                }
            }
        }
    }

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}
?>
