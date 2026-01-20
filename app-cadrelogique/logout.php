<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/../shared-auth/config.php';
logout();
header('Location: login.php');
exit;
