<?php
/**
 * Point d'entree - Cadre Logique
 * Redirige vers login ou l'application selon l'etat de connexion
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/../shared-auth/config.php';

// Si connecte avec une session active ET participant_id, aller vers l'app
if (isLoggedIn() && isset($_SESSION['current_session_id']) && isset($_SESSION['participant_id'])) {
    header('Location: app.php');
    exit;
}

// Sinon, aller vers login (et nettoyer la session incomplete)
if (isLoggedIn() && !isset($_SESSION['participant_id'])) {
    // Session incomplete, deconnecter pour recommencer proprement
    logout();
}

header('Location: login.php');
exit;
