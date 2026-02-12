<?php
$appName = 'Pilotage de Projet';
$appColor = 'emerald';
$redirectAfterLogin = 'index.php';
$showRegister = true;
$restrictedApp = 'app-pilotage-projet';
require_once __DIR__ . '/config.php';
$db = getDB();
require_once __DIR__ . '/../shared-auth/login-template.php';
