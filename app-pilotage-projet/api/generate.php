<?php
/**
 * Appel a l'API Claude pour generer un plan de projet structure
 */
require_once __DIR__ . '/../config.php';

// Charger la config AI
$aiConfigPath = __DIR__ . '/../../ai-config.php';
if (!file_exists($aiConfigPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Configuration AI manquante. Copiez ai-config.example.php en ai-config.php et ajoutez votre cle API.']);
    exit;
}
require_once $aiConfigPath;

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

// Verifier l'acces a l'app restreinte
$user = getLoggedUser();
if (!$user || !hasAppAccess('app-pilotage-projet', $user['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acces non autorise a cette application.']);
    exit;
}

if (ANTHROPIC_API_KEY === 'YOUR_API_KEY_HERE') {
    http_response_code(500);
    echo json_encode(['error' => 'Cle API non configuree. Editez ai-config.php avec votre cle API Anthropic.']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || empty(trim($data['description'] ?? ''))) {
    http_response_code(400);
    echo json_encode(['error' => 'Description du projet requise']);
    exit;
}

$description = trim($data['description'] ?? '');
$contexte = trim($data['contexte'] ?? '');
$contraintes = trim($data['contraintes'] ?? '');

$systemPrompt = <<<'PROMPT'
Tu es un expert en gestion de projet pour des associations et organisations a but non lucratif.

On te donne la description d'un projet avec son contexte et ses contraintes. Tu dois produire un plan de projet structure et actionnable.

## Principes a respecter
1. **Planification rigoureuse** : Decompose le projet en phases claires avec des livrables concrets
2. **Simplicite d'abord** : Chaque tache doit etre actionnable et realiste pour une petite equipe
3. **Points de controle** : Place des jalons de validation entre les phases critiques
4. **Pas de sur-ingenierie** : Reste pragmatique, adapte aux moyens d'une association

## Format de reponse OBLIGATOIRE
Reponds UNIQUEMENT avec un objet JSON valide (pas de texte avant ou apres), avec cette structure exacte :

{
  "nom_projet": "Nom clair et concis du projet",
  "description_projet": "Description enrichie du projet en 2-3 phrases",
  "objectifs": [
    {"titre": "Objectif clair et mesurable", "criteres": "Comment on sait que c'est atteint"}
  ],
  "phases": [
    {
      "nom": "Nom de la phase",
      "dates": "Estimation de periode (ex: Semaines 1-2)",
      "livrable": "Ce qui doit etre produit",
      "taches": [
        {"titre": "Tache concrete et actionnable", "responsable": "Role suggere (ex: Coordinateur, Benevole, CA...)", "statut": "todo"}
      ]
    }
  ],
  "checkpoints": [
    {
      "type": "validation",
      "apres_phase": 0,
      "validateur": "Qui valide (ex: Le CA, Le coordinateur)",
      "description": "Ce qu'on verifie",
      "criteres": "Conditions pour continuer"
    }
  ],
  "recommandations": "Conseils cles pour la reussite du projet (2-3 phrases)"
}

Notes sur les types de checkpoints possibles : "validation" (Go/No-Go), "revue" (Revue d'etape), "livraison" (Jalon), "feedback" (Retour parties prenantes), "decision" (Point de decision).
Le champ "apres_phase" est l'index (base 0) de la phase apres laquelle ce checkpoint intervient.

Genere entre 2 et 5 objectifs, 3 a 6 phases avec 2-5 taches chacune, et 2-4 checkpoints.
PROMPT;

$userMessage = "Projet : " . $description;
if (!empty($contexte)) {
    $userMessage .= "\n\nContexte : " . $contexte;
}
if (!empty($contraintes)) {
    $userMessage .= "\n\nContraintes : " . $contraintes;
}

// Appel API Anthropic
$payload = [
    'model' => ANTHROPIC_MODEL,
    'max_tokens' => ANTHROPIC_MAX_TOKENS,
    'system' => $systemPrompt,
    'messages' => [
        ['role' => 'user', 'content' => $userMessage]
    ]
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion a l\'API: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $errMsg = $errData['error']['message'] ?? 'Erreur API (HTTP ' . $httpCode . ')';
    http_response_code(500);
    echo json_encode(['error' => $errMsg]);
    exit;
}

$apiResponse = json_decode($response, true);
$content = $apiResponse['content'][0]['text'] ?? '';

// Extraire le JSON de la reponse (au cas ou il y a du texte autour)
$jsonMatch = $content;
if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
    $jsonMatch = $matches[0];
}

$plan = json_decode($jsonMatch, true);
if (!$plan) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de parser la reponse de Claude', 'raw' => $content]);
    exit;
}

echo json_encode(['success' => true, 'plan' => $plan]);
?>
