<?php
/**
 * Page de connexion - Analyse PESTEL
 * Utilise le template partage
 */

$appName = 'Analyse PESTEL';
$appColor = 'orange';
$redirectAfterLogin = 'index.php';
$showRegister = true;

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template de connexion partage
require_once __DIR__ . '/../shared-auth/login-template.php';
