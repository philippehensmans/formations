<?php
require_once __DIR__ . '/../shared-auth/auth.php';
logout();
header('Location: login.php');
exit;
