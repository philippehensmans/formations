<?php
/**
 * Deconnexion globale - redirige vers la page d'accueil
 */
require_once __DIR__ . '/config.php';
logout();
header('Location: ../');
exit;
