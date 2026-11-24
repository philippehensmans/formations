<?php
/**
 * Mise a jour du catalogue CO2 depuis l'API EcoLogits (v1beta)
 * https://api.ecologits.ai/v1beta/estimations
 *
 * Un modele de reference unique est utilise pour tous les cas d'usage.
 * Valeur retenue : moyenne de la fourchette min/max renvoyee par l'API (kgCO2eq),
 * pour les tokens de sortie (output_token_count = tokens_estimes).
 *
 * Actions (formateur connecte uniquement) :
 *  - GET  ?action=preview : calcule les nouvelles valeurs, n'ecrit rien
 *  - POST action=apply    : recalcule puis ecrit data/estimations.json
 */
require_once __DIR__ . '/config.php';

const ECOLOGITS_API = 'https://api.ecologits.ai/v1beta/estimations';
const MODELE_REFERENCE = ['provider' => 'anthropic', 'model_name' => 'claude-sonnet-5-5'];
// Pas de electricity_mix_zone : la doc annonce WOR par defaut, mais WOR explicite
// donne des valeurs differentes (0.000177 vs 0.000151 kg pour 300 tokens, claude-sonnet-5-5).
// On garde l'appel sans zone, comme les valeurs deja presentees.

/**
 * Appel de l'API pour un nombre de tokens de sortie.
 * Retourne ['min' => kgCO2eq, 'max' => kgCO2eq]
 */
function appelerEcoLogits($tokens) {
    $body = json_encode([
        'provider' => MODELE_REFERENCE['provider'],
        'model_name' => MODELE_REFERENCE['model_name'],
        'output_token_count' => (int)$tokens,
    ]);

    if (function_exists('curl_init')) {
        // cURL respecte les variables de proxy de l'environnement
        $ch = curl_init(ECOLOGITS_API);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents(ECOLOGITS_API, false, $ctx);
    }

    if ($raw === false || $raw === '') {
        throw new RuntimeException("API EcoLogits injoignable");
    }

    $data = json_decode($raw, true);
    $gwp = $data['impacts']['gwp']['value'] ?? null;
    if ($gwp === null) {
        throw new RuntimeException("Reponse EcoLogits inattendue : " . substr($raw, 0, 200));
    }

    // Certains modeles renvoient une valeur unique au lieu d'une fourchette
    if (is_array($gwp)) {
        return ['min' => (float)$gwp['min'], 'max' => (float)$gwp['max']];
    }
    return ['min' => (float)$gwp, 'max' => (float)$gwp];
}

/**
 * Calcule les nouvelles valeurs pour chaque cas d'usage (sans rien ecrire)
 */
function calculerApercu() {
    $est = getEstimations();
    $lignes = [];

    foreach ($est['use_cases'] ?? [] as $id => $uc) {
        $tokens = (int)($uc['tokens_estimes'] ?? 0);
        if ($tokens <= 0) {
            continue; // pas de tokens : cas non traite
        }

        $r = appelerEcoLogits($tokens);
        $lignes[] = [
            'id' => $id,
            'nom' => $uc['nom'] ?? $id,
            'tokens' => $tokens,
            'ancien_g' => (float)($uc['co2_grammes'] ?? 0),
            'min_kg' => $r['min'],
            'max_kg' => $r['max'],
            'moyenne_kg' => ($r['min'] + $r['max']) / 2,
        ];
    }

    return $lignes;
}

/**
 * Ecrit les valeurs calculees dans estimations.json
 */
function appliquerApercu($lignes) {
    $est = getEstimations();
    $modele = MODELE_REFERENCE['provider'] . '/' . MODELE_REFERENCE['model_name'];
    $maj = 0;

    foreach ($lignes as $l) {
        if (!isset($est['use_cases'][$l['id']])) {
            continue;
        }
        $uc = &$est['use_cases'][$l['id']];
        $uc['co2_grammes'] = round($l['moyenne_kg'] * 1000, 4);
        $uc['co2_min_kg'] = $l['min_kg'];
        $uc['co2_max_kg'] = $l['max_kg'];
        $uc['modele_ref'] = $modele;
        unset($uc['equivalent']); // ancien champ, non maintenu
        unset($uc);
        $maj++;
    }

    $est['_metadata'] = array_merge($est['_metadata'] ?? [], [
        'last_updated' => date('Y-m-d'),
        'source' => 'API EcoLogits v1beta (' . $modele . ', mix electrique par defaut de l\'API)',
        'valeur' => 'moyenne min/max, kgCO2eq par tokens de sortie',
    ]);

    if (!saveEstimations($est)) {
        throw new RuntimeException("Impossible d'ecrire estimations.json");
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO updates_log (source, status, message) VALUES (?, ?, ?)");
    $stmt->execute(['ecologits', 'success', "$maj cas mis a jour (" . $modele . ")"]);

    return $maj;
}

// --- Point d'entree (appel AJAX depuis la vue formateur) ---
if (!isFormateur()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Acces formateur requis']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'preview') {
        echo json_encode(['success' => true, 'lignes' => calculerApercu()]);
    } elseif ($action === 'apply') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Ecriture refusee hors POST']);
            exit;
        }
        $n = appliquerApercu(calculerApercu());
        echo json_encode(['success' => true, 'updated' => $n]);
    } else {
        echo json_encode(['error' => 'Action inconnue']);
    }
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()]);
}
