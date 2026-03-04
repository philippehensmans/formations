<?php
/**
 * Deconnexion - Craintes et Attentes
 */
require_once __DIR__ . '/../shared-auth/config.php';

logout();
header('Location: login.php');
exit;
