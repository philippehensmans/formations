<?php
/**
 * Page de connexion - Prompt Engineering pour Public Jeune
 * Utilise le template partage
 */

$appName = 'Prompt Engineering Jeunes';
$appColor = 'pink';
$redirectAfterLogin = 'app.php';
$showRegister = true;

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template de connexion partage
require_once __DIR__ . '/../shared-auth/login-template.php';
