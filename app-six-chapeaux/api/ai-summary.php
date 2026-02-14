<?php
/**
 * Synthese IA des avis d'une session Six Chapeaux
 * Accessible uniquement aux super-admins
 * Utilise l'API Claude (Anthropic) configuree dans ai-config.php
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

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifie']);
    exit;
}

// Acces reserve aux super-admins
if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acces reserve aux super-administrateurs.']);
    exit;
}

if (ANTHROPIC_API_KEY === 'YOUR_API_KEY_HERE') {
    http_response_code(500);
    echo json_encode(['error' => 'Cle API non configuree. Editez ai-config.php avec votre cle API Anthropic.']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$sessionId = (int)($data['session_id'] ?? 0);

if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de session requis']);
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();
$chapeaux = getChapeaux();

// Recuperer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => 'Session introuvable']);
    exit;
}

// Recuperer les avis partages de la session
$stmt = $db->prepare("SELECT * FROM avis WHERE session_id = ? AND is_shared = 1 ORDER BY chapeau, created_at");
$stmt->execute([$sessionId]);
$allAvis = $stmt->fetchAll();

if (empty($allAvis)) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucun avis partage dans cette session.']);
    exit;
}

// Enrichir avec les infos utilisateur et grouper par chapeau
$avisByChapeau = [];
foreach ($chapeaux as $key => $ch) {
    $avisByChapeau[$key] = [];
}

foreach ($allAvis as $a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $userName = trim(($userInfo['prenom'] ?? '') . ' ' . ($userInfo['nom'] ?? ''));

    if (isset($avisByChapeau[$a['chapeau']])) {
        $avisByChapeau[$a['chapeau']][] = [
            'auteur' => $userName ?: 'Anonyme',
            'contenu' => $a['contenu']
        ];
    }
}

// Construire le texte des avis pour le prompt
$avisText = "";
foreach ($chapeaux as $key => $chapeau) {
    $avisText .= "\n## " . $chapeau['icon'] . " " . $chapeau['nom'] . " (" . $chapeau['description'] . ")\n";
    if (empty($avisByChapeau[$key])) {
        $avisText .= "Aucun avis.\n";
    } else {
        foreach ($avisByChapeau[$key] as $a) {
            $avisText .= "- " . $a['auteur'] . " : " . $a['contenu'] . "\n";
        }
    }
}

$sujet = !empty($session['sujet']) ? $session['sujet'] : '(non defini)';

$systemPrompt = <<<'PROMPT'
Tu es un expert en facilitation de groupe et en methode des Six Chapeaux de Bono.

On te fournit les avis des participants d'une session de reflexion, organises par chapeau (couleur). Tu dois produire une synthese structuree.

## Format de reponse OBLIGATOIRE

Reponds en HTML structure (pas de JSON, pas de Markdown). Utilise les classes CSS Tailwind suivantes pour le style.

La synthese doit contenir :

1. **Pour chaque chapeau qui contient des avis**, une section avec :
   - Le titre du chapeau avec son emoji
   - Un resume des points cles exprimes par les participants
   - Les tendances ou themes communs

2. **Une synthese globale** a la fin avec :
   - Les points positifs majeurs (ce qui ressort du jaune, vert, et des aspects constructifs)
   - Les points negatifs / risques (ce qui ressort du noir et des preoccupations)
   - Une conclusion equilibree avec recommandations

## Format HTML attendu

<div class="space-y-6">
  <!-- Pour chaque chapeau -->
  <div class="border-l-4 border-[COULEUR] pl-4">
    <h3 class="font-bold text-lg mb-2">[EMOJI] [NOM DU CHAPEAU]</h3>
    <p class="text-gray-700">[Resume des avis]</p>
  </div>

  <!-- Synthese globale -->
  <div class="border-t-2 border-gray-300 pt-6 mt-6">
    <h3 class="font-bold text-xl mb-4">Synthese globale</h3>
    <div class="grid md:grid-cols-2 gap-4">
      <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <h4 class="font-bold text-green-800 mb-2">Points positifs</h4>
        <ul class="list-disc list-inside text-green-700 space-y-1">
          <li>...</li>
        </ul>
      </div>
      <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <h4 class="font-bold text-red-800 mb-2">Points negatifs / Risques</h4>
        <ul class="list-disc list-inside text-red-700 space-y-1">
          <li>...</li>
        </ul>
      </div>
    </div>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
      <h4 class="font-bold text-blue-800 mb-2">Conclusion et recommandations</h4>
      <p class="text-blue-700">...</p>
    </div>
  </div>
</div>

Utilise les couleurs de bordure suivantes pour chaque chapeau :
- Blanc : border-gray-400
- Rouge : border-red-400
- Noir : border-slate-800
- Jaune : border-yellow-400
- Vert : border-green-400
- Bleu : border-blue-400

Sois concis mais complet. La synthese doit etre utile pour un formateur qui veut comprendre rapidement les resultats de la session.
PROMPT;

$userMessage = "Session : " . $session['nom'] . "\nSujet de reflexion : " . $sujet . "\n\nVoici les avis des participants :\n" . $avisText;

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
    CURLOPT_TIMEOUT => 90
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

if (empty($content)) {
    http_response_code(500);
    echo json_encode(['error' => 'Reponse vide de l\'API']);
    exit;
}

echo json_encode(['success' => true, 'summary' => $content]);
?>
