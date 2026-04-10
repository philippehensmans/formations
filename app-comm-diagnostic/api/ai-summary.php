<?php
/**
 * Synthese IA des auto-diagnostics communication d'une session
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

// Recuperer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => 'Session introuvable']);
    exit;
}

// Recuperer les analyses partagees de la session
$stmt = $db->prepare("SELECT * FROM analyses WHERE session_id = ? AND is_shared = 1 ORDER BY updated_at DESC");
$stmt->execute([$sessionId]);
$analyses = $stmt->fetchAll();

if (empty($analyses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucun diagnostic partage dans cette session.']);
    exit;
}

$budgetLabels = ['moins_2' => 'Moins de 2%', '2_5' => '2-5%', '5_10' => '5-10%', 'plus_10' => 'Plus de 10%', 'ne_sais_pas' => 'Ne sais pas'];
$ressNonFinLabels = ['benevoles' => 'Benevoles', 'partenariats' => 'Partenariats', 'competences' => 'Competences internes', 'reseaux' => 'Reseaux personnels'];

// Construire le texte des diagnostics pour le prompt
$diagnosticText = "";
$defaults = getDefaultData();

foreach ($analyses as $a) {
    $userStmt = $sharedDb->prepare("SELECT prenom, nom FROM users WHERE id = ?");
    $userStmt->execute([$a['user_id']]);
    $userInfo = $userStmt->fetch();
    $userName = trim(($userInfo['prenom'] ?? '') . ' ' . ($userInfo['nom'] ?? ''));

    $s1 = json_decode($a['section1_data'] ?? '{}', true) ?: $defaults['section1_data'];
    $s2 = json_decode($a['section2_data'] ?? '{}', true) ?: $defaults['section2_data'];
    $s3 = json_decode($a['section3_data'] ?? '{}', true) ?: $defaults['section3_data'];
    $s4 = json_decode($a['section4_data'] ?? '{}', true) ?: $defaults['section4_data'];
    $s5 = json_decode($a['section5_data'] ?? '{}', true) ?: $defaults['section5_data'];

    $diagnosticText .= "\n\n=== " . ($userName ?: 'Anonyme') . " (" . ($a['nom_organisation'] ?? 'Organisation non precisee') . ") ===\n";

    // Section 1: Valeurs
    $diagnosticText .= "\n--- VALEURS ET MISSION ---\n";
    $valeurs = $s1['valeurs'] ?? [];
    $vScores = $s1['valeurs_scores'] ?? [];
    for ($i = 0; $i < 3; $i++) {
        $v = trim($valeurs[$i] ?? '');
        $sc = $vScores[$i]['score'] ?? 0;
        $comm = trim($vScores[$i]['commentaire'] ?? '');
        if (!empty($v)) {
            $diagnosticText .= "- Valeur: " . $v . " (visibilite: " . $sc . "/5)";
            if (!empty($comm)) $diagnosticText .= " - " . $comm;
            $diagnosticText .= "\n";
        }
    }
    if (!empty(trim($s1['exemple_positif'] ?? ''))) {
        $diagnosticText .= "Exemple positif: " . $s1['exemple_positif'] . "\n";
    }
    if (!empty(trim($s1['exemple_decalage'] ?? ''))) {
        $diagnosticText .= "Exemple decalage: " . $s1['exemple_decalage'] . "\n";
    }

    // Section 2: Contraintes et Ressources
    $diagnosticText .= "\n--- CONTRAINTES ET RESSOURCES ---\n";
    $budget = $s2['budget'] ?? '';
    if (!empty($budget)) {
        $diagnosticText .= "Budget communication: " . ($budgetLabels[$budget] ?? $budget) . "\n";
    }
    $diagnosticText .= "Contraintes: ";
    $contraintes = array_filter($s2['contraintes'] ?? [], fn($c) => !empty(trim($c)));
    $diagnosticText .= !empty($contraintes) ? implode(', ', $contraintes) : 'aucune mentionnee';
    $diagnosticText .= "\n";
    $diagnosticText .= "Atouts: ";
    $atouts = array_filter($s2['atouts'] ?? [], fn($a) => !empty(trim($a)));
    $diagnosticText .= !empty($atouts) ? implode(', ', $atouts) : 'aucun mentionne';
    $diagnosticText .= "\n";
    if (!empty(trim($s2['action_efficace'] ?? ''))) {
        $diagnosticText .= "Action efficace a moyens limites: " . $s2['action_efficace'] . "\n";
    }
    $selRes = $s2['ressources_non_financieres'] ?? [];
    if (!empty($selRes)) {
        $diagnosticText .= "Ressources non-financieres a mobiliser: " . implode(', ', array_map(fn($r) => $ressNonFinLabels[$r] ?? $r, $selRes)) . "\n";
    }

    // Section 3: Mobilisation
    $diagnosticText .= "\n--- MOBILISATION ET ENGAGEMENT ---\n";
    foreach (($s3['parties_prenantes'] ?? []) as $pp) {
        if (!empty(trim($pp['nom'] ?? ''))) {
            $diagnosticText .= "- " . $pp['nom'] . " (engagement: " . ($pp['engagement'] ?? 0) . "/5)";
            if (!empty(trim($pp['actions'] ?? ''))) $diagnosticText .= " -> " . $pp['actions'];
            $diagnosticText .= "\n";
        }
    }
    $transf = $s3['transformation_score'] ?? 0;
    if ($transf > 0) {
        $diagnosticText .= "Capacite de transformation: " . $transf . "/5\n";
    }
    $obstacles = array_filter($s3['obstacles'] ?? [], fn($o) => !empty(trim($o)));
    if (!empty($obstacles)) {
        $diagnosticText .= "Obstacles a l'engagement: " . implode(', ', $obstacles) . "\n";
    }
    if (!empty(trim($s3['exemple_mobilisation'] ?? ''))) {
        $diagnosticText .= "Exemple de mobilisation reussie: " . $s3['exemple_mobilisation'] . "\n";
    }

    // Section 4: Synthese
    $diagnosticText .= "\n--- SYNTHESE ---\n";
    if (!empty(trim($s4['force_distinctive'] ?? ''))) {
        $diagnosticText .= "Force distinctive: " . $s4['force_distinctive'] . "\n";
    }
    if (!empty(trim($s4['defi_prioritaire'] ?? ''))) {
        $diagnosticText .= "Defi prioritaire: " . $s4['defi_prioritaire'] . "\n";
    }
    if (!empty(trim($s4['articulation'] ?? ''))) {
        $diagnosticText .= "Articulation: " . $s4['articulation'] . "\n";
    }

    // Section 5: Pistes d'action
    $diagnosticText .= "\n--- PISTES D'ACTION ---\n";
    if (!empty(trim($s5['piste_valeurs'] ?? ''))) {
        $diagnosticText .= "Valeurs: " . $s5['piste_valeurs'] . "\n";
    }
    if (!empty(trim($s5['piste_ressources'] ?? ''))) {
        $diagnosticText .= "Ressources: " . $s5['piste_ressources'] . "\n";
    }
    if (!empty(trim($s5['piste_mobilisation'] ?? ''))) {
        $diagnosticText .= "Mobilisation: " . $s5['piste_mobilisation'] . "\n";
    }
}

$systemPrompt = <<<'PROMPT'
Tu es un expert en communication dans les secteurs marchands et en analyse des dynamiques organisationnelles.

On te fournit les auto-diagnostics communication de plusieurs participants d'un seminaire de formation. Chaque diagnostic couvre 5 dimensions : valeurs et mission, contraintes et ressources, mobilisation et engagement, synthese, et pistes d'action.

Tu dois produire une synthese structuree et actionnable qui permettra au formateur de comprendre les dynamiques du groupe et d'adapter son approche pedagogique.

## Format de reponse OBLIGATOIRE

Reponds en HTML structure (pas de JSON, pas de Markdown). Utilise les classes CSS Tailwind pour le style.

La synthese doit contenir :

1. **Vue d'ensemble** : nombre de participants, tendances generales
2. **Analyse des VALEURS** : valeurs les plus citees, niveau de visibilite moyen, ecarts entre valeurs affichees et communication reelle
3. **Analyse des CONTRAINTES ET RESSOURCES** : repartition des budgets, contraintes recurrentes, atouts partages, bonnes pratiques identifiees
4. **Analyse de la MOBILISATION** : parties prenantes les plus citees, niveaux d'engagement, obstacles recurrents, capacite de transformation
5. **Forces et defis communs** : forces distinctives partagees, defis prioritaires recurrents
6. **Recommandations pour le formateur** : points d'attention, axes de travail prioritaires, suggestions d'exercices ou de discussions

## Format HTML attendu

<div class="space-y-6">
  <!-- Vue d'ensemble -->
  <div class="bg-cyan-50 border border-cyan-200 rounded-lg p-4">
    <h3 class="font-bold text-lg mb-2 text-cyan-800">Vue d'ensemble</h3>
    <p class="text-gray-700">...</p>
  </div>

  <!-- Valeurs -->
  <div class="border-l-4 border-cyan-400 pl-4">
    <h3 class="font-bold text-lg mb-2 text-cyan-700">Analyse des Valeurs et Mission</h3>
    <p class="text-gray-700">...</p>
    <ul class="list-disc list-inside text-gray-700 space-y-1 mt-2">
      <li>...</li>
    </ul>
  </div>

  <!-- Contraintes et Ressources -->
  <div class="border-l-4 border-emerald-400 pl-4">
    <h3 class="font-bold text-lg mb-2 text-emerald-700">Contraintes et Ressources</h3>
    <div class="grid md:grid-cols-2 gap-4 mt-2">
      <div class="bg-red-50 border border-red-200 rounded-lg p-3">
        <h4 class="font-bold text-red-800 mb-1">Contraintes recurrentes</h4>
        <ul class="list-disc list-inside text-red-700 space-y-1">...</ul>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-lg p-3">
        <h4 class="font-bold text-green-800 mb-1">Atouts partages</h4>
        <ul class="list-disc list-inside text-green-700 space-y-1">...</ul>
      </div>
    </div>
  </div>

  <!-- Mobilisation -->
  <div class="border-l-4 border-amber-400 pl-4">
    <h3 class="font-bold text-lg mb-2 text-amber-700">Mobilisation et Engagement</h3>
    <p class="text-gray-700">...</p>
  </div>

  <!-- Forces et defis -->
  <div class="border-l-4 border-purple-400 pl-4">
    <h3 class="font-bold text-lg mb-2 text-purple-700">Forces et Defis communs</h3>
    <div class="grid md:grid-cols-2 gap-4 mt-2">
      <div class="bg-cyan-50 border border-cyan-200 rounded-lg p-3">
        <h4 class="font-bold text-cyan-800 mb-1">Forces distinctives</h4>
        <ul class="list-disc list-inside text-cyan-700 space-y-1">...</ul>
      </div>
      <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
        <h4 class="font-bold text-amber-800 mb-1">Defis prioritaires</h4>
        <ul class="list-disc list-inside text-amber-700 space-y-1">...</ul>
      </div>
    </div>
  </div>

  <!-- Recommandations -->
  <div class="border-t-2 border-gray-300 pt-6 mt-6">
    <h3 class="font-bold text-xl mb-4">Recommandations pour le formateur</h3>
    <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
      <ul class="list-disc list-inside text-teal-700 space-y-2">
        <li>...</li>
      </ul>
    </div>
  </div>
</div>

Sois concis mais complet. La synthese doit etre utile pour un formateur qui veut comprendre rapidement les dynamiques du groupe, identifier les points communs et les specificites, et adapter son approche pedagogique.
PROMPT;

$userMessage = "Session : " . $session['nom'] . "\nNombre de participants : " . count($analyses) . "\n\nVoici les auto-diagnostics communication des participants :\n" . $diagnosticText;

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
