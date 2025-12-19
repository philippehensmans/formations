<?php
/**
 * Page de connexion - Mesure d'Impact Social
 * Utilise le template partage
 */

// Debug temporaire
error_reporting(E_ALL);
ini_set('display_errors', 1);

$appName = 'Mesure d\'Impact Social';
$appColor = 'indigo';
$redirectAfterLogin = 'app.php';
$showRegister = true;

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config/database.php';
$db = getDB();

// Inclure le template de connexion partage
require_once __DIR__ . '/../shared-auth/login-template.php';
