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

$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$analyse = $stmt->fetch();

if (!$analyse) {
    $stmt = $db->prepare("INSERT INTO analyses (user_id, session_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $sessionId]);
    $stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$user['id'], $sessionId]);
    $analyse = $stmt->fetch();
}

$defaults = getDefaultData();
$nomOrg = $analyse['nom_organisation'] ?? '';
$s1 = json_decode($analyse['section1_data'] ?? '{}', true) ?: $defaults['section1_data'];
$s2 = json_decode($analyse['section2_data'] ?? '{}', true) ?: $defaults['section2_data'];
$s3 = json_decode($analyse['section3_data'] ?? '{}', true) ?: $defaults['section3_data'];
$s4 = json_decode($analyse['section4_data'] ?? '{}', true) ?: $defaults['section4_data'];
$s5 = json_decode($analyse['section5_data'] ?? '{}', true) ?: $defaults['section5_data'];
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;

// Ensure arrays have correct structure
$s1['valeurs'] = array_pad($s1['valeurs'] ?? [], 3, '');
$s1['valeurs_scores'] = array_pad($s1['valeurs_scores'] ?? [], 3, ['score' => 0, 'commentaire' => '']);
$s2['contraintes'] = array_pad($s2['contraintes'] ?? [], 3, '');
$s2['atouts'] = array_pad($s2['atouts'] ?? [], 3, '');
$s3['parties_prenantes'] = array_pad($s3['parties_prenantes'] ?? [], 4, ['nom' => '', 'engagement' => 0, 'actions' => '']);
$s3['obstacles'] = array_pad($s3['obstacles'] ?? [], 3, '');
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Diagnostic Communication - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%230891b2'/><path d='M8 10h16M8 16h12M8 22h14' stroke='white' stroke-width='2' stroke-linecap='round'/><circle cx='24' cy='22' r='4' fill='%2222d3ee'/><path d='M23 22l1 1 2-2' stroke='white' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #164e63 0%, #0e7490 50%, #155e75 100%); min-height: 100vh; }
        @media print { .no-print { display: none !important; } body { background: white; } .page-break { page-break-before: always; } }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .section-number { display: flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 50%; background: #0891b2; color: white; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .score-radio { display: none; }
        .score-label { cursor: pointer; padding: 0.4rem 0.75rem; border-radius: 0.5rem; border: 2px solid #e5e7eb; transition: all 0.2s; font-weight: 600; font-size: 0.9rem; }
        .score-label:hover { border-color: #0891b2; background: #ecfeff; }
        .score-radio:checked + .score-label { background: #0891b2; color: white; border-color: #0891b2; }
        .checkbox-custom { cursor: pointer; padding: 0.5rem 1rem; border-radius: 0.5rem; border: 2px solid #e5e7eb; transition: all 0.2s; }
        .checkbox-custom:hover { border-color: #0891b2; background: #ecfeff; }
        .checkbox-custom.active { background: #0891b2; color: white; border-color: #0891b2; }
        .progress-ring { transition: stroke-dashoffset 0.5s ease; }
    </style>
</head>
<body class="p-4 md:p-8">
    <!-- Barre utilisateur -->
    <div class="max-w-5xl mx-auto mb-4 bg-white/90 backdrop-blur rounded-lg shadow-lg p-3 no-print">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium text-gray-800"><?= h($user['prenom']) ?> <?= h($user['nom']) ?></span>
                <span class="text-gray-500 text-sm ml-2"><?= h($sessionNom) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('text-sm bg-white/20 text-gray-800 px-2 py-1 rounded border border-gray-300') ?>
                <button onclick="manualSave()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">Sauvegarder</button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-gray-200"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <span id="completion" class="text-sm text-gray-600">Completion: <strong>0%</strong></span>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="text-sm bg-cyan-600 hover:bg-cyan-500 text-white px-3 py-1 rounded transition">Formateur</a>
                <?php endif; ?>
                <?= renderHomeLink() ?>
                <a href="logout.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
            <div class="text-center mb-6">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Fiche d'Auto-Diagnostic</h1>
                <p class="text-gray-600 italic">Evaluez la validite de la communication de votre organisation</p>
            </div>

            <div class="bg-gradient-to-r from-cyan-50 via-sky-50 to-teal-50 p-6 rounded-lg border-2 border-cyan-200 shadow-md mb-6">
                <p class="text-gray-700 leading-relaxed">
                    Ce questionnaire vous permet d'evaluer votre communication sur <strong>3 dimensions cles</strong> :
                    vos <strong>valeurs et mission</strong>, vos <strong>contraintes et ressources</strong>, et votre capacite
                    de <strong>mobilisation et engagement</strong>. Prenez le temps de refleter sur chaque question en pensant
                    a votre organisation.
                </p>
            </div>

            <div class="bg-gradient-to-r from-cyan-50 to-sky-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">Votre organisation</label>
                <input type="text" id="nomOrganisation"
                    class="w-full px-4 py-2 border-2 border-cyan-200 rounded-md focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
                    placeholder="Nom de votre organisation..."
                    value="<?= h($nomOrg) ?>"
                    oninput="scheduleAutoSave()">
            </div>
        </div>

        <!-- ======================== -->
        <!-- 1. VALEURS ET MISSION    -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-6">
                <div class="section-number">1</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Valeurs et Mission</h2>
                    <p class="text-gray-500 text-sm">Identifiez vos valeurs fondamentales et evaluez leur visibilite</p>
                </div>
            </div>

            <!-- a) 3 valeurs fondamentales -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-3">a) Quelles sont les 3 valeurs fondamentales de votre organisation ?</label>
                <div class="space-y-3">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="flex items-center gap-3">
                        <span class="bg-cyan-100 text-cyan-700 font-bold w-8 h-8 rounded-full flex items-center justify-center text-sm flex-shrink-0"><?= $i + 1 ?></span>
                        <input type="text" id="valeur_<?= $i ?>"
                            class="flex-1 px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
                            placeholder="Valeur <?= $i + 1 ?>..."
                            value="<?= h($s1['valeurs'][$i] ?? '') ?>"
                            oninput="scheduleAutoSave()">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- b) Scores de visibilite -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-1">b) Evaluez la visibilite de ces valeurs dans votre communication actuelle</label>
                <p class="text-gray-500 text-sm mb-3">(1 = tres peu visible, 5 = parfaitement visible)</p>
                <div class="space-y-4">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="bg-cyan-50 p-4 rounded-lg border border-cyan-200">
                        <div class="flex flex-wrap items-center gap-4 mb-2">
                            <span class="font-medium text-gray-700 min-w-[80px]" id="valeurLabel_<?= $i ?>">
                                <?= !empty(trim($s1['valeurs'][$i] ?? '')) ? h($s1['valeurs'][$i]) : 'Valeur ' . ($i + 1) ?>
                            </span>
                            <div class="flex gap-2">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <input type="radio" name="score_<?= $i ?>" value="<?= $s ?>" id="score_<?= $i ?>_<?= $s ?>"
                                    class="score-radio" <?= (($s1['valeurs_scores'][$i]['score'] ?? 0) == $s) ? 'checked' : '' ?>
                                    onchange="scheduleAutoSave()">
                                <label for="score_<?= $i ?>_<?= $s ?>" class="score-label"><?= $s ?></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <input type="text" id="commentaire_<?= $i ?>"
                            class="w-full px-3 py-1.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-cyan-500"
                            placeholder="Commentaire (optionnel)..."
                            value="<?= h($s1['valeurs_scores'][$i]['commentaire'] ?? '') ?>"
                            oninput="scheduleAutoSave()">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- c) Exemple positif -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-2">c) Identifiez un exemple concret ou votre communication traduit parfaitement vos valeurs</label>
                <textarea id="exemplePositif" rows="3"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500"
                    placeholder="Decrivez un exemple concret..."
                    oninput="scheduleAutoSave()"><?= h($s1['exemple_positif'] ?? '') ?></textarea>
            </div>

            <!-- d) Exemple de decalage -->
            <div>
                <label class="block text-base font-semibold text-gray-700 mb-2">d) Identifiez un exemple ou il y a un decalage entre vos valeurs et votre communication</label>
                <textarea id="exempleDecalage" rows="3"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500"
                    placeholder="Decrivez un exemple de decalage..."
                    oninput="scheduleAutoSave()"><?= h($s1['exemple_decalage'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 2. CONTRAINTES ET RESSOURCES -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-6">
                <div class="section-number">2</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Contraintes et Ressources</h2>
                    <p class="text-gray-500 text-sm">Analysez vos moyens et vos limites en communication</p>
                </div>
            </div>

            <!-- a) Budget -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-3">a) Quel pourcentage approximatif de votre budget global est dedie a la communication ?</label>
                <div class="flex flex-wrap gap-3">
                    <?php
                    $budgetOptions = ['moins_2' => 'Moins de 2%', '2_5' => '2-5%', '5_10' => '5-10%', 'plus_10' => 'Plus de 10%', 'ne_sais_pas' => 'Ne sais pas'];
                    foreach ($budgetOptions as $val => $label): ?>
                    <input type="radio" name="budget" value="<?= $val ?>" id="budget_<?= $val ?>"
                        class="score-radio" <?= (($s2['budget'] ?? '') === $val) ? 'checked' : '' ?>
                        onchange="scheduleAutoSave()">
                    <label for="budget_<?= $val ?>" class="score-label"><?= $label ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- b) Contraintes -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-3">b) Quelles sont vos 3 principales contraintes en matiere de communication ?</label>
                <div class="space-y-3">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="flex items-center gap-3">
                        <span class="bg-red-100 text-red-600 font-bold w-8 h-8 rounded-full flex items-center justify-center text-sm flex-shrink-0"><?= $i + 1 ?></span>
                        <input type="text" id="contrainte_<?= $i ?>"
                            class="flex-1 px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
                            placeholder="Contrainte <?= $i + 1 ?>..."
                            value="<?= h($s2['contraintes'][$i] ?? '') ?>"
                            oninput="scheduleAutoSave()">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- c) Atouts -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-3">c) Quels sont vos 3 principaux atouts/ressources pour votre communication ?</label>
                <div class="space-y-3">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="flex items-center gap-3">
                        <span class="bg-green-100 text-green-600 font-bold w-8 h-8 rounded-full flex items-center justify-center text-sm flex-shrink-0"><?= $i + 1 ?></span>
                        <input type="text" id="atout_<?= $i ?>"
                            class="flex-1 px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
                            placeholder="Atout <?= $i + 1 ?>..."
                            value="<?= h($s2['atouts'][$i] ?? '') ?>"
                            oninput="scheduleAutoSave()">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- d) Action efficace -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-2">d) Decrivez une action de communication efficace que vous avez realisee avec des moyens limites</label>
                <textarea id="actionEfficace" rows="3"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500"
                    placeholder="Decrivez cette action..."
                    oninput="scheduleAutoSave()"><?= h($s2['action_efficace'] ?? '') ?></textarea>
            </div>

            <!-- e) Ressources non-financieres -->
            <div>
                <label class="block text-base font-semibold text-gray-700 mb-3">e) Quelles ressources non-financieres pourriez-vous mieux mobiliser ?</label>
                <div class="flex flex-wrap gap-3">
                    <?php
                    $ressNonFin = ['benevoles' => 'Benevoles', 'partenariats' => 'Partenariats', 'competences' => 'Competences internes', 'reseaux' => 'Reseaux personnels'];
                    $selectedRes = $s2['ressources_non_financieres'] ?? [];
                    foreach ($ressNonFin as $val => $label): ?>
                    <div class="checkbox-custom <?= in_array($val, $selectedRes) ? 'active' : '' ?>"
                         onclick="toggleCheckbox(this, '<?= $val ?>')" data-value="<?= $val ?>">
                        <?= $label ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <input type="text" id="ressourceAutre"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500"
                        placeholder="Autres ressources..."
                        value="<?= h($s2['ressources_autre'] ?? '') ?>"
                        oninput="scheduleAutoSave()">
                </div>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 3. MOBILISATION ET ENGAGEMENT -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-6">
                <div class="section-number">3</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Mobilisation et Engagement</h2>
                    <p class="text-gray-500 text-sm">Evaluez l'engagement de vos parties prenantes</p>
                </div>
            </div>

            <!-- a) Parties prenantes -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-1">a) Identifiez vos differentes parties prenantes et evaluez leur niveau d'engagement actuel</label>
                <p class="text-gray-500 text-sm mb-3">(1 = tres faible, 5 = tres fort)</p>
                <div class="space-y-4">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="bg-sky-50 p-4 rounded-lg border border-sky-200">
                        <div class="grid md:grid-cols-3 gap-3 items-start">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Partie prenante</label>
                                <input type="text" id="pp_nom_<?= $i ?>"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-cyan-500"
                                    placeholder="Ex: Donateurs, Benevoles, Usagers..."
                                    value="<?= h($s3['parties_prenantes'][$i]['nom'] ?? '') ?>"
                                    oninput="scheduleAutoSave()">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Niveau d'engagement (1-5)</label>
                                <div class="flex gap-1.5">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <input type="radio" name="pp_eng_<?= $i ?>" value="<?= $s ?>" id="pp_eng_<?= $i ?>_<?= $s ?>"
                                        class="score-radio" <?= (($s3['parties_prenantes'][$i]['engagement'] ?? 0) == $s) ? 'checked' : '' ?>
                                        onchange="scheduleAutoSave()">
                                    <label for="pp_eng_<?= $i ?>_<?= $s ?>" class="score-label text-xs"><?= $s ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Actions pour renforcer</label>
                                <input type="text" id="pp_actions_<?= $i ?>"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-cyan-500"
                                    placeholder="Actions envisagees..."
                                    value="<?= h($s3['parties_prenantes'][$i]['actions'] ?? '') ?>"
                                    oninput="scheduleAutoSave()">
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- b) Transformation -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-1">b) Evaluez votre capacite a transformer la sensibilisation en action concrete</label>
                <p class="text-gray-500 text-sm mb-3">(1 = Tres difficile, 5 = Tres efficace)</p>
                <div class="flex gap-3">
                    <?php for ($s = 1; $s <= 5; $s++):
                        $labels = [1 => 'Tres difficile', 2 => '2', 3 => '3', 4 => '4', 5 => 'Tres efficace'];
                    ?>
                    <input type="radio" name="transformation" value="<?= $s ?>" id="transf_<?= $s ?>"
                        class="score-radio" <?= (($s3['transformation_score'] ?? 0) == $s) ? 'checked' : '' ?>
                        onchange="scheduleAutoSave()">
                    <label for="transf_<?= $s ?>" class="score-label"><?= $labels[$s] ?></label>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- c) Obstacles -->
            <div class="mb-6">
                <label class="block text-base font-semibold text-gray-700 mb-3">c) Quels sont les 3 principaux obstacles a l'engagement de vos publics ?</label>
                <div class="space-y-3">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="flex items-center gap-3">
                        <span class="bg-amber-100 text-amber-600 font-bold w-8 h-8 rounded-full flex items-center justify-center text-sm flex-shrink-0"><?= $i + 1 ?></span>
                        <input type="text" id="obstacle_<?= $i ?>"
                            class="flex-1 px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
                            placeholder="Obstacle <?= $i + 1 ?>..."
                            value="<?= h($s3['obstacles'][$i] ?? '') ?>"
                            oninput="scheduleAutoSave()">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- d) Exemple de mobilisation -->
            <div>
                <label class="block text-base font-semibold text-gray-700 mb-2">d) Decrivez un exemple reussi de mobilisation dans votre organisation</label>
                <textarea id="exempleMobilisation" rows="3"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500"
                    placeholder="Decrivez cet exemple..."
                    oninput="scheduleAutoSave()"><?= h($s3['exemple_mobilisation'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 4. SYNTHESE              -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-6">
                <div class="section-number">4</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Synthese</h2>
                    <p class="text-gray-500 text-sm">Resumez votre diagnostic communication</p>
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <label class="block text-base font-semibold text-gray-700 mb-2">a) Quelle est, selon vous, la principale force distinctive de votre communication ?</label>
                    <textarea id="forceDistinctive" rows="3"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500"
                        placeholder="Votre force distinctive..."
                        oninput="scheduleAutoSave()"><?= h($s4['force_distinctive'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-base font-semibold text-gray-700 mb-2">b) Quel est votre principal defi de communication a relever en priorite ?</label>
                    <textarea id="defiPrioritaire" rows="3"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500"
                        placeholder="Votre defi prioritaire..."
                        oninput="scheduleAutoSave()"><?= h($s4['defi_prioritaire'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-base font-semibold text-gray-700 mb-2">c) Comment articulez-vous valeurs, contraintes et mobilisation dans votre approche ?</label>
                    <textarea id="articulation" rows="4"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500"
                        placeholder="Decrivez comment vous articulez ces trois dimensions..."
                        oninput="scheduleAutoSave()"><?= h($s4['articulation'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 5. PISTES D'ACTION       -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-6">
                <div class="section-number">5</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Pistes d'Action</h2>
                    <p class="text-gray-500 text-sm">Identifiez une action concrete pour ameliorer chaque dimension</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gradient-to-r from-cyan-50 to-sky-50 p-5 rounded-lg border-2 border-cyan-200">
                    <label class="block text-base font-semibold text-cyan-800 mb-2">Valeurs et mission</label>
                    <textarea id="pisteValeurs" rows="3"
                        class="w-full px-4 py-2 border-2 border-cyan-300 rounded-md focus:ring-2 focus:ring-cyan-500"
                        placeholder="Action concrete pour mieux aligner communication et valeurs..."
                        oninput="scheduleAutoSave()"><?= h($s5['piste_valeurs'] ?? '') ?></textarea>
                </div>

                <div class="bg-gradient-to-r from-emerald-50 to-green-50 p-5 rounded-lg border-2 border-emerald-200">
                    <label class="block text-base font-semibold text-emerald-800 mb-2">Optimisation des ressources</label>
                    <textarea id="pisteRessources" rows="3"
                        class="w-full px-4 py-2 border-2 border-emerald-300 rounded-md focus:ring-2 focus:ring-cyan-500"
                        placeholder="Action concrete pour mieux utiliser vos ressources..."
                        oninput="scheduleAutoSave()"><?= h($s5['piste_ressources'] ?? '') ?></textarea>
                </div>

                <div class="bg-gradient-to-r from-amber-50 to-orange-50 p-5 rounded-lg border-2 border-amber-200">
                    <label class="block text-base font-semibold text-amber-800 mb-2">Mobilisation et engagement</label>
                    <textarea id="pisteMobilisation" rows="3"
                        class="w-full px-4 py-2 border-2 border-amber-300 rounded-md focus:ring-2 focus:ring-cyan-500"
                        placeholder="Action concrete pour renforcer l'engagement de vos publics..."
                        oninput="scheduleAutoSave()"><?= h($s5['piste_mobilisation'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Actions finales -->
            <div class="no-print flex flex-wrap gap-3 pt-6 mt-6 border-t-2 border-gray-200">
                <button onclick="submitDiagnostic()" class="bg-cyan-600 text-white px-6 py-3 rounded-md hover:bg-cyan-700 transition font-semibold shadow-md">Soumettre au formateur</button>
                <button onclick="window.print()" class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition font-semibold shadow-md">Imprimer</button>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>
    <script>
    let autoSaveTimeout = null;
    let selectedRessources = <?= json_encode($s2['ressources_non_financieres'] ?? []) ?>;

    // Update value labels dynamically
    document.querySelectorAll('[id^="valeur_"]').forEach((input, i) => {
        input.addEventListener('input', () => {
            const label = document.getElementById('valeurLabel_' + i);
            if (label) label.textContent = input.value.trim() || ('Valeur ' + (i + 1));
        });
    });

    function toggleCheckbox(el, value) {
        const idx = selectedRessources.indexOf(value);
        if (idx > -1) {
            selectedRessources.splice(idx, 1);
            el.classList.remove('active');
        } else {
            selectedRessources.push(value);
            el.classList.add('active');
        }
        scheduleAutoSave();
    }

    function getRadioValue(name) {
        const el = document.querySelector('input[name="' + name + '"]:checked');
        return el ? el.value : '';
    }

    function getRadioIntValue(name) {
        const el = document.querySelector('input[name="' + name + '"]:checked');
        return el ? parseInt(el.value) : 0;
    }

    function gatherData() {
        return {
            nom_organisation: document.getElementById('nomOrganisation').value,
            section1_data: {
                valeurs: [
                    document.getElementById('valeur_0').value,
                    document.getElementById('valeur_1').value,
                    document.getElementById('valeur_2').value
                ],
                valeurs_scores: [
                    { score: getRadioIntValue('score_0'), commentaire: document.getElementById('commentaire_0').value },
                    { score: getRadioIntValue('score_1'), commentaire: document.getElementById('commentaire_1').value },
                    { score: getRadioIntValue('score_2'), commentaire: document.getElementById('commentaire_2').value }
                ],
                exemple_positif: document.getElementById('exemplePositif').value,
                exemple_decalage: document.getElementById('exempleDecalage').value
            },
            section2_data: {
                budget: getRadioValue('budget'),
                contraintes: [
                    document.getElementById('contrainte_0').value,
                    document.getElementById('contrainte_1').value,
                    document.getElementById('contrainte_2').value
                ],
                atouts: [
                    document.getElementById('atout_0').value,
                    document.getElementById('atout_1').value,
                    document.getElementById('atout_2').value
                ],
                action_efficace: document.getElementById('actionEfficace').value,
                ressources_non_financieres: selectedRessources,
                ressources_autre: document.getElementById('ressourceAutre').value
            },
            section3_data: {
                parties_prenantes: [0, 1, 2, 3].map(i => ({
                    nom: document.getElementById('pp_nom_' + i).value,
                    engagement: getRadioIntValue('pp_eng_' + i),
                    actions: document.getElementById('pp_actions_' + i).value
                })),
                transformation_score: getRadioIntValue('transformation'),
                obstacles: [
                    document.getElementById('obstacle_0').value,
                    document.getElementById('obstacle_1').value,
                    document.getElementById('obstacle_2').value
                ],
                exemple_mobilisation: document.getElementById('exempleMobilisation').value
            },
            section4_data: {
                force_distinctive: document.getElementById('forceDistinctive').value,
                defi_prioritaire: document.getElementById('defiPrioritaire').value,
                articulation: document.getElementById('articulation').value
            },
            section5_data: {
                piste_valeurs: document.getElementById('pisteValeurs').value,
                piste_ressources: document.getElementById('pisteRessources').value,
                piste_mobilisation: document.getElementById('pisteMobilisation').value
            }
        };
    }

    function scheduleAutoSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        document.getElementById('saveStatus').textContent = 'Sauvegarde...';
        document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-400 text-yellow-900';
        autoSaveTimeout = setTimeout(saveData, 1000);
    }

    async function saveData() {
        const payload = gatherData();
        try {
            const r = await fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await r.json();
            if (res.success) {
                document.getElementById('saveStatus').textContent = 'Sauvegarde';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                document.getElementById('completion').innerHTML = 'Completion: <strong>' + res.completion + '%</strong>';
            } else {
                document.getElementById('saveStatus').textContent = 'Erreur';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
            }
        } catch (e) {
            document.getElementById('saveStatus').textContent = 'Erreur reseau';
            document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
        }
    }

    async function manualSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        await saveData();
    }

    async function submitDiagnostic() {
        if (!confirm('Soumettre votre diagnostic au formateur ?')) return;
        await saveData();
        try {
            const r = await fetch('api/submit.php', { method: 'POST' });
            const res = await r.json();
            if (res.success) {
                document.getElementById('saveStatus').textContent = 'Soumis';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-cyan-500 text-white';
                alert('Diagnostic soumis avec succes !');
            }
        } catch (e) { console.error(e); }
    }

    function esc(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }

    // Initial save to compute completion
    setTimeout(saveData, 500);
    </script>
</body>
</html>
