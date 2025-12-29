<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test authentification formateur</h2>";

require_once __DIR__ . '/config.php';

echo "<h3>1. Session avant login</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session status: " . session_status() . " (1=disabled, 2=active)<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>2. Test authenticateUser()</h3>";
$testUser = authenticateUser('formateur', 'Formation2024!');
if ($testUser) {
    echo "Authentification OK - is_formateur=" . $testUser['is_formateur'] . ", is_admin=" . $testUser['is_admin'] . "<br>";

    echo "<h3>3. Appel login(\$user)</h3>";
    login($testUser);
    echo "Login() appele<br>";

    echo "<h3>4. Session apres login()</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";

    echo "<h3>5. Test getLoggedUser() apres login</h3>";
    $loggedUser = getLoggedUser();
    if ($loggedUser) {
        echo "getLoggedUser() OK: " . $loggedUser['username'] . "<br>";
        echo "is_formateur: " . $loggedUser['is_formateur'] . "<br>";
        echo "is_admin: " . $loggedUser['is_admin'] . "<br>";
    } else {
        echo "ERREUR: getLoggedUser() retourne null!<br>";
    }
} else {
    echo "ERREUR: Authentification echouee!<br>";
}

echo "<h3>6. Cookies</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<h3>7. Session save path</h3>";
echo session_save_path() ?: "(default)";
