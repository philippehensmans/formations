<?php
/**
 * Page Formateur - Cahier des Charges
 * Utilise le template partage
 */

$appName = 'Cahier des Charges Associatif';
$appColor = 'blue';

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template formateur partage
require_once __DIR__ . '/../shared-auth/formateur-template.php';
