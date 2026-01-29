<?php
/**
 * Page d'inscription - Six Chapeaux de Bono
 * Utilise le template partage
 */

$appName = 'Six Chapeaux';
$appColor = 'indigo';
$redirectAfterLogin = 'app.php';

// Charger la config locale
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template d'inscription partage
require_once __DIR__ . '/../shared-auth/register-template.php';
