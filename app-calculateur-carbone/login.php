<?php
$appName = 'Calculateur Carbone IA';
$appColor = 'emerald';
$redirectAfterLogin = 'app.php';
$showRegister = true;

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/login-template.php';
