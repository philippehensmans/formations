<?php
/**
 * Page Formateur - Analyse SWOT
 * Utilise le template partage
 */

$appName = 'Analyse SWOT';
$appColor = 'green';

$appKey = 'app-swot';

// Base unique de l'app (swot_analyzer.db) - meme source que l'app participant
require_once __DIR__ . '/config/database.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
