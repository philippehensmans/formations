<?php
/**
 * Point d'entree - Journey Mapping
 * Redirige vers login ou l'application selon l'etat de connexion
 */
require_once __DIR__ . '/config.php';

// Si connecte avec une session active, aller vers l'app
if (isLoggedIn() && isset($_SESSION['current_session_id'])) {
    header('Location: app.php');
    exit;
}

// Sinon, aller vers login
header('Location: login.php');
exit;
