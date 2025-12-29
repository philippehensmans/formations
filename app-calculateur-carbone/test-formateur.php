<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test formateur.php</h2>";

echo "<h3>1. Chargement config.php</h3>";
require_once __DIR__ . '/config.php';
echo "OK<br>";

echo "<h3>2. getLoggedUser()</h3>";
$user = getLoggedUser();
echo "User: " . ($user ? $user['username'] : 'null') . "<br>";

echo "<h3>3. getDB()</h3>";
$db = getDB();
echo "OK<br>";

echo "<h3>4. Session</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>5. Test condition formateur</h3>";
if (!$user) {
    echo "User is null - afficherait login form<br>";
} elseif (!$user['is_formateur'] && !$user['is_admin']) {
    echo "User n'est ni formateur ni admin - afficherait login form<br>";
} else {
    echo "User est formateur/admin - afficherait dashboard<br>";
}

echo "<h3>6. Variables disponibles</h3>";
echo "APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'non defini') . "<br>";
echo "h() exists: " . (function_exists('h') ? 'oui' : 'non') . "<br>";
