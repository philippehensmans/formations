<?php
/**
 * Generation IA de "Comment l'IA peut aider" pour une ou plusieurs activites
 * Reserve aux formateurs
 * Utilise l'API Claude (Anthropic) configuree dans ai-config.php
 */
require_once __DIR__ . '/../config.php';

// Charger la config AI
$aiConfigPath = __DIR__ . '/../../ai-config.php';
if (!file_exists($aiConfigPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Configuration AI manquante. Copiez ai-config.example.php en ai-config.php et ajoutez votre cle API."]);
    exit;
}
require_once $aiConfigPath;

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

if (!isFormateur()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acces reserve aux formateurs.']);
    exit;
}

if (ANTHROPIC_API_KEY === 'YOUR_API_KEY_HERE') {
    http_response_code(500);
    echo json_encode(['error' => "Cle API non configuree. Editez ai-config.php avec votre cle API Anthropic."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

$db = getDB();

/**
 * Appel API Claude pour generer le texte "Comment l'IA peut aider"
 * Retourne une chaine de texte (pas de HTML, pas de Markdown)
 */
function generateAiHelpText($nom, $description, $categorie) {
    $systemPrompt = <<<'PROMPT'
Tu es un expert en usage de l'IA generative (comme Claude, ChatGPT, Gemini) pour aider les associations et organisations a but non lucratif dans leurs activites quotidiennes.

On te decrit une activite realisee dans une association. Ta mission est d'expliquer **concretement** quels types de soutien l'IA peut apporter pour faciliter cette activite.

## Consignes strictes
- **Ne donne PAS de prompts** (pas d'exemples de questions a poser a l'IA).
- Decris le **type de soutien** que l'IA peut apporter (generation, reformulation, structuration, synthese, traduction, brainstorming, analyse, verification, etc.).
- Reste **concret et pragmatique** : parle d'utilisations reelles adaptees au contexte associatif.
- Sois **concis** : 2 a 4 phrases maximum, ou une liste courte de 3 a 5 puces si plusieurs usages pertinents.
- **Pas de HTML, pas de Markdown complexe**. Texte brut uniquement. Tu peux utiliser des tirets "-" pour les listes.
- Reponds en francais, ton professionnel mais accessible, s'adresse a un(e) benevole ou salarie(e) d'association non expert en IA.
- Ne commence PAS par "L'IA peut..." de maniere generique. Va droit au but avec des usages specifiques a l'activite decrite.

## Format de reponse
Uniquement le texte explicatif, sans prefixe, sans titre, sans formule d'introduction comme "Voici...".
PROMPT;

    $userMessage = "Activite : " . $nom;
    if (!empty($description)) {
        $userMessage .= "\nDescription : " . $description;
    }
    if (!empty($categorie)) {
        $userMessage .= "\nCategorie : " . $categorie;
    }
    $userMessage .= "\n\nDecris comment l'IA peut aider a realiser cette activite.";

    $payload = [
        'model' => ANTHROPIC_MODEL,
        'max_tokens' => 600,
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
        throw new Exception("Erreur de connexion a l'API: " . $curlError);
    }

    if ($httpCode !== 200) {
        $errData = json_decode($response, true);
        $errMsg = $errData['error']['message'] ?? 'Erreur API (HTTP ' . $httpCode . ')';
        throw new Exception($errMsg);
    }

    $apiResponse = json_decode($response, true);
    $content = trim($apiResponse['content'][0]['text'] ?? '');

    if (empty($content)) {
        throw new Exception("Reponse vide de l'API");
    }

    return $content;
}

try {
    if ($action === 'generate') {
        // Generation pour une seule activite
        $activityId = (int)($input['activity_id'] ?? 0);
        if (!$activityId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID activite requis']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM activites WHERE id = ?");
        $stmt->execute([$activityId]);
        $activite = $stmt->fetch();

        if (!$activite) {
            http_response_code(404);
            echo json_encode(['error' => 'Activite introuvable']);
            exit;
        }

        $categories = getCategories();
        $categorieLabel = $categories[$activite['categorie']]['label'] ?? $activite['categorie'];

        $text = generateAiHelpText($activite['nom'], $activite['description'] ?? '', $categorieLabel);

        // Sauvegarder en base
        $user = getLoggedUser();
        $stmt = $db->prepare("UPDATE activites SET notes_ia = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$text, $user['id'], $activityId]);

        echo json_encode(['success' => true, 'activity_id' => $activityId, 'notes_ia' => $text]);
        exit;
    }

    if ($action === 'generate_batch') {
        // Generation pour toutes les activites d'une session (avec option only_empty)
        $sessionId = (int)($input['session_id'] ?? 0);
        $onlyEmpty = !empty($input['only_empty']);

        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID session requis']);
            exit;
        }

        if ($onlyEmpty) {
            $stmt = $db->prepare("SELECT * FROM activites WHERE session_id = ? AND (notes_ia IS NULL OR notes_ia = '')");
        } else {
            $stmt = $db->prepare("SELECT * FROM activites WHERE session_id = ?");
        }
        $stmt->execute([$sessionId]);
        $activites = $stmt->fetchAll();

        if (empty($activites)) {
            echo json_encode(['success' => true, 'results' => [], 'count' => 0, 'message' => 'Aucune activite a traiter']);
            exit;
        }

        $categories = getCategories();
        $user = getLoggedUser();
        $updateStmt = $db->prepare("UPDATE activites SET notes_ia = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");

        $results = [];
        $errors = [];

        foreach ($activites as $a) {
            try {
                $categorieLabel = $categories[$a['categorie']]['label'] ?? $a['categorie'];
                $text = generateAiHelpText($a['nom'], $a['description'] ?? '', $categorieLabel);
                $updateStmt->execute([$text, $user['id'], $a['id']]);
                $results[] = ['id' => (int)$a['id'], 'notes_ia' => $text];
            } catch (Exception $e) {
                $errors[] = ['id' => (int)$a['id'], 'nom' => $a['nom'], 'error' => $e->getMessage()];
            }
        }

        echo json_encode([
            'success' => true,
            'results' => $results,
            'count' => count($results),
            'errors' => $errors
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Action inconnue']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
