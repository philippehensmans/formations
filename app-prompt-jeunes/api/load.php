<?php
/**
 * API Chargement - Prompt Engineering pour Public Jeune
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecte']);
    exit;
}

$db = getDB();
$user = getLoggedUser();

// Recuperer le numero d'exercice depuis GET ou POST
$exerciceNum = isset($_GET['exercice_num']) ? (int)$_GET['exercice_num'] : 1;

$stmt = $db->prepare("SELECT * FROM travaux WHERE user_id = ? AND session_id = ? AND exercice_num = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id'], $exerciceNum]);
$travail = $stmt->fetch();

if (!$travail) {
    echo json_encode(['success' => true, 'data' => null]);
    exit;
}

// Decoder les donnees JSON
$travail['synthese_cles'] = json_decode($travail['synthese_cles'] ?? '[]', true);

echo json_encode(['success' => true, 'data' => $travail]);
