<?php
$appName = 'Préparation à l\'interview';
$appColor = 'rose';
$appKey = 'app-interviews';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
