<?php
/**
 * Page Formateur - Tableau Blanc
 * Utilise le template partage
 */

$appName = 'Tableau Blanc Collaboratif';
$appColor = 'indigo';
$appKey = 'app-whiteboard';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
