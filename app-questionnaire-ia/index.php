<?php
/**
 * Index - Questionnaire IA
 * Redirige vers l'application principale
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn() && isset($_SESSION['current_session_id'])) {
    header('Location: app.php');
    exit;
}

header('Location: login.php');
exit;
