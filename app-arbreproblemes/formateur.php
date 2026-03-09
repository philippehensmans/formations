<?php
/**
 * Page Formateur - Arbre a Problemes
 * Utilise le template partage
 */

$appName = 'Arbre a Problemes';
$appColor = 'amber';

require_once __DIR__ . '/config.php';
$db = getDB();

require_once __DIR__ . '/../shared-auth/formateur-template.php';
