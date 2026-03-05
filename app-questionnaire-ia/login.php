<?php
/**
 * Page de connexion - Questionnaire IA
 * Utilise le template partage
 */

$appName = 'Questionnaire IA';
$appColor = 'sky';
$redirectAfterLogin = 'app.php';
$showRegister = true;

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/login-template.php';
