<?php
/**
 * Page formateur - Journey Mapping
 * Utilise le template partage
 */

$appName = 'Journey Mapping';
$appColor = 'cyan';
$appKey = 'app-journey-mapping';

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template formateur partage
require_once __DIR__ . '/../shared-auth/formateur-template.php';
