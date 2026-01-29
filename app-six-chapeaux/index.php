<?php
/**
 * Index - Six Chapeaux de Bono
 * Redirige vers l'application principale
 */
require_once __DIR__ . '/config.php';

// Si l'utilisateur est connecte avec une session, aller vers app.php
if (isLoggedIn() && isset($_SESSION['current_session_id'])) {
    header('Location: app.php');
    exit;
}

// Sinon, aller vers login
header('Location: login.php');
exit;
