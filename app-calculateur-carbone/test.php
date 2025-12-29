<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test authentification formateur</h2>";

require_once __DIR__ . '/config.php';

echo "<h3>1. Test getSharedDB() (base utilisateurs)</h3>";
$sharedDb = getSharedDB();
echo "SharedDB OK<br>";

echo "<h3>2. Verification utilisateur 'formateur' dans la base</h3>";
$stmt = $sharedDb->query("SELECT id, username, prenom, nom, is_admin, is_formateur FROM users WHERE username = 'formateur'");
$formateur = $stmt->fetch(PDO::FETCH_ASSOC);

if ($formateur) {
    echo "<pre>";
    print_r($formateur);
    echo "</pre>";
} else {
    echo "ERREUR: Utilisateur 'formateur' non trouve dans la base!<br>";

    echo "<h3>Liste des utilisateurs:</h3>";
    $stmt = $sharedDb->query("SELECT id, username, is_admin, is_formateur FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($users);
    echo "</pre>";
}

echo "<h3>3. Test authenticateUser()</h3>";
$testUser = authenticateUser('formateur', 'Formation2024!');
if ($testUser) {
    echo "Authentification OK<br>";
    echo "<pre>";
    print_r($testUser);
    echo "</pre>";

    echo "<h3>4. Verification is_formateur</h3>";
    echo "is_formateur = " . ($testUser['is_formateur'] ?? 'NON DEFINI') . "<br>";
    echo "is_admin = " . ($testUser['is_admin'] ?? 'NON DEFINI') . "<br>";

    $check = ($testUser['is_formateur'] || $testUser['is_admin']);
    echo "Condition (\$user['is_formateur'] || \$user['is_admin']) = " . ($check ? 'TRUE' : 'FALSE') . "<br>";
} else {
    echo "ERREUR: Authentification echouee!<br>";
}

echo "<h3>5. Session actuelle</h3>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
