<?php
require_once __DIR__ . '/config.php';

$user = getLoggedUser();

// Non connecte -> login
if (!$user || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

// Connecte -> app
header('Location: app.php');
exit;
