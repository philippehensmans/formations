<?php
$appName = 'Tableau Blanc';
$appColor = 'indigo';
require_once __DIR__ . '/config.php';
$db = getDB();
require_once __DIR__ . '/../shared-auth/login-template.php';
