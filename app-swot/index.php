<?php
/**
 * Page d'accueil - Analyse SWOT
 * Redirige vers login ou l'application
 */
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/config/database.php';

// Si connecte avec une session, valider et aller a l'application
if (isLoggedIn() && isset($_SESSION['current_session_id'])) {
    $db = getDB();
    $sessionId = validateCurrentSession($db);
    if ($sessionId) {
        ensureParticipant($db, $sessionId, getLoggedUser());
        header('Location: swot_app.php');
        exit;
    }
    // Session invalide dans cette app, renvoyer au login
}

// Sinon, rediriger vers login
header('Location: login.php');
exit;
