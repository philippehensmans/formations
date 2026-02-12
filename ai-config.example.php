<?php
/**
 * Configuration de l'API Claude (Anthropic)
 * Ce fichier est gitignore - ne pas commiter avec la cle API
 *
 * Instructions :
 * 1. Copiez ce fichier dans ai-config.php a la racine du projet
 * 2. Remplacez 'YOUR_API_KEY_HERE' par votre cle API Anthropic
 * 3. Le fichier est automatiquement ignore par git
 */

define('ANTHROPIC_API_KEY', 'YOUR_API_KEY_HERE');
define('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929');
define('ANTHROPIC_MAX_TOKENS', 4096);
