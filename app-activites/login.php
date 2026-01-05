<?php
$appName = 'Inventaire des Activités';
$appColor = 'teal';
$redirectAfterLogin = 'app.php';
require_once __DIR__ . '/config.php';
$db = getDB();
require_once __DIR__ . '/../shared-auth/login-template.php';
