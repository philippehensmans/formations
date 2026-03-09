<?php
/**
 * Page Formateur - Calculateur Carbone
 * Utilise le template partage
 */

$appName = 'Calculateur Carbone';
$appColor = 'emerald';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
