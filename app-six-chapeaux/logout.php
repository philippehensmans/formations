<?php
/**
 * Deconnexion - Six Chapeaux de Bono
 */
require_once __DIR__ . '/../shared-auth/config.php';

logout();
header('Location: login.php');
exit;
