<?php
/**
 * Page Formateur - Prompt Engineering pour Public Jeune
 * Utilise le template partage
 */

$appName = 'Prompt Engineering Jeunes';
$appColor = 'pink';
$appKey = 'app-prompt-jeunes';

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template formateur partage
require_once __DIR__ . '/../shared-auth/formateur-template.php';
