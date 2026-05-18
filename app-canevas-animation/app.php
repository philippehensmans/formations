<?php
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user = getLoggedUser();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$sessionId = validateCurrentSession($db);
if (!$sessionId) { header('Location: login.php'); exit; }
$sessionNom = $_SESSION['current_session_nom'] ?? '';
ensureParticipant($db, $sessionId, $user);

$stmt = $db->prepare("SELECT * FROM canevas WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$row = $stmt->fetch();
if (!$row) {
    $db->prepare("INSERT INTO canevas (user_id, session_id) VALUES (?, ?)")->execute([$user['id'], $sessionId]);
    $stmt->execute([$user['id'], $sessionId]);
    $row = $stmt->fetch();
}

$data = json_decode($row['data'] ?? '{}', true) ?: [];
$isSubmitted = ($row['is_shared'] ?? 0) == 1;

$pointsAttention = getPointsAttention();
$publics = getPublics();
$modalitesEval = getModalitesEval();
$formats = getFormats();

// Initialiser les valeurs par défaut
$defaults = [
    'animateur' => '',
    'date_lieu' => '',
    'classe_groupe' => '',
    'public' => '',
    'public_precisions' => '',
    'objectif_principal' => '',
    'objectif_sec_1' => '',
    'objectif_sec_2' => '',
    'fil_rouge' => '',
    'format' => '90',
    'sequences' => [
        ['min' => '0-15', 'objectif' => '', 'activite' => '', 'animation' => ''],
        ['min' => '15-30', 'objectif' => '', 'activite' => '', 'animation' => ''],
        ['min' => '30-45', 'objectif' => '', 'activite' => '', 'animation' => ''],
        ['min' => '45-60', 'objectif' => '', 'activite' => '', 'animation' => ''],
        ['min' => '60-75', 'objectif' => '', 'activite' => '', 'animation' => ''],
        ['min' => '75-90', 'objectif' => '', 'activite' => '', 'animation' => ''],
    ],
    'outil_projete_1' => '',
    'outil_projete_2' => '',
    'outil_manipule_1' => '',
    'outil_manipule_2' => '',
    'plan_b' => '',
    'points_coches' => [],
    'materiel_salle' => [],
    'materiel_formateur' => [],
    'materiel_eleves' => [],
    'preparation_j1' => '',
    'modalite_eval' => '',
    'bilan_marche' => '',
    'bilan_coince' => '',
    'bilan_change' => '',
    'suivi_enseignant' => '',
    'notes' => '',
];
$data = array_merge($defaults, $data);
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canevas d'animation IA - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect x='4' y='4' width='24' height='24' rx='3' fill='%234f46e5'/><rect x='8' y='8' width='16' height='2' rx='1' fill='%23c7d2fe'/><rect x='8' y='13' width='12' height='2' rx='1' fill='%23a5b4fc'/><rect x='8' y='18' width='14' height='2' rx='1' fill='%23a5b4fc'/><rect x='8' y='23' width='10' height='2' rx='1' fill='%23a5b4fc'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #312e81 0%, #3730a3 50%, #1e1b4b 100%); min-height: 100vh; }
        @media print { .no-print { display: none !important; } body { background: white; } .card-section { box-shadow: none !important; border: 1px solid #ddd !important; page-break-inside: avoid; } }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .section-number { display: flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 50%; background: #4f46e5; color: white; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }
        .indigo-border { border-left: 4px solid #4f46e5; padding-left: 1rem; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .chk-card { transition: all 0.2s ease; cursor: pointer; }
        .chk-card.checked { background: linear-gradient(135deg, #eef2ff 0%, #ddd6fe 100%); border-color: #4f46e5; }
    </style>
</head>
<body class="p-4 md:p-8">
    <!-- Barre utilisateur -->
    <div class="max-w-6xl mx-auto mb-4 bg-white/90 backdrop-blur rounded-lg shadow-lg p-3 no-print">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium text-gray-800"><?= h($user['prenom']) ?> <?= h($user['nom']) ?></span>
                <span class="text-gray-500 text-sm ml-2"><?= h($sessionNom) ?></span>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <?= renderLanguageSelector('text-sm bg-white/20 text-gray-800 px-2 py-1 rounded border border-gray-300') ?>
                <button onclick="manualSave()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">Sauvegarder</button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-gray-200"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <span id="completion" class="text-sm text-gray-600">Complétion : <strong>0%</strong></span>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1 rounded transition">Formateur</a>
                <?php endif; ?>
                <?= renderHomeLink() ?>
                <a href="logout.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition">Déconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 card-section">
            <div class="text-center mb-4">
                <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Animation · Canevas</p>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Mon animation IA</h1>
                <p class="text-gray-600 italic">90 minutes — public 12-20 ans · Canevas de co-construction</p>
            </div>

            <div class="bg-gradient-to-r from-indigo-50 via-blue-50 to-violet-50 p-5 rounded-lg border-2 border-indigo-200 shadow-md mb-6">
                <p class="text-gray-700 leading-relaxed text-sm">
                    Un document à remplir, pas à lire passivement. Ce canevas suit une logique séquentielle :
                    <strong>objectifs → choix d'outils → séquençage → réduction des risques</strong>.
                    Ne saute pas d'étape : la cohérence dépend de l'enchaînement.
                </p>
                <p class="text-indigo-800 mt-3 font-semibold text-sm">
                    Règle d'or : une animation IA réussie fait <strong>MANIPULER</strong> au moins une fois.
                    Si le créneau ne permet qu'une seule manipulation, tant pis pour les démos — la manipulation prime.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Animateur·rice</label>
                    <input type="text" id="animateur" value="<?= h($data['animateur']) ?>"
                        class="w-full px-3 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500"
                        placeholder="Prénom Nom" oninput="scheduleAutoSave()">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Date et lieu</label>
                    <input type="text" id="date_lieu" value="<?= h($data['date_lieu']) ?>"
                        class="w-full px-3 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ex : 15 mars 2026 · Athénée de Namur" oninput="scheduleAutoSave()">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Classe / groupe</label>
                    <input type="text" id="classe_groupe" value="<?= h($data['classe_groupe']) ?>"
                        class="w-full px-3 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ex : 4e secondaire · 22 élèves" oninput="scheduleAutoSave()">
                </div>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 1. CADRAGE              -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in card-section">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">1</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Cadrage de l'animation</h2>
                    <p class="text-gray-500 text-sm">Public, objectifs, fil rouge — la base qui conditionne tout le reste</p>
                </div>
            </div>

            <!-- Public visé -->
            <div class="mb-5 indigo-border">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Public visé</label>
                <p class="text-xs text-gray-500 mb-3 italic">Coche le public-cible. Cela conditionne les outils, le vocabulaire, la profondeur des points de risque.</p>
                <div class="grid md:grid-cols-2 gap-2 mb-3">
                    <?php foreach ($publics as $key => $label): ?>
                    <label class="flex items-center gap-2 p-3 border-2 rounded-lg cursor-pointer chk-card <?= $data['public'] === $key ? 'checked' : 'border-gray-200' ?>"
                        data-radio-target="public" data-radio-value="<?= $key ?>">
                        <input type="radio" name="public" value="<?= $key ?>" <?= $data['public'] === $key ? 'checked' : '' ?>
                            class="text-indigo-600" onchange="updateRadio('public', this.value)">
                        <span class="text-sm"><?= h($label) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <textarea id="public_precisions" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                    placeholder="Précisions sur le public (effectif, contexte scolaire, particularités). Ex : 22 élèves de 4e secondaire générale, classe peu numérique, école à Namur, demande de la prof de français suite à un projet sur la désinformation."
                    oninput="scheduleAutoSave()"><?= h($data['public_precisions']) ?></textarea>
            </div>

            <!-- Objectif principal -->
            <div class="mb-5 indigo-border">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Objectif principal <span class="text-red-500">*</span></label>
                <p class="text-xs text-gray-500 mb-2 italic">UN seul. L'objectif qui te dira « réussi » ou « raté » à la sortie.</p>
                <textarea id="objectif_principal" rows="2"
                    class="w-full px-3 py-2 border-2 border-indigo-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                    placeholder="Ex : « À la fin de l'animation, chaque élève sait identifier une hallucination d'IA et nomme au moins deux risques liés aux données personnelles. »"
                    oninput="scheduleAutoSave()"><?= h($data['objectif_principal']) ?></textarea>
            </div>

            <!-- Objectifs secondaires -->
            <div class="mb-5 indigo-border">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Objectifs secondaires (2 max)</label>
                <p class="text-xs text-gray-500 mb-2 italic">Ex : Manipuler concrètement un outil IA grand public. — Comprendre que l'IA n'est pas neutre (biais).</p>
                <div class="grid md:grid-cols-2 gap-3">
                    <input type="text" id="objectif_sec_1" value="<?= h($data['objectif_sec_1']) ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                        placeholder="Objectif secondaire 1" oninput="scheduleAutoSave()">
                    <input type="text" id="objectif_sec_2" value="<?= h($data['objectif_sec_2']) ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                        placeholder="Objectif secondaire 2" oninput="scheduleAutoSave()">
                </div>
            </div>

            <!-- Fil rouge -->
            <div class="indigo-border">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Fil rouge / accroche</label>
                <p class="text-xs text-gray-500 mb-2 italic">L'idée forte qui traverse les 90 min — le « pitch » en 1 phrase.</p>
                <input type="text" id="fil_rouge" value="<?= h($data['fil_rouge']) ?>"
                    class="w-full px-3 py-2 border-2 border-indigo-300 rounded-md focus:ring-2 focus:ring-indigo-500 text-lg"
                    placeholder='Ex : « L&apos;IA est utile, mais elle se trompe — apprenons à ne pas se faire avoir. »'
                    oninput="scheduleAutoSave()">
            </div>
        </div>

        <!-- ======================== -->
        <!-- 2. SEQUENCAGE           -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in card-section">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">2</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Séquençage</h2>
                    <p class="text-gray-500 text-sm">Format de référence : 6 séquences de 15 min. Adapte si nécessaire.</p>
                </div>
            </div>

            <!-- Choix du format -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <span class="text-sm font-semibold text-gray-700">Format choisi :</span>
                <?php foreach ($formats as $key => $label): ?>
                <label class="flex items-center gap-2 px-3 py-1.5 border-2 rounded-lg cursor-pointer chk-card <?= ($data['format'] ?? '90') === $key ? 'checked' : 'border-gray-200' ?>"
                    data-radio-target="format" data-radio-value="<?= $key ?>">
                    <input type="radio" name="format" value="<?= $key ?>" <?= ($data['format'] ?? '90') === $key ? 'checked' : '' ?>
                        class="text-indigo-600" onchange="setFormat(this.value)">
                    <span class="text-sm"><?= h($label) ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- Charpente recommandée -->
            <details class="mb-4 bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-sm">
                <summary class="font-semibold text-indigo-800 cursor-pointer">Charpente recommandée pour 6 séquences (90 min)</summary>
                <ul class="mt-2 space-y-1 text-gray-700 text-xs leading-relaxed">
                    <li><strong>S1 (0-15)</strong> — Accueil + accroche choc (démo hallucination ou biais projetée). Cadrage des règles.</li>
                    <li><strong>S2 (15-30)</strong> — Manipulation 1 : tous testent un même prompt dans un outil. Comparaison des sorties.</li>
                    <li><strong>S3 (30-45)</strong> — Mini-exposé interactif : comment ça marche, pourquoi les erreurs. 10 min max.</li>
                    <li><strong>S4 (45-60)</strong> — Manipulation 2 : défi en groupes (fausse interview, image biaisée…).</li>
                    <li><strong>S5 (60-75)</strong> — Mise en commun + transmission des 3-4 points d'attention retenus.</li>
                    <li><strong>S6 (75-90)</strong> — Évaluation à chaud + ressources + annonce de la suite.</li>
                </ul>
            </details>

            <!-- Tableau séquences -->
            <div class="flex justify-between items-center mb-2">
                <span id="seqCount" class="text-sm text-gray-500">0 séquence(s) remplie(s)</span>
                <button onclick="addSequence()" class="no-print bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-3 py-1.5 rounded font-medium">&#x2795; Ajouter une séquence</button>
            </div>

            <div id="sequencesContainer" class="space-y-3"></div>
        </div>

        <!-- ======================== -->
        <!-- 3. OUTILS               -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in card-section">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">3</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Outils utilisés</h2>
                    <p class="text-gray-500 text-sm">Distingue ce qui est PROJETÉ (toi qui pilotes) et ce qui est MANIPULÉ (les jeunes qui font)</p>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-5">
                <!-- Outils projetés -->
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <h3 class="font-bold text-blue-800 mb-3 text-sm">&#x1F4FA; Outils projetés (compte adulte)</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Outil principal projeté</label>
                            <textarea id="outil_projete_1" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                placeholder="Ex : ChatGPT (compte formateur). Pour démo hallucination + manipulation collective dictée par les élèves."
                                oninput="scheduleAutoSave()"><?= h($data['outil_projete_1']) ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Outil secondaire projeté <span class="text-gray-400">(optionnel)</span></label>
                            <textarea id="outil_projete_2" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                placeholder="Ex : Bing Image Creator pour la démo de biais."
                                oninput="scheduleAutoSave()"><?= h($data['outil_projete_2']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Outils manipulés -->
                <div class="bg-emerald-50 p-4 rounded-lg border border-emerald-200">
                    <h3 class="font-bold text-emerald-800 mb-3 text-sm">&#x270B; Outils manipulés par les jeunes</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Outil manipulé n°1</label>
                            <textarea id="outil_manipule_1" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                placeholder="Ex : Le Chat (Mistral) — hébergé en UE, gratuit, sans compte obligatoire."
                                oninput="scheduleAutoSave()"><?= h($data['outil_manipule_1']) ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Outil manipulé n°2 <span class="text-gray-400">(optionnel)</span></label>
                            <textarea id="outil_manipule_2" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                placeholder="Ex : Perplexity — pour exercice de recherche sourcée."
                                oninput="scheduleAutoSave()"><?= h($data['outil_manipule_2']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rappel CGU -->
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-4 text-xs text-amber-800">
                <strong>Rappel CGU :</strong> ChatGPT, Claude, Gemini → 13 ans minimum, avec accord parental jusqu'à 18 ans.
                ElevenLabs / Character.AI → 18+. Le Chat (Mistral) → 15+. Vérifier ce que ton école/centre autorise.
            </div>

            <!-- Plan B -->
            <div class="mt-4 indigo-border">
                <label class="block text-sm font-semibold text-gray-700 mb-1">&#x1F198; Plan B technique</label>
                <textarea id="plan_b" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                    placeholder="Ex : Si wifi instable, basculer en démo projetée 4G. Si ChatGPT plante, Claude prêt en onglet de secours."
                    oninput="scheduleAutoSave()"><?= h($data['plan_b']) ?></textarea>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 4. POINTS D'ATTENTION   -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in card-section">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">4</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Les 7 points d'attention à transmettre</h2>
                    <p class="text-gray-500 text-sm">Coche ceux que tu intègres explicitement. Vise <strong>3 à 4 minimum</strong> sur 90 min (plus = saturation cognitive).</p>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-3">
                <?php foreach ($pointsAttention as $key => $point):
                    $checked = in_array($key, $data['points_coches'] ?? []);
                ?>
                <label class="chk-card p-4 border-2 rounded-lg <?= $checked ? 'checked' : 'border-gray-200' ?>"
                    data-chk-target="points_coches" data-chk-value="<?= $key ?>">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" value="<?= $key ?>" <?= $checked ? 'checked' : '' ?>
                            onchange="toggleCheckbox('points_coches', this.value, this.checked)"
                            class="mt-1 text-indigo-600 w-5 h-5 cursor-pointer">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg"><?= $point['icon'] ?></span>
                                <strong class="text-gray-800"><?= h($point['titre']) ?></strong>
                                <span class="text-xs bg-<?= $point['color'] ?>-100 text-<?= $point['color'] ?>-700 px-2 py-0.5 rounded-full"><?= h($point['modalite']) ?></span>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed"><?= h($point['description']) ?></p>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                <strong>Cas particulier 12-13 ans :</strong> prioriser <strong>Biais</strong>, <strong>Hallucinations</strong> et <strong>Données perso</strong>. Les autres viendront dans un atelier de suivi. Mieux vaut 3 points solides que 7 survolés.
            </div>

            <div class="mt-3 text-sm font-semibold text-indigo-700">
                <span id="pointsCount">0</span> point(s) d'attention sélectionné(s)
            </div>
        </div>

        <!-- ======================== -->
        <!-- 5. MATERIEL             -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in card-section">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">5</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Matériel et préparation</h2>
                    <p class="text-gray-500 text-sm">Coche les éléments à vérifier ou prévoir</p>
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-4 mb-5">
                <!-- Matériel salle -->
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <h3 class="font-bold text-blue-800 mb-2 text-sm">&#x1F3EB; Matériel salle</h3>
                    <div class="space-y-2 text-sm" id="materielSalle"></div>
                </div>

                <!-- Matériel formateur -->
                <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                    <h3 class="font-bold text-purple-800 mb-2 text-sm">&#x1F4BB; Matériel formateur</h3>
                    <div class="space-y-2 text-sm" id="materielFormateur"></div>
                </div>

                <!-- Matériel élèves -->
                <div class="bg-emerald-50 p-4 rounded-lg border border-emerald-200">
                    <h3 class="font-bold text-emerald-800 mb-2 text-sm">&#x1F393; Matériel élèves</h3>
                    <div class="space-y-2 text-sm" id="materielEleves"></div>
                </div>
            </div>

            <div class="indigo-border">
                <label class="block text-sm font-semibold text-gray-700 mb-1">&#x1F4DD; Préparation J-1</label>
                <textarea id="preparation_j1" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                    placeholder="Ex : Imprimer 25 fiches « règle de la carte postale ». Préparer 3 prompts de démo. Tester la connexion à Le Chat sans compte sur navigateur incognito."
                    oninput="scheduleAutoSave()"><?= h($data['preparation_j1']) ?></textarea>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 6. EVALUATION ET SUIVI  -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in card-section">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">6</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Évaluation et suivi</h2>
                    <p class="text-gray-500 text-sm">Évaluation à chaud + bilan personnel + suivi avec l'enseignant·e</p>
                </div>
            </div>

            <!-- Modalité d'évaluation -->
            <div class="mb-5 indigo-border">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Modalité d'évaluation à chaud (5 dernières min)</label>
                <p class="text-xs text-gray-500 mb-3 italic">Choisis UNE modalité, pas trois. La fatigue est réelle en fin d'animation.</p>
                <div class="grid md:grid-cols-2 gap-2">
                    <?php foreach ($modalitesEval as $key => $label): ?>
                    <label class="flex items-start gap-2 p-3 border-2 rounded-lg cursor-pointer chk-card <?= $data['modalite_eval'] === $key ? 'checked' : 'border-gray-200' ?>"
                        data-radio-target="modalite_eval" data-radio-value="<?= $key ?>">
                        <input type="radio" name="modalite_eval" value="<?= $key ?>" <?= $data['modalite_eval'] === $key ? 'checked' : '' ?>
                            class="mt-0.5 text-indigo-600" onchange="updateRadio('modalite_eval', this.value)">
                        <span class="text-sm"><?= h($label) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Bilan personnel -->
            <h3 class="font-bold text-gray-800 mb-3 text-sm">&#x1F4D3; Bilan personnel <span class="text-gray-500 font-normal italic">(à remplir le soir même ou le lendemain)</span></h3>
            <div class="grid md:grid-cols-3 gap-4 mb-5">
                <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                    <label class="block text-xs font-semibold text-green-800 mb-1">&#x2705; Ce qui a marché</label>
                    <textarea id="bilan_marche" rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        placeholder="Ex : Le défi de la fausse interview a captivé. Tous les groupes ont participé activement."
                        oninput="scheduleAutoSave()"><?= h($data['bilan_marche']) ?></textarea>
                </div>
                <div class="bg-red-50 p-3 rounded-lg border border-red-200">
                    <label class="block text-xs font-semibold text-red-800 mb-1">&#x26A0;&#xFE0F; Ce qui a coincé</label>
                    <textarea id="bilan_coince" rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        placeholder="Ex : Le mini-exposé sur le fonctionnement des LLM a été trop long, j'ai perdu les 12-13 ans."
                        oninput="scheduleAutoSave()"><?= h($data['bilan_coince']) ?></textarea>
                </div>
                <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                    <label class="block text-xs font-semibold text-blue-800 mb-1">&#x1F504; Ce que je change la prochaine fois</label>
                    <textarea id="bilan_change" rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        placeholder="Ex : Supprimer le passage sur les tokens. Garder l'analogie de l'autocomplétion seule."
                        oninput="scheduleAutoSave()"><?= h($data['bilan_change']) ?></textarea>
                </div>
            </div>

            <!-- Suivi enseignant -->
            <div class="indigo-border">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Suivi avec l'enseignant·e</label>
                <p class="text-xs text-gray-500 mb-2 italic">Proposer un retour 1 ou 2 semaines après pour récolter les observations à froid.</p>
                <textarea id="suivi_enseignant" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                    placeholder="Ex : Mail prévu le 30 mars. Questions : qu'est-ce qui a marqué ? qu'est-ce qui a déjà été réutilisé ?"
                    oninput="scheduleAutoSave()"><?= h($data['suivi_enseignant']) ?></textarea>
            </div>
        </div>

        <!-- ======================== -->
        <!-- NOTES & ACTIONS         -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in card-section">
            <h2 class="text-xl font-bold text-gray-800 mb-4">&#x270F;&#xFE0F; Notes libres</h2>
            <textarea id="notes" rows="3"
                class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500"
                placeholder="Notes libres, questions pour le débriefing, idées d'amélioration..."
                oninput="scheduleAutoSave()"><?= h($data['notes']) ?></textarea>

            <div class="bg-gradient-to-r from-indigo-50 to-violet-50 border-2 border-indigo-300 rounded-lg p-4 mt-5 text-sm">
                <p class="text-indigo-900 font-semibold mb-1">&#x1F4A1; Une dernière chose</p>
                <p class="text-indigo-800 leading-relaxed">
                    Cette animation n'est pas un cours d'informatique. Elle est un atelier d'éducation au regard critique.
                    <strong>Le test de réussite</strong> : à la sortie, est-ce que les jeunes hésitent une seconde de plus
                    avant de croire ce que leur dit un écran ? Si la réponse est oui, c'est réussi. Le reste est du bonus.
                </p>
            </div>

            <div class="no-print flex flex-wrap gap-3 pt-4 mt-4 border-t-2 border-gray-200">
                <button onclick="submitCanevas()" class="bg-indigo-600 text-white px-6 py-3 rounded-md hover:bg-indigo-700 transition font-semibold shadow-md">&#x2705; Soumettre au formateur</button>
                <button onclick="exportToExcel()" class="bg-emerald-600 text-white px-6 py-3 rounded-md hover:bg-emerald-700 transition font-semibold shadow-md">&#x1F4CA; Export Excel</button>
                <button onclick="exportJSON()" class="bg-gray-500 text-white px-6 py-3 rounded-md hover:bg-gray-600 transition font-semibold shadow-md">&#x1F4E5; JSON</button>
                <button onclick="window.print()" class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition font-semibold shadow-md">&#x1F5A8;&#xFE0F; Imprimer</button>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>
    <script>
    // Données
    let data = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
    let autoSaveTimeout = null;
    const pointsAttention = <?= json_encode($pointsAttention, JSON_UNESCAPED_UNICODE) ?>;
    const publics = <?= json_encode($publics, JSON_UNESCAPED_UNICODE) ?>;
    const modalitesEval = <?= json_encode($modalitesEval, JSON_UNESCAPED_UNICODE) ?>;

    // Listes du matériel
    const materielSalleItems = [
        'Vidéoprojecteur connecté à ton ordinateur',
        'Son (haut-parleurs ou sortie audio testée)',
        'Wifi vérifié (faire un test 24h avant)',
        'Tables modulables pour groupes de 3-4',
        'Tableau ou paperboard'
    ];
    const materielFormateurItems = [
        'Ordinateur + chargeur',
        'Clé 4G de secours',
        'Smartphone pour démo image si besoin',
        'Comptes connectés sur les outils utilisés',
        'Onglets pré-ouverts dans l\'ordre des démos',
        'Démos pré-enregistrées en local (au cas où)',
        'Minuteur visible (chronomètre projeté ou téléphone)'
    ];
    const materielElevesItems = [
        'Au moins un appareil pour 2-3 élèves (ordi, tablette, smartphone)',
        'Crayon et feuille pour la prise de notes ou l\'évaluation',
        'Post-it si activité de mur des positions prévue'
    ];

    // ========================
    // SEQUENCES
    // ========================
    function renderSequences() {
        const c = document.getElementById('sequencesContainer');
        c.innerHTML = '';
        if (!data.sequences || data.sequences.length === 0) {
            c.innerHTML = '<p class="text-center text-gray-400 py-4">Aucune séquence — cliquez sur Ajouter</p>';
            updateSeqCount();
            return;
        }
        data.sequences.forEach((seq, i) => c.appendChild(createSeqCard(seq, i)));
        updateSeqCount();
    }

    function createSeqCard(seq, index) {
        const div = document.createElement('div');
        div.className = 'card-hover fade-in bg-white rounded-lg border-2 border-indigo-100 shadow p-3';
        div.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-20">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Min</label>
                    <input type="text" value="${esc(seq.min || '')}"
                        class="w-full px-2 py-1.5 border rounded text-sm font-mono bg-indigo-50"
                        placeholder="0-15"
                        oninput="updateSequence(${index}, 'min', this.value)">
                </div>
                <div class="flex-1 grid md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Objectif de la séquence</label>
                        <textarea rows="2" class="w-full px-2 py-1.5 border rounded text-sm resize-none"
                            placeholder="Ex : Accueillir + créer la curiosité"
                            oninput="updateSequence(${index}, 'objectif', this.value)">${esc(seq.objectif || '')}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Activité / outil</label>
                        <textarea rows="2" class="w-full px-2 py-1.5 border rounded text-sm resize-none"
                            placeholder="Ex : Démo hallucination projetée"
                            oninput="updateSequence(${index}, 'activite', this.value)">${esc(seq.activite || '')}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Animation (consignes, posture)</label>
                        <textarea rows="2" class="w-full px-2 py-1.5 border rounded text-sm resize-none"
                            placeholder="Ex : Faire dicter le prompt par un élève, lire à voix haute"
                            oninput="updateSequence(${index}, 'animation', this.value)">${esc(seq.animation || '')}</textarea>
                    </div>
                </div>
                <button onclick="removeSequence(${index})" class="no-print text-red-400 hover:text-red-600 mt-5" title="Supprimer">&#x2716;</button>
            </div>
        `;
        return div;
    }

    function addSequence() {
        if (!data.sequences) data.sequences = [];
        data.sequences.push({ min: '', objectif: '', activite: '', animation: '' });
        renderSequences();
        scheduleAutoSave();
        setTimeout(() => { document.getElementById('sequencesContainer').lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    }

    function removeSequence(i) {
        if (!confirm('Supprimer cette séquence ?')) return;
        data.sequences.splice(i, 1);
        renderSequences();
        scheduleAutoSave();
    }

    function updateSequence(i, field, val) {
        if (data.sequences[i]) {
            data.sequences[i][field] = val;
            updateSeqCount();
            scheduleAutoSave();
        }
    }

    function updateSeqCount() {
        const n = (data.sequences || []).filter(s => s.objectif || s.activite || s.animation).length;
        document.getElementById('seqCount').textContent = n + ' séquence(s) remplie(s)';
    }

    // Adapter le séquençage au format choisi
    function setFormat(fmt) {
        data.format = fmt;
        document.querySelectorAll('[data-radio-target="format"]').forEach(el => el.classList.toggle('checked', el.dataset.radioValue === fmt));
        if (!confirm('Remplacer le séquençage actuel par un modèle ' + fmt + ' min ? (les contenus existants seront perdus)')) {
            scheduleAutoSave();
            return;
        }
        if (fmt === '60') {
            data.sequences = [
                { min: '0-10', objectif: 'Accroche + démo hallucination projetée', activite: '', animation: '' },
                { min: '10-25', objectif: 'Manipulation guidée (un outil, défi en groupe)', activite: '', animation: '' },
                { min: '25-40', objectif: 'Mini-exposé interactif + démo biais', activite: '', animation: '' },
                { min: '40-55', objectif: '3 points d\'attention (biais / hallucinations / données perso)', activite: '', animation: '' },
                { min: '55-60', objectif: 'Évaluation à chaud + ressources', activite: '', animation: '' },
            ];
        } else if (fmt === '120') {
            data.sequences = [
                { min: '0-15', objectif: 'Accueil + accroche', activite: '', animation: '' },
                { min: '15-35', objectif: 'Manipulation 1 (texte)', activite: '', animation: '' },
                { min: '35-50', objectif: 'Mini-exposé : comment ça marche', activite: '', animation: '' },
                { min: '50-75', objectif: 'Manipulation 2 (image OU recherche sourcée)', activite: '', animation: '' },
                { min: '75-95', objectif: 'Travail en groupes : message de prévention « 1 risque IA »', activite: '', animation: '' },
                { min: '95-115', objectif: 'Restitutions + points d\'attention', activite: '', animation: '' },
                { min: '115-120', objectif: 'Évaluation à chaud', activite: '', animation: '' },
            ];
        } else {
            data.sequences = [
                { min: '0-15', objectif: '', activite: '', animation: '' },
                { min: '15-30', objectif: '', activite: '', animation: '' },
                { min: '30-45', objectif: '', activite: '', animation: '' },
                { min: '45-60', objectif: '', activite: '', animation: '' },
                { min: '60-75', objectif: '', activite: '', animation: '' },
                { min: '75-90', objectif: '', activite: '', animation: '' },
            ];
        }
        renderSequences();
        scheduleAutoSave();
    }

    // ========================
    // MATERIEL (checklists)
    // ========================
    function renderMateriel(containerId, items, dataKey) {
        const c = document.getElementById(containerId);
        c.innerHTML = '';
        const selected = data[dataKey] || [];
        items.forEach(item => {
            const checked = selected.includes(item);
            const label = document.createElement('label');
            label.className = 'flex items-start gap-2 cursor-pointer p-1 rounded hover:bg-white/50';
            label.innerHTML = `
                <input type="checkbox" ${checked ? 'checked' : ''} class="mt-0.5 text-indigo-600"
                    onchange="toggleCheckbox('${dataKey}', ${JSON.stringify(item).replace(/'/g, '&#39;')}, this.checked)">
                <span class="text-gray-700">${esc(item)}</span>
            `;
            c.appendChild(label);
        });
    }

    // ========================
    // POINTS D'ATTENTION
    // ========================
    function updatePointsCount() {
        document.getElementById('pointsCount').textContent = (data.points_coches || []).length;
    }

    function toggleCheckbox(field, value, checked) {
        if (!Array.isArray(data[field])) data[field] = [];
        if (checked) {
            if (!data[field].includes(value)) data[field].push(value);
        } else {
            data[field] = data[field].filter(v => v !== value);
        }
        // Mettre à jour visuel chk-card
        document.querySelectorAll(`[data-chk-target="${field}"][data-chk-value="${CSS.escape(value)}"]`).forEach(el => el.classList.toggle('checked', checked));
        if (field === 'points_coches') updatePointsCount();
        scheduleAutoSave();
    }

    // ========================
    // RADIOS
    // ========================
    function updateRadio(field, value) {
        data[field] = value;
        document.querySelectorAll(`[data-radio-target="${field}"]`).forEach(el => el.classList.toggle('checked', el.dataset.radioValue === value));
        scheduleAutoSave();
    }

    // ========================
    // SAUVEGARDE
    // ========================
    function scheduleAutoSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        document.getElementById('saveStatus').textContent = 'Sauvegarde...';
        document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-400 text-yellow-900';
        autoSaveTimeout = setTimeout(saveData, 1000);
    }

    function collectFromInputs() {
        // Collecter les champs texte directs
        const fields = ['animateur', 'date_lieu', 'classe_groupe', 'public_precisions',
            'objectif_principal', 'objectif_sec_1', 'objectif_sec_2', 'fil_rouge',
            'outil_projete_1', 'outil_projete_2', 'outil_manipule_1', 'outil_manipule_2',
            'plan_b', 'preparation_j1', 'bilan_marche', 'bilan_coince', 'bilan_change',
            'suivi_enseignant', 'notes'];
        fields.forEach(f => {
            const el = document.getElementById(f);
            if (el) data[f] = el.value;
        });
    }

    async function saveData() {
        collectFromInputs();
        try {
            const r = await fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const res = await r.json();
            if (res.success) {
                document.getElementById('saveStatus').textContent = 'Sauvegardé';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                document.getElementById('completion').innerHTML = 'Complétion : <strong>' + res.completion + '%</strong>';
            } else {
                document.getElementById('saveStatus').textContent = 'Erreur';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
            }
        } catch (e) {
            document.getElementById('saveStatus').textContent = 'Erreur réseau';
            document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
        }
    }

    async function manualSave() { if (autoSaveTimeout) clearTimeout(autoSaveTimeout); await saveData(); }

    async function submitCanevas() {
        if (!confirm('Soumettre votre canevas au formateur ?')) return;
        await saveData();
        try {
            const r = await fetch('api/submit.php', { method: 'POST' });
            const res = await r.json();
            if (res.success) {
                document.getElementById('saveStatus').textContent = 'Soumis';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-indigo-500 text-white';
                alert('Canevas soumis !');
            }
        } catch (e) { console.error(e); }
    }

    // ========================
    // EXPORTS
    // ========================
    function exportJSON() {
        collectFromInputs();
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'canevas_animation_' + new Date().toISOString().split('T')[0] + '.json';
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function exportToExcel() {
        collectFromInputs();
        const wb = XLSX.utils.book_new();

        // Cadrage
        const cadrageData = [
            ['CANEVAS D\'ANIMATION IA — 90 min'],
            [],
            ['Animateur·rice', data.animateur || ''],
            ['Date et lieu', data.date_lieu || ''],
            ['Classe / groupe', data.classe_groupe || ''],
            [],
            ['CADRAGE'],
            ['Public visé', publics[data.public] || ''],
            ['Précisions public', data.public_precisions || ''],
            ['Objectif principal', data.objectif_principal || ''],
            ['Objectif secondaire 1', data.objectif_sec_1 || ''],
            ['Objectif secondaire 2', data.objectif_sec_2 || ''],
            ['Fil rouge', data.fil_rouge || ''],
            ['Format', data.format + ' min']
        ];
        const ws1 = XLSX.utils.aoa_to_sheet(cadrageData);
        ws1['!cols'] = [{wch: 25}, {wch: 80}];

        // Séquençage
        const seqData = [['SÉQUENÇAGE'], [], ['Min', 'Objectif', 'Activité / outil', 'Animation']];
        (data.sequences || []).forEach(s => {
            seqData.push([s.min || '', s.objectif || '', s.activite || '', s.animation || '']);
        });
        const ws2 = XLSX.utils.aoa_to_sheet(seqData);
        ws2['!cols'] = [{wch: 8}, {wch: 30}, {wch: 35}, {wch: 35}];

        // Outils
        const outilsData = [
            ['OUTILS'],
            [],
            ['Outil principal projeté', data.outil_projete_1 || ''],
            ['Outil secondaire projeté', data.outil_projete_2 || ''],
            ['Outil manipulé n°1', data.outil_manipule_1 || ''],
            ['Outil manipulé n°2', data.outil_manipule_2 || ''],
            ['Plan B technique', data.plan_b || '']
        ];
        const ws3 = XLSX.utils.aoa_to_sheet(outilsData);
        ws3['!cols'] = [{wch: 25}, {wch: 70}];

        // Points d'attention
        const pointsData = [['POINTS D\'ATTENTION'], [], ['Coché', 'Point', 'Modalité', 'Description']];
        Object.keys(pointsAttention).forEach(key => {
            const p = pointsAttention[key];
            const checked = (data.points_coches || []).includes(key) ? 'OUI' : '';
            pointsData.push([checked, p.titre, p.modalite, p.description]);
        });
        const ws4 = XLSX.utils.aoa_to_sheet(pointsData);
        ws4['!cols'] = [{wch: 8}, {wch: 25}, {wch: 18}, {wch: 60}];

        // Matériel
        const matData = [['MATÉRIEL ET PRÉPARATION'], [], ['Catégorie', 'Élément']];
        (data.materiel_salle || []).forEach(i => matData.push(['Salle', i]));
        (data.materiel_formateur || []).forEach(i => matData.push(['Formateur', i]));
        (data.materiel_eleves || []).forEach(i => matData.push(['Élèves', i]));
        matData.push([]);
        matData.push(['Préparation J-1', data.preparation_j1 || '']);
        const ws5 = XLSX.utils.aoa_to_sheet(matData);
        ws5['!cols'] = [{wch: 15}, {wch: 70}];

        // Évaluation et bilan
        const evalData = [
            ['ÉVALUATION ET SUIVI'],
            [],
            ['Modalité éval à chaud', modalitesEval[data.modalite_eval] || ''],
            [],
            ['BILAN PERSONNEL'],
            ['Ce qui a marché', data.bilan_marche || ''],
            ['Ce qui a coincé', data.bilan_coince || ''],
            ['Ce que je change', data.bilan_change || ''],
            ['Suivi enseignant·e', data.suivi_enseignant || ''],
            [],
            ['Notes libres', data.notes || '']
        ];
        const ws6 = XLSX.utils.aoa_to_sheet(evalData);
        ws6['!cols'] = [{wch: 25}, {wch: 70}];

        XLSX.utils.book_append_sheet(wb, ws1, 'Cadrage');
        XLSX.utils.book_append_sheet(wb, ws2, 'Séquençage');
        XLSX.utils.book_append_sheet(wb, ws3, 'Outils');
        XLSX.utils.book_append_sheet(wb, ws4, 'Points attention');
        XLSX.utils.book_append_sheet(wb, ws5, 'Matériel');
        XLSX.utils.book_append_sheet(wb, ws6, 'Évaluation');

        const nom = (data.animateur || 'canevas').replace(/[^a-z0-9]/gi, '_').toLowerCase();
        XLSX.writeFile(wb, 'canevas_anim_' + nom + '_' + new Date().toISOString().split('T')[0] + '.xlsx');
    }

    function esc(t) { const d = document.createElement('div'); d.appendChild(document.createTextNode(t || '')); return d.innerHTML; }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        renderSequences();
        renderMateriel('materielSalle', materielSalleItems, 'materiel_salle');
        renderMateriel('materielFormateur', materielFormateurItems, 'materiel_formateur');
        renderMateriel('materielEleves', materielElevesItems, 'materiel_eleves');
        updatePointsCount();
    });
    </script>
</body>
</html>
