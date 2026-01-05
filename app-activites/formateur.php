<?php
/**
 * Page Formateur - Inventaire des Activites
 */

$appName = 'Inventaire des Activités';
$appColor = 'teal';
$appKey = 'app-activites';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
