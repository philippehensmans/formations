<?php
/**
 * Interface principale - Atelier IA pour Associations
 */
require_once __DIR__ . '/config.php';
requireLoginWithSession();

$user = getLoggedUser();
$db = getDB();

// Creer ou recuperer l'atelier pour cette session
$stmt = $db->prepare("SELECT * FROM ateliers WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$atelier = $stmt->fetch();

if (!$atelier) {
    $stmt = $db->prepare("INSERT INTO ateliers (user_id, session_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $_SESSION['current_session_id']]);
    $atelier = [
        'association_nom' => '',
        'association_mission' => '',
        'post_its' => '[]',
        'themes' => '[]',
        'interactions' => '[]',
        'conditions_reussite' => '[]',
        'notes' => '',
        'is_shared' => 0
    ];
} else {
    // S'assurer que les valeurs ne sont jamais null
    $atelier['post_its'] = $atelier['post_its'] ?: '[]';
    $atelier['themes'] = $atelier['themes'] ?: '[]';
    $atelier['interactions'] = $atelier['interactions'] ?: '[]';
    $atelier['conditions_reussite'] = $atelier['conditions_reussite'] ?: '[]';
    $atelier['association_nom'] = $atelier['association_nom'] ?? '';
    $atelier['association_mission'] = $atelier['association_mission'] ?? '';
    $atelier['notes'] = $atelier['notes'] ?? '';
}

$isSubmitted = $atelier['is_shared'] == 1;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('aia.title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .post-it { transition: transform 0.2s, box-shadow 0.2s; }
        .post-it:hover { transform: scale(1.02); box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .post-it.dragging { opacity: 0.5; transform: rotate(3deg); }
        .theme-zone { min-height: 150px; border: 2px dashed #e5e7eb; transition: all 0.3s; }
        .theme-zone.drag-over { border-color: #8b5cf6; background-color: #f5f3ff; }
        .interaction-card { transition: all 0.3s; }
        .interaction-card:hover { transform: translateY(-2px); }
        .condition-card { transition: all 0.3s; }
        .progress-ring { transform: rotate(-90deg); }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 to-indigo-100 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-purple-600 to-indigo-700 text-white p-4 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-xl font-bold"><?= t('aia.title') ?></h1>
                    <p class="text-purple-200 text-sm"><?= h($user['prenom']) ?> <?= h($user['nom']) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('purple') ?>
                <button onclick="saveData()" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    <?= t('common.save') ?>
                </button>
                <button onclick="submitAtelier()" class="bg-green-500 hover:bg-green-600 px-4 py-2 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= t('common.submit') ?>
                </button>
                <a href="login.php?logout=1" class="bg-red-500/80 hover:bg-red-600 px-4 py-2 rounded-lg"><?= t('auth.logout') ?></a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-6 space-y-8">
        <!-- Association Info -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <?= t('aia.association_info') ?>
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.association_name') ?></label>
                    <input type="text" id="associationNom" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="<?= t('aia.association_name_placeholder') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.association_mission') ?></label>
                    <input type="text" id="associationMission" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="<?= t('aia.association_mission_placeholder') ?>">
                </div>
            </div>
        </div>

        <!-- Step 1: Post-its -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span class="bg-purple-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">1</span>
                    <?= t('aia.step1_title') ?>
                </h2>
                <button onclick="addPostIt()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <?= t('aia.add_postit') ?>
                </button>
            </div>
            <p class="text-gray-600 mb-4"><?= t('aia.step1_desc') ?></p>
            <div id="postItContainer" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <!-- Post-its seront ajoutes ici -->
            </div>
        </div>

        <!-- Step 2: Themes -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span class="bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">2</span>
                    <?= t('aia.step2_title') ?>
                </h2>
                <button onclick="addTheme()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <?= t('aia.add_theme') ?>
                </button>
            </div>
            <p class="text-gray-600 mb-4"><?= t('aia.step2_desc') ?></p>
            <div id="themesContainer" class="space-y-4">
                <!-- Themes seront ajoutes ici -->
            </div>
        </div>

        <!-- Step 3: Interactions -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">3</span>
                    <?= t('aia.step3_title') ?>
                </h2>
                <button onclick="addInteraction()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <?= t('aia.add_interaction') ?>
                </button>
            </div>
            <p class="text-gray-600 mb-4"><?= t('aia.step3_desc') ?></p>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-semibold text-green-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <?= t('aia.preserve_human') ?>
                    </h3>
                    <div id="preserveContainer" class="space-y-2 min-h-[100px] theme-zone rounded-lg p-3">
                        <!-- Interactions a preserver -->
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-700 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <?= t('aia.ai_assisted') ?>
                    </h3>
                    <div id="aiAssistedContainer" class="space-y-2 min-h-[100px] theme-zone rounded-lg p-3">
                        <!-- Interactions avec IA -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Success Conditions -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span class="bg-green-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">4</span>
                    <?= t('aia.step4_title') ?>
                </h2>
                <button onclick="addCondition()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <?= t('aia.add_condition') ?>
                </button>
            </div>
            <p class="text-gray-600 mb-4"><?= t('aia.step4_desc') ?></p>
            <div id="conditionsContainer" class="grid md:grid-cols-2 gap-4">
                <!-- Conditions seront ajoutees ici -->
            </div>
        </div>

        <!-- Notes -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <?= t('common.notes') ?>
            </h2>
            <textarea id="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="<?= t('aia.notes_placeholder') ?>"></textarea>
        </div>
    </div>

    <!-- Modal Post-it -->
    <div id="postItModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4"><?= t('aia.postit_modal_title') ?></h3>
            <input type="hidden" id="editPostItId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.problem_desc') ?></label>
                    <textarea id="postItText" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.postit_color') ?></label>
                    <div class="flex gap-2">
                        <button type="button" onclick="selectColor('yellow')" class="w-10 h-10 bg-yellow-200 rounded-lg border-2 border-transparent hover:border-gray-400 color-btn" data-color="yellow"></button>
                        <button type="button" onclick="selectColor('pink')" class="w-10 h-10 bg-pink-200 rounded-lg border-2 border-transparent hover:border-gray-400 color-btn" data-color="pink"></button>
                        <button type="button" onclick="selectColor('green')" class="w-10 h-10 bg-green-200 rounded-lg border-2 border-transparent hover:border-gray-400 color-btn" data-color="green"></button>
                        <button type="button" onclick="selectColor('blue')" class="w-10 h-10 bg-blue-200 rounded-lg border-2 border-transparent hover:border-gray-400 color-btn" data-color="blue"></button>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closePostItModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg"><?= t('common.cancel') ?></button>
                <button onclick="savePostIt()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg"><?= t('common.save') ?></button>
            </div>
        </div>
    </div>

    <!-- Modal Theme -->
    <div id="themeModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4"><?= t('aia.theme_modal_title') ?></h3>
            <input type="hidden" id="editThemeId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.theme_name') ?></label>
                    <input type="text" id="themeName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.theme_desc') ?></label>
                    <textarea id="themeDesc" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeThemeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg"><?= t('common.cancel') ?></button>
                <button onclick="saveTheme()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg"><?= t('common.save') ?></button>
            </div>
        </div>
    </div>

    <!-- Modal Interaction -->
    <div id="interactionModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4"><?= t('aia.interaction_modal_title') ?></h3>
            <input type="hidden" id="editInteractionId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.interaction_name') ?></label>
                    <input type="text" id="interactionName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.interaction_type') ?></label>
                    <select id="interactionType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="preserve"><?= t('aia.preserve_human') ?></option>
                        <option value="ai"><?= t('aia.ai_assisted') ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.interaction_reason') ?></label>
                    <textarea id="interactionReason" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeInteractionModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg"><?= t('common.cancel') ?></button>
                <button onclick="saveInteraction()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg"><?= t('common.save') ?></button>
            </div>
        </div>
    </div>

    <!-- Modal Condition -->
    <div id="conditionModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4"><?= t('aia.condition_modal_title') ?></h3>
            <input type="hidden" id="editConditionId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.condition_name') ?></label>
                    <input type="text" id="conditionName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.condition_indicator') ?></label>
                    <input type="text" id="conditionIndicator" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="<?= t('aia.condition_indicator_placeholder') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('aia.condition_target') ?></label>
                    <input type="text" id="conditionTarget" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="<?= t('aia.condition_target_placeholder') ?>">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeConditionModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg"><?= t('common.cancel') ?></button>
                <button onclick="saveCondition()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg"><?= t('common.save') ?></button>
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
            noElement: <?= json_encode(t('aia.no_element')) ?>
        };

        // Data
        let postIts = <?= $atelier['post_its'] ?>;
        let themes = <?= $atelier['themes'] ?>;
        let interactions = <?= $atelier['interactions'] ?>;
        let conditions = <?= $atelier['conditions_reussite'] ?>;
        let selectedColor = 'yellow';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('associationNom').value = <?= json_encode($atelier['association_nom']) ?>;
            document.getElementById('associationMission').value = <?= json_encode($atelier['association_mission']) ?>;
            document.getElementById('notes').value = <?= json_encode($atelier['notes']) ?>;

            renderPostIts();
            renderThemes();
            renderInteractions();
            renderConditions();
        });

        // Post-its
        function addPostIt() {
            document.getElementById('editPostItId').value = '';
            document.getElementById('postItText').value = '';
            selectColor('yellow');
            document.getElementById('postItModal').classList.remove('hidden');
        }

        function editPostIt(id) {
            const postIt = postIts.find(p => p.id === id);
            if (postIt) {
                document.getElementById('editPostItId').value = id;
                document.getElementById('postItText').value = postIt.text;
                selectColor(postIt.color || 'yellow');
                document.getElementById('postItModal').classList.remove('hidden');
            }
        }

        function selectColor(color) {
            selectedColor = color;
            document.querySelectorAll('.color-btn').forEach(btn => {
                btn.classList.toggle('border-gray-800', btn.dataset.color === color);
                btn.classList.toggle('border-transparent', btn.dataset.color !== color);
            });
        }

        function closePostItModal() {
            document.getElementById('postItModal').classList.add('hidden');
        }

        function savePostIt() {
            const id = document.getElementById('editPostItId').value;
            const text = document.getElementById('postItText').value.trim();
            if (!text) return;

            if (id) {
                const index = postIts.findIndex(p => p.id === id);
                if (index !== -1) {
                    postIts[index].text = text;
                    postIts[index].color = selectedColor;
                }
            } else {
                postIts.push({
                    id: 'postit_' + Date.now(),
                    text: text,
                    color: selectedColor,
                    themeId: null
                });
            }
            renderPostIts();
            closePostItModal();
        }

        function deletePostIt(id) {
            if (confirm(trans.confirmDelete)) {
                postIts = postIts.filter(p => p.id !== id);
                renderPostIts();
            }
        }

        function getColorClass(color) {
            const colors = {
                yellow: 'bg-yellow-200 border-yellow-400',
                pink: 'bg-pink-200 border-pink-400',
                green: 'bg-green-200 border-green-400',
                blue: 'bg-blue-200 border-blue-400'
            };
            return colors[color] || colors.yellow;
        }

        function renderPostIts() {
            const container = document.getElementById('postItContainer');
            const unassigned = postIts.filter(p => !p.themeId);

            container.innerHTML = unassigned.map(postIt => `
                <div class="post-it ${getColorClass(postIt.color)} p-4 rounded-lg shadow-md cursor-move border-2" data-id="${postIt.id}">
                    <p class="text-sm font-medium text-gray-800">${escapeHtml(postIt.text)}</p>
                    <div class="flex justify-end gap-1 mt-2">
                        <button onclick="editPostIt('${postIt.id}')" class="p-1 hover:bg-white/50 rounded">
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                        <button onclick="deletePostIt('${postIt.id}')" class="p-1 hover:bg-white/50 rounded">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            `).join('') || `<p class="col-span-full text-center text-gray-400 py-8">${trans.noElement}</p>`;

            // Initialize sortable
            new Sortable(container, {
                animation: 150,
                ghostClass: 'dragging',
                group: 'postits'
            });

            // Update theme zones
            renderThemePostIts();
        }

        // Themes
        function addTheme() {
            document.getElementById('editThemeId').value = '';
            document.getElementById('themeName').value = '';
            document.getElementById('themeDesc').value = '';
            document.getElementById('themeModal').classList.remove('hidden');
        }

        function editTheme(id) {
            const theme = themes.find(t => t.id === id);
            if (theme) {
                document.getElementById('editThemeId').value = id;
                document.getElementById('themeName').value = theme.name;
                document.getElementById('themeDesc').value = theme.description || '';
                document.getElementById('themeModal').classList.remove('hidden');
            }
        }

        function closeThemeModal() {
            document.getElementById('themeModal').classList.add('hidden');
        }

        function saveTheme() {
            const id = document.getElementById('editThemeId').value;
            const name = document.getElementById('themeName').value.trim();
            if (!name) return;

            if (id) {
                const index = themes.findIndex(t => t.id === id);
                if (index !== -1) {
                    themes[index].name = name;
                    themes[index].description = document.getElementById('themeDesc').value.trim();
                }
            } else {
                themes.push({
                    id: 'theme_' + Date.now(),
                    name: name,
                    description: document.getElementById('themeDesc').value.trim()
                });
            }
            renderThemes();
            closeThemeModal();
        }

        function deleteTheme(id) {
            if (confirm(trans.confirmDelete)) {
                // Move post-its back to unassigned
                postIts.forEach(p => {
                    if (p.themeId === id) p.themeId = null;
                });
                themes = themes.filter(t => t.id !== id);
                renderThemes();
                renderPostIts();
            }
        }

        function renderThemes() {
            const container = document.getElementById('themesContainer');
            container.innerHTML = themes.map(theme => `
                <div class="bg-indigo-50 rounded-xl p-4 border-2 border-indigo-200">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="font-bold text-indigo-800">${escapeHtml(theme.name)}</h3>
                            ${theme.description ? `<p class="text-sm text-indigo-600">${escapeHtml(theme.description)}</p>` : ''}
                        </div>
                        <div class="flex gap-1">
                            <button onclick="editTheme('${theme.id}')" class="p-1 hover:bg-indigo-200 rounded">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                            <button onclick="deleteTheme('${theme.id}')" class="p-1 hover:bg-indigo-200 rounded">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="theme-zone rounded-lg p-3 grid grid-cols-2 md:grid-cols-4 gap-2" data-theme-id="${theme.id}">
                        <!-- Post-its for this theme -->
                    </div>
                </div>
            `).join('') || `<p class="text-center text-gray-400 py-8">${trans.noElement}</p>`;

            renderThemePostIts();
        }

        function renderThemePostIts() {
            themes.forEach(theme => {
                const zone = document.querySelector(`[data-theme-id="${theme.id}"]`);
                if (!zone) return;

                const themePostIts = postIts.filter(p => p.themeId === theme.id);
                zone.innerHTML = themePostIts.map(postIt => `
                    <div class="post-it ${getColorClass(postIt.color)} p-3 rounded-lg shadow-sm cursor-move border text-sm" data-id="${postIt.id}">
                        <p class="text-gray-800">${escapeHtml(postIt.text)}</p>
                    </div>
                `).join('');

                new Sortable(zone, {
                    animation: 150,
                    ghostClass: 'dragging',
                    group: 'postits',
                    onAdd: function(evt) {
                        const postItId = evt.item.dataset.id;
                        const postIt = postIts.find(p => p.id === postItId);
                        if (postIt) {
                            postIt.themeId = theme.id;
                        }
                    },
                    onRemove: function(evt) {
                        const postItId = evt.item.dataset.id;
                        const postIt = postIts.find(p => p.id === postItId);
                        if (postIt && evt.to.dataset.themeId === undefined) {
                            postIt.themeId = null;
                        }
                    }
                });
            });
        }

        // Interactions
        function addInteraction() {
            document.getElementById('editInteractionId').value = '';
            document.getElementById('interactionName').value = '';
            document.getElementById('interactionType').value = 'preserve';
            document.getElementById('interactionReason').value = '';
            document.getElementById('interactionModal').classList.remove('hidden');
        }

        function editInteraction(id) {
            const interaction = interactions.find(i => i.id === id);
            if (interaction) {
                document.getElementById('editInteractionId').value = id;
                document.getElementById('interactionName').value = interaction.name;
                document.getElementById('interactionType').value = interaction.type;
                document.getElementById('interactionReason').value = interaction.reason || '';
                document.getElementById('interactionModal').classList.remove('hidden');
            }
        }

        function closeInteractionModal() {
            document.getElementById('interactionModal').classList.add('hidden');
        }

        function saveInteraction() {
            const id = document.getElementById('editInteractionId').value;
            const name = document.getElementById('interactionName').value.trim();
            if (!name) return;

            const data = {
                name: name,
                type: document.getElementById('interactionType').value,
                reason: document.getElementById('interactionReason').value.trim()
            };

            if (id) {
                const index = interactions.findIndex(i => i.id === id);
                if (index !== -1) {
                    interactions[index] = { ...interactions[index], ...data };
                }
            } else {
                interactions.push({
                    id: 'interaction_' + Date.now(),
                    ...data
                });
            }
            renderInteractions();
            closeInteractionModal();
        }

        function deleteInteraction(id) {
            if (confirm(trans.confirmDelete)) {
                interactions = interactions.filter(i => i.id !== id);
                renderInteractions();
            }
        }

        function renderInteractions() {
            const preserveContainer = document.getElementById('preserveContainer');
            const aiContainer = document.getElementById('aiAssistedContainer');

            const preserve = interactions.filter(i => i.type === 'preserve');
            const ai = interactions.filter(i => i.type === 'ai');

            preserveContainer.innerHTML = preserve.map(i => `
                <div class="interaction-card bg-green-100 p-3 rounded-lg border border-green-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-green-800">${escapeHtml(i.name)}</p>
                            ${i.reason ? `<p class="text-sm text-green-600 mt-1">${escapeHtml(i.reason)}</p>` : ''}
                        </div>
                        <div class="flex gap-1">
                            <button onclick="editInteraction('${i.id}')" class="p-1 hover:bg-green-200 rounded">
                                <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                            <button onclick="deleteInteraction('${i.id}')" class="p-1 hover:bg-green-200 rounded">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('') || `<p class="text-center text-gray-400 py-4">${trans.noElement}</p>`;

            aiContainer.innerHTML = ai.map(i => `
                <div class="interaction-card bg-blue-100 p-3 rounded-lg border border-blue-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-blue-800">${escapeHtml(i.name)}</p>
                            ${i.reason ? `<p class="text-sm text-blue-600 mt-1">${escapeHtml(i.reason)}</p>` : ''}
                        </div>
                        <div class="flex gap-1">
                            <button onclick="editInteraction('${i.id}')" class="p-1 hover:bg-blue-200 rounded">
                                <svg class="w-4 h-4 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                            <button onclick="deleteInteraction('${i.id}')" class="p-1 hover:bg-blue-200 rounded">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('') || `<p class="text-center text-gray-400 py-4">${trans.noElement}</p>`;
        }

        // Conditions
        function addCondition() {
            document.getElementById('editConditionId').value = '';
            document.getElementById('conditionName').value = '';
            document.getElementById('conditionIndicator').value = '';
            document.getElementById('conditionTarget').value = '';
            document.getElementById('conditionModal').classList.remove('hidden');
        }

        function editCondition(id) {
            const condition = conditions.find(c => c.id === id);
            if (condition) {
                document.getElementById('editConditionId').value = id;
                document.getElementById('conditionName').value = condition.name;
                document.getElementById('conditionIndicator').value = condition.indicator || '';
                document.getElementById('conditionTarget').value = condition.target || '';
                document.getElementById('conditionModal').classList.remove('hidden');
            }
        }

        function closeConditionModal() {
            document.getElementById('conditionModal').classList.add('hidden');
        }

        function saveCondition() {
            const id = document.getElementById('editConditionId').value;
            const name = document.getElementById('conditionName').value.trim();
            if (!name) return;

            const data = {
                name: name,
                indicator: document.getElementById('conditionIndicator').value.trim(),
                target: document.getElementById('conditionTarget').value.trim()
            };

            if (id) {
                const index = conditions.findIndex(c => c.id === id);
                if (index !== -1) {
                    conditions[index] = { ...conditions[index], ...data };
                }
            } else {
                conditions.push({
                    id: 'condition_' + Date.now(),
                    ...data
                });
            }
            renderConditions();
            closeConditionModal();
        }

        function deleteCondition(id) {
            if (confirm(trans.confirmDelete)) {
                conditions = conditions.filter(c => c.id !== id);
                renderConditions();
            }
        }

        function renderConditions() {
            const container = document.getElementById('conditionsContainer');
            container.innerHTML = conditions.map(c => `
                <div class="condition-card bg-green-50 p-4 rounded-xl border-2 border-green-200">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold text-green-800">${escapeHtml(c.name)}</h4>
                        <div class="flex gap-1">
                            <button onclick="editCondition('${c.id}')" class="p-1 hover:bg-green-200 rounded">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                            <button onclick="deleteCondition('${c.id}')" class="p-1 hover:bg-green-200 rounded">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                    ${c.indicator ? `<p class="text-sm text-gray-600"><span class="font-medium">Indicateur:</span> ${escapeHtml(c.indicator)}</p>` : ''}
                    ${c.target ? `<p class="text-sm text-gray-600"><span class="font-medium">Cible:</span> ${escapeHtml(c.target)}</p>` : ''}
                </div>
            `).join('') || `<p class="col-span-full text-center text-gray-400 py-8">${trans.noElement}</p>`;
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Save data
        async function saveData() {
            const data = {
                association_nom: document.getElementById('associationNom').value,
                association_mission: document.getElementById('associationMission').value,
                post_its: postIts,
                themes: themes,
                interactions: interactions,
                conditions_reussite: conditions,
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

        // Submit atelier
        async function submitAtelier() {
            await saveData();
            try {
                const response = await fetch('api/submit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
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

        <?= renderLanguageScript() ?>
    </script>
</body>
</html>
