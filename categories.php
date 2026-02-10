<?php
/**
 * Chargement des categories d'applications
 *
 * Les categories sont stockees dans categories.json
 * et gerees via l'interface admin (admin-categories.php)
 */

$jsonPath = __DIR__ . '/categories.json';
if (file_exists($jsonPath)) {
    $data = json_decode(file_get_contents($jsonPath), true);
    if ($data && isset($data['categories'])) {
        return $data;
    }
}

// Fallback si le JSON est absent ou invalide
return ['categories' => [], 'apps' => []];
