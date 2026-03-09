<?php
/**
 * Page Formateur - Guide du Prompting
 * Utilise le template partage
 */

$appName = 'Guide du Prompting';
$appColor = 'violet';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
