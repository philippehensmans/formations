<?php
/**
 * Page d'inscription - Questionnaire IA
 * Utilise le template partage
 */

$appName = 'Questionnaire IA';
$appColor = 'sky';
$redirectAfterLogin = 'app.php';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/register-template.php';
