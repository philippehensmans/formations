<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Donnees invalides']);
    exit;
}

$db = getDB();
$user = getLoggedUser();
$sessionId = $_SESSION['current_session_id'];

// Calculer completion
$completion = 0;
$etape1 = $input['etape1_analyses'] ?? [];
$etape2 = $input['etape2_reformulations'] ?? [];
$etape3 = $input['etape3_creations'] ?? [];

// Etape 1: analyses
$objectifsAnalyse = getObjectifsAnalyse();
$total1 = count($objectifsAnalyse) * 5;
$filled1 = 0;
foreach ($etape1 as $a) {
    if (isset($a['evaluations'])) {
        foreach ($a['evaluations'] as $e) {
            if (!empty($e['reponse'])) $filled1++;
        }
    }
}

// Etape 2: reformulations
$objectifsReform = getObjectifsReformulation();
$total2 = count($objectifsReform) * 5;
$filled2 = 0;
foreach ($etape2 as $r) {
    if (isset($r['composantes'])) {
        foreach ($r['composantes'] as $c) {
            if (!empty($c)) $filled2++;
        }
    }
}

// Etape 3: creations
$filled3 = 0;
$total3 = 5; // Au moins 1 objectif avec 5 composantes
foreach ($etape3 as $c) {
    if (isset($c['composantes'])) {
        foreach ($c['composantes'] as $comp) {
            if (!empty($comp)) $filled3++;
        }
    }
}

$totalAll = $total1 + $total2 + $total3;
$filledAll = $filled1 + $filled2 + $filled3;
$completion = $totalAll > 0 ? round(($filledAll / $totalAll) * 100) : 0;

$stmt = $db->prepare("UPDATE objectifs_smart SET
    etape_courante = ?,
    etape1_analyses = ?,
    etape2_reformulations = ?,
    etape3_creations = ?,
    completion_percent = ?,
    updated_at = CURRENT_TIMESTAMP
    WHERE user_id = ? AND session_id = ?");

$stmt->execute([
    $input['etape_courante'] ?? 1,
    json_encode($etape1),
    json_encode($etape2),
    json_encode($etape3),
    $completion,
    $user['id'],
    $sessionId
]);

echo json_encode(['success' => true, 'completion' => $completion]);
