<?php
$appName = 'Préparation à l\'interview';
$appColor = 'rose';
$redirectAfterLogin = 'index.php';
$showRegister = true;

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/login-template.php';
