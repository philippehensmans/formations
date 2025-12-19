<?php
/**
 * Page de connexion - Arbre a Problemes
 * Utilise le template partage
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$appName = 'Arbre a Problemes';
$appColor = 'amber';
$redirectAfterLogin = 'index.php';
$showRegister = true;

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template de connexion partage
require_once __DIR__ . '/../shared-auth/login-template.php';
