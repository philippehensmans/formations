<?php
$appName = 'Empreinte Carbone IA';
$appColor = 'green';
$appEmoji = '🌱';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/register-template.php';
