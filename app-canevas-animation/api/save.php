<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$input = file_get_contents('php://input');
$payload = json_decode($input, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$userId = $user['id'];
$sessionId = $_SESSION['current_session_id'];

// Vérifier que le canevas n'est pas soumis
$stmt = $db->prepare("SELECT id, is_shared FROM canevas WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$existing = $stmt->fetch();

if ($existing && $existing['is_shared']) {
    http_response_code(403);
    echo json_encode(['error' => 'Canevas déjà soumis']);
    exit;
}

$completion = calculateCompletion($payload);
$clean = json_encode($payload, JSON_UNESCAPED_UNICODE);

if ($existing) {
    $stmt = $db->prepare("UPDATE canevas SET data = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$clean, $userId, $sessionId]);
} else {
    $stmt = $db->prepare("INSERT INTO canevas (user_id, session_id, data) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $clean]);
}

echo json_encode(['success' => true, 'completion' => $completion]);

function calculateCompletion($d) {
    $total = 0;
    $filled = 0;

    // Identification (5pts)
    $total += 5;
    if (!empty(trim($d['animateur'] ?? '')) && !empty(trim($d['date_lieu'] ?? '')) && !empty(trim($d['classe_groupe'] ?? ''))) {
        $filled += 5;
    } elseif (!empty(trim($d['animateur'] ?? '')) || !empty(trim($d['date_lieu'] ?? ''))) {
        $filled += 2;
    }

    // Cadrage (25pts)
    $total += 25;
    if (!empty($d['public'] ?? '')) $filled += 5;
    if (!empty(trim($d['objectif_principal'] ?? ''))) $filled += 10;
    if (!empty(trim($d['fil_rouge'] ?? ''))) $filled += 5;
    if (!empty(trim($d['objectif_sec_1'] ?? '')) || !empty(trim($d['objectif_sec_2'] ?? ''))) $filled += 5;

    // Séquençage (20pts)
    $total += 20;
    $sequences = $d['sequences'] ?? [];
    $filledSeq = 0;
    foreach ($sequences as $s) {
        if (!empty(trim($s['objectif'] ?? '')) || !empty(trim($s['activite'] ?? ''))) $filledSeq++;
    }
    if ($filledSeq >= 5) $filled += 20;
    elseif ($filledSeq >= 3) $filled += 15;
    elseif ($filledSeq >= 1) $filled += 7;

    // Outils (15pts)
    $total += 15;
    if (!empty(trim($d['outil_projete_1'] ?? ''))) $filled += 5;
    if (!empty(trim($d['outil_manipule_1'] ?? ''))) $filled += 7;
    if (!empty(trim($d['plan_b'] ?? ''))) $filled += 3;

    // Points d'attention (15pts) — viser 3-4 minimum
    $total += 15;
    $nb = count($d['points_coches'] ?? []);
    if ($nb >= 4) $filled += 15;
    elseif ($nb >= 3) $filled += 12;
    elseif ($nb >= 2) $filled += 7;
    elseif ($nb >= 1) $filled += 3;

    // Matériel (10pts)
    $total += 10;
    $matSum = count($d['materiel_salle'] ?? []) + count($d['materiel_formateur'] ?? []) + count($d['materiel_eleves'] ?? []);
    if ($matSum >= 8) $filled += 7;
    elseif ($matSum >= 4) $filled += 4;
    elseif ($matSum >= 1) $filled += 2;
    if (!empty(trim($d['preparation_j1'] ?? ''))) $filled += 3;

    // Évaluation (10pts)
    $total += 10;
    if (!empty($d['modalite_eval'] ?? '')) $filled += 5;
    if (!empty(trim($d['bilan_marche'] ?? '')) || !empty(trim($d['bilan_coince'] ?? '')) || !empty(trim($d['bilan_change'] ?? ''))) $filled += 3;
    if (!empty(trim($d['suivi_enseignant'] ?? ''))) $filled += 2;

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}
