<?php
require_once 'config.php';
requireLoginWithSession();

$db = getDB();
$user = getLoggedUser();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$sessionId = validateCurrentSession($db);
if (!$sessionId) { header('Location: login.php'); exit; }
$sessionNom = $_SESSION['current_session_nom'] ?? '';

// Charger l'arbre existant
$stmt = $db->prepare("SELECT * FROM arbres WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$arbre = $stmt->fetch();

if (!$arbre) {
    $stmt = $db->prepare("INSERT INTO arbres (user_id, session_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $sessionId]);
    $stmt = $db->prepare("SELECT * FROM arbres WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$user['id'], $sessionId]);
    $arbre = $stmt->fetch();
}

$consequences = json_decode($arbre['consequences'] ?? '[]', true) ?: [];
$causes = json_decode($arbre['causes'] ?? '[]', true) ?: [];
$objectifs = json_decode($arbre['objectifs'] ?? '[]', true) ?: [];
$moyens = json_decode($arbre['moyens'] ?? '[]', true) ?: [];
$isShared = (bool)$arbre['is_shared'];
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('problemtree.title') ?> - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect x='12' y='2' width='8' height='6' fill='%23dc2626'/><rect x='4' y='12' width='8' height='6' fill='%23f59e0b'/><rect x='20' y='12' width='8' height='6' fill='%23f59e0b'/><rect x='12' y='24' width='8' height='6' fill='%2322c55e'/><line x1='16' y1='8' x2='16' y2='24' stroke='%23374151' stroke-width='2'/><line x1='8' y1='12' x2='16' y2='16' stroke='%23374151' stroke-width='2'/><line x1='24' y1='12' x2='16' y2='16' stroke='%23374151' stroke-width='2'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #92400e 100%); min-height: 100vh; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
        .tree-box { transition: transform 0.2s, box-shadow 0.2s; }
        .tree-box:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    </style>
</head>
<body class="p-4 md:p-8">
    <?= renderLanguageScript() ?>

    <!-- Header -->
    <header class="no-print max-w-6xl mx-auto mb-4">
        <div class="bg-amber-900 text-white rounded-lg p-4 flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="font-bold text-lg"><?= t('problemtree.title') ?></h1>
                <p class="text-amber-200 text-sm">
                    <?= h($user['prenom']) ?> <?= h($user['nom']) ?>
                    | Session: <?= h($sessionNom) ?>
                </p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <?= renderLanguageSelector('lang-select') ?>
                <label class="flex items-center gap-2 text-sm cursor-pointer bg-amber-800 px-3 py-2 rounded">
                    <input type="checkbox" id="shareToggle" <?= $isShared ? 'checked' : '' ?> onchange="toggleShare()">
                    <?= t('problemtree.share_trainer') ?>
                </label>
                <span id="saveStatus" class="text-sm text-amber-200"></span>
                <button onclick="manualSave()" class="bg-amber-700 hover:bg-amber-600 px-4 py-2 rounded text-sm">
                    <?= t('app.save') ?>
                </button>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="bg-amber-700 hover:bg-amber-600 px-4 py-2 rounded text-sm">
                    <?= t('trainer.title') ?>
                </a>
                <?php endif; ?>
                <?= renderHomeLink() ?>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">
                    <?= t('auth.logout') ?>
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto">
        <!-- Infos projet -->
        <div class="bg-white rounded-t-xl shadow-lg p-6 border-b-2 border-amber-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= t('problemtree.project_name') ?></label>
                    <input type="text" id="nomProjet" value="<?= h($arbre['nom_projet'] ?? '') ?>"
                           class="w-full px-4 py-2 border-2 border-amber-200 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="<?= t('problemtree.project_placeholder') ?>" oninput="scheduleSave()">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= t('problemtree.group_participants') ?></label>
                    <input type="text" id="participants" value="<?= h($arbre['participants'] ?? '') ?>"
                           class="w-full px-4 py-2 border-2 border-amber-200 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="<?= t('problemtree.participants_placeholder') ?>" oninput="scheduleSave()">
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white shadow-lg no-print">
            <div class="flex border-b-2 border-gray-200">
                <button id="tab-problemes" onclick="switchTab('problemes')"
                        class="flex-1 py-3 px-6 font-semibold text-center transition border-b-3 border-transparent text-red-700 border-red-500" style="border-bottom: 3px solid #dc2626;">
                    <?= t('problemtree.problems_tree') ?>
                </button>
                <button id="tab-solutions" onclick="switchTab('solutions')"
                        class="flex-1 py-3 px-6 font-semibold text-center transition border-b-3 border-transparent text-gray-400 hover:text-green-700">
                    <?= t('problemtree.solutions_tree') ?>
                </button>
            </div>
        </div>

        <!-- Tab Problemes -->
        <div id="panel-problemes" class="bg-white shadow-lg p-6 md:p-8">
            <!-- Consequences -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-red-700"><?= t('problemtree.consequences') ?></h3>
                        <p class="text-sm text-gray-500 italic"><?= t('problemtree.consequences_desc') ?></p>
                    </div>
                    <button onclick="addItem('consequences')" class="no-print bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                        + <?= t('common.add') ?>
                    </button>
                </div>
                <div id="list-consequences" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <!-- Filled by JS -->
                </div>
            </div>

            <!-- Fleches -->
            <div class="text-center my-4 text-3xl text-gray-300 select-none">&#8595; &#8595; &#8595;</div>

            <!-- Probleme central -->
            <div class="mb-8">
                <h3 class="text-lg font-bold text-orange-700 text-center mb-2"><?= t('problemtree.central_problem') ?></h3>
                <p class="text-sm text-gray-500 italic text-center mb-3"><?= t('problemtree.central_problem_question') ?></p>
                <div class="max-w-2xl mx-auto">
                    <textarea id="problemeCentral" rows="3"
                              class="w-full px-4 py-3 border-3 border-orange-400 rounded-xl text-center font-semibold text-lg focus:ring-2 focus:ring-orange-500 bg-orange-50"
                              placeholder="<?= t('problemtree.click_define_problem') ?>"
                              oninput="scheduleSave()"><?= h($arbre['probleme_central'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Fleches -->
            <div class="text-center my-4 text-3xl text-gray-300 select-none">&#8593; &#8593; &#8593;</div>

            <!-- Causes -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-amber-700"><?= t('problemtree.causes') ?></h3>
                        <p class="text-sm text-gray-500 italic"><?= t('problemtree.causes_desc') ?></p>
                    </div>
                    <button onclick="addItem('causes')" class="no-print bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                        + <?= t('common.add') ?>
                    </button>
                </div>
                <div id="list-causes" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <!-- Filled by JS -->
                </div>
            </div>
        </div>

        <!-- Tab Solutions (hidden by default) -->
        <div id="panel-solutions" class="bg-white shadow-lg p-6 md:p-8" style="display:none;">
            <!-- Objectifs -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-green-700"><?= t('problemtree.objectives') ?></h3>
                        <p class="text-sm text-gray-500 italic"><?= t('problemtree.objectives_desc') ?></p>
                    </div>
                    <button onclick="addItem('objectifs')" class="no-print bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                        + <?= t('common.add') ?>
                    </button>
                </div>
                <div id="list-objectifs" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <!-- Filled by JS -->
                </div>
            </div>

            <!-- Fleches -->
            <div class="text-center my-4 text-3xl text-gray-300 select-none">&#8595; &#8595; &#8595;</div>

            <!-- Objectif central -->
            <div class="mb-8">
                <h3 class="text-lg font-bold text-teal-700 text-center mb-2"><?= t('problemtree.central_objective') ?></h3>
                <p class="text-sm text-gray-500 italic text-center mb-3"><?= t('problemtree.central_objective_reformulation') ?></p>
                <div class="max-w-2xl mx-auto">
                    <textarea id="objectifCentral" rows="3"
                              class="w-full px-4 py-3 border-3 border-teal-400 rounded-xl text-center font-semibold text-lg focus:ring-2 focus:ring-teal-500 bg-teal-50"
                              placeholder="<?= t('problemtree.click_define_objective') ?>"
                              oninput="scheduleSave()"><?= h($arbre['objectif_central'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Fleches -->
            <div class="text-center my-4 text-3xl text-gray-300 select-none">&#8593; &#8593; &#8593;</div>

            <!-- Moyens -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-blue-700"><?= t('problemtree.means') ?></h3>
                        <p class="text-sm text-gray-500 italic"><?= t('problemtree.means_desc') ?></p>
                    </div>
                    <button onclick="addItem('moyens')" class="no-print bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                        + <?= t('common.add') ?>
                    </button>
                </div>
                <div id="list-moyens" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <!-- Filled by JS -->
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-b-xl shadow-lg p-6 border-t-2 border-gray-100 no-print">
            <div class="flex flex-wrap gap-3">
                <button onclick="window.print()" class="bg-amber-600 text-white px-6 py-3 rounded-lg hover:bg-amber-700 font-semibold shadow">
                    <?= t('common.print') ?> / PDF
                </button>
                <button onclick="exportToExcel()" class="bg-emerald-600 text-white px-6 py-3 rounded-lg hover:bg-emerald-700 font-semibold shadow">
                    Excel
                </button>
                <button onclick="exportJSON()" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 font-semibold shadow">
                    JSON
                </button>
                <button onclick="resetForm()" class="ml-auto bg-white text-red-600 border-2 border-red-300 px-6 py-3 rounded-lg hover:bg-red-50 font-semibold">
                    <?= t('app.reset') ?>
                </button>
            </div>
        </div>
    </div>

    <script>
        let data = {
            consequences: <?= json_encode($consequences) ?>,
            causes: <?= json_encode($causes) ?>,
            objectifs: <?= json_encode($objectifs) ?>,
            moyens: <?= json_encode($moyens) ?>
        };
        let currentTab = 'problemes';
        let saveTimeout = null;

        const colors = {
            consequences: { bg: 'bg-red-50', border: 'border-red-300', focus: 'focus:ring-red-400', text: 'text-red-600' },
            causes: { bg: 'bg-amber-50', border: 'border-amber-300', focus: 'focus:ring-amber-400', text: 'text-amber-600' },
            objectifs: { bg: 'bg-green-50', border: 'border-green-300', focus: 'focus:ring-green-400', text: 'text-green-600' },
            moyens: { bg: 'bg-blue-50', border: 'border-blue-300', focus: 'focus:ring-blue-400', text: 'text-blue-600' }
        };

        document.addEventListener('DOMContentLoaded', renderAll);

        function renderAll() {
            ['consequences', 'causes', 'objectifs', 'moyens'].forEach(renderList);
        }

        function renderList(key) {
            const container = document.getElementById('list-' + key);
            const c = colors[key];

            if (data[key].length === 0) {
                container.innerHTML = '<div class="col-span-full text-center text-gray-400 italic py-6">Cliquez sur "+ Ajouter" pour commencer</div>';
                return;
            }

            container.innerHTML = data[key].map((text, i) => `
                <div class="tree-box ${c.bg} border-2 ${c.border} rounded-xl p-1 relative group">
                    <textarea rows="2" class="w-full bg-transparent border-none resize-none focus:ring-2 ${c.focus} rounded-lg p-2 text-sm"
                              placeholder="Saisir ici..."
                              oninput="data['${key}'][${i}] = this.value; scheduleSave()">${escapeHtml(text)}</textarea>
                    <button onclick="removeItem('${key}', ${i})"
                            class="no-print absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs font-bold opacity-0 group-hover:opacity-100 transition shadow">
                        x
                    </button>
                </div>
            `).join('');
        }

        function addItem(key) {
            data[key].push('');
            renderList(key);
            // Focus the new textarea
            const container = document.getElementById('list-' + key);
            const textareas = container.querySelectorAll('textarea');
            if (textareas.length > 0) {
                textareas[textareas.length - 1].focus();
            }
            scheduleSave();
        }

        function removeItem(key, index) {
            data[key].splice(index, 1);
            renderList(key);
            scheduleSave();
        }

        function switchTab(tab) {
            currentTab = tab;
            document.getElementById('panel-problemes').style.display = tab === 'problemes' ? '' : 'none';
            document.getElementById('panel-solutions').style.display = tab === 'solutions' ? '' : 'none';

            const tabP = document.getElementById('tab-problemes');
            const tabS = document.getElementById('tab-solutions');

            if (tab === 'problemes') {
                tabP.className = 'flex-1 py-3 px-6 font-semibold text-center transition text-red-700';
                tabP.style.borderBottom = '3px solid #dc2626';
                tabS.className = 'flex-1 py-3 px-6 font-semibold text-center transition text-gray-400 hover:text-green-700';
                tabS.style.borderBottom = '3px solid transparent';
            } else {
                tabS.className = 'flex-1 py-3 px-6 font-semibold text-center transition text-green-700';
                tabS.style.borderBottom = '3px solid #16a34a';
                tabP.className = 'flex-1 py-3 px-6 font-semibold text-center transition text-gray-400 hover:text-red-700';
                tabP.style.borderBottom = '3px solid transparent';
            }
        }

        function scheduleSave() {
            if (saveTimeout) clearTimeout(saveTimeout);
            document.getElementById('saveStatus').textContent = 'Modifications...';
            saveTimeout = setTimeout(doSave, 1500);
        }

        function manualSave() {
            if (saveTimeout) clearTimeout(saveTimeout);
            doSave();
        }

        function getFormData() {
            return {
                nomProjet: document.getElementById('nomProjet').value,
                participants: document.getElementById('participants').value,
                problemeCentral: document.getElementById('problemeCentral').value,
                objectifCentral: document.getElementById('objectifCentral').value,
                consequences: data.consequences,
                causes: data.causes,
                objectifs: data.objectifs,
                moyens: data.moyens
            };
        }

        async function doSave() {
            document.getElementById('saveStatus').textContent = 'Sauvegarde...';
            try {
                const response = await fetch('api.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(getFormData())
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('saveStatus').textContent = 'Sauvegarde OK';
                    setTimeout(() => { document.getElementById('saveStatus').textContent = ''; }, 2000);
                } else {
                    document.getElementById('saveStatus').textContent = 'Erreur!';
                }
            } catch (e) {
                document.getElementById('saveStatus').textContent = 'Erreur reseau';
            }
        }

        async function toggleShare() {
            const shared = document.getElementById('shareToggle').checked;
            try {
                await fetch('api.php?action=share', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ shared: shared })
                });
            } catch (e) {
                console.error('Erreur partage:', e);
            }
        }

        function exportJSON() {
            const formData = getFormData();
            formData.exported_at = new Date().toISOString();
            const blob = new Blob([JSON.stringify(formData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'arbre_problemes_' + new Date().toISOString().split('T')[0] + '.json';
            a.click();
            URL.revokeObjectURL(url);
        }

        function exportToExcel() {
            const d = getFormData();
            const wb = XLSX.utils.book_new();

            const info = [
                ['ARBRE A PROBLEMES & SOLUTIONS'],
                [''],
                ['Projet', d.nomProjet],
                ['Participants', d.participants],
                ["Date d'export", new Date().toLocaleDateString('fr-FR')]
            ];
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(info), 'Informations');

            const prob = [
                ['ARBRE A PROBLEMES'], [''],
                ['PROBLEME CENTRAL'], [d.problemeCentral], [''],
                ['CONSEQUENCES']
            ];
            d.consequences.forEach((c, i) => prob.push([(i+1)+'.', c]));
            prob.push([''], ['CAUSES']);
            d.causes.forEach((c, i) => prob.push([(i+1)+'.', c]));
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(prob), 'Arbre Problemes');

            const sol = [
                ['ARBRE A SOLUTIONS'], [''],
                ['OBJECTIF CENTRAL'], [d.objectifCentral], [''],
                ['OBJECTIFS']
            ];
            d.objectifs.forEach((o, i) => sol.push([(i+1)+'.', o]));
            sol.push([''], ['MOYENS']);
            d.moyens.forEach((m, i) => sol.push([(i+1)+'.', m]));
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(sol), 'Arbre Solutions');

            XLSX.writeFile(wb, 'arbre_' + (d.nomProjet || 'projet').replace(/[^a-z0-9]/gi, '_').toLowerCase() + '.xlsx');
        }

        function resetForm() {
            if (!confirm('Reinitialiser toutes les donnees ?')) return;
            document.getElementById('nomProjet').value = '';
            document.getElementById('participants').value = '';
            document.getElementById('problemeCentral').value = '';
            document.getElementById('objectifCentral').value = '';
            data = { consequences: [], causes: [], objectifs: [], moyens: [] };
            renderAll();
            scheduleSave();
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    </script>
</body>
</html>
