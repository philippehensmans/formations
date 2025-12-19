<?php
/**
 * Page d'accueil - Analyse SWOT
 * Redirige vers login ou l'application
 */
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/config/database.php';

// Si connecte avec une session, aller a l'application
if (isLoggedIn() && isset($_SESSION['current_session_id'])) {
    header('Location: swot_app.php');
    exit;
}

// Sinon, rediriger vers login
header('Location: login.php');
exit;
