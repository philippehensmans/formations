<?php
/**
 * Page de connexion - Carte d'identite du Projet
 * Utilise le template partage
 */

$appName = 'Carte d\'identite du Projet';
$appColor = 'purple';
$redirectAfterLogin = 'index.php';
$showRegister = true;

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Utiliser le template de login partage
require_once __DIR__ . '/../shared-auth/login-template.php';
