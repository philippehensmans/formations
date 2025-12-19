<?php
/**
 * Page Formateur - Analyse PESTEL
 * Utilise le template partage
 */

$appName = 'Analyse PESTEL';
$appColor = 'orange';

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template formateur partage
require_once __DIR__ . '/../shared-auth/formateur-template.php';
