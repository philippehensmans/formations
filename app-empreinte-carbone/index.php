<?php
require_once __DIR__ . '/../shared-auth/auth.php';

// Rediriger vers login ou app selon l'état de connexion
if (isLoggedIn()) {
    header('Location: app.php');
} else {
    header('Location: login.php');
}
exit;
