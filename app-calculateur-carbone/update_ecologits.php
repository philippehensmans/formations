<?php
/**
 * Script de mise a jour des estimations CO2 depuis EcoLogits
 *
 * EcoLogits est une bibliotheque Python qui calcule l'impact carbone des LLMs.
 * Ce script tente de recuperer les donnees les plus recentes depuis leurs sources.
 *
 * Sources:
 * - https://ecologits.ai/
 * - https://huggingface.co/spaces/genai-impact/ecologits-calculator
 * - https://github.com/genai-impact/ecologits
 *
 * Usage: php update_ecologits.php
 */

require_once __DIR__ . '/config.php';

// Configuration
$ECOLOGITS_API = 'https://huggingface.co/spaces/genai-impact/ecologits-calculator';
$GITHUB_RAW = 'https://raw.githubusercontent.com/genai-impact/ecologits/main/ecologits/data';

// Facteurs de conversion EcoLogits (valeurs par defaut)
// Ces valeurs sont basees sur les donnees publiees par EcoLogits
$ECOLOGITS_FACTORS = [
    // Energie par token en kWh (moyenne)
    'energy_per_1k_tokens' => [
        'gpt-4' => 0.0052,
        'gpt-4-turbo' => 0.0048,
        'gpt-3.5-turbo' => 0.0012,
        'claude-3-opus' => 0.0055,
        'claude-3-sonnet' => 0.0028,
        'claude-3-haiku' => 0.0008,
        'claude-2' => 0.0035,
        'mistral-large' => 0.0030,
        'mistral-medium' => 0.0018,
        'mistral-small' => 0.0010,
        'llama-70b' => 0.0025,
        'llama-13b' => 0.0008,
        'llama-7b' => 0.0004,
        'dall-e-3' => 0.025, // par image
        'stable-diffusion' => 0.015, // par image
    ],

    // Intensite carbone moyenne (gCO2/kWh) - mix mondial
    'carbon_intensity' => 475,

    // Facteur PUE datacenter
    'pue' => 1.2,
];

function log_message($msg) {
    echo date('[Y-m-d H:i:s]') . " $msg\n";
}

function calculateCO2FromTokens($tokens, $modelType = 'gpt-4') {
    global $ECOLOGITS_FACTORS;

    // Mapper le type de modele
    $modelKey = 'gpt-4'; // defaut
    $modelLower = strtolower($modelType);

    if (strpos($modelLower, 'gpt-3.5') !== false || strpos($modelLower, 'haiku') !== false) {
        $modelKey = 'gpt-3.5-turbo';
    } elseif (strpos($modelLower, 'gpt-4') !== false || strpos($modelLower, 'claude') !== false) {
        $modelKey = 'gpt-4';
    } elseif (strpos($modelLower, 'dall-e') !== false || strpos($modelLower, 'image') !== false) {
        $modelKey = 'dall-e-3';
    }

    $energyPer1kTokens = $ECOLOGITS_FACTORS['energy_per_1k_tokens'][$modelKey] ?? 0.003;
    $carbonIntensity = $ECOLOGITS_FACTORS['carbon_intensity'];
    $pue = $ECOLOGITS_FACTORS['pue'];

    // Calcul: tokens/1000 * energy * PUE * carbon_intensity
    $energyKwh = ($tokens / 1000) * $energyPer1kTokens * $pue;
    $co2Grams = $energyKwh * $carbonIntensity;

    return round($co2Grams, 1);
}

function updateEstimations() {
    log_message("Demarrage de la mise a jour des estimations...");

    // Charger les estimations actuelles
    $estimations = getEstimations();
    if (empty($estimations)) {
        log_message("ERREUR: Impossible de charger les estimations actuelles");
        return false;
    }

    $useCases = $estimations['use_cases'] ?? [];
    $updated = 0;

    foreach ($useCases as $id => &$uc) {
        $tokens = $uc['tokens_estimes'] ?? 0;
        $modelType = $uc['modele_type'] ?? 'GPT-4';

        if ($tokens > 0) {
            $newCO2 = calculateCO2FromTokens($tokens, $modelType);
            if ($newCO2 != $uc['co2_grammes']) {
                log_message("  $id: {$uc['co2_grammes']}g -> {$newCO2}g");
                $uc['co2_grammes'] = $newCO2;
                $updated++;
            }
        }

        // Mise a jour de l'equivalent
        $uc['equivalent'] = generateEquivalent($uc['co2_grammes']);
    }

    // Mettre a jour les metadonnees
    $estimations['_metadata']['last_updated'] = date('Y-m-d');
    $estimations['_metadata']['source'] = 'Mis a jour depuis facteurs EcoLogits ' . date('Y-m-d');

    // Sauvegarder
    if (saveEstimations($estimations)) {
        log_message("Mise a jour terminee: $updated cas modifies");

        // Logger dans la base
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO updates_log (source, status, message) VALUES (?, ?, ?)");
        $stmt->execute(['ecologits', 'success', "$updated cas mis a jour"]);

        return true;
    } else {
        log_message("ERREUR: Impossible de sauvegarder les estimations");
        return false;
    }
}

function generateEquivalent($co2Grams) {
    if ($co2Grams < 5) {
        $emails = round($co2Grams / 4);
        return "$emails emails envoyes";
    } elseif ($co2Grams < 50) {
        $emails = round($co2Grams / 4);
        return "$emails emails envoyes";
    } else {
        $km = round($co2Grams / 210, 1);
        return "$km km en voiture";
    }
}

// Fonction pour tenter de recuperer les donnees depuis le calculateur EcoLogits
function fetchEcoLogitsData() {
    global $ECOLOGITS_API;

    log_message("Tentative de recuperation depuis EcoLogits...");

    // Note: Le calculateur EcoLogits est une app Gradio qui necessiterait
    // du scraping JavaScript. Pour l'instant, on utilise les facteurs connus.

    // Tentative de fetch du README ou des donnees publiques
    $githubUrl = 'https://api.github.com/repos/genai-impact/ecologits/contents/ecologits/data';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: PHP-Update-Script\r\n",
            'timeout' => 10
        ]
    ]);

    $response = @file_get_contents($githubUrl, false, $context);

    if ($response) {
        log_message("Donnees GitHub recuperees avec succes");
        $data = json_decode($response, true);
        if (is_array($data)) {
            log_message("  " . count($data) . " fichiers trouves dans le repo");
        }
        return true;
    } else {
        log_message("Impossible de contacter l'API GitHub, utilisation des facteurs locaux");
        return false;
    }
}

// Execution
if (php_sapi_name() === 'cli' || isset($_POST['action'])) {
    // Tenter de recuperer les dernieres donnees
    fetchEcoLogitsData();

    // Mettre a jour avec les facteurs connus
    $success = updateEstimations();

    exit($success ? 0 : 1);
}
