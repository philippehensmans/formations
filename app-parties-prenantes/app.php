<?php
/**
 * Interface de travail - Cartographie des Parties Prenantes
 */
require_once 'config/database.php';
requireParticipant();

$db = getDB();
$participant = getCurrentParticipant();

// Verifier que le participant existe
if (!$participant) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$categories = getCategories();

// Charger la cartographie
$stmt = $db->prepare("SELECT * FROM cartographie WHERE participant_id = ?");
$stmt->execute([$participant['id']]);
$carto = $stmt->fetch();

if (!$carto) {
    $stmt = $db->prepare("INSERT INTO cartographie (participant_id, session_id, stakeholders_data) VALUES (?, ?, '[]')");
    $stmt->execute([$participant['id'], $participant['session_id']]);
    $stmt = $db->prepare("SELECT * FROM cartographie WHERE participant_id = ?");
    $stmt->execute([$participant['id']]);
    $carto = $stmt->fetch();
}

$stakeholders = json_decode($carto['stakeholders_data'], true) ?: [];
$isSubmitted = $carto['is_submitted'] == 1;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('stakeholders.title') ?> - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --clr-primary: #000;
            --clr-accent: #1e3a8a;
        }
        .matrix {
            position: relative;
            width: 100%;
            height: 400px;
            border: 2px solid var(--clr-primary);
            border-radius: 4px;
            background: #f5f5f5;
        }
        .matrix-lines::before, .matrix-lines::after {
            content: '';
            position: absolute;
            background: var(--clr-primary);
        }
        .matrix-lines::before { left: 50%; top: 0; bottom: 0; width: 2px; transform: translateX(-50%); }
        .matrix-lines::after { top: 50%; left: 0; right: 0; height: 2px; transform: translateY(-50%); }
        .quadrant-label {
            position: absolute;
            font-weight: 700;
            color: #666;
            font-size: 0.7rem;
            background: rgba(255,255,255,0.9);
            padding: 4px 8px;
            border-radius: 4px;
        }
        .q1 { top: 8px; right: 8px; }
        .q2 { top: 8px; left: 8px; }
        .q3 { bottom: 8px; left: 8px; }
        .q4 { bottom: 8px; right: 8px; }
        .stakeholder-dot {
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #fff;
            cursor: pointer;
            transform: translate(-50%, -50%);
            transition: transform 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .stakeholder-dot:hover { transform: translate(-50%, -50%) scale(1.3); }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<?= renderLanguageScript() ?>
<body class="bg-gray-100 min-h-screen">
    <!-- Barre utilisateur -->
    <div class="bg-gray-900 text-white p-3 no-print sticky top-0 z-50">
        <div class="max-w-6xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></span>
                <span class="text-gray-400 text-sm ml-2"><?= sanitize($participant['session_nom']) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('text-sm bg-gray-700 text-white px-2 py-1 rounded border border-gray-600') ?>
                <button onclick="manualSave()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-medium transition">
                    <?= t('common.save') ?>
                </button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-gray-700">
                    <?= $isSubmitted ? t('app.submitted') : t('app.draft') ?>
                </span>
                <span id="completion" class="text-sm"><?= t('app.completion') ?>: <strong><?= $carto['completion_percent'] ?>%</strong></span>
                <a href="logout.php" class="text-sm bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded transition"><?= t('auth.logout') ?></a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto p-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="text-center p-6 border-b">
                <h1 class="text-3xl font-bold text-gray-800 mb-2"><?= t('stakeholders.title') ?></h1>
                <p class="text-gray-600"><?= t('stakeholders.subtitle') ?></p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-0">
                <!-- Sidebar -->
                <div class="bg-gray-50 p-6 border-r">
                    <h3 class="text-lg font-bold mb-4"><?= t('stakeholders.add_stakeholder') ?></h3>

                    <div class="mb-4">
                        <label class="block text-gray-700 font-semibold mb-1"><?= t('stakeholders.project_title') ?></label>
                        <input type="text" id="titreProjet"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:border-blue-900 focus:outline-none"
                            placeholder="Ex: Projet associatif XYZ"
                            value="<?= sanitize($carto['titre_projet']) ?>"
                            onchange="scheduleAutoSave()">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 font-semibold mb-1"><?= t('stakeholders.name_org') ?></label>
                        <input type="text" id="stakeholderName"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:border-blue-900 focus:outline-none"
                            placeholder="Ex: Commune de Liege">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 font-semibold mb-1"><?= t('stakeholders.category') ?></label>
                        <select id="stakeholderCategory"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:border-blue-900 focus:outline-none">
                            <?php foreach ($categories as $key => $cat): ?>
                                <option value="<?= $key ?>"><?= $cat['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between mb-1">
                            <label class="font-semibold"><?= t('stakeholders.influence') ?></label>
                            <span id="influenceValue" class="font-bold text-blue-900">5</span>
                        </div>
                        <input type="range" id="influenceSlider" min="1" max="10" value="5"
                            class="w-full h-2 bg-gray-300 rounded-lg appearance-none cursor-pointer"
                            oninput="document.getElementById('influenceValue').textContent = this.value">
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between mb-1">
                            <label class="font-semibold"><?= t('stakeholders.interest') ?></label>
                            <span id="interestValue" class="font-bold text-blue-900">5</span>
                        </div>
                        <input type="range" id="interestSlider" min="1" max="10" value="5"
                            class="w-full h-2 bg-gray-300 rounded-lg appearance-none cursor-pointer"
                            oninput="document.getElementById('interestValue').textContent = this.value">
                    </div>

                    <button onclick="addStakeholder()" class="w-full bg-blue-900 text-white py-2 rounded font-semibold hover:bg-blue-800 transition mb-2">
                        <?= t('stakeholders.add') ?>
                    </button>

                    <!-- Legende -->
                    <div class="mt-6 p-4 bg-white rounded border">
                        <h4 class="font-bold mb-3"><?= t('stakeholders.legend') ?></h4>
                        <?php foreach ($categories as $key => $cat): ?>
                            <div class="flex items-center mb-2 text-sm">
                                <span class="w-3 h-3 rounded-full mr-2" style="background: <?= $cat['color'] ?>"></span>
                                <?= $cat['label'] ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Zone matrice -->
                <div class="lg:col-span-3 p-6">
                    <h2 class="text-xl font-bold mb-4"><?= t('stakeholders.matrix_title') ?></h2>

                    <div class="matrix" id="stakeholderMatrix">
                        <div class="matrix-lines"></div>
                        <div class="absolute bottom-[-24px] left-1/2 transform -translate-x-1/2 font-bold text-sm"><?= t('stakeholders.interest') ?> →</div>
                        <div class="absolute left-[-40px] top-1/2 transform -translate-y-1/2 -rotate-90 font-bold text-sm"><?= t('stakeholders.influence') ?> ↑</div>
                        <div class="quadrant-label q1"><strong><?= t('stakeholders.manage_closely') ?></strong><br><?= t('stakeholders.manage_closely_desc') ?></div>
                        <div class="quadrant-label q2"><strong><?= t('stakeholders.keep_satisfied') ?></strong><br><?= t('stakeholders.keep_satisfied_desc') ?></div>
                        <div class="quadrant-label q3"><strong><?= t('stakeholders.monitor') ?></strong><br><?= t('stakeholders.monitor_desc') ?></div>
                        <div class="quadrant-label q4"><strong><?= t('stakeholders.keep_informed') ?></strong><br><?= t('stakeholders.keep_informed_desc') ?></div>
                        <div id="stakeholdersContainer"></div>
                    </div>

                    <!-- Liste des parties prenantes -->
                    <div class="mt-8 bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-bold mb-4"><?= t('stakeholders.stakeholders_list') ?></h3>
                        <div id="stakeholdersList">
                            <p class="text-gray-500 text-center"><?= t('stakeholders.no_stakeholder') ?></p>
                        </div>
                    </div>

                    <!-- Strategies -->
                    <div id="strategiesSection" class="mt-6 bg-blue-50 rounded-lg p-4" style="display: none;">
                        <h3 class="text-lg font-bold mb-4"><?= t('stakeholders.strategies') ?></h3>
                        <div id="strategiesContent"></div>
                    </div>

                    <!-- Notes -->
                    <div class="mt-6">
                        <label class="block text-gray-700 font-semibold mb-2"><?= t('app.notes') ?></label>
                        <textarea id="notes" rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded focus:border-blue-900 focus:outline-none"
                            placeholder="<?= t('app.notes_placeholder') ?>"
                            onchange="scheduleAutoSave()"><?= sanitize($carto['notes']) ?></textarea>
                    </div>

                    <!-- Boutons export -->
                    <div class="mt-6 flex flex-wrap gap-3 no-print">
                        <button onclick="submitCarto()" class="bg-purple-600 text-white px-6 py-3 rounded font-semibold hover:bg-purple-700 transition">
                            ✅ <?= t('app.mark_complete') ?>
                        </button>
                        <button onclick="exportToExcel()" class="bg-emerald-600 text-white px-6 py-3 rounded font-semibold hover:bg-emerald-700 transition">
                            📊 Excel
                        </button>
                        <button onclick="exportToWord()" class="bg-blue-600 text-white px-6 py-3 rounded font-semibold hover:bg-blue-700 transition">
                            📄 Word
                        </button>
                        <button onclick="exportJSON()" class="bg-gray-500 text-white px-6 py-3 rounded font-semibold hover:bg-gray-600 transition">
                            📥 JSON
                        </button>
                        <button onclick="window.print()" class="bg-gray-600 text-white px-6 py-3 rounded font-semibold hover:bg-gray-700 transition">
                            🖨️ <?= t('common.print') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Traductions JavaScript
        const jsTranslations = {
            saving: '<?= t('app.saving') ?>',
            saved: '<?= t('app.saved') ?>',
            error: '<?= t('common.error') ?>',
            networkError: '<?= t('app.network_error') ?>',
            submitted: '<?= t('app.submitted') ?>',
            completion: '<?= t('app.completion') ?>',
            markedComplete: '<?= t('app.marked_complete') ?>',
            noStakeholder: '<?= t('stakeholders.no_stakeholder') ?>',
            confirmSubmit: '<?= t('app.submit_confirm') ?>',
            confirmDelete: '<?= t('stakeholders.remove_confirm') ?>',
            enterName: '<?= t('stakeholders.name_required') ?>',
            alreadyExists: '<?= t('stakeholders.already_exists') ?>',
            delete: '<?= t('common.delete') ?>',
            manageClosely: '<?= t('stakeholders.manage_closely') ?>',
            keepSatisfied: '<?= t('stakeholders.keep_satisfied') ?>',
            monitor: '<?= t('stakeholders.monitor') ?>',
            keepInformed: '<?= t('stakeholders.keep_informed') ?>',
            strategyManage: '<?= t('stakeholders.strategy_manage') ?>',
            strategySatisfied: '<?= t('stakeholders.strategy_satisfied') ?>',
            strategyMonitor: '<?= t('stakeholders.strategy_monitor') ?>',
            strategyInformed: '<?= t('stakeholders.strategy_informed') ?>',
            concerned: '<?= t('stakeholders.concerned') ?>'
        };

        const categoryColors = <?= json_encode(array_map(fn($c) => $c['color'], $categories)) ?>;
        const categoryLabels = <?= json_encode(array_map(fn($c) => $c['label'], $categories)) ?>;
        let stakeholders = <?= json_encode($stakeholders) ?>;
        let autoSaveTimeout = null;

        // Init
        updateMatrix();
        updateStakeholdersList();
        updateStrategies();

        function addStakeholder() {
            const name = document.getElementById('stakeholderName').value.trim();
            const category = document.getElementById('stakeholderCategory').value;
            const influence = parseInt(document.getElementById('influenceSlider').value);
            const interest = parseInt(document.getElementById('interestSlider').value);

            if (!name) {
                alert(jsTranslations.enterName);
                return;
            }

            if (stakeholders.some(s => s.name.toLowerCase() === name.toLowerCase())) {
                alert(jsTranslations.alreadyExists);
                return;
            }

            stakeholders.push({
                id: Date.now(),
                name: name,
                category: category,
                influence: influence,
                interest: interest
            });

            document.getElementById('stakeholderName').value = '';
            document.getElementById('influenceSlider').value = 5;
            document.getElementById('interestSlider').value = 5;
            document.getElementById('influenceValue').textContent = '5';
            document.getElementById('interestValue').textContent = '5';

            updateMatrix();
            updateStakeholdersList();
            updateStrategies();
            scheduleAutoSave();
        }

        function updateMatrix() {
            const cont = document.getElementById('stakeholdersContainer');
            cont.innerHTML = '';

            stakeholders.forEach(s => {
                const dot = document.createElement('div');
                dot.className = 'stakeholder-dot';
                dot.style.backgroundColor = categoryColors[s.category];

                const x = ((s.interest - 1) / 9) * 100;
                const y = ((10 - s.influence) / 9) * 100;
                dot.style.left = x + '%';
                dot.style.top = y + '%';

                dot.title = `${s.name} - ${categoryLabels[s.category]}\nInfluence: ${s.influence}/10, Interet: ${s.interest}/10`;
                dot.onclick = () => removeStakeholder(s.id);

                cont.appendChild(dot);
            });
        }

        function updateStakeholdersList() {
            const c = document.getElementById('stakeholdersList');

            if (!stakeholders.length) {
                c.innerHTML = '<p class="text-gray-500 text-center">' + jsTranslations.noStakeholder + '</p>';
                return;
            }

            c.innerHTML = '';
            stakeholders.forEach(s => {
                const q = getQuadrant(s.influence, s.interest);
                const item = document.createElement('div');
                item.className = 'flex justify-between items-center p-3 bg-white rounded mb-2 border-l-4';
                item.style.borderLeftColor = categoryColors[s.category];
                item.innerHTML = `
                    <div>
                        <strong>${s.name}</strong><br>
                        <small class="text-gray-500">${categoryLabels[s.category]} - ${q}</small>
                    </div>
                    <div class="text-right">
                        <small>I: ${s.influence}/10, Int: ${s.interest}/10</small><br>
                        <button onclick="removeStakeholder(${s.id})" class="text-red-600 hover:text-red-800 text-sm">${jsTranslations.delete}</button>
                    </div>
                `;
                c.appendChild(item);
            });
        }

        function getQuadrant(influence, interest) {
            if (influence > 5 && interest > 5) return jsTranslations.manageClosely;
            if (influence > 5 && interest <= 5) return jsTranslations.keepSatisfied;
            if (influence <= 5 && interest <= 5) return jsTranslations.monitor;
            return jsTranslations.keepInformed;
        }

        function getStrategy(quadrant) {
            const strategies = {};
            strategies[jsTranslations.manageClosely] = jsTranslations.strategyManage;
            strategies[jsTranslations.keepSatisfied] = jsTranslations.strategySatisfied;
            strategies[jsTranslations.monitor] = jsTranslations.strategyMonitor;
            strategies[jsTranslations.keepInformed] = jsTranslations.strategyInformed;
            return strategies[quadrant] || '';
        }

        function updateStrategies() {
            const sec = document.getElementById('strategiesSection');
            const content = document.getElementById('strategiesContent');

            if (!stakeholders.length) {
                sec.style.display = 'none';
                return;
            }

            sec.style.display = 'block';
            const groups = {};

            stakeholders.forEach(s => {
                const q = getQuadrant(s.influence, s.interest);
                if (!groups[q]) groups[q] = [];
                groups[q].push(s);
            });

            content.innerHTML = '';
            Object.keys(groups).forEach(q => {
                const div = document.createElement('div');
                div.className = 'bg-white p-4 rounded mb-3 border-l-4 border-blue-900';
                div.innerHTML = `
                    <strong>${q}</strong> (${groups[q].length})<br>
                    <em class="text-gray-600">${getStrategy(q)}</em><br>
                    <small><strong>${jsTranslations.concerned}:</strong> ${groups[q].map(s => s.name).join(', ')}</small>
                `;
                content.appendChild(div);
            });
        }

        function removeStakeholder(id) {
            if (confirm(jsTranslations.confirmDelete)) {
                stakeholders = stakeholders.filter(s => s.id !== id);
                updateMatrix();
                updateStakeholdersList();
                updateStrategies();
                scheduleAutoSave();
            }
        }

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            document.getElementById('saveStatus').textContent = jsTranslations.saving;
            document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-500 text-yellow-900';
            autoSaveTimeout = setTimeout(saveData, 1000);
        }

        async function saveData() {
            const payload = {
                titre_projet: document.getElementById('titreProjet').value,
                stakeholders_data: stakeholders,
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

        async function submitCarto() {
            if (!confirm(jsTranslations.confirmSubmit)) return;
            await saveData();

            try {
                const response = await fetch('api/submit.php', { method: 'POST' });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('saveStatus').textContent = jsTranslations.submitted;
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-purple-500 text-white';
                    alert(jsTranslations.markedComplete);
                } else {
                    alert(result.error || jsTranslations.error);
                }
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        function exportJSON() {
            const data = {
                titre_projet: document.getElementById('titreProjet').value,
                stakeholders: stakeholders,
                notes: document.getElementById('notes').value,
                dateExport: new Date().toISOString()
            };

            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `parties_prenantes_${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            URL.revokeObjectURL(url);
        }

        function exportToExcel() {
            const wb = XLSX.utils.book_new();
            const titre = document.getElementById('titreProjet').value || 'Cartographie';

            // Feuille 1: Liste
            const listData = [
                ['CARTOGRAPHIE DES PARTIES PRENANTES'],
                [''],
                ['Projet', titre],
                ['Date', new Date().toLocaleDateString('fr-FR')],
                [''],
                ['Nom', 'Categorie', 'Influence', 'Interet', 'Quadrant', 'Strategie']
            ];

            stakeholders.forEach(s => {
                const q = getQuadrant(s.influence, s.interest);
                listData.push([s.name, categoryLabels[s.category], s.influence, s.interest, q, getStrategy(q)]);
            });

            const ws = XLSX.utils.aoa_to_sheet(listData);
            ws['!cols'] = [{ wch: 30 }, { wch: 15 }, { wch: 10 }, { wch: 10 }, { wch: 20 }, { wch: 40 }];

            XLSX.utils.book_append_sheet(wb, ws, 'Parties Prenantes');

            const filename = `parties_prenantes_${titre.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, filename);
        }

        function exportToWord() {
            const titre = document.getElementById('titreProjet').value || 'Cartographie';
            const notes = document.getElementById('notes').value;

            let tableRows = '';
            stakeholders.forEach(s => {
                const q = getQuadrant(s.influence, s.interest);
                tableRows += `
                    <tr>
                        <td style="border:1px solid #ddd;padding:8px;"><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:${categoryColors[s.category]};margin-right:8px;"></span>${s.name}</td>
                        <td style="border:1px solid #ddd;padding:8px;">${categoryLabels[s.category]}</td>
                        <td style="border:1px solid #ddd;padding:8px;text-align:center;">${s.influence}</td>
                        <td style="border:1px solid #ddd;padding:8px;text-align:center;">${s.interest}</td>
                        <td style="border:1px solid #ddd;padding:8px;">${q}</td>
                    </tr>
                `;
            });

            const html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Cartographie - ${titre}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
                        h1 { color: #1e3a8a; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
                        h2 { color: #374151; margin-top: 30px; }
                        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
                        th { background-color: #1e3a8a; color: white; padding: 10px; text-align: left; }
                    </style>
                </head>
                <body>
                    <h1>Cartographie des Parties Prenantes</h1>
                    <p><strong>Projet:</strong> ${titre}</p>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString('fr-FR')}</p>

                    <h2>Liste des Parties Prenantes (${stakeholders.length})</h2>
                    <table>
                        <tr>
                            <th>Nom</th>
                            <th>Categorie</th>
                            <th>Influence</th>
                            <th>Interet</th>
                            <th>Strategie</th>
                        </tr>
                        ${tableRows}
                    </table>

                    ${notes ? `<h2>Notes</h2><p>${notes.replace(/\n/g, '<br>')}</p>` : ''}
                </body>
                </html>
            `;

            const blob = new Blob([html], { type: 'application/msword' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `parties_prenantes_${titre.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_${new Date().toISOString().split('T')[0]}.doc`;
            link.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
