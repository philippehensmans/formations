<?php
/**
 * Page Formateur - Mesure d'Impact
 * Utilise le template partage
 */

$appName = 'Mesure d\'Impact';
$appColor = 'emerald';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
