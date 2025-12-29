<?php
/**
 * Interface de travail - Analyse PESTEL
 */
require_once __DIR__ . '/config.php';

// Vérifier l'authentification
if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user = getLoggedUser();

// Vérifier que l'utilisateur existe en base
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$sessionId = $_SESSION['current_session_id'];
$sessionNom = $_SESSION['current_session_nom'] ?? '';

// Charger l'analyse PESTEL
$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$analyse = $stmt->fetch();

if (!$analyse) {
    $stmt = $db->prepare("INSERT INTO analyses (user_id, session_id, pestel_data) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $sessionId, json_encode(getEmptyPestel())]);
    $stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$user['id'], $sessionId]);
    $analyse = $stmt->fetch();
}

$pestelData = json_decode($analyse['pestel_data'], true) ?: getEmptyPestel();
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;

// Fonction helper pour PESTEL vide
function getEmptyPestel() {
    return [
        'politique' => [''],
        'economique' => [''],
        'socioculturel' => [''],
        'technologique' => [''],
        'environnemental' => [''],
        'legal' => ['']
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('pestel.title') ?> - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .pestel-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        @media (max-width: 768px) { .pestel-grid { grid-template-columns: 1fr; } }
        @media print { .no-print { display: none !important; } }
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
                <button onclick="manualSave()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">
                    <?= t('common.save') ?>
                </button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-gray-200">
                    <?= $isSubmitted ? t('app.submitted') : t('app.draft') ?>
                </span>
                <span id="completion" class="text-sm text-gray-600"><?= t('app.completion') ?>: <strong>0%</strong></span>
                <a href="logout.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition"><?= t('auth.logout') ?></a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto bg-white rounded-lg shadow-2xl p-6 md:p-8">
        <div class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2"><?= t('pestel.title') ?></h1>
            <p class="text-gray-600 italic"><?= t('pestel.subtitle') ?></p>
        </div>

        <!-- Introduction methodologique -->
        <div class="mb-6 bg-gradient-to-r from-blue-50 via-teal-50 to-green-50 p-6 rounded-lg border-2 border-teal-200 shadow-md">
            <h2 class="text-xl font-bold text-teal-800 mb-3 flex items-center">
                <span class="text-2xl mr-2">📚</span> <?= t('pestel.what_is') ?>
            </h2>
            <div class="space-y-4 text-gray-700">
                <p class="leading-relaxed">
                    <?= t('pestel.intro') ?>
                </p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-red-50 border-l-4 border-red-500 p-3 rounded">
                        <p class="font-semibold text-red-800 mb-1">🏛️ <?= t('pestel.political') ?></p>
                        <p class="text-sm text-red-700"><?= t('pestel.political_help') ?></p>
                    </div>
                    <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded">
                        <p class="font-semibold text-green-800 mb-1">💰 <?= t('pestel.economic') ?></p>
                        <p class="text-sm text-green-700"><?= t('pestel.economic_help') ?></p>
                    </div>
                    <div class="bg-purple-50 border-l-4 border-purple-500 p-3 rounded">
                        <p class="font-semibold text-purple-800 mb-1">👥 <?= t('pestel.sociocultural') ?></p>
                        <p class="text-sm text-purple-700"><?= t('pestel.sociocultural_help') ?></p>
                    </div>
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                        <p class="font-semibold text-blue-800 mb-1">🔬 <?= t('pestel.technological') ?></p>
                        <p class="text-sm text-blue-700"><?= t('pestel.technological_help') ?></p>
                    </div>
                    <div class="bg-teal-50 border-l-4 border-teal-500 p-3 rounded">
                        <p class="font-semibold text-teal-800 mb-1">🌱 <?= t('pestel.environmental') ?></p>
                        <p class="text-sm text-teal-700"><?= t('pestel.environmental_help') ?></p>
                    </div>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded">
                        <p class="font-semibold text-amber-800 mb-1">⚖️ <?= t('pestel.legal') ?></p>
                        <p class="text-sm text-amber-700"><?= t('pestel.legal_help') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations du projet -->
        <div class="mb-6 bg-gradient-to-r from-purple-50 to-indigo-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                📋 <?= t('pestel.project_name') ?>
            </label>
            <input type="text" id="nomProjet"
                class="w-full px-4 py-2 border-2 border-purple-200 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                placeholder="<?= t('pestel.project_placeholder') ?>"
                value="<?= h($analyse['titre_projet'] ?? '') ?>"
                oninput="scheduleAutoSave()">
        </div>

        <div class="mb-6 bg-gradient-to-r from-indigo-50 to-blue-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                👥 <?= t('pestel.analysis_participants') ?>
            </label>
            <input type="text" id="participantsAnalyse"
                class="w-full px-4 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                placeholder="<?= t('app.participants_placeholder') ?>"
                value="<?= h($pestelData['participants_analyse'] ?? '') ?>"
                oninput="scheduleAutoSave()">
        </div>

        <div class="mb-6 bg-gradient-to-r from-blue-50 to-teal-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                🎯 <?= t('pestel.geo_zone') ?>
            </label>
            <input type="text" id="zone"
                class="w-full px-4 py-2 border-2 border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="<?= t('pestel.geo_placeholder') ?>"
                value="<?= h($pestelData['zone'] ?? '') ?>"
                oninput="scheduleAutoSave()">
        </div>

        <!-- Matrice PESTEL -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">🌍 <?= t('pestel.factors_analysis') ?></h2>

            <div class="pestel-grid">
                <!-- POLITIQUE -->
                <div class="bg-red-50 p-5 rounded-lg border-2 border-red-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-red-800 flex items-center">
                            <span class="text-2xl mr-2">🏛️</span> <?= t('pestel.political') ?>
                        </h3>
                        <button type="button" onclick="addItem('politique', 'red')"
                            class="no-print bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-red-700 mb-3 italic"><?= t('pestel.political_desc') ?></p>
                    <div id="politiqueList" class="space-y-2">
                        <?php foreach ($pestelData['politique'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="politique">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-red-300 rounded-md focus:ring-2 focus:ring-red-500 text-sm resize-none"
                                placeholder="Ex: Nouvelle politique de soutien aux energies renouvelables..."
                                oninput="scheduleAutoSave()"><?= h($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-red-700 hover:text-red-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ECONOMIQUE -->
                <div class="bg-green-50 p-5 rounded-lg border-2 border-green-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-green-800 flex items-center">
                            <span class="text-2xl mr-2">💰</span> <?= t('pestel.economic') ?>
                        </h3>
                        <button type="button" onclick="addItem('economique', 'green')"
                            class="no-print bg-green-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-green-700 mb-3 italic"><?= t('pestel.economic_desc') ?></p>
                    <div id="economiqueList" class="space-y-2">
                        <?php foreach ($pestelData['economique'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="economique">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-green-300 rounded-md focus:ring-2 focus:ring-green-500 text-sm resize-none"
                                placeholder="Ex: Ralentissement economique reduisant les budgets disponibles..."
                                oninput="scheduleAutoSave()"><?= h($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-green-700 hover:text-green-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SOCIOCULTUREL -->
                <div class="bg-purple-50 p-5 rounded-lg border-2 border-purple-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-purple-800 flex items-center">
                            <span class="text-2xl mr-2">👥</span> <?= t('pestel.sociocultural') ?>
                        </h3>
                        <button type="button" onclick="addItem('socioculturel', 'purple')"
                            class="no-print bg-purple-600 text-white px-3 py-1 rounded-md hover:bg-purple-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-purple-700 mb-3 italic"><?= t('pestel.sociocultural_desc') ?></p>
                    <div id="socioculturelList" class="space-y-2">
                        <?php foreach ($pestelData['socioculturel'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="socioculturel">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-purple-300 rounded-md focus:ring-2 focus:ring-purple-500 text-sm resize-none"
                                placeholder="Ex: Vieillissement de la population augmentant la demande..."
                                oninput="scheduleAutoSave()"><?= h($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-purple-700 hover:text-purple-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- TECHNOLOGIQUE -->
                <div class="bg-blue-50 p-5 rounded-lg border-2 border-blue-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-blue-800 flex items-center">
                            <span class="text-2xl mr-2">🔬</span> <?= t('pestel.technological') ?>
                        </h3>
                        <button type="button" onclick="addItem('technologique', 'blue')"
                            class="no-print bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-blue-700 mb-3 italic"><?= t('pestel.technological_desc') ?></p>
                    <div id="technologiqueList" class="space-y-2">
                        <?php foreach ($pestelData['technologique'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="technologique">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-blue-300 rounded-md focus:ring-2 focus:ring-blue-500 text-sm resize-none"
                                placeholder="Ex: Developpement de l'IA transformant les pratiques..."
                                oninput="scheduleAutoSave()"><?= h($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-blue-700 hover:text-blue-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ENVIRONNEMENTAL -->
                <div class="bg-teal-50 p-5 rounded-lg border-2 border-teal-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-teal-800 flex items-center">
                            <span class="text-2xl mr-2">🌱</span> <?= t('pestel.environmental') ?>
                        </h3>
                        <button type="button" onclick="addItem('environnemental', 'teal')"
                            class="no-print bg-teal-600 text-white px-3 py-1 rounded-md hover:bg-teal-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-teal-700 mb-3 italic"><?= t('pestel.environmental_desc') ?></p>
                    <div id="environnementalList" class="space-y-2">
                        <?php foreach ($pestelData['environnemental'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="environnemental">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-teal-300 rounded-md focus:ring-2 focus:ring-teal-500 text-sm resize-none"
                                placeholder="Ex: Pression croissante pour reduire l'empreinte carbone..."
                                oninput="scheduleAutoSave()"><?= h($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-teal-700 hover:text-teal-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- LEGAL -->
                <div class="bg-amber-50 p-5 rounded-lg border-2 border-amber-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-amber-800 flex items-center">
                            <span class="text-2xl mr-2">⚖️</span> <?= t('pestel.legal') ?>
                        </h3>
                        <button type="button" onclick="addItem('legal', 'amber')"
                            class="no-print bg-amber-600 text-white px-3 py-1 rounded-md hover:bg-amber-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-amber-700 mb-3 italic"><?= t('pestel.legal_desc') ?></p>
                    <div id="legalList" class="space-y-2">
                        <?php foreach ($pestelData['legal'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="legal">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-amber-300 rounded-md focus:ring-2 focus:ring-amber-500 text-sm resize-none"
                                placeholder="Ex: Nouvelle reglementation RGPD sur la protection des donnees..."
                                oninput="scheduleAutoSave()"><?= h($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-amber-700 hover:text-amber-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Synthese -->
        <div class="mb-6 bg-gradient-to-r from-indigo-50 to-purple-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                🎯 <?= t('pestel.synthesis') ?>
            </label>
            <p class="text-sm text-gray-600 mb-3 italic">
                <?= t('pestel.synthesis_help') ?>
            </p>
            <textarea id="synthese" rows="5"
                class="w-full px-4 py-2 border-2 border-indigo-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                placeholder="<?= t('pestel.synthesis_placeholder') ?>"
                oninput="scheduleAutoSave()"><?= h($pestelData['synthese'] ?? '') ?></textarea>
        </div>

        <!-- Notes -->
        <div class="mb-6 bg-gray-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                ✏️ <?= t('app.notes') ?>
            </label>
            <textarea id="notes" rows="3"
                class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500"
                placeholder="<?= t('app.notes_placeholder') ?>"
                oninput="scheduleAutoSave()"><?= h($pestelData['notes'] ?? '') ?></textarea>
        </div>

        <!-- Boutons d'action -->
        <div class="no-print flex flex-wrap gap-3 pt-4 border-t-2 border-gray-200">
            <button type="button" onclick="submitAnalyse()"
                class="bg-purple-600 text-white px-6 py-3 rounded-md hover:bg-purple-700 transition font-semibold shadow-md">
                ✅ <?= t('common.submit') ?>
            </button>
            <button type="button" onclick="exportToExcel()"
                class="bg-emerald-600 text-white px-6 py-3 rounded-md hover:bg-emerald-700 transition font-semibold shadow-md">
                📊 <?= t('app.export_excel') ?>
            </button>
            <button type="button" onclick="exportToWord()"
                class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition font-semibold shadow-md">
                📄 <?= t('app.export_word') ?>
            </button>
            <button type="button" onclick="exportJSON()"
                class="bg-gray-500 text-white px-6 py-3 rounded-md hover:bg-gray-600 transition font-semibold shadow-md">
                📥 JSON
            </button>
            <button type="button" onclick="window.print()"
                class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition font-semibold shadow-md">
                🖨️ <?= t('common.print') ?>
            </button>
        </div>
    </div>

    <?= renderLanguageScript() ?>
    <script>
        // Traductions JavaScript
        const jsTranslations = {
            saving: '<?= t('app.saving') ?>',
            saved: '<?= t('app.saved') ?>',
            error: '<?= t('common.error') ?>',
            networkError: '<?= t('app.network_error') ?>',
            submitted: '<?= t('app.submitted') ?>',
            completion: '<?= t('app.completion') ?>',
            confirmSubmit: '<?= t('app.confirm_submit_analysis') ?>',
            submitSuccess: '<?= t('app.submit_success') ?>',
            keepOneMinimum: '<?= t('app.keep_one_minimum') ?>',
            newItem: '<?= t('app.new_item') ?>'
        };

        let autoSaveTimeout = null;

        function addItem(category, color) {
            const list = document.getElementById(category + 'List');
            const item = document.createElement('div');
            item.className = 'pestel-item flex gap-2';
            item.dataset.category = category;
            item.innerHTML = `
                <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-${color}-300 rounded-md focus:ring-2 focus:ring-${color}-500 text-sm resize-none"
                    placeholder="${jsTranslations.newItem}"
                    oninput="scheduleAutoSave()"></textarea>
                <button type="button" onclick="removeItem(this)" class="no-print text-${color}-700 hover:text-${color}-900 px-2">❌</button>
            `;
            list.appendChild(item);
            scheduleAutoSave();
        }

        function removeItem(button) {
            const parent = button.parentElement;
            const grandParent = parent.parentElement;
            if (grandParent.children.length > 1) {
                parent.remove();
                scheduleAutoSave();
            } else {
                alert(jsTranslations.keepOneMinimum);
            }
        }

        function getCategoryValues(category) {
            const items = document.querySelectorAll(`[data-category="${category}"] textarea`);
            const values = [];
            items.forEach(textarea => {
                values.push(textarea.value);
            });
            return values;
        }

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            document.getElementById('saveStatus').textContent = jsTranslations.saving;
            document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-400 text-yellow-900';
            autoSaveTimeout = setTimeout(saveData, 1000);
        }

        async function saveData() {
            const payload = {
                nom_projet: document.getElementById('nomProjet').value,
                participants_analyse: document.getElementById('participantsAnalyse').value,
                zone: document.getElementById('zone').value,
                pestel_data: {
                    politique: getCategoryValues('politique'),
                    economique: getCategoryValues('economique'),
                    socioculturel: getCategoryValues('socioculturel'),
                    technologique: getCategoryValues('technologique'),
                    environnemental: getCategoryValues('environnemental'),
                    legal: getCategoryValues('legal')
                },
                synthese: document.getElementById('synthese').value,
                notes: document.getElementById('notes').value
            };

            try {
                const response = await fetch('api/save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('saveStatus').textContent = jsTranslations.saved;
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                    document.getElementById('completion').innerHTML = jsTranslations.completion + ': <strong>' + result.completion + '%</strong>';
                } else {
                    document.getElementById('saveStatus').textContent = jsTranslations.error;
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
                }
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('saveStatus').textContent = jsTranslations.networkError;
            }
        }

        async function manualSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            await saveData();
        }

        async function submitAnalyse() {
            if (!confirm(jsTranslations.confirmSubmit)) return;
            await saveData();

            try {
                const response = await fetch('api/submit.php', { method: 'POST' });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('saveStatus').textContent = jsTranslations.submitted;
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-purple-500 text-white';
                    alert(jsTranslations.submitSuccess);
                } else {
                    alert(result.error || 'Erreur lors de la soumission');
                }
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        function exportJSON() {
            const data = {
                nom_projet: document.getElementById('nomProjet').value,
                participants: document.getElementById('participantsAnalyse').value,
                zone: document.getElementById('zone').value,
                pestel: {
                    politique: getCategoryValues('politique'),
                    economique: getCategoryValues('economique'),
                    socioculturel: getCategoryValues('socioculturel'),
                    technologique: getCategoryValues('technologique'),
                    environnemental: getCategoryValues('environnemental'),
                    legal: getCategoryValues('legal')
                },
                synthese: document.getElementById('synthese').value,
                notes: document.getElementById('notes').value,
                dateExport: new Date().toISOString()
            };

            const dataStr = JSON.stringify(data, null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            const nom = data.nom_projet ? data.nom_projet.replace(/[^a-z0-9]/gi, '_').toLowerCase() : 'pestel';
            link.download = `pestel_${nom}_${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            URL.revokeObjectURL(url);
        }

        function exportToExcel() {
            const wb = XLSX.utils.book_new();
            const nomProjet = document.getElementById('nomProjet').value || 'Analyse PESTEL';

            // Feuille 1: Informations
            const infoData = [
                ['ANALYSE PESTEL'],
                [''],
                ['Projet / Organisation', document.getElementById('nomProjet').value],
                ['Participants', document.getElementById('participantsAnalyse').value],
                ['Zone geographique', document.getElementById('zone').value],
                ['Date d\'export', new Date().toLocaleDateString('fr-FR')]
            ];
            const wsInfo = XLSX.utils.aoa_to_sheet(infoData);
            wsInfo['!cols'] = [{ wch: 25 }, { wch: 60 }];

            // Feuille 2: Analyse PESTEL
            const pestelData = [
                ['ANALYSE DES FACTEURS PESTEL'],
                [''],
                ['POLITIQUE']
            ];
            getCategoryValues('politique').forEach((item, i) => {
                if (item.trim()) pestelData.push([`${i + 1}.`, item]);
            });

            pestelData.push([''], ['ECONOMIQUE']);
            getCategoryValues('economique').forEach((item, i) => {
                if (item.trim()) pestelData.push([`${i + 1}.`, item]);
            });

            pestelData.push([''], ['SOCIOCULTUREL']);
            getCategoryValues('socioculturel').forEach((item, i) => {
                if (item.trim()) pestelData.push([`${i + 1}.`, item]);
            });

            pestelData.push([''], ['TECHNOLOGIQUE']);
            getCategoryValues('technologique').forEach((item, i) => {
                if (item.trim()) pestelData.push([`${i + 1}.`, item]);
            });

            pestelData.push([''], ['ENVIRONNEMENTAL']);
            getCategoryValues('environnemental').forEach((item, i) => {
                if (item.trim()) pestelData.push([`${i + 1}.`, item]);
            });

            pestelData.push([''], ['LEGAL']);
            getCategoryValues('legal').forEach((item, i) => {
                if (item.trim()) pestelData.push([`${i + 1}.`, item]);
            });

            const wsPestel = XLSX.utils.aoa_to_sheet(pestelData);
            wsPestel['!cols'] = [{ wch: 5 }, { wch: 80 }];

            // Feuille 3: Synthese
            const syntheseData = [
                ['SYNTHESE : FACTEURS CLES & IMPLICATIONS'],
                [''],
                [document.getElementById('synthese').value],
                [''],
                ['NOTES COMPLEMENTAIRES'],
                [''],
                [document.getElementById('notes').value]
            ];
            const wsSynthese = XLSX.utils.aoa_to_sheet(syntheseData);
            wsSynthese['!cols'] = [{ wch: 100 }];

            // Ajouter les feuilles
            XLSX.utils.book_append_sheet(wb, wsInfo, 'Informations');
            XLSX.utils.book_append_sheet(wb, wsPestel, 'Analyse PESTEL');
            XLSX.utils.book_append_sheet(wb, wsSynthese, 'Synthese');

            // Telecharger
            const filename = `pestel_${nomProjet.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, filename);
        }

        function exportToWord() {
            const nomProjet = document.getElementById('nomProjet').value || 'Analyse PESTEL';
            const participants = document.getElementById('participantsAnalyse').value;
            const zone = document.getElementById('zone').value;
            const synthese = document.getElementById('synthese').value;
            const notes = document.getElementById('notes').value;

            const categories = [
                { key: 'politique', label: 'POLITIQUE', color: '#DC2626' },
                { key: 'economique', label: 'ECONOMIQUE', color: '#16A34A' },
                { key: 'socioculturel', label: 'SOCIOCULTUREL', color: '#9333EA' },
                { key: 'technologique', label: 'TECHNOLOGIQUE', color: '#2563EB' },
                { key: 'environnemental', label: 'ENVIRONNEMENTAL', color: '#0D9488' },
                { key: 'legal', label: 'LEGAL', color: '#D97706' }
            ];

            let pestelHtml = '';
            categories.forEach(cat => {
                const items = getCategoryValues(cat.key).filter(item => item.trim());
                pestelHtml += `
                    <h3 style="color: ${cat.color}; margin-top: 20px;">${cat.label}</h3>
                    <ul>
                        ${items.length > 0 ? items.map(item => `<li>${item}</li>`).join('') : '<li><em>Aucun element</em></li>'}
                    </ul>
                `;
            });

            const html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Analyse PESTEL - ${nomProjet}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
                        h1 { color: #4F46E5; border-bottom: 2px solid #4F46E5; padding-bottom: 10px; }
                        h2 { color: #374151; margin-top: 30px; }
                        h3 { margin-bottom: 10px; }
                        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
                        td, th { border: 1px solid #ddd; padding: 10px; text-align: left; }
                        th { background-color: #f3f4f6; }
                        ul { margin: 10px 0; }
                        li { margin: 5px 0; }
                        .synthese { background-color: #EEF2FF; padding: 15px; border-radius: 8px; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <h1>Analyse PESTEL</h1>

                    <table>
                        <tr><th>Projet / Organisation</th><td>${nomProjet}</td></tr>
                        <tr><th>Participants</th><td>${participants}</td></tr>
                        <tr><th>Zone geographique</th><td>${zone}</td></tr>
                        <tr><th>Date</th><td>${new Date().toLocaleDateString('fr-FR')}</td></tr>
                    </table>

                    <h2>Analyse des Facteurs PESTEL</h2>
                    ${pestelHtml}

                    <div class="synthese">
                        <h2>Synthese : Facteurs Cles & Implications</h2>
                        <p>${synthese.replace(/\n/g, '<br>') || '<em>Non renseigne</em>'}</p>
                    </div>

                    ${notes ? `
                    <h2>Notes Complementaires</h2>
                    <p>${notes.replace(/\n/g, '<br>')}</p>
                    ` : ''}
                </body>
                </html>
            `;

            const blob = new Blob([html], { type: 'application/msword' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `pestel_${nomProjet.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_${new Date().toISOString().split('T')[0]}.doc`;
            link.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
