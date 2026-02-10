<?php
/**
 * Page Formateur - Carte d'identite du Projet
 * Utilise le template partage
 */

$appName = 'Carte d\'identite du Projet';
$appColor = 'purple';
$appKey = 'app-carte-identite';

// Charger la config locale pour avoir acces a la base des sessions
require_once __DIR__ . '/config.php';
$db = getDB();

// Inclure le template formateur partage
require_once __DIR__ . '/../shared-auth/formateur-template.php';
