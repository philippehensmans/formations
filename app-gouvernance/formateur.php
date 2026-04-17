<?php
$appName = 'Évaluateur de Gouvernance';
$appColor = 'indigo';
$appKey = 'app-gouvernance';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
