<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Correction des calculs CO2</h2>";

require_once __DIR__ . '/config.php';

$db = getDB();

// Recuperer tous les calculs
$calculs = $db->query("SELECT * FROM calculs")->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Nombre de calculs a corriger: " . count($calculs) . "</p>";

$corriges = 0;
foreach ($calculs as $calc) {
    $newCO2 = calculerCO2($calc['use_case_id'], $calc['frequence'], $calc['quantite']);

    if ($newCO2 != $calc['co2_total']) {
        $stmt = $db->prepare("UPDATE calculs SET co2_total = ? WHERE id = ?");
        $stmt->execute([$newCO2, $calc['id']]);

        echo "<p>Calcul #{$calc['id']} ({$calc['use_case_id']}): {$calc['co2_total']}g -> {$newCO2}g</p>";
        $corriges++;
    }
}

echo "<h3>Termine: $corriges calculs corriges</h3>";

echo "<h3>Verification:</h3>";
$calculs = $db->query("SELECT * FROM calculs")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($calculs);
echo "</pre>";
