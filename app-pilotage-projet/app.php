<?php
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user = getLoggedUser();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

// Verifier l'acces a cette app restreinte
if (!hasAppAccess('app-pilotage-projet', $user['id'])) {
    header('Location: login.php');
    exit;
}

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

$objectifsData = json_decode($analyse['objectifs_data'], true) ?: [];
$phasesData = json_decode($analyse['phases_data'], true) ?: [];
$checkpointsData = json_decode($analyse['checkpoints_data'], true) ?: [];
$lessonsData = json_decode($analyse['lessons_data'], true) ?: [];
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;
$taskStatuses = getTaskStatuses();
$checkpointTypes = getCheckpointTypes();

// Check if there's already generated content
$hasContent = !empty(trim($analyse['nom_projet'] ?? '')) || !empty($objectifsData) || !empty($phasesData);

// Check if AI config exists
$aiConfigPath = __DIR__ . '/../ai-config.php';
$hasAiConfig = file_exists($aiConfigPath);
if ($hasAiConfig) {
    require_once $aiConfigPath;
    $hasValidKey = defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY !== 'YOUR_API_KEY_HERE';
} else {
    $hasValidKey = false;
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('pp.title') ?> - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect x='3' y='4' width='26' height='24' rx='3' fill='%23059669'/><path d='M8 12h16M8 17h12M8 22h8' stroke='%2334d399' stroke-width='2' stroke-linecap='round'/><circle cx='24' cy='20' r='5' fill='%23fbbf24' stroke='%23059669' stroke-width='1.5'/><path d='M22 20l1.5 1.5L26 18.5' stroke='%23059669' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #022c22 100%); min-height: 100vh; }
        @media print { .no-print { display: none !important; } body { background: white; } }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .tab-btn { transition: all 0.2s; }
        .tab-btn.active { background: white; color: #059669; font-weight: 700; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .phase-card { border-left: 4px solid #059669; }
        @keyframes pulse-glow { 0%, 100% { box-shadow: 0 0 15px rgba(5, 150, 105, 0.3); } 50% { box-shadow: 0 0 30px rgba(5, 150, 105, 0.6); } }
        .generating { animation: pulse-glow 1.5s ease-in-out infinite; }
        .spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
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
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('text-sm bg-white/20 text-gray-800 px-2 py-1 rounded border border-gray-300') ?>
                <button onclick="manualSave()" id="btnSave" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition" style="display:none;"><?= t('pp.save') ?></button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-gray-200"><?= $isSubmitted ? t('pp.submitted') : t('pp.draft') ?></span>
                <span id="completion" class="text-sm text-gray-600" style="display:none;"><?= t('pp.completion') ?>: <strong>0%</strong></span>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="text-sm bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1 rounded transition"><?= t('pp.trainer') ?></a>
                <?php endif; ?>
                <a href="logout.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition"><?= t('pp.logout') ?></a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto">
        <!-- ======================== -->
        <!-- STEP 1: GENERATION AI    -->
        <!-- ======================== -->
        <div id="generatorPanel" class="<?= $hasContent ? 'hidden' : '' ?>">
            <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
                <div class="text-center mb-8">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-3"><?= t('pp.title') ?></h1>
                    <p class="text-gray-600 text-lg"><?= t('pp.describe_intro') ?></p>
                </div>

                <div class="max-w-3xl mx-auto">
                    <div class="bg-gradient-to-r from-emerald-50 via-green-50 to-teal-50 p-6 rounded-xl border-2 border-emerald-200 shadow-md mb-8">
                        <div class="flex items-start gap-4">
                            <div class="text-4xl">&#x1F916;</div>
                            <div>
                                <h3 class="font-bold text-emerald-800 text-lg mb-1"><?= t('pp.how_it_works') ?></h3>
                                <ol class="text-gray-700 space-y-1 text-sm list-decimal list-inside">
                                    <li><?= t('pp.how_step1') ?></li>
                                    <li><?= t('pp.how_step2') ?></li>
                                    <li><?= t('pp.how_step3') ?></li>
                                    <li><?= t('pp.how_step4') ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-lg font-semibold text-gray-800 mb-2">&#x1F4DD; <?= t('pp.project_desc_label') ?> <span class="text-red-500">*</span></label>
                            <p class="text-sm text-gray-500 mb-2"><?= t('pp.project_desc_hint') ?></p>
                            <textarea id="genDescription" rows="5"
                                class="w-full px-4 py-3 border-2 border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-base"
                                placeholder="<?= h(t('pp.project_desc_placeholder')) ?>"><?= h($analyse['contexte'] ?? '') ?></textarea>
                        </div>

                        <div class="grid md:grid-cols-2 gap-5">
                            <div class="bg-blue-50 p-5 rounded-xl border border-blue-200">
                                <label class="block text-sm font-semibold text-blue-800 mb-2">&#x1F4D8; <?= t('pp.context_label') ?> <span class="text-blue-400 text-xs">(<?= t('pp.recommended') ?>)</span></label>
                                <p class="text-xs text-blue-600 mb-2 italic"><?= t('pp.context_hint') ?></p>
                                <textarea id="genContexte" rows="4"
                                    class="w-full px-3 py-2 border rounded-lg text-sm resize-none focus:ring-2 focus:ring-blue-400"
                                    placeholder="<?= h(t('pp.context_placeholder')) ?>"></textarea>
                            </div>
                            <div class="bg-amber-50 p-5 rounded-xl border border-amber-200">
                                <label class="block text-sm font-semibold text-amber-800 mb-2">&#x26A0;&#xFE0F; <?= t('pp.constraints_label') ?> <span class="text-amber-500 text-xs">(<?= t('pp.recommended') ?>)</span></label>
                                <p class="text-xs text-amber-600 mb-2 italic"><?= t('pp.constraints_hint') ?></p>
                                <textarea id="genContraintes" rows="4"
                                    class="w-full px-3 py-2 border rounded-lg text-sm resize-none focus:ring-2 focus:ring-amber-400"
                                    placeholder="<?= h(t('pp.constraints_placeholder')) ?>"></textarea>
                            </div>
                        </div>

                        <div class="text-center pt-4">
                            <?php if ($hasValidKey): ?>
                            <button onclick="generatePlan()" id="btnGenerate"
                                class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-10 py-4 rounded-xl font-bold text-lg transition-all shadow-xl hover:shadow-2xl transform hover:scale-105">
                                &#x2728; <?= t('pp.generate_plan') ?>
                            </button>
                            <p class="text-xs text-gray-400 mt-3"><?= t('pp.generation_time') ?></p>
                            <?php else: ?>
                            <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
                                <strong><?= t('pp.api_key_required') ?></strong>
                                <?= t('pp.api_key_instructions') ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($hasContent): ?>
                        <div class="text-center pt-2">
                            <button onclick="showEditor()" class="text-emerald-600 hover:text-emerald-700 underline text-sm">
                                <?= t('pp.back_to_plan') ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Loading state -->
            <div id="generatingPanel" class="hidden">
                <div class="bg-white rounded-xl shadow-2xl p-8 md:p-12 text-center generating">
                    <div class="text-6xl mb-6">&#x1F916;</div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-3"><?= t('pp.generating') ?></h2>
                    <p class="text-gray-600 mb-6"><?= t('pp.generating_desc') ?></p>
                    <div class="flex justify-center items-center gap-4">
                        <div class="spinner border-emerald-600 border-t-emerald-200"></div>
                        <span id="generatingStatus" class="text-emerald-700 font-medium"><?= t('pp.connecting_api') ?></span>
                    </div>
                    <div class="mt-8 max-w-md mx-auto">
                        <div class="bg-gray-100 rounded-full h-2 overflow-hidden">
                            <div id="progressBar" class="bg-emerald-500 h-2 rounded-full transition-all duration-1000" style="width: 5%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error state -->
            <div id="errorPanel" class="hidden">
                <div class="bg-red-50 rounded-xl shadow-lg p-6 border border-red-200">
                    <div class="flex items-start gap-4">
                        <div class="text-3xl">&#x26A0;&#xFE0F;</div>
                        <div>
                            <h3 class="font-bold text-red-800 text-lg mb-1"><?= t('pp.generation_error') ?></h3>
                            <p id="errorMessage" class="text-red-700 mb-4"></p>
                            <button onclick="resetGenerator()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition">
                                <?= t('pp.retry') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================== -->
        <!-- EDITOR (after generation) -->
        <!-- ======================== -->
        <div id="editorPanel" class="<?= $hasContent ? '' : 'hidden' ?>">
            <!-- En-tete -->
            <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
                <div class="text-center mb-4">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2"><?= t('pp.title') ?></h1>
                    <p class="text-gray-600 italic"><?= t('pp.subtitle') ?></p>
                </div>

                <div class="flex flex-wrap justify-center gap-3 no-print">
                    <button onclick="showGenerator()" class="text-sm bg-emerald-100 hover:bg-emerald-200 text-emerald-700 px-4 py-2 rounded-lg font-medium transition">
                        &#x1F504; <?= t('pp.regenerate') ?>
                    </button>
                </div>
            </div>

            <!-- Onglets -->
            <div class="flex gap-2 mb-4 no-print">
                <button onclick="switchTab('cadrage')" id="tab-cadrage" class="tab-btn active flex-1 py-3 rounded-lg text-sm font-medium bg-white/30 text-white text-center">
                    &#x1F3AF; <?= t('pp.tab_cadrage') ?>
                </button>
                <button onclick="switchTab('planification')" id="tab-planification" class="tab-btn flex-1 py-3 rounded-lg text-sm font-medium bg-white/30 text-white text-center">
                    &#x1F4CB; <?= t('pp.tab_planification') ?>
                </button>
                <button onclick="switchTab('checkpoints')" id="tab-checkpoints" class="tab-btn flex-1 py-3 rounded-lg text-sm font-medium bg-white/30 text-white text-center">
                    &#x2705; <?= t('pp.tab_checkpoints') ?>
                </button>
                <button onclick="switchTab('suivi')" id="tab-suivi" class="tab-btn flex-1 py-3 rounded-lg text-sm font-medium bg-white/30 text-white text-center">
                    &#x1F4D6; <?= t('pp.tab_suivi') ?>
                </button>
            </div>

            <!-- ======================== -->
            <!-- TAB 1: CADRAGE           -->
            <!-- ======================== -->
            <div id="panel-cadrage" class="tab-panel">
                <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">&#x1F3AF; <?= t('pp.cadrage_title') ?></h2>
                    <p class="text-gray-600 text-sm mb-6"><?= t('pp.cadrage_desc') ?></p>

                    <div class="space-y-5">
                        <div class="bg-emerald-50 p-4 rounded-lg border border-emerald-200">
                            <label class="block text-lg font-semibold text-gray-800 mb-2"><?= t('pp.project_name') ?></label>
                            <input type="text" id="nomProjet"
                                class="w-full px-4 py-2 border-2 border-emerald-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-lg font-medium"
                                placeholder="<?= h(t('pp.project_name_placeholder')) ?>"
                                value="<?= h($analyse['nom_projet'] ?? '') ?>"
                                oninput="scheduleAutoSave()">
                        </div>

                        <div>
                            <label class="block text-lg font-semibold text-gray-800 mb-2"><?= t('pp.project_desc_editor_label') ?></label>
                            <textarea id="descriptionProjet" rows="3"
                                class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500"
                                placeholder="<?= h(t('pp.project_desc_editor_placeholder')) ?>"
                                oninput="scheduleAutoSave()"><?= h($analyse['description_projet'] ?? '') ?></textarea>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <label class="block text-sm font-semibold text-blue-800 mb-2">&#x1F4D8; <?= t('pp.context_label') ?></label>
                                <textarea id="contexte" rows="4"
                                    class="w-full px-3 py-2 border rounded-md text-sm resize-none"
                                    placeholder="<?= h(t('pp.context_placeholder_editor')) ?>"
                                    oninput="scheduleAutoSave()"><?= h($analyse['contexte'] ?? '') ?></textarea>
                            </div>
                            <div class="bg-amber-50 p-4 rounded-lg border border-amber-200">
                                <label class="block text-sm font-semibold text-amber-800 mb-2">&#x26A0;&#xFE0F; <?= t('pp.constraints_label') ?></label>
                                <textarea id="contraintes" rows="4"
                                    class="w-full px-3 py-2 border rounded-md text-sm resize-none"
                                    placeholder="<?= h(t('pp.constraints_placeholder_editor')) ?>"
                                    oninput="scheduleAutoSave()"><?= h($analyse['contraintes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- Recommandations AI -->
                        <div id="recommandationsBlock" class="hidden bg-emerald-50 border-2 border-emerald-200 rounded-xl p-5">
                            <h3 class="font-bold text-emerald-800 mb-2">&#x1F4A1; <?= t('pp.ai_recommendations') ?></h3>
                            <p id="recommandationsText" class="text-gray-700 text-sm"></p>
                        </div>

                        <!-- Objectifs -->
                        <div>
                            <div class="flex justify-between items-center mb-3">
                                <div>
                                    <label class="block text-lg font-semibold text-gray-800">&#x1F3AF; <?= t('pp.objectives_title') ?></label>
                                    <p class="text-sm text-gray-500"><?= t('pp.objectives_hint') ?></p>
                                </div>
                                <button onclick="addObjectif()" class="no-print bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                                    &#x2795; <?= t('pp.add_objective') ?>
                                </button>
                            </div>
                            <div id="objectifsContainer" class="space-y-3"></div>
                            <div id="objectifEmpty" class="text-center py-8 text-gray-400">
                                <p class="text-3xl mb-2">&#x1F3AF;</p>
                                <p><?= t('pp.objectives_empty') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================== -->
            <!-- TAB 2: PLANIFICATION     -->
            <!-- ======================== -->
            <div id="panel-planification" class="tab-panel" style="display:none;">
                <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">&#x1F4CB; <?= t('pp.phases_title') ?></h2>
                        <p class="text-gray-600 text-sm"><?= t('pp.phases_desc') ?></p>
                    </div>

                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <span id="phaseCount" class="text-sm text-gray-500">0 <?= t('pp.phase_count') ?></span>
                        <button onclick="addPhase()" class="no-print bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                            &#x2795; <?= t('pp.add_phase') ?>
                        </button>
                    </div>

                    <div id="phasesContainer" class="space-y-6"></div>
                    <div id="phaseEmpty" class="text-center py-10 text-gray-400">
                        <p class="text-4xl mb-3">&#x1F4CB;</p>
                        <p><?= t('pp.phases_empty') ?></p>
                    </div>
                </div>
            </div>

            <!-- ======================== -->
            <!-- TAB 3: POINTS DE CONTROLE -->
            <!-- ======================== -->
            <div id="panel-checkpoints" class="tab-panel" style="display:none;">
                <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">&#x2705; <?= t('pp.checkpoints_title') ?></h2>
                        <p class="text-gray-600 text-sm"><?= t('pp.checkpoints_desc') ?></p>
                    </div>

                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <span id="checkpointCount" class="text-sm text-gray-500">0 <?= t('pp.checkpoint_count') ?></span>
                        <button onclick="addCheckpoint()" class="no-print bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                            &#x2795; <?= t('pp.add_checkpoint') ?>
                        </button>
                    </div>

                    <div id="checkpointsContainer" class="space-y-4"></div>
                    <div id="checkpointEmpty" class="text-center py-10 text-gray-400">
                        <p class="text-4xl mb-3">&#x2705;</p>
                        <p><?= t('pp.checkpoints_empty') ?></p>
                    </div>
                </div>
            </div>

            <!-- ======================== -->
            <!-- TAB 4: SUIVI & LECONS    -->
            <!-- ======================== -->
            <div id="panel-suivi" class="tab-panel" style="display:none;">
                <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">&#x1F4D6; <?= t('pp.suivi_title') ?></h2>

                    <!-- Stats rapides -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-emerald-50 rounded-xl p-4 text-center border border-emerald-200">
                            <div id="statObjectifs" class="text-3xl font-bold text-emerald-600">0</div>
                            <div class="text-sm text-gray-500"><?= t('pp.stat_objectives') ?></div>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-4 text-center border border-blue-200">
                            <div id="statPhases" class="text-3xl font-bold text-blue-600">0</div>
                            <div class="text-sm text-gray-500"><?= t('pp.stat_phases') ?></div>
                        </div>
                        <div class="bg-purple-50 rounded-xl p-4 text-center border border-purple-200">
                            <div id="statTaches" class="text-3xl font-bold text-purple-600">0</div>
                            <div class="text-sm text-gray-500"><?= t('pp.stat_tasks') ?></div>
                        </div>
                        <div class="bg-amber-50 rounded-xl p-4 text-center border border-amber-200">
                            <div id="statCheckpoints" class="text-3xl font-bold text-amber-600">0</div>
                            <div class="text-sm text-gray-500"><?= t('pp.stat_checkpoints') ?></div>
                        </div>
                    </div>

                    <!-- Lecons apprises -->
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-3">
                            <div>
                                <label class="block text-lg font-semibold text-gray-800">&#x1F4A1; <?= t('pp.lessons_title') ?></label>
                                <p class="text-sm text-gray-500"><?= t('pp.lessons_hint') ?></p>
                            </div>
                            <button onclick="addLesson()" class="no-print bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                                &#x2795; <?= t('pp.add_lesson') ?>
                            </button>
                        </div>
                        <div id="lessonsContainer" class="space-y-3"></div>
                        <div id="lessonEmpty" class="text-center py-6 text-gray-400">
                            <p class="text-2xl mb-2">&#x1F4A1;</p>
                            <p class="text-sm"><?= t('pp.lessons_empty') ?></p>
                        </div>
                    </div>

                    <div class="mb-6 bg-gradient-to-r from-emerald-50 to-green-50 p-4 rounded-lg">
                        <label class="block text-lg font-semibold text-gray-800 mb-2"><?= t('pp.synthese_label') ?></label>
                        <textarea id="synthese" rows="5"
                            class="w-full px-4 py-2 border-2 border-emerald-300 rounded-md focus:ring-2 focus:ring-emerald-500"
                            placeholder="<?= h(t('pp.synthese_placeholder')) ?>"
                            oninput="scheduleAutoSave()"><?= h($analyse['synthese'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                        <label class="block text-lg font-semibold text-gray-800 mb-2">&#x270F;&#xFE0F; <?= t('pp.notes_label') ?></label>
                        <textarea id="notes" rows="3"
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500"
                            placeholder="<?= h(t('pp.notes_placeholder')) ?>"
                            oninput="scheduleAutoSave()"><?= h($analyse['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="no-print flex flex-wrap gap-3 pt-4 border-t-2 border-gray-200">
                        <button onclick="submitAnalyse()" class="bg-emerald-600 text-white px-6 py-3 rounded-md hover:bg-emerald-700 transition font-semibold shadow-md">&#x2705; <?= t('pp.submit') ?></button>
                        <button onclick="exportToExcel()" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition font-semibold shadow-md">&#x1F4CA; <?= t('pp.export_excel') ?></button>
                        <button onclick="exportJSON()" class="bg-gray-500 text-white px-6 py-3 rounded-md hover:bg-gray-600 transition font-semibold shadow-md">&#x1F4E5; <?= t('pp.export_json') ?></button>
                        <button onclick="window.print()" class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition font-semibold shadow-md">&#x1F5A8;&#xFE0F; <?= t('pp.print') ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>
    <script>
    // Donnees
    let objectifs = <?= json_encode($objectifsData) ?>;
    let phases = <?= json_encode($phasesData) ?>;
    let checkpoints = <?= json_encode($checkpointsData) ?>;
    let lessons = <?= json_encode($lessonsData) ?>;
    let autoSaveTimeout = null;
    let recommandations = '';

    const taskStatuses = <?= json_encode($taskStatuses) ?>;
    const checkpointTypes = <?= json_encode($checkpointTypes) ?>;
    const hasContent = <?= $hasContent ? 'true' : 'false' ?>;

    // Translations for JS
    const T = {
        describe_first: <?= json_encode(t('pp.describe_first')) ?>,
        analyzing_desc: <?= json_encode(t('pp.analyzing_desc')) ?>,
        structuring_phases: <?= json_encode(t('pp.structuring_phases')) ?>,
        finalizing_plan: <?= json_encode(t('pp.finalizing_plan')) ?>,
        plan_generated: <?= json_encode(t('pp.plan_generated')) ?>,
        generation_api_error: <?= json_encode(t('pp.generation_api_error')) ?>,
        connecting_api: <?= json_encode(t('pp.connecting_api')) ?>,
        confirm_regenerate: <?= json_encode(t('pp.confirm_regenerate')) ?>,
        confirm_delete_objective: <?= json_encode(t('pp.confirm_delete_objective')) ?>,
        confirm_delete_phase: <?= json_encode(t('pp.confirm_delete_phase')) ?>,
        confirm_delete_task: <?= json_encode(t('pp.confirm_delete_task')) ?>,
        confirm_delete_checkpoint: <?= json_encode(t('pp.confirm_delete_checkpoint')) ?>,
        confirm_delete_lesson: <?= json_encode(t('pp.confirm_delete_lesson')) ?>,
        confirm_submit: <?= json_encode(t('pp.confirm_submit')) ?>,
        project_submitted: <?= json_encode(t('pp.project_submitted')) ?>,
        saving: <?= json_encode(t('pp.saving')) ?>,
        saved: <?= json_encode(t('pp.saved')) ?>,
        error: <?= json_encode(t('pp.error')) ?>,
        network_error: <?= json_encode(t('pp.network_error')) ?>,
        completion: <?= json_encode(t('pp.completion')) ?>,
        phase_count: <?= json_encode(t('pp.phase_count')) ?>,
        checkpoint_count: <?= json_encode(t('pp.checkpoint_count')) ?>,
        phase_label: <?= json_encode(t('pp.phase_label')) ?>,
        phase_name_placeholder: <?= json_encode(t('pp.phase_name_placeholder')) ?>,
        phase_dates_label: <?= json_encode(t('pp.phase_dates_label')) ?>,
        phase_dates_placeholder: <?= json_encode(t('pp.phase_dates_placeholder')) ?>,
        phase_deliverable_label: <?= json_encode(t('pp.phase_deliverable_label')) ?>,
        phase_deliverable_placeholder: <?= json_encode(t('pp.phase_deliverable_placeholder')) ?>,
        tasks_label: <?= json_encode(t('pp.tasks_label')) ?>,
        add_task: <?= json_encode(t('pp.add_task')) ?>,
        task_placeholder: <?= json_encode(t('pp.task_placeholder')) ?>,
        responsible_placeholder: <?= json_encode(t('pp.responsible_placeholder')) ?>,
        tasks_count: <?= json_encode(t('pp.tasks_count')) ?>,
        no_tasks: <?= json_encode(t('pp.no_tasks')) ?>,
        objective_placeholder: <?= json_encode(t('pp.objective_placeholder')) ?>,
        criteria_placeholder: <?= json_encode(t('pp.criteria_placeholder')) ?>,
        checkpoint_type: <?= json_encode(t('pp.checkpoint_type')) ?>,
        checkpoint_type_select: <?= json_encode(t('pp.checkpoint_type_select')) ?>,
        checkpoint_after_phase: <?= json_encode(t('pp.checkpoint_after_phase')) ?>,
        checkpoint_after_phase_select: <?= json_encode(t('pp.checkpoint_after_phase_select')) ?>,
        checkpoint_validator: <?= json_encode(t('pp.checkpoint_validator')) ?>,
        checkpoint_validator_placeholder: <?= json_encode(t('pp.checkpoint_validator_placeholder')) ?>,
        checkpoint_desc_label: <?= json_encode(t('pp.checkpoint_desc_label')) ?>,
        checkpoint_desc_placeholder: <?= json_encode(t('pp.checkpoint_desc_placeholder')) ?>,
        checkpoint_criteria_label: <?= json_encode(t('pp.checkpoint_criteria_label')) ?>,
        checkpoint_criteria_placeholder: <?= json_encode(t('pp.checkpoint_criteria_placeholder')) ?>,
        lesson_success: <?= json_encode(t('pp.lesson_success')) ?>,
        lesson_problem: <?= json_encode(t('pp.lesson_problem')) ?>,
        lesson_improvement: <?= json_encode(t('pp.lesson_improvement')) ?>,
        lesson_placeholder: <?= json_encode(t('pp.lesson_placeholder')) ?>,
        submitted: <?= json_encode(t('pp.submitted')) ?>,
        excel_cadrage: <?= json_encode(t('pp.excel_cadrage')) ?>,
        excel_objectives: <?= json_encode(t('pp.excel_objectives')) ?>,
        excel_objective: <?= json_encode(t('pp.excel_objective')) ?>,
        excel_success_criteria: <?= json_encode(t('pp.excel_success_criteria')) ?>,
        excel_recommendations: <?= json_encode(t('pp.excel_recommendations')) ?>,
        excel_phases_tasks: <?= json_encode(t('pp.excel_phases_tasks')) ?>,
        excel_phase: <?= json_encode(t('pp.excel_phase')) ?>,
        excel_dates: <?= json_encode(t('pp.excel_dates')) ?>,
        excel_deliverable: <?= json_encode(t('pp.excel_deliverable')) ?>,
        excel_task: <?= json_encode(t('pp.excel_task')) ?>,
        excel_responsible: <?= json_encode(t('pp.excel_responsible')) ?>,
        excel_status: <?= json_encode(t('pp.excel_status')) ?>,
        excel_checkpoints: <?= json_encode(t('pp.excel_checkpoints')) ?>,
        excel_type: <?= json_encode(t('pp.excel_type')) ?>,
        excel_after_phase: <?= json_encode(t('pp.excel_after_phase')) ?>,
        excel_who_validates: <?= json_encode(t('pp.excel_who_validates')) ?>,
        excel_description: <?= json_encode(t('pp.excel_description')) ?>,
        excel_criteria: <?= json_encode(t('pp.excel_criteria')) ?>,
        cadrage_sheet: <?= json_encode(t('pp.cadrage_sheet')) ?>,
        phases_sheet: <?= json_encode(t('pp.phases_sheet')) ?>,
        checkpoints_sheet: <?= json_encode(t('pp.checkpoints_sheet')) ?>,
        project_name: <?= json_encode(t('pp.project_name')) ?>,
        context_label: <?= json_encode(t('pp.context_label')) ?>,
        constraints_label: <?= json_encode(t('pp.constraints_label')) ?>,
    };

    // ========================
    // AI GENERATION
    // ========================
    async function generatePlan() {
        const description = document.getElementById('genDescription').value.trim();
        if (!description) {
            alert(T.describe_first);
            document.getElementById('genDescription').focus();
            return;
        }

        const contexte = document.getElementById('genContexte').value.trim();
        const contraintes = document.getElementById('genContraintes').value.trim();

        // Show loading
        document.getElementById('btnGenerate').parentElement.style.display = 'none';
        document.getElementById('generatingPanel').classList.remove('hidden');
        document.getElementById('errorPanel').classList.add('hidden');

        // Animate progress bar
        let progress = 5;
        const progressInterval = setInterval(() => {
            if (progress < 85) {
                progress += Math.random() * 8;
                document.getElementById('progressBar').style.width = Math.min(progress, 85) + '%';
            }
            // Update status text
            if (progress > 20 && progress < 40) {
                document.getElementById('generatingStatus').textContent = T.analyzing_desc;
            } else if (progress > 40 && progress < 60) {
                document.getElementById('generatingStatus').textContent = T.structuring_phases;
            } else if (progress > 60) {
                document.getElementById('generatingStatus').textContent = T.finalizing_plan;
            }
        }, 800);

        try {
            const response = await fetch('api/generate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ description, contexte, contraintes })
            });

            const data = await response.json();

            clearInterval(progressInterval);

            if (!response.ok || data.error) {
                throw new Error(data.error || T.generation_api_error);
            }

            const plan = data.plan;

            // Fill progress to 100%
            document.getElementById('progressBar').style.width = '100%';
            document.getElementById('generatingStatus').textContent = T.plan_generated;

            // Short delay then apply
            await new Promise(r => setTimeout(r, 600));

            // Apply generated plan to data
            applyGeneratedPlan(plan, description, contexte, contraintes);

        } catch (err) {
            clearInterval(progressInterval);
            document.getElementById('generatingPanel').classList.add('hidden');
            document.getElementById('errorPanel').classList.remove('hidden');
            document.getElementById('errorMessage').textContent = err.message;
            document.getElementById('btnGenerate').parentElement.style.display = '';
        }
    }

    function applyGeneratedPlan(plan, description, contexte, contraintes) {
        // Set base fields
        document.getElementById('nomProjet').value = plan.nom_projet || '';
        document.getElementById('descriptionProjet').value = plan.description_projet || description;
        document.getElementById('contexte').value = contexte;
        document.getElementById('contraintes').value = contraintes;

        // Objectifs
        objectifs = (plan.objectifs || []).map(o => ({
            titre: o.titre || '',
            criteres: o.criteres || ''
        }));

        // Phases with tasks
        phases = (plan.phases || []).map(p => ({
            nom: p.nom || '',
            dates: p.dates || '',
            livrable: p.livrable || '',
            taches: (p.taches || []).map(t => ({
                titre: t.titre || '',
                responsable: t.responsable || '',
                statut: t.statut || 'todo'
            }))
        }));

        // Checkpoints
        checkpoints = (plan.checkpoints || []).map(cp => ({
            type: cp.type || 'validation',
            apres_phase: cp.apres_phase !== undefined ? cp.apres_phase : '',
            validateur: cp.validateur || '',
            description: cp.description || '',
            criteres: cp.criteres || ''
        }));

        // Recommendations
        recommandations = plan.recommandations || '';

        // Switch to editor
        showEditor();

        // Render everything
        renderObjectifs();
        renderPhases();
        renderCheckpoints();
        renderLessons();

        // Show recommandations if present
        if (recommandations) {
            document.getElementById('recommandationsBlock').classList.remove('hidden');
            document.getElementById('recommandationsText').textContent = recommandations;
        }

        // Save immediately
        scheduleAutoSave();
    }

    function showEditor() {
        document.getElementById('generatorPanel').classList.add('hidden');
        document.getElementById('editorPanel').classList.remove('hidden');
        document.getElementById('btnSave').style.display = '';
        document.getElementById('completion').style.display = '';
    }

    function showGenerator() {
        if (hasContent || objectifs.length > 0 || phases.length > 0) {
            if (!confirm(T.confirm_regenerate)) return;
        }
        document.getElementById('editorPanel').classList.add('hidden');
        document.getElementById('generatorPanel').classList.remove('hidden');
        document.getElementById('generatingPanel').classList.add('hidden');
        document.getElementById('errorPanel').classList.add('hidden');
        document.getElementById('btnGenerate')?.parentElement && (document.getElementById('btnGenerate').parentElement.style.display = '');
        // Reset progress
        document.getElementById('progressBar').style.width = '5%';
        document.getElementById('generatingStatus').textContent = T.connecting_api;
        document.getElementById('btnSave').style.display = 'none';
        document.getElementById('completion').style.display = 'none';
    }

    function resetGenerator() {
        document.getElementById('errorPanel').classList.add('hidden');
        document.getElementById('generatingPanel').classList.add('hidden');
        document.getElementById('btnGenerate').parentElement.style.display = '';
    }

    // Onglets
    function switchTab(tab) {
        document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('panel-' + tab).style.display = 'block';
        document.getElementById('tab-' + tab).classList.add('active');
        if (tab === 'suivi') updateStats();
    }

    // ========================
    // OBJECTIFS
    // ========================
    function renderObjectifs() {
        const c = document.getElementById('objectifsContainer');
        const empty = document.getElementById('objectifEmpty');
        if (objectifs.length === 0) { c.innerHTML = ''; empty.style.display = 'block'; return; }
        empty.style.display = 'none';
        c.innerHTML = '';
        objectifs.forEach((o, i) => c.appendChild(createObjectifCard(o, i)));
    }

    function createObjectifCard(o, index) {
        const div = document.createElement('div');
        div.className = 'card-hover fade-in bg-white rounded-lg border-2 border-emerald-100 shadow p-4';
        div.innerHTML = `
            <div class="flex items-start gap-3">
                <span class="bg-emerald-600 text-white text-xs font-bold px-2.5 py-1 rounded-full mt-1">O${index + 1}</span>
                <div class="flex-1">
                    <input type="text" value="${esc(o.titre || '')}"
                        class="w-full px-3 py-2 border-2 border-emerald-200 rounded-md text-sm font-bold focus:ring-2 focus:ring-emerald-500 mb-2"
                        placeholder="${esc(T.objective_placeholder)}"
                        oninput="updateObj(${index}, 'titre', this.value)">
                    <input type="text" value="${esc(o.criteres || '')}"
                        class="w-full px-3 py-2 border rounded-md text-sm"
                        placeholder="${esc(T.criteria_placeholder)}"
                        oninput="updateObj(${index}, 'criteres', this.value)">
                </div>
                <button onclick="removeObjectif(${index})" class="no-print text-red-400 hover:text-red-600">&#x2716;</button>
            </div>
        `;
        return div;
    }

    function addObjectif() {
        objectifs.push({ titre: '', criteres: '' });
        renderObjectifs(); scheduleAutoSave();
        setTimeout(() => { document.getElementById('objectifsContainer').lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    }
    function removeObjectif(i) { if (!confirm(T.confirm_delete_objective)) return; objectifs.splice(i, 1); renderObjectifs(); scheduleAutoSave(); }
    function updateObj(i, f, v) { if (objectifs[i]) { objectifs[i][f] = v; scheduleAutoSave(); } }

    // ========================
    // PHASES & TACHES
    // ========================
    function renderPhases() {
        const c = document.getElementById('phasesContainer');
        const empty = document.getElementById('phaseEmpty');
        if (phases.length === 0) { c.innerHTML = ''; empty.style.display = 'block'; updatePhaseCount(); return; }
        empty.style.display = 'none';
        c.innerHTML = '';
        phases.forEach((p, i) => c.appendChild(createPhaseCard(p, i)));
        updatePhaseCount();
    }

    function createPhaseCard(p, index) {
        const div = document.createElement('div');
        div.className = 'fade-in phase-card bg-white rounded-xl shadow-lg p-5 mb-2';

        let tasksHTML = '';
        (p.taches || []).forEach((t, ti) => {
            let statusOptions = '';
            for (const [key, s] of Object.entries(taskStatuses)) {
                statusOptions += `<option value="${key}"${t.statut === key ? ' selected' : ''}>${s.icon} ${s.label}</option>`;
            }
            tasksHTML += `
                <div class="flex items-start gap-2 bg-gray-50 rounded-lg p-3 border">
                    <div class="flex-1 grid md:grid-cols-4 gap-2">
                        <div class="md:col-span-2">
                            <input type="text" value="${esc(t.titre || '')}" class="w-full px-2 py-1.5 border rounded text-sm"
                                placeholder="${esc(T.task_placeholder)}" oninput="updateTask(${index}, ${ti}, 'titre', this.value)">
                        </div>
                        <div>
                            <input type="text" value="${esc(t.responsable || '')}" class="w-full px-2 py-1.5 border rounded text-sm"
                                placeholder="${esc(T.responsible_placeholder)}" oninput="updateTask(${index}, ${ti}, 'responsable', this.value)">
                        </div>
                        <div class="flex gap-2">
                            <select class="flex-1 px-2 py-1.5 border rounded text-sm bg-white"
                                onchange="updateTaskStatus(${index}, ${ti}, this.value)">${statusOptions}</select>
                            <button onclick="removeTask(${index}, ${ti})" class="no-print text-red-400 hover:text-red-600 text-sm">&#x2716;</button>
                        </div>
                    </div>
                </div>
            `;
        });

        const taskCount = (p.taches || []).length;
        const doneCount = (p.taches || []).filter(t => t.statut === 'done').length;
        const pct = taskCount > 0 ? Math.round((doneCount / taskCount) * 100) : 0;

        div.innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <span class="bg-emerald-600 text-white text-sm font-bold px-3 py-1 rounded-full">${T.phase_label} ${index + 1}</span>
                    <input type="text" value="${esc(p.nom || '')}"
                        class="text-lg font-bold text-gray-800 border-0 border-b-2 border-transparent focus:border-emerald-400 focus:outline-none bg-transparent"
                        placeholder="${esc(T.phase_name_placeholder)}" oninput="updatePhase(${index}, 'nom', this.value)">
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500">${doneCount}/${taskCount} ${T.tasks_count}</span>
                    <div class="w-20 bg-gray-200 rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: ${pct}%"></div>
                    </div>
                    <button onclick="removePhase(${index})" class="no-print text-red-400 hover:text-red-600">&#x2716;</button>
                </div>
            </div>
            <div class="grid md:grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">${T.phase_dates_label}</label>
                    <input type="text" value="${esc(p.dates || '')}" class="w-full px-3 py-1.5 border rounded text-sm"
                        placeholder="${esc(T.phase_dates_placeholder)}" oninput="updatePhase(${index}, 'dates', this.value)">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">${T.phase_deliverable_label}</label>
                    <input type="text" value="${esc(p.livrable || '')}" class="w-full px-3 py-1.5 border rounded text-sm"
                        placeholder="${esc(T.phase_deliverable_placeholder)}" oninput="updatePhase(${index}, 'livrable', this.value)">
                </div>
            </div>

            <div class="mb-2 flex justify-between items-center">
                <span class="text-xs font-semibold text-gray-500">${T.tasks_label}</span>
                <button onclick="addTask(${index})" class="no-print text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-700 px-3 py-1 rounded font-medium">+ ${T.add_task}</button>
            </div>
            <div class="space-y-2">${tasksHTML}</div>
            ${taskCount === 0 ? '<p class="text-center text-gray-400 text-sm py-3">' + T.no_tasks + '</p>' : ''}
        `;
        return div;
    }

    function addPhase() {
        phases.push({ nom: '', dates: '', livrable: '', taches: [] });
        renderPhases(); scheduleAutoSave();
        setTimeout(() => { document.getElementById('phasesContainer').lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    }
    function removePhase(i) { if (!confirm(T.confirm_delete_phase)) return; phases.splice(i, 1); renderPhases(); scheduleAutoSave(); }
    function updatePhase(i, f, v) { if (phases[i]) { phases[i][f] = v; scheduleAutoSave(); } }

    function addTask(phaseIndex) {
        if (!phases[phaseIndex].taches) phases[phaseIndex].taches = [];
        phases[phaseIndex].taches.push({ titre: '', responsable: '', statut: 'todo' });
        renderPhases(); scheduleAutoSave();
    }
    function removeTask(pi, ti) {
        if (!confirm(T.confirm_delete_task)) return;
        phases[pi].taches.splice(ti, 1); renderPhases(); scheduleAutoSave();
    }
    function updateTask(pi, ti, f, v) { if (phases[pi]?.taches?.[ti]) { phases[pi].taches[ti][f] = v; scheduleAutoSave(); } }
    function updateTaskStatus(pi, ti, v) { if (phases[pi]?.taches?.[ti]) { phases[pi].taches[ti].statut = v; renderPhases(); scheduleAutoSave(); } }
    function updatePhaseCount() { document.getElementById('phaseCount').textContent = phases.length + ' ' + T.phase_count; }

    // ========================
    // CHECKPOINTS
    // ========================
    function renderCheckpoints() {
        const c = document.getElementById('checkpointsContainer');
        const empty = document.getElementById('checkpointEmpty');
        if (checkpoints.length === 0) { c.innerHTML = ''; empty.style.display = 'block'; updateCheckpointCount(); return; }
        empty.style.display = 'none';
        c.innerHTML = '';
        checkpoints.forEach((cp, i) => c.appendChild(createCheckpointCard(cp, i)));
        updateCheckpointCount();
    }

    function createCheckpointCard(cp, index) {
        const div = document.createElement('div');
        const typeInfo = checkpointTypes[cp.type] || {};
        const borderColor = typeInfo.color ? `border-${typeInfo.color}-400` : 'border-gray-300';
        div.className = `card-hover fade-in bg-white rounded-lg border-l-4 ${borderColor} shadow p-4`;

        let typeOptions = `<option value="">${T.checkpoint_type_select}</option>`;
        for (const [key, t] of Object.entries(checkpointTypes)) {
            typeOptions += `<option value="${key}"${cp.type === key ? ' selected' : ''}>${t.icon} ${t.label}</option>`;
        }

        let phaseOptions = `<option value="">${T.checkpoint_after_phase_select}</option>`;
        phases.forEach((p, i) => {
            phaseOptions += `<option value="${i}"${cp.apres_phase == i ? ' selected' : ''}>${p.nom || T.phase_label + ' ' + (i+1)}</option>`;
        });

        div.innerHTML = `
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <div class="grid md:grid-cols-3 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">${T.checkpoint_type}</label>
                            <select class="w-full px-3 py-2 border rounded-md text-sm bg-white" onchange="updateCP(${index}, 'type', this.value); renderCheckpoints();">${typeOptions}</select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">${T.checkpoint_after_phase}</label>
                            <select class="w-full px-3 py-2 border rounded-md text-sm bg-white" onchange="updateCP(${index}, 'apres_phase', this.value)">${phaseOptions}</select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">${T.checkpoint_validator}</label>
                            <input type="text" value="${esc(cp.validateur || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="${esc(T.checkpoint_validator_placeholder)}"
                                oninput="updateCP(${index}, 'validateur', this.value)">
                        </div>
                    </div>
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">${T.checkpoint_desc_label}</label>
                            <input type="text" value="${esc(cp.description || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="${esc(T.checkpoint_desc_placeholder)}"
                                oninput="updateCP(${index}, 'description', this.value)">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">${T.checkpoint_criteria_label}</label>
                            <input type="text" value="${esc(cp.criteres || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="${esc(T.checkpoint_criteria_placeholder)}"
                                oninput="updateCP(${index}, 'criteres', this.value)">
                        </div>
                    </div>
                </div>
                <button onclick="removeCheckpoint(${index})" class="no-print text-red-400 hover:text-red-600 mt-1">&#x2716;</button>
            </div>
        `;
        return div;
    }

    function addCheckpoint() {
        checkpoints.push({ type: '', apres_phase: '', validateur: '', description: '', criteres: '' });
        renderCheckpoints(); scheduleAutoSave();
        setTimeout(() => { document.getElementById('checkpointsContainer').lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    }
    function removeCheckpoint(i) { if (!confirm(T.confirm_delete_checkpoint)) return; checkpoints.splice(i, 1); renderCheckpoints(); scheduleAutoSave(); }
    function updateCP(i, f, v) { if (checkpoints[i]) { checkpoints[i][f] = v; scheduleAutoSave(); } }
    function updateCheckpointCount() { document.getElementById('checkpointCount').textContent = checkpoints.length + ' ' + T.checkpoint_count; }

    // ========================
    // LECONS APPRISES
    // ========================
    function renderLessons() {
        const c = document.getElementById('lessonsContainer');
        const empty = document.getElementById('lessonEmpty');
        if (lessons.length === 0) { c.innerHTML = ''; empty.style.display = 'block'; return; }
        empty.style.display = 'none';
        c.innerHTML = '';
        lessons.forEach((l, i) => c.appendChild(createLessonCard(l, i)));
    }

    function createLessonCard(l, index) {
        const div = document.createElement('div');
        const catColors = { 'success': 'border-green-400 bg-green-50', 'problem': 'border-red-400 bg-red-50', 'improvement': 'border-blue-400 bg-blue-50' };
        const cls = catColors[l.categorie] || 'border-gray-300 bg-gray-50';
        div.className = `card-hover fade-in rounded-lg border-l-4 p-4 ${cls}`;

        div.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-1">
                    <div class="flex gap-3 mb-2">
                        <select class="px-2 py-1 border rounded text-sm bg-white" onchange="updateLesson(${index}, 'categorie', this.value); renderLessons();">
                            <option value="success"${l.categorie === 'success' ? ' selected' : ''}>&#x2705; ${T.lesson_success}</option>
                            <option value="problem"${l.categorie === 'problem' ? ' selected' : ''}>&#x26A0;&#xFE0F; ${T.lesson_problem}</option>
                            <option value="improvement"${l.categorie === 'improvement' ? ' selected' : ''}>&#x1F4A1; ${T.lesson_improvement}</option>
                        </select>
                    </div>
                    <input type="text" value="${esc(l.lecon || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                        placeholder="${esc(T.lesson_placeholder)}" oninput="updateLesson(${index}, 'lecon', this.value)">
                </div>
                <button onclick="removeLesson(${index})" class="no-print text-red-400 hover:text-red-600">&#x2716;</button>
            </div>
        `;
        return div;
    }

    function addLesson() {
        lessons.push({ categorie: 'improvement', lecon: '' });
        renderLessons(); scheduleAutoSave();
    }
    function removeLesson(i) { if (!confirm(T.confirm_delete_lesson)) return; lessons.splice(i, 1); renderLessons(); scheduleAutoSave(); }
    function updateLesson(i, f, v) { if (lessons[i]) { lessons[i][f] = v; scheduleAutoSave(); } }

    // Stats
    function updateStats() {
        document.getElementById('statObjectifs').textContent = objectifs.length;
        document.getElementById('statPhases').textContent = phases.length;
        let totalTasks = 0;
        phases.forEach(p => { totalTasks += (p.taches || []).length; });
        document.getElementById('statTaches').textContent = totalTasks;
        document.getElementById('statCheckpoints').textContent = checkpoints.length;
    }

    // ========================
    // SAUVEGARDE
    // ========================
    function scheduleAutoSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        document.getElementById('saveStatus').textContent = T.saving;
        document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-400 text-yellow-900';
        autoSaveTimeout = setTimeout(saveData, 1000);
    }

    async function saveData() {
        const payload = {
            nom_projet: document.getElementById('nomProjet').value,
            description_projet: document.getElementById('descriptionProjet').value,
            contexte: document.getElementById('contexte').value,
            contraintes: document.getElementById('contraintes').value,
            objectifs_data: objectifs,
            phases_data: phases,
            checkpoints_data: checkpoints,
            lessons_data: lessons,
            synthese: document.getElementById('synthese').value,
            notes: document.getElementById('notes').value
        };
        try {
            const r = await fetch('api/save.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const res = await r.json();
            if (res.success) {
                document.getElementById('saveStatus').textContent = T.saved;
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                document.getElementById('completion').innerHTML = T.completion + ': <strong>' + res.completion + '%</strong>';
            } else {
                document.getElementById('saveStatus').textContent = T.error;
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
            }
        } catch (e) {
            document.getElementById('saveStatus').textContent = T.network_error;
            document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
        }
    }

    async function manualSave() { if (autoSaveTimeout) clearTimeout(autoSaveTimeout); await saveData(); }

    async function submitAnalyse() {
        if (!confirm(T.confirm_submit)) return;
        await saveData();
        try {
            const r = await fetch('api/submit.php', { method: 'POST' });
            const res = await r.json();
            if (res.success) {
                document.getElementById('saveStatus').textContent = T.submitted;
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-emerald-500 text-white';
                alert(T.project_submitted);
            }
        } catch (e) { console.error(e); }
    }

    // Exports
    function exportJSON() {
        const data = {
            nom_projet: document.getElementById('nomProjet').value,
            description: document.getElementById('descriptionProjet').value,
            contexte: document.getElementById('contexte').value,
            contraintes: document.getElementById('contraintes').value,
            objectifs, phases, checkpoints, lessons,
            synthese: document.getElementById('synthese').value,
            notes: document.getElementById('notes').value,
            recommandations,
            dateExport: new Date().toISOString()
        };
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const link = document.createElement('a'); link.href = URL.createObjectURL(blob);
        link.download = 'projet_' + new Date().toISOString().split('T')[0] + '.json';
        link.click(); URL.revokeObjectURL(link.href);
    }

    function exportToExcel() {
        const wb = XLSX.utils.book_new();
        const nom = document.getElementById('nomProjet').value || 'Mon projet';

        // Cadrage
        const cadData = [[T.excel_cadrage + ' — ' + nom], [],
            [T.project_name, nom],
            [T.excel_description, document.getElementById('descriptionProjet').value],
            [T.context_label, document.getElementById('contexte').value],
            [T.constraints_label, document.getElementById('contraintes').value],
            [], [T.excel_objectives], ['#', T.excel_objective, T.excel_success_criteria]
        ];
        objectifs.forEach((o, i) => cadData.push([(i+1).toString(), o.titre || '', o.criteres || '']));
        if (recommandations) { cadData.push([], [T.excel_recommendations], [recommandations]); }
        const ws1 = XLSX.utils.aoa_to_sheet(cadData);
        ws1['!cols'] = [{wch:15},{wch:50},{wch:50}];

        // Phases & Taches
        const ptData = [[T.excel_phases_tasks], [], [T.excel_phase, T.excel_dates, T.excel_deliverable, T.excel_task, T.excel_responsible, T.excel_status]];
        phases.forEach((p, i) => {
            if (!p.taches || p.taches.length === 0) {
                ptData.push([p.nom || T.phase_label + ' ' + (i+1), p.dates || '', p.livrable || '', '', '', '']);
            } else {
                p.taches.forEach((t, ti) => {
                    ptData.push([ti === 0 ? (p.nom || T.phase_label + ' ' + (i+1)) : '', ti === 0 ? (p.dates || '') : '', ti === 0 ? (p.livrable || '') : '', t.titre || '', t.responsable || '', taskStatuses[t.statut]?.label || t.statut || '']);
                });
            }
        });
        const ws2 = XLSX.utils.aoa_to_sheet(ptData);
        ws2['!cols'] = [{wch:20},{wch:20},{wch:30},{wch:40},{wch:20},{wch:15}];

        // Checkpoints
        const cpData = [[T.excel_checkpoints], [], [T.excel_type, T.excel_after_phase, T.excel_who_validates, T.excel_description, T.excel_criteria]];
        checkpoints.forEach(cp => {
            const phaseName = phases[cp.apres_phase]?.nom || T.phase_label + ' ' + (parseInt(cp.apres_phase)+1) || '';
            cpData.push([checkpointTypes[cp.type]?.label || cp.type || '', phaseName, cp.validateur || '', cp.description || '', cp.criteres || '']);
        });
        const ws3 = XLSX.utils.aoa_to_sheet(cpData);
        ws3['!cols'] = [{wch:25},{wch:20},{wch:20},{wch:40},{wch:40}];

        XLSX.utils.book_append_sheet(wb, ws1, T.cadrage_sheet);
        XLSX.utils.book_append_sheet(wb, ws2, T.phases_sheet);
        XLSX.utils.book_append_sheet(wb, ws3, T.checkpoints_sheet);
        XLSX.writeFile(wb, 'projet_' + nom.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_' + new Date().toISOString().split('T')[0] + '.xlsx');
    }

    function esc(t) { const d = document.createElement('div'); d.appendChild(document.createTextNode(t)); return d.innerHTML; }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        if (hasContent) {
            document.getElementById('btnSave').style.display = '';
            document.getElementById('completion').style.display = '';
        }
        renderObjectifs();
        renderPhases();
        renderCheckpoints();
        renderLessons();
    });
    </script>
</body>
</html>
