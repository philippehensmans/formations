<?php
/**
 * Page Formateur - Analyse SWOT
 * Utilise le template partage
 */

$appName = 'Analyse SWOT';
$appColor = 'green';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
