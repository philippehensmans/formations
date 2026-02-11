<?php
$appName = 'Mini-Plan de Communication';
$appColor = 'indigo';
$redirectAfterLogin = 'index.php';
$showRegister = true;
require_once __DIR__ . '/config.php';
$db = getDB();
require_once __DIR__ . '/../shared-auth/login-template.php';
