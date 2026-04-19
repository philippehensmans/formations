<?php
/**
 * Génération IA d'un communiqué de presse à partir des éléments du participant.
 * Accessible uniquement aux super-admins.
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$aiConfigPath = __DIR__ . '/../../ai-config.php';
if (!file_exists($aiConfigPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration IA manquante. Copiez ai-config.example.php en ai-config.php et ajoutez votre clé API.']);
    exit;
}
require_once $aiConfigPath;

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Non authentifié']); exit; }
if (!isFormateur()) { http_response_code(403); echo json_encode(['error' => 'Accès réservé aux formateurs']); exit; }

if (ANTHROPIC_API_KEY === 'YOUR_API_KEY_HERE') {
    http_response_code(500); echo json_encode(['error' => 'Clé API non configurée dans ai-config.php']); exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$participantId = (int)($input['participant_id'] ?? 0);
if (!$participantId) { http_response_code(400); echo json_encode(['error' => 'ID participant requis']); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.nom as session_nom FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) { http_response_code(404); echo json_encode(['error' => 'Participant introuvable']); exit; }

$stmt = $db->prepare("SELECT * FROM fiches WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$fiche = $stmt->fetch() ?: [];

$stmt = $db->prepare("SELECT * FROM lignes_reponse WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$lignes = $stmt->fetch() ?: [];
$qrData = json_decode($lignes['qr_data'] ?? '[]', true) ?: [];
$elData = json_decode($lignes['elements_data'] ?? '[]', true) ?: [];

$stmt = $db->prepare("SELECT * FROM communiques WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$cp = $stmt->fetch() ?: [];

$hasContent = !empty(trim($fiche['sujet'] ?? ''))
    || !empty(trim($fiche['message1'] ?? ''))
    || !empty(trim($fiche['message2'] ?? ''))
    || !empty(trim($fiche['message3'] ?? ''))
    || !empty($qrData)
    || !empty($elData)
    || !empty(trim($cp['titre'] ?? ''))
    || !empty(trim($cp['chapeau'] ?? ''))
    || !empty(trim($cp['paragraphe1'] ?? ''));

if (!$hasContent) {
    http_response_code(400); echo json_encode(['error' => 'Le participant n\'a pas encore saisi de contenu.']); exit;
}

// Construire le contexte pour l'IA
$participantName = trim(($participant['prenom'] ?? '') . ' ' . ($participant['nom'] ?? '')) ?: 'Porte-parole';

$contexte = "**Personne interviewée :** $participantName\n\n";
if (!empty(trim($fiche['sujet'] ?? '')))    $contexte .= "**Sujet / contexte de l'interview :**\n" . trim($fiche['sujet']) . "\n\n";
$messages = array_filter([$fiche['message1'] ?? '', $fiche['message2'] ?? '', $fiche['message3'] ?? ''], fn($m) => trim($m) !== '');
if ($messages) {
    $contexte .= "**Messages clés à faire passer :**\n";
    foreach ($messages as $i => $m) $contexte .= ($i + 1) . ". " . trim($m) . "\n";
    $contexte .= "\n";
}
if (!empty(trim($fiche['anecdote'] ?? ''))) $contexte .= "**Anecdote / exemple concret :**\n" . trim($fiche['anecdote']) . "\n\n";
if (!empty(trim($fiche['a_eviter'] ?? ''))) $contexte .= "**À NE PAS MENTIONNER (sensible) :**\n" . trim($fiche['a_eviter']) . "\n\n";

$validQr = array_filter($qrData, fn($r) => trim($r['question'] ?? '') !== '' || trim($r['reponse'] ?? '') !== '');
if ($validQr) {
    $contexte .= "**Questions probables et réponses préparées :**\n";
    foreach ($validQr as $r) {
        if (trim($r['question'] ?? '')) $contexte .= "Q : " . trim($r['question']) . "\n";
        if (trim($r['reponse'] ?? ''))  $contexte .= "R : " . trim($r['reponse']) . "\n";
    }
}

$systemPrompt = <<<'PROMPT'
Tu es un rédacteur de presse expérimenté. Tu écris uniquement en français.

Ta mission : rédiger un COMMUNIQUÉ DE PRESSE à partir des éléments fournis par une personne qui se prépare à une interview journalistique.

RÈGLES ABSOLUES :
- Applique la pyramide inversée : l'essentiel dans le titre et le chapeau, les détails ensuite.
- Ne contredis jamais les messages clés de la personne.
- N'INCLUS JAMAIS d'éléments listés dans "À NE PAS MENTIONNER".
- La citation DOIT être attribuée à la personne interviewée et refléter ses messages clés.
- Ton style : factuel, concis, neutre. Pas d'effets marketing exagérés.
- Réponds UNIQUEMENT avec un objet JSON valide, sans aucun texte avant ou après.

FORMAT DE RÉPONSE (JSON strict) :
{
  "titre": "Titre accrocheur et factuel, max 100 caractères",
  "chapeau": "Résumé de 3-4 lignes répondant aux questions qui/quoi/où/quand/pourquoi",
  "paragraphe1": "Développement de l'info principale (le plus important)",
  "paragraphe2": "Détails, chiffres, contexte",
  "paragraphe3": "Informations complémentaires, perspectives",
  "citation": "Une phrase forte entre guillemets, reprenant un message clé",
  "citation_source": "Nom et fonction de la personne interviewée"
}

Aucun champ ne doit être vide. Pas de markdown. Pas de retour à la ligne dans les chaînes (utilise des phrases continues).
PROMPT;

$payload = [
    'model' => ANTHROPIC_MODEL,
    'max_tokens' => ANTHROPIC_MAX_TOKENS,
    'system' => $systemPrompt,
    'messages' => [
        ['role' => 'user', 'content' => $contexte],
    ],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) { http_response_code(500); echo json_encode(['error' => 'Erreur de connexion à l\'API : ' . $curlError]); exit; }
if ($httpCode !== 200) {
    $err = json_decode($response, true);
    $msg = $err['error']['message'] ?? ('Erreur API HTTP ' . $httpCode);
    http_response_code(500); echo json_encode(['error' => $msg]); exit;
}

$api = json_decode($response, true);
$text = $api['content'][0]['text'] ?? '';
if ($text === '') { http_response_code(500); echo json_encode(['error' => 'Réponse vide de l\'API']); exit; }

// Nettoyer : enlever ```json ... ``` si présent
$text = trim($text);
$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
$text = preg_replace('/\s*```\s*$/', '', $text);

$cp = json_decode($text, true);
if (!is_array($cp)) {
    echo json_encode(['success' => true, 'raw' => $text, 'parsed' => false]);
    exit;
}

echo json_encode([
    'success' => true,
    'parsed' => true,
    'communique' => [
        'titre'           => (string)($cp['titre']           ?? ''),
        'chapeau'         => (string)($cp['chapeau']         ?? ''),
        'paragraphe1'     => (string)($cp['paragraphe1']     ?? ''),
        'paragraphe2'     => (string)($cp['paragraphe2']     ?? ''),
        'paragraphe3'     => (string)($cp['paragraphe3']     ?? ''),
        'citation'        => (string)($cp['citation']        ?? ''),
        'citation_source' => (string)($cp['citation_source'] ?? ''),
    ],
]);
