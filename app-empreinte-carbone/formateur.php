<?php
/**
 * Page Formateur - Empreinte Carbone
 * Utilise le template partage
 */

$appName = 'Empreinte Carbone';
$appColor = 'green';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
