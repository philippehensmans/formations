<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnostic des sessions</h2>";

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<h3>1. Toutes les sessions (formateur.php)</h3>";
$stmt = $db->query("SELECT * FROM sessions ORDER BY created_at DESC");
$allSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($allSessions);
echo "</pre>";

echo "<h3>2. Sessions actives uniquement (login.php)</h3>";
$stmt = $db->query("SELECT * FROM sessions WHERE is_active = 1 ORDER BY created_at DESC");
$activeSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($activeSessions);
echo "</pre>";

echo "<h3>3. Chemin de la base de donnees</h3>";
echo "DB_PATH: " . DB_PATH . "<br>";
echo "Existe: " . (file_exists(DB_PATH) ? 'Oui' : 'Non') . "<br>";

echo "<h3>4. Comparaison</h3>";
echo "Total sessions: " . count($allSessions) . "<br>";
echo "Sessions actives: " . count($activeSessions) . "<br>";
echo "Sessions inactives: " . (count($allSessions) - count($activeSessions)) . "<br>";
