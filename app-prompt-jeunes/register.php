<?php
/**
 * Page d'inscription - Prompt Engineering pour Public Jeune
 * Utilise le template partage
 */

$appName = 'Prompt Engineering';
$appColor = 'pink';
$appKey = 'app-prompt-jeunes';

// Charger la config locale
require_once __DIR__ . '/config.php';

// Inclure le template d'inscription partage
require_once __DIR__ . '/../shared-auth/register-template.php';
