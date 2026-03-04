<?php
/**
 * Page d'inscription - Craintes et Attentes
 * Utilise le template partage
 */

$appName = 'Craintes & Attentes';
$appColor = 'teal';
$redirectAfterLogin = 'app.php';

// Charger la config locale
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template d'inscription partage
require_once __DIR__ . '/../shared-auth/register-template.php';
