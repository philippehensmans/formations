<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnostic des donnees</h2>";

require_once __DIR__ . '/config.php';

$db = getDB();
$sharedDb = getSharedDB();

echo "<h3>1. Sessions dans la base locale</h3>";
$sessions = $db->query("SELECT * FROM sessions")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($sessions);
echo "</pre>";

echo "<h3>2. Participants dans la base locale</h3>";
$participants = $db->query("SELECT * FROM participants")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($participants);
echo "</pre>";

echo "<h3>3. Calculs dans la base locale</h3>";
$calculs = $db->query("SELECT * FROM calculs")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($calculs);
echo "</pre>";

echo "<h3>4. Session en cours (depuis $_SESSION)</h3>";
echo "current_session_id: " . ($_SESSION['current_session_id'] ?? 'non defini') . "<br>";
echo "current_session_code: " . ($_SESSION['current_session_code'] ?? 'non defini') . "<br>";
echo "participant_id: " . ($_SESSION['participant_id'] ?? 'non defini') . "<br>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'non defini') . "<br>";

echo "<h3>5. Utilisateurs dans la base partagee</h3>";
$users = $sharedDb->query("SELECT id, username, prenom FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($users);
echo "</pre>";

echo "<h3>6. Test getEstimations()</h3>";
$estimations = getEstimations();
$useCases = $estimations['use_cases'] ?? [];
echo "Nombre de cas d'usage: " . count($useCases) . "<br>";
if (!empty($useCases)) {
    $first = array_keys($useCases)[0];
    echo "Premier cas: $first => " . $useCases[$first]['co2_grammes'] . "g<br>";
}
