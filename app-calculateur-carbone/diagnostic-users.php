<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnostic des utilisateurs formateurs</h2>";

require_once __DIR__ . '/config.php';

$sharedDb = getSharedDB();

echo "<h3>Utilisateurs avec droits formateur/admin</h3>";
$stmt = $sharedDb->query("SELECT id, username, prenom, nom, is_admin, is_formateur, is_super_admin FROM users WHERE is_formateur = 1 OR is_admin = 1 ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Prenom</th><th>Nom</th><th>Admin</th><th>Formateur</th><th>Super Admin</th></tr>";
foreach ($users as $u) {
    echo "<tr>";
    echo "<td>{$u['id']}</td>";
    echo "<td>{$u['username']}</td>";
    echo "<td>{$u['prenom']}</td>";
    echo "<td>{$u['nom']}</td>";
    echo "<td>" . ($u['is_admin'] ? 'Oui' : 'Non') . "</td>";
    echo "<td>" . ($u['is_formateur'] ? 'Oui' : 'Non') . "</td>";
    echo "<td>" . ($u['is_super_admin'] ? 'Oui' : 'Non') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Sessions par formateur</h3>";
$db = getDB();
$stmt = $db->query("SELECT formateur_id, COUNT(*) as nb FROM sessions GROUP BY formateur_id");
$byFormateur = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($byFormateur as $bf) {
    $stmtUser = $sharedDb->prepare("SELECT username, prenom FROM users WHERE id = ?");
    $stmtUser->execute([$bf['formateur_id']]);
    $user = $stmtUser->fetch();
    echo "Formateur ID {$bf['formateur_id']} ({$user['prenom']} - {$user['username']}): {$bf['nb']} sessions<br>";
}
