<?php
/**
 * Page de connexion - Carte d'identite du Projet
 */
require_once __DIR__ . '/config.php';
$db = getDB();

// Utiliser le template de login partage
require_once __DIR__ . '/../shared-auth/login-template.php';
