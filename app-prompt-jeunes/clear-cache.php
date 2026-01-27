<?php
/**
 * Script temporaire pour vider le cache OPcache
 * À supprimer après utilisation
 */

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache vidé avec succès. Vous pouvez supprimer ce fichier.";
} else {
    // Invalider les fichiers spécifiques
    $files = [
        __DIR__ . '/config.php',
        __DIR__ . '/app.php',
        __DIR__ . '/../shared-auth/lang.php',
        __DIR__ . '/../shared-auth/lang/fr.php'
    ];

    foreach ($files as $file) {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
    echo "Cache invalidé. Vous pouvez supprimer ce fichier.";
}
