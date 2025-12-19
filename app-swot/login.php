<?php
/**
 * Page de connexion - Analyse SWOT
 * Utilise le template partage
 */

$appName = 'Analyse SWOT';
$appColor = 'blue';
$redirectAfterLogin = 'index.php';
$showRegister = true;

// Charger shared-auth pour l'authentification
require_once __DIR__ . '/../shared-auth/config.php';
// Charger la config locale pour les sessions (meme base que formateur.php)
require_once __DIR__ . '/config/database.php';
$db = getDB();

// Inclure le template de connexion partage
require_once __DIR__ . '/../shared-auth/login-template.php';
