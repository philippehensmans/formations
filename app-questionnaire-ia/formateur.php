<?php
/**
 * Page Formateur - Questionnaire IA
 * Utilise le template partage
 */

$appName = 'Questionnaire IA';
$appColor = 'sky';
$appKey = 'app-questionnaire-ia';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
