<?php
/**
 * Interface principale - Guide de Prompting sur Mesure
 */
require_once __DIR__ . '/config.php';
requireLoginWithSession();

$user = getLoggedUser();
$db = getDB();

// Creer ou recuperer le guide pour cette session
$stmt = $db->prepare("SELECT * FROM guides WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$guide = $stmt->fetch();

if (!$guide) {
    $stmt = $db->prepare("INSERT INTO guides (user_id, session_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $_SESSION['current_session_id']]);
    $guide = [
        'organisation_nom' => '',
        'organisation_mission' => '',
        'current_step' => 1,
        'tasks' => '[]',
        'experimentations' => '[]',
        'templates' => '[]',
        'guide_intro' => '',
        'notes' => '',
        'is_shared' => 0
    ];
} else {
    $guide['tasks'] = $guide['tasks'] ?: '[]';
    $guide['experimentations'] = $guide['experimentations'] ?: '[]';
    $guide['templates'] = $guide['templates'] ?: '[]';
    $guide['organisation_nom'] = $guide['organisation_nom'] ?? '';
    $guide['organisation_mission'] = $guide['organisation_mission'] ?? '';
    $guide['guide_intro'] = $guide['guide_intro'] ?? '';
    $guide['notes'] = $guide['notes'] ?? '';
    $guide['current_step'] = $guide['current_step'] ?? 1;
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('gp.title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .step-content { display: none; }
        .step-content.active { display: block; }
        .task-card { transition: all 0.3s; }
        .task-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .template-section { transition: all 0.3s; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-100 to-purple-100 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white p-4 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold"><?= t('gp.title') ?></h1>
                <p class="text-indigo-200 text-sm"><?= h($user['prenom']) ?> <?= h($user['nom']) ?></p>
            </div>
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('indigo') ?>
                <button onclick="saveData()" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    <?= t('common.save') ?>
                </button>
                <a href="logout.php" class="bg-red-500/80 hover:bg-red-600 px-4 py-2 rounded-lg"><?= t('auth.logout') ?></a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-6">
        <!-- Progress Steps -->
        <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <div class="step-indicator flex-1 text-center py-2 mx-1 rounded-full cursor-pointer transition-all" data-step="1">
                    <span class="font-medium text-sm">1. <?= t('gp.step1_short') ?></span>
                </div>
                <div class="step-indicator flex-1 text-center py-2 mx-1 rounded-full cursor-pointer transition-all" data-step="2">
                    <span class="font-medium text-sm">2. <?= t('gp.step2_short') ?></span>
                </div>
                <div class="step-indicator flex-1 text-center py-2 mx-1 rounded-full cursor-pointer transition-all" data-step="3">
                    <span class="font-medium text-sm">3. <?= t('gp.step3_short') ?></span>
                </div>
                <div class="step-indicator flex-1 text-center py-2 mx-1 rounded-full cursor-pointer transition-all" data-step="4">
                    <span class="font-medium text-sm">4. <?= t('gp.step4_short') ?></span>
                </div>
            </div>
        </div>

        <!-- Step 1: Task Identification -->
        <div class="step-content" data-step="1">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-2xl font-bold text-indigo-800 mb-4 flex items-center gap-2">
                    <span class="bg-indigo-600 text-white w-10 h-10 rounded-full flex items-center justify-center">1</span>
                    <?= t('gp.step1_title') ?>
                </h2>
                <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 mb-6">
                    <p class="text-indigo-800"><?= t('gp.step1_desc') ?></p>
                </div>

                <div class="grid md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('gp.org_name') ?></label>
                        <input type="text" id="orgName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="<?= t('gp.org_name_placeholder') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('gp.org_mission') ?></label>
                        <input type="text" id="orgMission" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="<?= t('gp.org_mission_placeholder') ?>">
                    </div>
                </div>

                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-indigo-700"><?= t('gp.your_tasks') ?></h3>
                    <button onclick="addTask()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <?= t('gp.add_task') ?>
                    </button>
                </div>
                <div id="tasksContainer" class="space-y-4">
                    <!-- Tasks will be added here -->
                </div>
            </div>
        </div>

        <!-- Step 2: Experimentation -->
        <div class="step-content" data-step="2">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-2xl font-bold text-indigo-800 mb-4 flex items-center gap-2">
                    <span class="bg-indigo-600 text-white w-10 h-10 rounded-full flex items-center justify-center">2</span>
                    <?= t('gp.step2_title') ?>
                </h2>
                <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 mb-6">
                    <p class="text-indigo-800"><?= t('gp.step2_desc') ?></p>
                </div>
                <div id="experimentContainer" class="space-y-6">
                    <!-- Experimentation sections will be generated here -->
                </div>
            </div>
        </div>

        <!-- Step 3: Template Creation -->
        <div class="step-content" data-step="3">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-2xl font-bold text-indigo-800 mb-4 flex items-center gap-2">
                    <span class="bg-indigo-600 text-white w-10 h-10 rounded-full flex items-center justify-center">3</span>
                    <?= t('gp.step3_title') ?>
                </h2>
                <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 mb-6">
                    <p class="text-indigo-800"><?= t('gp.step3_desc') ?></p>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h4 class="font-semibold text-blue-800 mb-2"><?= t('gp.template_structure') ?></h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li><strong>Section 1:</strong> <?= t('gp.section_context') ?></li>
                        <li><strong>Section 2:</strong> <?= t('gp.section_task') ?></li>
                        <li><strong>Section 3:</strong> <?= t('gp.section_format') ?></li>
                        <li><strong>Section 4:</strong> <?= t('gp.section_instructions') ?></li>
                        <li><strong>Section 5:</strong> <?= t('gp.section_examples') ?></li>
                    </ul>
                </div>
                <div id="templatesContainer" class="space-y-6">
                    <!-- Templates will be generated here -->
                </div>
            </div>
        </div>

        <!-- Step 4: Final Guide -->
        <div class="step-content" data-step="4">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-2xl font-bold text-indigo-800 mb-4 flex items-center gap-2">
                    <span class="bg-indigo-600 text-white w-10 h-10 rounded-full flex items-center justify-center">4</span>
                    <?= t('gp.step4_title') ?>
                </h2>
                <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 mb-6">
                    <p class="text-indigo-800"><?= t('gp.step4_desc') ?></p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('gp.guide_intro') ?></label>
                    <textarea id="guideIntro" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="<?= t('gp.guide_intro_placeholder') ?>"></textarea>
                </div>

                <div id="guidePreview" class="bg-gray-50 border-2 border-indigo-200 rounded-lg p-6 max-h-96 overflow-y-auto">
                    <p class="text-gray-400 text-center"><?= t('gp.preview_placeholder') ?></p>
                </div>

                <div class="flex gap-4 mt-6">
                    <button onclick="exportGuide()" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <?= t('gp.export_guide') ?>
                    </button>
                    <button onclick="submitGuide()" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-semibold flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?= t('common.submit') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex justify-between">
            <button id="prevBtn" onclick="changeStep(-1)" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                <?= t('common.previous') ?>
            </button>
            <button id="nextBtn" onclick="changeStep(1)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-semibold">
                <?= t('common.next') ?>
            </button>
        </div>
    </div>

    <!-- Task Modal -->
    <div id="taskModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-bold text-gray-800 mb-4"><?= t('gp.task_modal_title') ?></h3>
            <input type="hidden" id="editTaskId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('gp.task_name') ?></label>
                    <input type="text" id="taskName" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="<?= t('gp.task_name_placeholder') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('gp.task_objective') ?></label>
                    <textarea id="taskObjective" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="<?= t('gp.task_objective_placeholder') ?>"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('gp.task_audience') ?></label>
                    <input type="text" id="taskAudience" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="<?= t('gp.task_audience_placeholder') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('gp.task_style') ?></label>
                    <input type="text" id="taskStyle" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="<?= t('gp.task_style_placeholder') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('gp.task_elements') ?></label>
                    <textarea id="taskElements" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="<?= t('gp.task_elements_placeholder') ?>"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeTaskModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg"><?= t('common.cancel') ?></button>
                <button onclick="saveTask()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg"><?= t('common.save') ?></button>
            </div>
        </div>
    </div>

    <script>
        const trans = {
            confirmDelete: <?= json_encode(t('common.confirm_delete')) ?>,
            saveSuccess: <?= json_encode(t('common.save_success')) ?>,
            saveError: <?= json_encode(t('common.save_error')) ?>,
            submitSuccess: <?= json_encode(t('common.submit_success')) ?>,
            submitError: <?= json_encode(t('common.submit_error')) ?>,
            noTasks: <?= json_encode(t('gp.no_tasks')) ?>,
            taskLabel: <?= json_encode(t('gp.task_label')) ?>,
            promptV1: <?= json_encode(t('gp.prompt_v1')) ?>,
            resultAnalysis: <?= json_encode(t('gp.result_analysis')) ?>,
            promptV2: <?= json_encode(t('gp.prompt_v2')) ?>,
            optimizationNotes: <?= json_encode(t('gp.optimization_notes')) ?>,
            sectionContext: <?= json_encode(t('gp.section_context')) ?>,
            sectionTask: <?= json_encode(t('gp.section_task')) ?>,
            sectionFormat: <?= json_encode(t('gp.section_format')) ?>,
            sectionInstructions: <?= json_encode(t('gp.section_instructions')) ?>,
            sectionExamples: <?= json_encode(t('gp.section_examples')) ?>,
            adaptTips: <?= json_encode(t('gp.adapt_tips')) ?>
        };

        let currentStep = <?= (int)$guide['current_step'] ?>;
        let tasks = <?= $guide['tasks'] ?>;
        let experimentations = <?= $guide['experimentations'] ?>;
        let templates = <?= $guide['templates'] ?>;

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('orgName').value = <?= json_encode($guide['organisation_nom']) ?>;
            document.getElementById('orgMission').value = <?= json_encode($guide['organisation_mission']) ?>;
            document.getElementById('guideIntro').value = <?= json_encode($guide['guide_intro']) ?>;

            renderTasks();
            updateStepDisplay();
        });

        function updateStepDisplay() {
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.add('active');

            document.querySelectorAll('.step-indicator').forEach(el => {
                const step = parseInt(el.dataset.step);
                el.classList.remove('bg-indigo-600', 'text-white', 'bg-green-500', 'bg-gray-200', 'text-gray-600');
                if (step < currentStep) {
                    el.classList.add('bg-green-500', 'text-white');
                } else if (step === currentStep) {
                    el.classList.add('bg-indigo-600', 'text-white');
                } else {
                    el.classList.add('bg-gray-200', 'text-gray-600');
                }
            });

            document.getElementById('prevBtn').disabled = currentStep === 1;
            document.getElementById('nextBtn').style.display = currentStep === 4 ? 'none' : 'block';
        }

        function changeStep(direction) {
            const newStep = currentStep + direction;
            if (newStep < 1 || newStep > 4) return;

            if (newStep === 2 && direction === 1) generateExperimentations();
            if (newStep === 3 && direction === 1) generateTemplates();
            if (newStep === 4 && direction === 1) generatePreview();

            currentStep = newStep;
            updateStepDisplay();
            saveData();
        }

        // Tasks
        function addTask() {
            document.getElementById('editTaskId').value = '';
            document.getElementById('taskName').value = '';
            document.getElementById('taskObjective').value = '';
            document.getElementById('taskAudience').value = '';
            document.getElementById('taskStyle').value = '';
            document.getElementById('taskElements').value = '';
            document.getElementById('taskModal').classList.remove('hidden');
        }

        function editTask(id) {
            const task = tasks.find(t => t.id === id);
            if (task) {
                document.getElementById('editTaskId').value = id;
                document.getElementById('taskName').value = task.name || '';
                document.getElementById('taskObjective').value = task.objective || '';
                document.getElementById('taskAudience').value = task.audience || '';
                document.getElementById('taskStyle').value = task.style || '';
                document.getElementById('taskElements').value = task.elements || '';
                document.getElementById('taskModal').classList.remove('hidden');
            }
        }

        function closeTaskModal() {
            document.getElementById('taskModal').classList.add('hidden');
        }

        function saveTask() {
            const id = document.getElementById('editTaskId').value;
            const data = {
                name: document.getElementById('taskName').value.trim(),
                objective: document.getElementById('taskObjective').value.trim(),
                audience: document.getElementById('taskAudience').value.trim(),
                style: document.getElementById('taskStyle').value.trim(),
                elements: document.getElementById('taskElements').value.trim()
            };
            if (!data.name) return;

            if (id) {
                const index = tasks.findIndex(t => t.id === id);
                if (index !== -1) tasks[index] = { ...tasks[index], ...data };
            } else {
                tasks.push({ id: 'task_' + Date.now(), ...data });
            }
            renderTasks();
            closeTaskModal();
        }

        function deleteTask(id) {
            if (confirm(trans.confirmDelete)) {
                tasks = tasks.filter(t => t.id !== id);
                renderTasks();
            }
        }

        function renderTasks() {
            const container = document.getElementById('tasksContainer');
            if (tasks.length === 0) {
                container.innerHTML = `<p class="text-gray-400 text-center py-8">${trans.noTasks}</p>`;
                return;
            }
            container.innerHTML = tasks.map((task, i) => `
                <div class="task-card bg-gray-50 border-2 border-gray-200 rounded-xl p-4">
                    <div class="flex justify-between items-start mb-3">
                        <h4 class="font-bold text-indigo-700">${trans.taskLabel} ${i + 1}: ${escapeHtml(task.name)}</h4>
                        <div class="flex gap-2">
                            <button onclick="editTask('${task.id}')" class="text-indigo-600 hover:bg-indigo-100 p-1 rounded">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                            <button onclick="deleteTask('${task.id}')" class="text-red-600 hover:bg-red-100 p-1 rounded">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                    ${task.objective ? `<p class="text-sm text-gray-600 mb-1"><strong>Objectif:</strong> ${escapeHtml(task.objective)}</p>` : ''}
                    ${task.audience ? `<p class="text-sm text-gray-600 mb-1"><strong>Public:</strong> ${escapeHtml(task.audience)}</p>` : ''}
                    ${task.style ? `<p class="text-sm text-gray-600"><strong>Style:</strong> ${escapeHtml(task.style)}</p>` : ''}
                </div>
            `).join('');
        }

        function generateExperimentations() {
            const container = document.getElementById('experimentContainer');
            if (tasks.length === 0) {
                container.innerHTML = `<p class="text-gray-400 text-center py-8">${trans.noTasks}</p>`;
                return;
            }
            container.innerHTML = tasks.map((task, i) => {
                const exp = experimentations.find(e => e.taskId === task.id) || {};
                return `
                <div class="bg-indigo-50 border-2 border-dashed border-indigo-300 rounded-xl p-5">
                    <h4 class="font-bold text-indigo-700 mb-4">${trans.taskLabel} ${i + 1}: ${escapeHtml(task.name)}</h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${trans.promptV1}</label>
                            <textarea id="exp_prompt1_${task.id}" rows="3" class="w-full px-3 py-2 border rounded-lg" onchange="updateExperimentation('${task.id}')">${escapeHtml(exp.prompt1 || '')}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${trans.resultAnalysis}</label>
                            <textarea id="exp_result1_${task.id}" rows="2" class="w-full px-3 py-2 border rounded-lg" onchange="updateExperimentation('${task.id}')">${escapeHtml(exp.result1 || '')}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${trans.promptV2}</label>
                            <textarea id="exp_prompt2_${task.id}" rows="3" class="w-full px-3 py-2 border rounded-lg" onchange="updateExperimentation('${task.id}')">${escapeHtml(exp.prompt2 || '')}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${trans.optimizationNotes}</label>
                            <textarea id="exp_notes_${task.id}" rows="2" class="w-full px-3 py-2 border rounded-lg" onchange="updateExperimentation('${task.id}')">${escapeHtml(exp.notes || '')}</textarea>
                        </div>
                    </div>
                </div>
            `}).join('');
        }

        function updateExperimentation(taskId) {
            const exp = {
                taskId: taskId,
                prompt1: document.getElementById(`exp_prompt1_${taskId}`)?.value || '',
                result1: document.getElementById(`exp_result1_${taskId}`)?.value || '',
                prompt2: document.getElementById(`exp_prompt2_${taskId}`)?.value || '',
                notes: document.getElementById(`exp_notes_${taskId}`)?.value || ''
            };
            const index = experimentations.findIndex(e => e.taskId === taskId);
            if (index !== -1) experimentations[index] = exp;
            else experimentations.push(exp);
        }

        function generateTemplates() {
            const container = document.getElementById('templatesContainer');
            const orgName = document.getElementById('orgName').value || '[Organisation]';
            const orgMission = document.getElementById('orgMission').value || '[Mission]';

            if (tasks.length === 0) {
                container.innerHTML = `<p class="text-gray-400 text-center py-8">${trans.noTasks}</p>`;
                return;
            }

            container.innerHTML = tasks.map((task, i) => {
                const tpl = templates.find(t => t.taskId === task.id) || {};
                return `
                <div class="bg-blue-50 rounded-xl p-5 space-y-4">
                    <h4 class="font-bold text-indigo-700">${trans.taskLabel} ${i + 1}: ${escapeHtml(task.name)}</h4>
                    <div class="template-section bg-white rounded-lg p-4 border">
                        <label class="block text-sm font-semibold text-indigo-600 mb-2">${trans.sectionContext}</label>
                        <textarea id="tpl_context_${task.id}" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm" onchange="updateTemplate('${task.id}')">${escapeHtml(tpl.context || `Tu es un assistant pour ${orgName}, une organisation qui ${orgMission}.`)}</textarea>
                    </div>
                    <div class="template-section bg-white rounded-lg p-4 border">
                        <label class="block text-sm font-semibold text-indigo-600 mb-2">${trans.sectionTask}</label>
                        <textarea id="tpl_task_${task.id}" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm" onchange="updateTemplate('${task.id}')">${escapeHtml(tpl.task || `Je dois rediger [type de document] a destination de ${task.audience || '[public]'}. Ce document doit ${task.objective || '[objectif]'}.`)}</textarea>
                    </div>
                    <div class="template-section bg-white rounded-lg p-4 border">
                        <label class="block text-sm font-semibold text-indigo-600 mb-2">${trans.sectionFormat}</label>
                        <textarea id="tpl_format_${task.id}" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm" onchange="updateTemplate('${task.id}')">${escapeHtml(tpl.format || `Le style doit etre ${task.style || '[style]'}.\n\nFormat souhaite:\n- [Structure]\n- [Longueur]\n- [Elements obligatoires]`)}</textarea>
                    </div>
                    <div class="template-section bg-white rounded-lg p-4 border">
                        <label class="block text-sm font-semibold text-indigo-600 mb-2">${trans.sectionInstructions}</label>
                        <textarea id="tpl_instructions_${task.id}" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm" onchange="updateTemplate('${task.id}')">${escapeHtml(tpl.instructions || `Informations essentielles:\n${task.elements || '- [Point 1]\n- [Point 2]'}`)}</textarea>
                    </div>
                    <div class="template-section bg-white rounded-lg p-4 border">
                        <label class="block text-sm font-semibold text-indigo-600 mb-2">${trans.sectionExamples}</label>
                        <textarea id="tpl_examples_${task.id}" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm" onchange="updateTemplate('${task.id}')">${escapeHtml(tpl.examples || 'Voici un exemple du ton: [extrait]\n\nNe pas inclure: [elements a eviter]')}</textarea>
                    </div>
                    <div class="template-section bg-white rounded-lg p-4 border">
                        <label class="block text-sm font-semibold text-indigo-600 mb-2">${trans.adaptTips}</label>
                        <textarea id="tpl_tips_${task.id}" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm" onchange="updateTemplate('${task.id}')">${escapeHtml(tpl.tips || '')}</textarea>
                    </div>
                </div>
            `}).join('');
        }

        function updateTemplate(taskId) {
            const tpl = {
                taskId: taskId,
                context: document.getElementById(`tpl_context_${taskId}`)?.value || '',
                task: document.getElementById(`tpl_task_${taskId}`)?.value || '',
                format: document.getElementById(`tpl_format_${taskId}`)?.value || '',
                instructions: document.getElementById(`tpl_instructions_${taskId}`)?.value || '',
                examples: document.getElementById(`tpl_examples_${taskId}`)?.value || '',
                tips: document.getElementById(`tpl_tips_${taskId}`)?.value || ''
            };
            const index = templates.findIndex(t => t.taskId === taskId);
            if (index !== -1) templates[index] = tpl;
            else templates.push(tpl);
        }

        function generatePreview() {
            const orgName = document.getElementById('orgName').value || '[Organisation]';
            const intro = document.getElementById('guideIntro').value || '';

            let html = `<h2 class="text-2xl font-bold text-indigo-800 mb-4">Guide de Prompting - ${escapeHtml(orgName)}</h2>`;
            if (intro) html += `<div class="mb-6"><h3 class="font-semibold text-lg mb-2">Introduction</h3><p>${escapeHtml(intro)}</p></div>`;

            tasks.forEach((task, i) => {
                const tpl = templates.find(t => t.taskId === task.id) || {};
                const fullPrompt = [tpl.context, tpl.task, tpl.format, tpl.instructions, tpl.examples].filter(s => s).join('\n\n');
                html += `
                    <div class="border-t pt-4 mt-4">
                        <h3 class="font-semibold text-lg text-indigo-700 mb-2">Fiche ${i + 1}: ${escapeHtml(task.name)}</h3>
                        <p class="text-sm text-gray-600 mb-2"><strong>Objectif:</strong> ${escapeHtml(task.objective || '-')}</p>
                        <p class="text-sm text-gray-600 mb-3"><strong>Public:</strong> ${escapeHtml(task.audience || '-')}</p>
                        <div class="bg-gray-100 p-3 rounded-lg text-sm font-mono whitespace-pre-wrap">${escapeHtml(fullPrompt)}</div>
                        ${tpl.tips ? `<p class="text-sm text-gray-500 mt-2"><em>Conseils: ${escapeHtml(tpl.tips)}</em></p>` : ''}
                    </div>
                `;
            });

            document.getElementById('guidePreview').innerHTML = html;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function saveData() {
            // Collect experimentations and templates from DOM if on those steps
            if (currentStep === 2) tasks.forEach(t => updateExperimentation(t.id));
            if (currentStep === 3) tasks.forEach(t => updateTemplate(t.id));

            const data = {
                organisation_nom: document.getElementById('orgName').value,
                organisation_mission: document.getElementById('orgMission').value,
                current_step: currentStep,
                tasks: tasks,
                experimentations: experimentations,
                templates: templates,
                guide_intro: document.getElementById('guideIntro').value
            };

            try {
                const response = await fetch('api/save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (!result.success) console.error('Save error:', result.error);
            } catch (e) {
                console.error('Save error:', e);
            }
        }

        async function submitGuide() {
            await saveData();
            try {
                const response = await fetch('api/submit.php', { method: 'POST' });
                const result = await response.json();
                if (result.success) alert(trans.submitSuccess);
                else alert(trans.submitError);
            } catch (e) {
                alert(trans.submitError);
            }
        }

        function exportGuide() {
            generatePreview();
            const content = document.getElementById('guidePreview').innerHTML;
            const orgName = document.getElementById('orgName').value || 'Organisation';
            const today = new Date().toLocaleDateString('fr-FR');

            const html = `<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Guide de Prompting - ${orgName}</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; line-height: 1.6; }
        h2, h3 { color: #4338ca; }
        .template-box { background: #f3f4f6; padding: 15px; border-radius: 8px; font-family: monospace; white-space: pre-wrap; }
    </style>
</head>
<body>
    ${content}
    <hr><p><small>Guide genere le ${today}</small></p>
</body>
</html>`;

            const blob = new Blob([html], { type: 'text/html' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `guide_prompting_${orgName.replace(/[^a-z0-9]/gi, '_')}_${today.replace(/\//g, '-')}.html`;
            a.click();
        }

        // Auto-save on input
        document.addEventListener('input', () => setTimeout(saveData, 1000));
    </script>
    <?= renderLanguageScript() ?>
</body>
</html>
