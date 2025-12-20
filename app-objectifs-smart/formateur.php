<?php
/**
 * Page formateur - Objectifs SMART
 * Utilise le template partage
 */

$appName = 'Objectifs SMART';
$appColor = 'emerald';
$appKey = 'app-objectifs-smart';

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template formateur partage
require_once __DIR__ . '/../shared-auth/formateur-template.php';
