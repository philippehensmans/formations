<?php
/**
 * Page de connexion - Cadre Logique
 * Utilise le template partage
 */

$appName = 'Cadre Logique';
$appColor = 'indigo';
$redirectAfterLogin = 'index.php';
$showRegister = true;

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/../shared-auth/config.php';
$db = getDB();

// Inclure le template de connexion partage
require_once __DIR__ . '/../shared-auth/login-template.php';
