<?php
/**
 * Page de connexion - Carte du Projet
 * Utilise le template partage
 */

$appName = 'Carte du Projet';
$appColor = 'teal';
$redirectAfterLogin = 'index.php';
$showRegister = true;

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template de connexion partage
require_once __DIR__ . '/../shared-auth/login-template.php';
