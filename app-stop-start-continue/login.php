<?php
/**
 * Page de connexion - Stop Start Continue
 * Utilise le template partage
 */

$appName = 'Stop Start Continue';
$appColor = 'pink';
$redirectAfterLogin = 'index.php';
$showRegister = true;

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template de connexion partage
require_once __DIR__ . '/../shared-auth/login-template.php';
