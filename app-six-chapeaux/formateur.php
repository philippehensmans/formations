<?php
/**
 * Page Formateur - Six Chapeaux de Bono
 * Utilise le template partage
 */

$appName = 'Six Chapeaux';
$appColor = 'indigo';
$appKey = 'app-six-chapeaux';

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template formateur partage
require_once __DIR__ . '/../shared-auth/formateur-template.php';
