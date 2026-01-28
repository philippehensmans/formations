<?php
/**
 * Interface principale - Prompt Engineering pour Public Jeune
 * Atelier de 45 minutes pour maitriser le prompt engineering
 */
require_once __DIR__ . '/config.php';

// Traductions locales pour l'etape 5 (Feedback IA)
global $GLOBALS;
if (!isset($GLOBALS['_local_translations'])) {
    $GLOBALS['_local_translations'] = [];
}
$GLOBALS['_local_translations']['pj'] = array_merge(
    $GLOBALS['_local_translations']['pj'] ?? [],
    [
        'step5_title_ai' => 'Feedback de l\'IA',
        'step5_desc_ai' => 'Demandez a l\'IA d\'analyser votre prompt final et de vous donner un retour.',
        'your_final_prompt' => 'Votre prompt final',
        'no_prompt_yet' => 'Vous n\'avez pas encore redige de prompt ameliore. Retournez a l\'etape 3 pour completer votre travail.',
        'ask_ai_analysis' => 'Demander une analyse a l\'IA',
        'ask_ai_analysis_desc' => 'Cliquez sur le bouton ci-dessous pour generer une demande d\'analyse que vous pourrez copier et coller dans votre IA.',
        'copy_analysis_prompt' => 'Copier la demande d\'analyse',
        'prompt_copied' => 'Demande copiee !',
        'analysis_prompt_template' => "Voici un prompt que j'ai redige. Peux-tu l'analyser selon ces criteres :\n\n1. **Contexte** : Est-ce que j'ai bien explique qui je suis et pour quelle organisation ?\n2. **Objectif** : Est-ce que ma demande est claire et precise ?\n3. **Contraintes** : Est-ce que j'ai indique le ton, la longueur, le public cible ?\n4. **Format** : Est-ce que j'ai precise le format de reponse attendu ?\n\nDonne-moi une note sur 10 et des suggestions concretes d'amelioration.\n\nVoici mon prompt :\n\"{PROMPT}\"",
        'ai_feedback_label' => 'Retour de l\'IA',
        'ai_feedback_hint' => 'Collez ici la reponse de l\'IA a votre demande d\'analyse.',
        'ai_feedback_placeholder' => 'Collez ici l\'analyse de votre prompt par l\'IA...',
        'ready_to_submit' => 'Pret a soumettre',
        'ready_to_submit_desc' => 'Une fois que vous avez recu et note le feedback de l\'IA, vous pouvez soumettre votre travail.',
        'step6_tab' => 'Synthese',
    ]
);

// Fonction locale pour recuperer les traductions (priorite aux locales)
function tl($key) {
    global $GLOBALS;
    $keys = explode('.', $key);
    if (count($keys) === 2 && isset($GLOBALS['_local_translations'][$keys[0]][$keys[1]])) {
        return $GLOBALS['_local_translations'][$keys[0]][$keys[1]];
    }
    return t($key);
}

requireLoginWithSession();

$user = getLoggedUser();
$db = getDB();

// Recuperer tous les exercices de cet utilisateur pour cette session
$stmt = $db->prepare("SELECT id, exercice_num, cas_choisi, is_shared, completion_percent, created_at FROM travaux WHERE user_id = ? AND session_id = ? ORDER BY exercice_num ASC");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$allExercices = $stmt->fetchAll();

// Gerer la creation d'un nouvel exercice
if (isset($_GET['new'])) {
    $maxNum = 0;
    foreach ($allExercices as $ex) {
        if ($ex['exercice_num'] > $maxNum) $maxNum = $ex['exercice_num'];
    }
    $newNum = $maxNum + 1;
    $stmt = $db->prepare("INSERT INTO travaux (user_id, session_id, exercice_num) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $_SESSION['current_session_id'], $newNum]);
    header('Location: app.php?ex=' . $newNum);
    exit;
}

// Determiner l'exercice courant
$currentExerciceNum = isset($_GET['ex']) ? (int)$_GET['ex'] : null;

// Si pas d'exercice specifie, prendre le dernier non soumis ou le dernier
if ($currentExerciceNum === null) {
    $currentExerciceNum = 1;
    foreach ($allExercices as $ex) {
        if ($ex['is_shared'] == 0) {
            $currentExerciceNum = $ex['exercice_num'];
            break;
        }
    }
    if (empty($allExercices)) {
        $currentExerciceNum = 1;
    } else {
        // Si tous soumis, prendre le dernier
        $lastEx = end($allExercices);
        if ($lastEx['is_shared'] == 1) {
            $currentExerciceNum = $lastEx['exercice_num'];
        }
    }
}

// Charger l'exercice courant
$stmt = $db->prepare("SELECT * FROM travaux WHERE user_id = ? AND session_id = ? AND exercice_num = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id'], $currentExerciceNum]);
$travail = $stmt->fetch();

// Creer l'exercice s'il n'existe pas
if (!$travail) {
    $stmt = $db->prepare("INSERT INTO travaux (user_id, session_id, exercice_num) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $_SESSION['current_session_id'], $currentExerciceNum]);
    $travail = [
        'id' => $db->lastInsertId(),
        'exercice_num' => $currentExerciceNum,
        'organisation_nom' => '',
        'organisation_type' => '',
        'cas_choisi' => '',
        'cas_description' => '',
        'prompt_initial' => '',
        'resultat_initial' => '',
        'analyse_resultat' => '',
        'prompt_ameliore' => '',
        'resultat_ameliore' => '',
        'ameliorations_notes' => '',
        'feedback_binome' => '',
        'points_forts' => '',
        'points_ameliorer' => '',
        'feedback_ia' => '',
        'synthese_cles' => '[]',
        'notes' => '',
        'is_shared' => 0
    ];
    // Rafraichir la liste des exercices
    $stmt = $db->prepare("SELECT id, exercice_num, cas_choisi, is_shared, completion_percent, created_at FROM travaux WHERE user_id = ? AND session_id = ? ORDER BY exercice_num ASC");
    $stmt->execute([$user['id'], $_SESSION['current_session_id']]);
    $allExercices = $stmt->fetchAll();
} else {
    // S'assurer que les valeurs ne sont jamais null
    $travail['organisation_nom'] = $travail['organisation_nom'] ?? '';
    $travail['organisation_type'] = $travail['organisation_type'] ?? '';
    $travail['cas_choisi'] = $travail['cas_choisi'] ?? '';
    $travail['cas_description'] = $travail['cas_description'] ?? '';
    $travail['prompt_initial'] = $travail['prompt_initial'] ?? '';
    $travail['resultat_initial'] = $travail['resultat_initial'] ?? '';
    $travail['analyse_resultat'] = $travail['analyse_resultat'] ?? '';
    $travail['prompt_ameliore'] = $travail['prompt_ameliore'] ?? '';
    $travail['resultat_ameliore'] = $travail['resultat_ameliore'] ?? '';
    $travail['ameliorations_notes'] = $travail['ameliorations_notes'] ?? '';
    $travail['feedback_binome'] = $travail['feedback_binome'] ?? '';
    $travail['points_forts'] = $travail['points_forts'] ?? '';
    $travail['points_ameliorer'] = $travail['points_ameliorer'] ?? '';
    $travail['feedback_ia'] = $travail['feedback_ia'] ?? '';
    $travail['synthese_cles'] = $travail['synthese_cles'] ?: '[]';
    $travail['notes'] = $travail['notes'] ?? '';
}

$isSubmitted = $travail['is_shared'] == 1;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('pj.title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .step-card { transition: all 0.3s ease; }
        .step-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .tab-active { border-bottom: 3px solid #ec4899; color: #ec4899; }
        .example-card { transition: all 0.2s; }
        .example-card:hover { background-color: #fdf2f8; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-gradient-to-br from-pink-50 to-rose-100 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-pink-500 to-rose-600 text-white p-4 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-xl font-bold"><?= t('pj.title') ?></h1>
                    <p class="text-pink-200 text-sm"><?= h($user['prenom']) ?> <?= h($user['nom']) ?> - <?= h($_SESSION['session_name'] ?? '') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('pink') ?>
                <button onclick="saveData()" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    <?= t('common.save') ?>
                </button>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg"><?= t('trainer.title') ?></a>
                <?php endif; ?>
                <a href="logout.php" class="bg-red-500/80 hover:bg-red-600 px-4 py-2 rounded-lg"><?= t('auth.logout') ?></a>
            </div>
        </div>
    </div>

    <!-- Exercise Selector -->
    <?php if (count($allExercices) > 0): ?>
    <div class="bg-pink-50 border-b border-pink-200 no-print">
        <div class="max-w-5xl mx-auto px-4 py-2 flex items-center justify-between">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm text-pink-700 font-medium">Mes exercices :</span>
                <?php
                $casShortLabels = [
                    'instagram' => 'Insta',
                    'benevoles' => 'Benev',
                    'quiz' => 'Quiz',
                    'jeu_role' => 'JdR',
                    'experience' => 'Exp',
                    'impro' => 'Impro',
                    'education_medias' => 'Medias',
                    'plaidoyer' => 'Plaid',
                    'sensibilisation' => 'Sensi',
                ];
                foreach ($allExercices as $ex): ?>
                    <a href="app.php?ex=<?= $ex['exercice_num'] ?>"
                       class="px-3 py-1 rounded-full text-sm <?= $ex['exercice_num'] == $currentExerciceNum ? 'bg-pink-500 text-white' : 'bg-white text-pink-700 hover:bg-pink-100' ?> border border-pink-300 flex items-center gap-1">
                        #<?= $ex['exercice_num'] ?>
                        <?php if ($ex['cas_choisi']): ?>
                            <span class="text-xs opacity-75">(<?= $casShortLabels[$ex['cas_choisi']] ?? $ex['cas_choisi'] ?>)</span>
                        <?php endif; ?>
                        <?php if ($ex['is_shared']): ?>
                            <svg class="w-3 h-3 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <a href="app.php?new=1" class="bg-green-500 hover:bg-green-600 text-white px-4 py-1 rounded-full text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvel exercice
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="bg-white shadow-md no-print">
        <div class="max-w-5xl mx-auto">
            <nav class="flex">
                <button onclick="showStep(1)" id="tab1" class="flex-1 py-4 px-4 text-center font-medium border-b-3 tab-active text-sm">
                    <span class="bg-pink-500 text-white w-6 h-6 rounded-full inline-flex items-center justify-center text-sm mr-1">1</span>
                    <?= t('pj.step1_tab') ?>
                </button>
                <button onclick="showStep(2)" id="tab2" class="flex-1 py-4 px-4 text-center font-medium border-b-3 border-transparent text-gray-500 hover:text-pink-500 text-sm">
                    <span class="bg-gray-300 text-white w-6 h-6 rounded-full inline-flex items-center justify-center text-sm mr-1">2</span>
                    <?= t('pj.step2_tab') ?>
                </button>
                <button onclick="showStep(3)" id="tab3" class="flex-1 py-4 px-4 text-center font-medium border-b-3 border-transparent text-gray-500 hover:text-pink-500 text-sm">
                    <span class="bg-gray-300 text-white w-6 h-6 rounded-full inline-flex items-center justify-center text-sm mr-1">3</span>
                    <?= t('pj.step3_tab') ?>
                </button>
                <button onclick="showStep(4)" id="tab4" class="flex-1 py-4 px-4 text-center font-medium border-b-3 border-transparent text-gray-500 hover:text-pink-500 text-sm">
                    <span class="bg-gray-300 text-white w-6 h-6 rounded-full inline-flex items-center justify-center text-sm mr-1">4</span>
                    <?= t('pj.step4_tab') ?>
                </button>
                <button onclick="showStep(5)" id="tab5" class="flex-1 py-4 px-4 text-center font-medium border-b-3 border-transparent text-gray-500 hover:text-pink-500 text-sm">
                    <span class="bg-gray-300 text-white w-6 h-6 rounded-full inline-flex items-center justify-center text-sm mr-1">5</span>
                    <?= t('pj.step5_tab') ?>
                </button>
                <button onclick="showStep(6)" id="tab6" class="flex-1 py-4 px-4 text-center font-medium border-b-3 border-transparent text-gray-500 hover:text-pink-500 text-sm">
                    <span class="bg-gray-300 text-white w-6 h-6 rounded-full inline-flex items-center justify-center text-sm mr-1">6</span>
                    <?= t('pj.step6_tab') ?>
                </button>
            </nav>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-6">
        <!-- Step 1: Introduction -->
        <div id="step1" class="step-content space-y-6">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                    <span class="bg-pink-500 text-white w-10 h-10 rounded-full flex items-center justify-center">1</span>
                    <?= t('pj.step1_title') ?>
                </h2>
                <p class="text-gray-600 mb-6"><?= t('pj.step1_desc') ?></p>

                <!-- What is a prompt -->
                <div class="bg-pink-50 rounded-xl p-6 mb-6">
                    <h3 class="text-lg font-bold text-pink-800 mb-3"><?= t('pj.what_is_prompt') ?></h3>
                    <p class="text-gray-700 mb-4"><?= t('pj.prompt_definition') ?></p>
                </div>

                <!-- Example comparison -->
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <div class="bg-red-50 rounded-xl p-5 border-2 border-red-200">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h4 class="font-bold text-red-700"><?= t('pj.vague_prompt') ?></h4>
                        </div>
                        <p class="text-gray-700 italic bg-white p-3 rounded-lg">"<?= t('pj.vague_example') ?>"</p>
                        <p class="text-red-600 text-sm mt-2"><?= t('pj.vague_problem') ?></p>
                    </div>
                    <div class="bg-green-50 rounded-xl p-5 border-2 border-green-200">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h4 class="font-bold text-green-700"><?= t('pj.structured_prompt') ?></h4>
                        </div>
                        <p class="text-gray-700 italic bg-white p-3 rounded-lg">"<?= t('pj.structured_example') ?>"</p>
                        <p class="text-green-600 text-sm mt-2"><?= t('pj.structured_benefit') ?></p>
                    </div>
                </div>

                <!-- Key elements -->
                <div class="bg-gradient-to-r from-pink-100 to-rose-100 rounded-xl p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4"><?= t('pj.key_elements') ?></h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="flex items-start gap-3">
                            <span class="bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">C</span>
                            <div>
                                <p class="font-bold text-gray-800"><?= t('pj.element_context') ?></p>
                                <p class="text-gray-600 text-sm"><?= t('pj.element_context_desc') ?></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">O</span>
                            <div>
                                <p class="font-bold text-gray-800"><?= t('pj.element_objective') ?></p>
                                <p class="text-gray-600 text-sm"><?= t('pj.element_objective_desc') ?></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">C</span>
                            <div>
                                <p class="font-bold text-gray-800"><?= t('pj.element_constraints') ?></p>
                                <p class="text-gray-600 text-sm"><?= t('pj.element_constraints_desc') ?></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">F</span>
                            <div>
                                <p class="font-bold text-gray-800"><?= t('pj.element_format') ?></p>
                                <p class="text-gray-600 text-sm"><?= t('pj.element_format_desc') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="showStep(2)" class="bg-pink-500 hover:bg-pink-600 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <?= t('pj.next_step') ?>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 2: Individual Phase -->
        <div id="step2" class="step-content space-y-6 hidden">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                    <span class="bg-pink-500 text-white w-10 h-10 rounded-full flex items-center justify-center">2</span>
                    <?= t('pj.step2_title') ?>
                </h2>
                <p class="text-gray-600 mb-6"><?= t('pj.step2_desc') ?></p>

                <!-- Organisation info -->
                <div class="bg-gray-50 rounded-xl p-5 mb-6">
                    <h3 class="font-bold text-gray-800 mb-4"><?= t('pj.your_org') ?></h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('pj.org_name') ?></label>
                            <input type="text" id="organisationNom" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="<?= t('pj.org_name_placeholder') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('pj.org_type') ?></label>
                            <select id="organisationType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
                                <option value=""><?= t('pj.select_type') ?></option>
                                <option value="mouvement_jeunesse"><?= t('pj.type_youth') ?></option>
                                <option value="maison_jeunes"><?= t('pj.type_youth_center') ?></option>
                                <option value="club_sport"><?= t('pj.type_sports') ?></option>
                                <option value="asbl_culturelle"><?= t('pj.type_cultural') ?></option>
                                <option value="association_etudiante"><?= t('pj.type_student') ?></option>
                                <option value="autre"><?= t('pj.type_other') ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Choose case -->
                <div class="mb-6">
                    <h3 class="font-bold text-gray-800 mb-4"><?= t('pj.choose_case') ?></h3>
                    <div class="grid md:grid-cols-3 gap-3">
                        <!-- Instagram -->
                        <label class="example-card cursor-pointer block bg-white border-2 rounded-xl p-4 hover:border-pink-500 transition-all" id="caseInstagram">
                            <input type="radio" name="casChoisi" value="instagram" class="hidden" onchange="selectCase('instagram')">
                            <div class="flex items-start gap-3">
                                <div class="bg-gradient-to-br from-purple-500 to-pink-500 w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073z"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Publication Instagram</p>
                                    <p class="text-gray-600 text-xs">Annoncer une activite</p>
                                </div>
                            </div>
                        </label>
                        <!-- Benevoles -->
                        <label class="example-card cursor-pointer block bg-white border-2 rounded-xl p-4 hover:border-pink-500 transition-all" id="caseVolunteer">
                            <input type="radio" name="casChoisi" value="benevoles" class="hidden" onchange="selectCase('benevoles')">
                            <div class="flex items-start gap-3">
                                <div class="bg-gradient-to-br from-green-500 to-teal-500 w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Appel a benevoles</p>
                                    <p class="text-gray-600 text-xs">Recruter des volontaires</p>
                                </div>
                            </div>
                        </label>
                        <!-- Quiz -->
                        <label class="example-card cursor-pointer block bg-white border-2 rounded-xl p-4 hover:border-pink-500 transition-all" id="caseQuiz">
                            <input type="radio" name="casChoisi" value="quiz" class="hidden" onchange="selectCase('quiz')">
                            <div class="flex items-start gap-3">
                                <div class="bg-gradient-to-br from-blue-500 to-indigo-500 w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Quiz interactif</p>
                                    <p class="text-gray-600 text-xs">Questions engageantes</p>
                                </div>
                            </div>
                        </label>
                        <!-- Jeu de role -->
                        <label class="example-card cursor-pointer block bg-white border-2 rounded-xl p-4 hover:border-pink-500 transition-all" id="caseJeuRole">
                            <input type="radio" name="casChoisi" value="jeu_role" class="hidden" onchange="selectCase('jeu_role')">
                            <div class="flex items-start gap-3">
                                <div class="bg-gradient-to-br from-amber-500 to-orange-500 w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Scenario jeu de role</p>
                                    <p class="text-gray-600 text-xs">Mise en situation</p>
                                </div>
                            </div>
                        </label>
                        <!-- Experience scientifique -->
                        <label class="example-card cursor-pointer block bg-white border-2 rounded-xl p-4 hover:border-pink-500 transition-all" id="caseExperience">
                            <input type="radio" name="casChoisi" value="experience" class="hidden" onchange="selectCase('experience')">
                            <div class="flex items-start gap-3">
                                <div class="bg-gradient-to-br from-cyan-500 to-blue-500 w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Fiche experience</p>
                                    <p class="text-gray-600 text-xs">Experience scientifique</p>
                                </div>
                            </div>
                        </label>
                        <!-- Improvisation -->
                        <label class="example-card cursor-pointer block bg-white border-2 rounded-xl p-4 hover:border-pink-500 transition-all" id="caseImpro">
                            <input type="radio" name="casChoisi" value="impro" class="hidden" onchange="selectCase('impro')">
                            <div class="flex items-start gap-3">
                                <div class="bg-gradient-to-br from-fuchsia-500 to-purple-500 w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2m0 2v2m0-2H5m2 0h2m6 8v8m-4-8v8m8-8v8M3 8h18M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Themes impro</p>
                                    <p class="text-gray-600 text-xs">Banque d'improvisation</p>
                                </div>
                            </div>
                        </label>
                        <!-- Education medias -->
                        <label class="example-card cursor-pointer block bg-white border-2 rounded-xl p-4 hover:border-pink-500 transition-all" id="caseEducMedias">
                            <input type="radio" name="casChoisi" value="education_medias" class="hidden" onchange="selectCase('education_medias')">
                            <div class="flex items-start gap-3">
                                <div class="bg-gradient-to-br from-rose-500 to-red-500 w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Education medias</p>
                                    <p class="text-gray-600 text-xs">Esprit critique</p>
                                </div>
                            </div>
                        </label>
                        <!-- Plaidoyer -->
                        <label class="example-card cursor-pointer block bg-white border-2 rounded-xl p-4 hover:border-pink-500 transition-all" id="casePlaidoyer">
                            <input type="radio" name="casChoisi" value="plaidoyer" class="hidden" onchange="selectCase('plaidoyer')">
                            <div class="flex items-start gap-3">
                                <div class="bg-gradient-to-br from-emerald-500 to-green-600 w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Campagne plaidoyer</p>
                                    <p class="text-gray-600 text-xs">Mobiliser pour une cause</p>
                                </div>
                            </div>
                        </label>
                        <!-- Sensibilisation -->
                        <label class="example-card cursor-pointer block bg-white border-2 rounded-xl p-4 hover:border-pink-500 transition-all" id="caseSensibilisation">
                            <input type="radio" name="casChoisi" value="sensibilisation" class="hidden" onchange="selectCase('sensibilisation')">
                            <div class="flex items-start gap-3">
                                <div class="bg-gradient-to-br from-sky-500 to-blue-600 w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Sensibilisation reseaux</p>
                                    <p class="text-gray-600 text-xs">Campagne sociale</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Describe case -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= t('pj.describe_case') ?></label>
                    <textarea id="casDescription" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="<?= t('pj.describe_case_placeholder') ?>"></textarea>
                </div>

                <!-- Write initial prompt -->
                <div class="bg-pink-50 rounded-xl p-5">
                    <h3 class="font-bold text-pink-800 mb-3"><?= t('pj.write_prompt') ?></h3>
                    <p class="text-gray-600 text-sm mb-3"><?= t('pj.write_prompt_hint') ?></p>
                    <textarea id="promptInitial" rows="5" class="w-full px-4 py-3 border border-pink-200 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent bg-white" placeholder="<?= t('pj.prompt_placeholder') ?>"></textarea>
                </div>

                <div class="mt-6 flex justify-between">
                    <button onclick="showStep(1)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <?= t('pj.previous') ?>
                    </button>
                    <button onclick="showStep(3)" class="bg-pink-500 hover:bg-pink-600 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <?= t('pj.next_step') ?>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 3: Test and Iterate -->
        <div id="step3" class="step-content space-y-6 hidden">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                    <span class="bg-pink-500 text-white w-10 h-10 rounded-full flex items-center justify-center">3</span>
                    <?= t('pj.step3_title') ?>
                </h2>
                <p class="text-gray-600 mb-6"><?= t('pj.step3_desc') ?></p>

                <!-- Initial result -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= t('pj.initial_result') ?></label>
                    <p class="text-gray-500 text-sm mb-2"><?= t('pj.initial_result_hint') ?></p>
                    <textarea id="resultatInitial" rows="5" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="<?= t('pj.result_placeholder') ?>"></textarea>
                </div>

                <!-- Analyze result -->
                <div class="bg-yellow-50 rounded-xl p-5 mb-6">
                    <h3 class="font-bold text-yellow-800 mb-3"><?= t('pj.analyze_result') ?></h3>
                    <div class="space-y-3">
                        <p class="text-gray-700"><?= t('pj.analyze_questions') ?></p>
                        <ul class="text-gray-600 text-sm list-disc list-inside space-y-1">
                            <li><?= t('pj.analyze_q1') ?></li>
                            <li><?= t('pj.analyze_q2') ?></li>
                            <li><?= t('pj.analyze_q3') ?></li>
                            <li><?= t('pj.analyze_q4') ?></li>
                        </ul>
                    </div>
                    <textarea id="analyseResultat" rows="3" class="w-full mt-4 px-4 py-3 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent bg-white" placeholder="<?= t('pj.analyze_placeholder') ?>"></textarea>
                </div>

                <!-- Improved prompt -->
                <div class="bg-green-50 rounded-xl p-5 mb-6">
                    <h3 class="font-bold text-green-800 mb-3"><?= t('pj.improved_prompt') ?></h3>
                    <p class="text-gray-600 text-sm mb-3"><?= t('pj.improved_prompt_hint') ?></p>
                    <textarea id="promptAmeliore" rows="5" class="w-full px-4 py-3 border border-green-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white" placeholder="<?= t('pj.improved_prompt_placeholder') ?>"></textarea>
                </div>

                <!-- Improved result -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= t('pj.improved_result') ?></label>
                    <textarea id="resultatAmeliore" rows="5" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="<?= t('pj.improved_result_placeholder') ?>"></textarea>
                </div>

                <!-- Notes on improvements -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= t('pj.improvement_notes') ?></label>
                    <textarea id="ameliorationsNotes" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="<?= t('pj.improvement_notes_placeholder') ?>"></textarea>
                </div>

                <div class="mt-6 flex justify-between">
                    <button onclick="showStep(2)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <?= t('pj.previous') ?>
                    </button>
                    <button onclick="showStep(4)" class="bg-pink-500 hover:bg-pink-600 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <?= t('pj.next_step') ?>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 4: Pair Sharing -->
        <div id="step4" class="step-content space-y-6 hidden">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                    <span class="bg-pink-500 text-white w-10 h-10 rounded-full flex items-center justify-center">4</span>
                    <?= t('pj.step4_title') ?>
                </h2>
                <p class="text-gray-600 mb-6"><?= t('pj.step4_desc') ?></p>

                <!-- Instructions -->
                <div class="bg-blue-50 rounded-xl p-5 mb-6">
                    <h3 class="font-bold text-blue-800 mb-3"><?= t('pj.pair_instructions') ?></h3>
                    <ol class="text-gray-700 list-decimal list-inside space-y-2">
                        <li><?= t('pj.pair_step1') ?></li>
                        <li><?= t('pj.pair_step2') ?></li>
                        <li><?= t('pj.pair_step3') ?></li>
                    </ol>
                </div>

                <!-- Feedback from partner -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= t('pj.partner_feedback') ?></label>
                    <textarea id="feedbackBinome" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="<?= t('pj.partner_feedback_placeholder') ?>"></textarea>
                </div>

                <!-- What works -->
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="bg-green-50 rounded-xl p-5">
                        <h3 class="font-bold text-green-800 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?= t('pj.what_works') ?>
                        </h3>
                        <textarea id="pointsForts" rows="4" class="w-full px-4 py-3 border border-green-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white" placeholder="<?= t('pj.what_works_placeholder') ?>"></textarea>
                    </div>
                    <div class="bg-orange-50 rounded-xl p-5">
                        <h3 class="font-bold text-orange-800 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <?= t('pj.what_improve') ?>
                        </h3>
                        <textarea id="pointsAmeliorer" rows="4" class="w-full px-4 py-3 border border-orange-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent bg-white" placeholder="<?= t('pj.what_improve_placeholder') ?>"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-between">
                    <button onclick="showStep(3)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <?= t('pj.previous') ?>
                    </button>
                    <button onclick="showStep(5)" class="bg-pink-500 hover:bg-pink-600 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <?= t('pj.next_step') ?>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 5: AI Feedback -->
        <div id="step5" class="step-content space-y-6 hidden">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                    <span class="bg-pink-500 text-white w-10 h-10 rounded-full flex items-center justify-center">5</span>
                    <?= tl('pj.step5_title_ai') ?>
                </h2>
                <p class="text-gray-600 mb-6"><?= tl('pj.step5_desc_ai') ?></p>

                <!-- Display final prompt -->
                <div class="bg-purple-50 rounded-xl p-5 mb-6">
                    <h3 class="font-bold text-purple-800 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <?= tl('pj.your_final_prompt') ?>
                    </h3>
                    <div id="displayPromptAmeliore" class="bg-white p-4 rounded-lg border border-purple-200 text-gray-700 whitespace-pre-wrap min-h-[100px]">
                        <span class="text-gray-400 italic"><?= tl('pj.no_prompt_yet') ?></span>
                    </div>
                </div>

                <!-- Generate analysis prompt -->
                <div class="bg-indigo-50 rounded-xl p-5 mb-6">
                    <h3 class="font-bold text-indigo-800 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        <?= tl('pj.ask_ai_analysis') ?>
                    </h3>
                    <p class="text-gray-600 text-sm mb-4"><?= tl('pj.ask_ai_analysis_desc') ?></p>
                    <button onclick="generateAnalysisPrompt()" class="bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <?= tl('pj.copy_analysis_prompt') ?>
                    </button>
                    <div id="analysisPromptBox" class="hidden mt-4">
                        <div class="bg-white p-4 rounded-lg border border-indigo-200">
                            <p class="text-gray-700 text-sm whitespace-pre-wrap" id="analysisPromptText"></p>
                        </div>
                        <p class="text-green-600 text-sm mt-2 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <?= tl('pj.prompt_copied') ?>
                        </p>
                    </div>
                </div>

                <!-- AI Feedback textarea -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= tl('pj.ai_feedback_label') ?></label>
                    <p class="text-gray-500 text-sm mb-2"><?= tl('pj.ai_feedback_hint') ?></p>
                    <textarea id="feedbackIa" rows="8" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="<?= tl('pj.ai_feedback_placeholder') ?>"></textarea>
                </div>

                <!-- Submit section -->
                <div class="bg-green-50 rounded-xl p-6">
                    <h3 class="font-bold text-green-800 mb-4 flex items-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?= tl('pj.ready_to_submit') ?>
                    </h3>
                    <p class="text-gray-700 mb-4"><?= tl('pj.ready_to_submit_desc') ?></p>
                    <button onclick="submitWork()" class="bg-green-500 hover:bg-green-600 text-white px-8 py-4 rounded-lg font-medium text-lg flex items-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?= t('pj.submit_work') ?>
                    </button>
                </div>

                <div class="mt-6 flex justify-between">
                    <button onclick="showStep(4)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <?= t('pj.previous') ?>
                    </button>
                    <button onclick="showStep(6)" class="bg-pink-500 hover:bg-pink-600 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <?= t('pj.next_step') ?>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 6: Collective Synthesis -->
        <div id="step6" class="step-content space-y-6 hidden">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                    <span class="bg-pink-500 text-white w-10 h-10 rounded-full flex items-center justify-center">6</span>
                    <?= t('pj.step6_title') ?>
                </h2>
                <p class="text-gray-600 mb-6"><?= t('pj.step6_desc') ?></p>

                <!-- Key elements summary -->
                <div class="bg-gradient-to-r from-pink-100 to-rose-100 rounded-xl p-6 mb-6">
                    <h3 class="font-bold text-gray-800 mb-4"><?= t('pj.key_elements_recap') ?></h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-pink-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold">C</span>
                                <span class="font-bold text-gray-800"><?= t('pj.element_context') ?></span>
                            </div>
                            <p class="text-gray-600 text-sm"><?= t('pj.recap_context') ?></p>
                        </div>
                        <div class="bg-white rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-pink-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold">O</span>
                                <span class="font-bold text-gray-800"><?= t('pj.element_objective') ?></span>
                            </div>
                            <p class="text-gray-600 text-sm"><?= t('pj.recap_objective') ?></p>
                        </div>
                        <div class="bg-white rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-pink-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold">C</span>
                                <span class="font-bold text-gray-800"><?= t('pj.element_constraints') ?></span>
                            </div>
                            <p class="text-gray-600 text-sm"><?= t('pj.recap_constraints') ?></p>
                        </div>
                        <div class="bg-white rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-pink-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold">F</span>
                                <span class="font-bold text-gray-800"><?= t('pj.element_format') ?></span>
                            </div>
                            <p class="text-gray-600 text-sm"><?= t('pj.recap_format') ?></p>
                        </div>
                    </div>
                </div>

                <!-- Checklist -->
                <div class="mb-6">
                    <h3 class="font-bold text-gray-800 mb-4"><?= t('pj.learnings_checklist') ?></h3>
                    <div id="syntheseContainer" class="space-y-3">
                        <!-- Checklist items will be added here -->
                    </div>
                    <div class="flex gap-2 mt-4">
                        <input type="text" id="newSyntheseItem" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="<?= t('pj.add_learning') ?>">
                        <button onclick="addSyntheseItem()" class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Additional notes -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= t('pj.additional_notes') ?></label>
                    <textarea id="notes" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="<?= t('pj.notes_placeholder') ?>"></textarea>
                </div>

                <!-- Final actions -->
                <div class="bg-gray-50 rounded-xl p-6">
                    <h3 class="font-bold text-gray-800 mb-4"><?= t('pj.workshop_complete') ?></h3>
                    <p class="text-gray-700 mb-4"><?= t('pj.workshop_complete_desc') ?></p>
                    <button onclick="window.print()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <?= t('common.print') ?>
                    </button>
                </div>

                <div class="mt-6 flex justify-start">
                    <button onclick="showStep(5)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <?= t('pj.previous') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Translations
        const trans = {
            confirmDelete: <?= json_encode(t('common.confirm_delete')) ?>,
            saveSuccess: <?= json_encode(t('common.save_success')) ?>,
            saveError: <?= json_encode(t('common.save_error')) ?>,
            submitSuccess: <?= json_encode(t('common.submit_success')) ?>,
            submitError: <?= json_encode(t('common.submit_error')) ?>,
            noPromptYet: <?= json_encode(tl('pj.no_prompt_yet')) ?>,
            analysisPromptTemplate: <?= json_encode(tl('pj.analysis_prompt_template')) ?>
        };

        // Data
        let syntheseCles = <?= $travail['synthese_cles'] ?>;
        let currentStep = 1;
        const currentExerciceNum = <?= $currentExerciceNum ?>;

        // Default synthesis items
        const defaultSyntheseItems = [
            <?= json_encode(t('pj.learning1')) ?>,
            <?= json_encode(t('pj.learning2')) ?>,
            <?= json_encode(t('pj.learning3')) ?>,
            <?= json_encode(t('pj.learning4')) ?>,
            <?= json_encode(t('pj.learning5')) ?>
        ];

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved data
            document.getElementById('organisationNom').value = <?= json_encode($travail['organisation_nom']) ?>;
            document.getElementById('organisationType').value = <?= json_encode($travail['organisation_type']) ?>;
            document.getElementById('casDescription').value = <?= json_encode($travail['cas_description']) ?>;
            document.getElementById('promptInitial').value = <?= json_encode($travail['prompt_initial']) ?>;
            document.getElementById('resultatInitial').value = <?= json_encode($travail['resultat_initial']) ?>;
            document.getElementById('analyseResultat').value = <?= json_encode($travail['analyse_resultat']) ?>;
            document.getElementById('promptAmeliore').value = <?= json_encode($travail['prompt_ameliore']) ?>;
            document.getElementById('resultatAmeliore').value = <?= json_encode($travail['resultat_ameliore']) ?>;
            document.getElementById('ameliorationsNotes').value = <?= json_encode($travail['ameliorations_notes']) ?>;
            document.getElementById('feedbackBinome').value = <?= json_encode($travail['feedback_binome']) ?>;
            document.getElementById('pointsForts').value = <?= json_encode($travail['points_forts']) ?>;
            document.getElementById('pointsAmeliorer').value = <?= json_encode($travail['points_ameliorer']) ?>;
            document.getElementById('feedbackIa').value = <?= json_encode($travail['feedback_ia']) ?>;
            document.getElementById('notes').value = <?= json_encode($travail['notes']) ?>;

            // Set selected case
            const casChoisi = <?= json_encode($travail['cas_choisi']) ?>;
            if (casChoisi) {
                selectCase(casChoisi);
                document.querySelector(`input[value="${casChoisi}"]`).checked = true;
            }

            // Initialize synthesis items
            if (syntheseCles.length === 0) {
                syntheseCles = defaultSyntheseItems.map((text, i) => ({
                    id: 'synth_' + i,
                    text: text,
                    checked: false
                }));
            }
            renderSynthese();

            // Update display when prompt changes
            document.getElementById('promptAmeliore').addEventListener('input', updatePromptDisplay);
            updatePromptDisplay();
        });

        // Update the prompt display in step 5
        function updatePromptDisplay() {
            const prompt = document.getElementById('promptAmeliore').value.trim();
            const display = document.getElementById('displayPromptAmeliore');
            if (prompt) {
                display.textContent = prompt;
                display.classList.remove('text-gray-400', 'italic');
            } else {
                display.innerHTML = '<span class="text-gray-400 italic">' + trans.noPromptYet + '</span>';
            }
        }

        // Generate and copy analysis prompt
        function generateAnalysisPrompt() {
            const prompt = document.getElementById('promptAmeliore').value.trim();
            if (!prompt) {
                alert(trans.noPromptYet);
                return;
            }

            const analysisPrompt = trans.analysisPromptTemplate.replace('{PROMPT}', prompt);

            // Copy to clipboard
            navigator.clipboard.writeText(analysisPrompt).then(() => {
                document.getElementById('analysisPromptText').textContent = analysisPrompt;
                document.getElementById('analysisPromptBox').classList.remove('hidden');
            });
        }

        // Step navigation
        function showStep(step) {
            currentStep = step;
            for (let i = 1; i <= 6; i++) {
                document.getElementById('step' + i).classList.add('hidden');
                document.getElementById('tab' + i).classList.remove('tab-active');
                document.getElementById('tab' + i).classList.add('text-gray-500', 'border-transparent');
                document.getElementById('tab' + i).querySelector('span').classList.remove('bg-pink-500');
                document.getElementById('tab' + i).querySelector('span').classList.add('bg-gray-300');
            }
            document.getElementById('step' + step).classList.remove('hidden');
            document.getElementById('tab' + step).classList.add('tab-active');
            document.getElementById('tab' + step).classList.remove('text-gray-500', 'border-transparent');
            document.getElementById('tab' + step).querySelector('span').classList.add('bg-pink-500');
            document.getElementById('tab' + step).querySelector('span').classList.remove('bg-gray-300');
            window.scrollTo(0, 0);

            // Update prompt display when going to step 5
            if (step === 5) {
                updatePromptDisplay();
            }
        }

        // Case selection
        function selectCase(caseType) {
            const allCases = ['caseInstagram', 'caseVolunteer', 'caseQuiz', 'caseJeuRole', 'caseExperience', 'caseImpro', 'caseEducMedias', 'casePlaidoyer', 'caseSensibilisation'];
            const caseMap = {
                'instagram': 'caseInstagram',
                'benevoles': 'caseVolunteer',
                'quiz': 'caseQuiz',
                'jeu_role': 'caseJeuRole',
                'experience': 'caseExperience',
                'impro': 'caseImpro',
                'education_medias': 'caseEducMedias',
                'plaidoyer': 'casePlaidoyer',
                'sensibilisation': 'caseSensibilisation'
            };

            // Remove selection from all
            allCases.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.remove('border-pink-500', 'bg-pink-50');
            });

            // Add selection to chosen case
            const selectedId = caseMap[caseType];
            if (selectedId) {
                const el = document.getElementById(selectedId);
                if (el) el.classList.add('border-pink-500', 'bg-pink-50');
            }
        }

        // Synthesis items
        function renderSynthese() {
            const container = document.getElementById('syntheseContainer');
            container.innerHTML = syntheseCles.map(item => `
                <div class="flex items-center gap-3 bg-gray-50 p-3 rounded-lg">
                    <input type="checkbox" ${item.checked ? 'checked' : ''} onchange="toggleSynthese('${item.id}')" class="w-5 h-5 text-pink-500 rounded focus:ring-pink-500">
                    <span class="flex-1 ${item.checked ? 'line-through text-gray-400' : 'text-gray-700'}">${escapeHtml(item.text)}</span>
                    <button onclick="deleteSynthese('${item.id}')" class="text-red-400 hover:text-red-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            `).join('');
        }

        function addSyntheseItem() {
            const input = document.getElementById('newSyntheseItem');
            const text = input.value.trim();
            if (!text) return;

            syntheseCles.push({
                id: 'synth_' + Date.now(),
                text: text,
                checked: false
            });
            input.value = '';
            renderSynthese();
        }

        function toggleSynthese(id) {
            const item = syntheseCles.find(s => s.id === id);
            if (item) {
                item.checked = !item.checked;
                renderSynthese();
            }
        }

        function deleteSynthese(id) {
            syntheseCles = syntheseCles.filter(s => s.id !== id);
            renderSynthese();
        }

        // Utility
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Get selected case
        function getSelectedCase() {
            const radio = document.querySelector('input[name="casChoisi"]:checked');
            return radio ? radio.value : '';
        }

        // Save data
        async function saveData() {
            const data = {
                exercice_num: currentExerciceNum,
                organisation_nom: document.getElementById('organisationNom').value,
                organisation_type: document.getElementById('organisationType').value,
                cas_choisi: getSelectedCase(),
                cas_description: document.getElementById('casDescription').value,
                prompt_initial: document.getElementById('promptInitial').value,
                resultat_initial: document.getElementById('resultatInitial').value,
                analyse_resultat: document.getElementById('analyseResultat').value,
                prompt_ameliore: document.getElementById('promptAmeliore').value,
                resultat_ameliore: document.getElementById('resultatAmeliore').value,
                ameliorations_notes: document.getElementById('ameliorationsNotes').value,
                feedback_binome: document.getElementById('feedbackBinome').value,
                points_forts: document.getElementById('pointsForts').value,
                points_ameliorer: document.getElementById('pointsAmeliorer').value,
                feedback_ia: document.getElementById('feedbackIa').value,
                synthese_cles: syntheseCles,
                notes: document.getElementById('notes').value
            };

            try {
                const response = await fetch('api/save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    alert(trans.saveSuccess);
                } else {
                    alert(trans.saveError + ': ' + (result.error || ''));
                }
            } catch (e) {
                alert(trans.saveError);
            }
        }

        // Submit work
        async function submitWork() {
            // Save first
            const data = {
                exercice_num: currentExerciceNum,
                organisation_nom: document.getElementById('organisationNom').value,
                organisation_type: document.getElementById('organisationType').value,
                cas_choisi: getSelectedCase(),
                cas_description: document.getElementById('casDescription').value,
                prompt_initial: document.getElementById('promptInitial').value,
                resultat_initial: document.getElementById('resultatInitial').value,
                analyse_resultat: document.getElementById('analyseResultat').value,
                prompt_ameliore: document.getElementById('promptAmeliore').value,
                resultat_ameliore: document.getElementById('resultatAmeliore').value,
                ameliorations_notes: document.getElementById('ameliorationsNotes').value,
                feedback_binome: document.getElementById('feedbackBinome').value,
                points_forts: document.getElementById('pointsForts').value,
                points_ameliorer: document.getElementById('pointsAmeliorer').value,
                feedback_ia: document.getElementById('feedbackIa').value,
                synthese_cles: syntheseCles,
                notes: document.getElementById('notes').value
            };

            try {
                // Save first
                await fetch('api/save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                // Then submit
                const response = await fetch('api/submit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ exercice_num: currentExerciceNum })
                });
                const result = await response.json();
                if (result.success) {
                    alert(trans.submitSuccess);
                } else {
                    alert(trans.submitError + ': ' + (result.error || ''));
                }
            } catch (e) {
                alert(trans.submitError);
            }
        }
    </script>
    <?= renderLanguageScript() ?>
</body>
</html>
