<?php
/**
 * Synthese IA des reponses au questionnaire
 * Accessible uniquement aux super-admins
 */
require_once __DIR__ . '/../config.php';

$aiConfigPath = __DIR__ . '/../../ai-config.php';
if (!file_exists($aiConfigPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Configuration AI manquante. Copiez ai-config.example.php en ai-config.php et ajoutez votre cle API.']);
    exit;
}
require_once $aiConfigPath;

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Non authentifie']); exit; }
if (!isSuperAdmin()) { http_response_code(403); echo json_encode(['error' => 'Acces reserve aux super-administrateurs.']); exit; }
if (ANTHROPIC_API_KEY === 'YOUR_API_KEY_HERE') { http_response_code(500); echo json_encode(['error' => 'Cle API non configuree.']); exit; }

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$sessionId = (int)($data['session_id'] ?? 0);

if (!$sessionId) { http_response_code(400); echo json_encode(['error' => 'ID de session requis']); exit; }

$db = getDB();
$sharedDb = getSharedDB();

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) { http_response_code(404); echo json_encode(['error' => 'Session introuvable']); exit; }

$questions = getQuestions($sessionId);

// Recuperer toutes les reponses partagees
$stmt = $db->prepare("SELECT r.*, q.label as question_label, q.type as question_type
                      FROM reponses r
                      JOIN questions q ON r.question_id = q.id
                      WHERE r.session_id = ? AND r.is_shared = 1
                      ORDER BY r.user_id, q.ordre");
$stmt->execute([$sessionId]);
$allReponses = $stmt->fetchAll();

if (empty($allReponses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune reponse partagee dans cette session.']);
    exit;
}

// Grouper par participant
$byParticipant = [];
foreach ($allReponses as $r) {
    $uid = $r['user_id'];
    if (!isset($byParticipant[$uid])) {
        $userStmt = $sharedDb->prepare("SELECT prenom, nom FROM users WHERE id = ?");
        $userStmt->execute([$uid]);
        $userInfo = $userStmt->fetch();
        $byParticipant[$uid] = [
            'nom' => trim(($userInfo['prenom'] ?? '') . ' ' . ($userInfo['nom'] ?? '')) ?: 'Anonyme',
            'reponses' => []
        ];
    }
    $byParticipant[$uid]['reponses'][] = $r;
}

// Construire le texte pour le prompt
$reponsesText = "";
foreach ($byParticipant as $uid => $data) {
    $reponsesText .= "\n### " . $data['nom'] . "\n";
    foreach ($data['reponses'] as $r) {
        if (!empty($r['contenu'])) {
            $reponsesText .= "- **" . $r['question_label'] . "** : " . $r['contenu'] . "\n";
        }
    }
}

$questionsText = "";
foreach ($questions as $i => $q) {
    $questionsText .= ($i + 1) . ". " . $q['label'] . " (" . $q['type'] . ")\n";
}

$sujet = !empty($session['sujet']) ? $session['sujet'] : '(non defini)';

$systemPrompt = <<<'PROMPT'
Tu es un expert en facilitation de formation et en analyse de groupes.

On te fournit les reponses d'un questionnaire prealable a une formation sur l'Intelligence Artificielle. Tu dois produire une synthese structuree et actionnable pour le formateur.

## Format de reponse OBLIGATOIRE

Reponds en HTML structure (pas de JSON, pas de Markdown). Utilise les classes CSS Tailwind.

La synthese doit contenir :

1. **Profil du groupe** : Niveau d'utilisation de l'IA, outils connus, maturite globale
2. **Analyse par question** : Pour chaque question, synthese des tendances et reponses cles
3. **Carte des attentes et inquietudes** : Ce que le groupe espere vs ce qui l'inquiete
4. **Recommandations pour le formateur** : Comment adapter la formation au profil du groupe

## Format HTML attendu

<div class="space-y-6">
  <div class="border-l-4 border-sky-400 pl-4">
    <h3 class="font-bold text-lg mb-2 text-sky-700">📊 Profil du groupe</h3>
    <p class="text-gray-700">[Synthese du profil]</p>
  </div>

  <div class="border-l-4 border-blue-400 pl-4">
    <h3 class="font-bold text-lg mb-2 text-blue-700">📝 Analyse des reponses</h3>
    [Pour chaque question pertinente, un paragraphe de synthese]
  </div>

  <div class="border-t-2 border-gray-300 pt-6 mt-6">
    <h3 class="font-bold text-xl mb-4">🎯 Carte attentes / inquietudes</h3>
    <div class="grid md:grid-cols-2 gap-4">
      <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <h4 class="font-bold text-green-800 mb-2">Attentes et espoirs</h4>
        <ul class="list-disc list-inside text-green-700 space-y-1"><li>...</li></ul>
      </div>
      <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <h4 class="font-bold text-red-800 mb-2">Inquietudes et questions</h4>
        <ul class="list-disc list-inside text-red-700 space-y-1"><li>...</li></ul>
      </div>
    </div>
  </div>

  <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-4">
    <h4 class="font-bold text-amber-800 mb-2">💡 Recommandations pour le formateur</h4>
    <ul class="list-disc list-inside text-amber-700 space-y-1"><li>...</li></ul>
  </div>
</div>

Sois concis mais complet. La synthese doit permettre au formateur d'adapter immediatement sa session au profil du groupe.
PROMPT;

$userMessage = "Formation : " . $session['nom'] . "\nContexte : " . $sujet . "\n\nQuestions posees :\n" . $questionsText . "\n\nReponses des participants :\n" . $reponsesText;

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

if ($curlError) { http_response_code(500); echo json_encode(['error' => 'Erreur de connexion: ' . $curlError]); exit; }
if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $errMsg = $errData['error']['message'] ?? 'Erreur API (HTTP ' . $httpCode . ')';
    http_response_code(500); echo json_encode(['error' => $errMsg]); exit;
}

$apiResponse = json_decode($response, true);
$content = $apiResponse['content'][0]['text'] ?? '';

if (empty($content)) { http_response_code(500); echo json_encode(['error' => 'Reponse vide de l\'API']); exit; }

echo json_encode(['success' => true, 'summary' => $content]);
