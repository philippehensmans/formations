<?php
require_once __DIR__ . '/config.php';

// Rediriger vers login ou app selon l'etat de connexion
if (isLoggedIn()) {
    header('Location: app.php');
} else {
    header('Location: login.php');
}
exit;
