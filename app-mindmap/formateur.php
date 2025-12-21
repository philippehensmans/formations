<?php
/**
 * Page Formateur - Carte Mentale
 * Utilise le template partage
 */

$appName = 'Carte Mentale Collaborative';
$appColor = 'violet';
$appKey = 'app-mindmap';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
