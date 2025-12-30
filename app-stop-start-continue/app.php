<?php
/**
 * Interface de travail - Stop Start Continue
 */
require_once __DIR__ . '/config.php';
requireLoginWithSession();

$db = getDB();
$user = getLoggedUser();

// Verifier que l'utilisateur existe
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Charger ou creer la retrospective
$stmt = $db->prepare("SELECT * FROM retrospectives WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $_SESSION['current_session_id']]);
$retro = $stmt->fetch();

if (!$retro) {
    $stmt = $db->prepare("INSERT INTO retrospectives (user_id, session_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $_SESSION['current_session_id']]);
    $stmt = $db->prepare("SELECT * FROM retrospectives WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$user['id'], $_SESSION['current_session_id']]);
    $retro = $stmt->fetch();
}

$itemsCesser = json_decode($retro['stop_items'] ?? '[]', true) ?: [];
$itemsCommencer = json_decode($retro['start_items'] ?? '[]', true) ?: [];
$itemsContinuer = json_decode($retro['continue_items'] ?? '[]', true) ?: [];
$isSubmitted = ($retro['is_shared'] ?? 0) == 1;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('ssc.title') ?> - <?= h($user['prenom'] ?? '') ?> <?= h($user['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        .column-stop { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-top: 4px solid #dc2626; }
        .column-start { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-top: 4px solid #16a34a; }
        .column-continue { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-top: 4px solid #2563eb; }
        .item-card { transition: all 0.2s ease; }
        .item-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-stop { background-color: #dc2626; }
        .btn-stop:hover { background-color: #b91c1c; }
        .btn-start { background-color: #16a34a; }
        .btn-start:hover { background-color: #15803d; }
        .btn-continue { background-color: #2563eb; }
        .btn-continue:hover { background-color: #1d4ed8; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-blue-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h1 class="text-xl font-bold"><?= t('ssc.title') ?></h1>
                    <p class="text-blue-200 text-sm">
                        <?= h($user['prenom'] ?? '') ?> <?= h($user['nom'] ?? '') ?>
                        <?php if (!empty($user['organisation'])): ?>
                            - <?= h($user['organisation']) ?>
                        <?php endif; ?>
                        | <?= t('ssc.session') ?>: <?= h($_SESSION['current_session_code'] ?? '') ?>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <?= renderLanguageSelector('lang-select') ?>
                    <span id="saveStatus" class="text-sm text-blue-200"></span>
                    <?php if (!$isSubmitted): ?>
                        <button onclick="manualSave()" class="bg-blue-700 hover:bg-blue-600 px-4 py-2 rounded text-sm">
                            <?= t('ssc.save') ?>
                        </button>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">
                        <?= t('ssc.logout') ?>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <?php if ($isSubmitted): ?>
        <div class="bg-green-100 border-l-4 border-green-500 p-4 max-w-7xl mx-auto mt-4">
            <p class="text-green-700 font-medium"><?= t('ssc.work_complete') ?></p>
        </div>
    <?php endif; ?>

    <main class="max-w-7xl mx-auto p-4">
        <!-- Projet Info -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4"><?= t('ssc.project_info') ?></h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('ssc.project_name') ?></label>
                    <input type="text" id="projetNom" value="<?= h($retro['projet_nom'] ?? '') ?>"

                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 "
                           placeholder="<?= t('ssc.project_name_placeholder') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('ssc.context') ?></label>
                    <input type="text" id="projetContexte" value="<?= h($retro['projet_contexte'] ?? '') ?>"

                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 "
                           placeholder="<?= t('ssc.context_placeholder') ?>">
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-3">
                <?= t('ssc.project_instruction') ?>
            </p>
        </div>

        <!-- 3 Colonnes -->
        <div class="grid md:grid-cols-3 gap-6">
            <!-- CESSER (Stop) -->
            <div class="column-stop rounded-lg shadow p-4">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">🛑</span>
                    <div>
                        <h3 class="font-bold text-red-800 text-lg"><?= t('ssc.stop') ?></h3>
                        <p class="text-xs text-red-600"><?= t('ssc.stop_sub') ?></p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">
                    <?= t('ssc.stop_desc') ?>
                </p>
                <div id="listCesser" class="space-y-3 mb-4">
                    <!-- Items dynamiques -->
                </div>
                <?php if (!$isSubmitted): ?>
                    <button onclick="addItem('cesser')" class="w-full btn-stop text-white py-2 rounded-lg text-sm font-medium">
                        + <?= t('ssc.add_element') ?>
                    </button>
                <?php endif; ?>
            </div>

            <!-- COMMENCER (Start) -->
            <div class="column-start rounded-lg shadow p-4">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">🚀</span>
                    <div>
                        <h3 class="font-bold text-green-800 text-lg"><?= t('ssc.start') ?></h3>
                        <p class="text-xs text-green-600"><?= t('ssc.start_sub') ?></p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">
                    <?= t('ssc.start_desc') ?>
                </p>
                <div id="listCommencer" class="space-y-3 mb-4">
                    <!-- Items dynamiques -->
                </div>
                <?php if (!$isSubmitted): ?>
                    <button onclick="addItem('commencer')" class="w-full btn-start text-white py-2 rounded-lg text-sm font-medium">
                        + <?= t('ssc.add_element') ?>
                    </button>
                <?php endif; ?>
            </div>

            <!-- CONTINUER (Continue) -->
            <div class="column-continue rounded-lg shadow p-4">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">✅</span>
                    <div>
                        <h3 class="font-bold text-blue-800 text-lg"><?= t('ssc.continue') ?></h3>
                        <p class="text-xs text-blue-600"><?= t('ssc.continue_sub') ?></p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">
                    <?= t('ssc.continue_desc') ?>
                </p>
                <div id="listContinuer" class="space-y-3 mb-4">
                    <!-- Items dynamiques -->
                </div>
                <?php if (!$isSubmitted): ?>
                    <button onclick="addItem('continuer')" class="w-full btn-continue text-white py-2 rounded-lg text-sm font-medium">
                        + <?= t('ssc.add_element') ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes et Actions -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="font-semibold mb-3"><?= t('ssc.additional_notes') ?></h3>
            <textarea id="notes" rows="3"
                      class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 "
                      placeholder="<?= t('ssc.notes_placeholder') ?>"><?= h($retro['notes'] ?? '') ?></textarea>
        </div>

        <!-- Boutons d'action -->
        <div class="flex flex-wrap justify-between items-center mt-6 gap-4">
            <div class="flex gap-2">
                <button onclick="exportExcel()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                    Excel
                </button>
                <button onclick="exportWord()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Word
                </button>
                <button onclick="exportJSON()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    JSON
                </button>
                <button onclick="window.print()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
                    <?= t('ssc.print') ?>
                </button>
            </div>
            <?php if (!$isSubmitted): ?>
                <button onclick="submitWork()" class="bg-blue-900 hover:bg-blue-800 text-white px-6 py-3 rounded-lg font-medium">
                    <?= t('ssc.mark_complete') ?>
                </button>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Ajout/Edition -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 p-6">
            <h3 id="modalTitle" class="text-lg font-bold mb-4"><?= t('ssc.add_item') ?></h3>
            <input type="hidden" id="editCategory">
            <input type="hidden" id="editIndex" value="-1">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('ssc.description') ?> *</label>
                    <textarea id="itemDescription" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="<?= t('ssc.description_placeholder') ?>"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('ssc.reason') ?></label>
                    <textarea id="itemRaison" rows="2"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="<?= t('ssc.reason_placeholder') ?>"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('ssc.priority') ?></label>
                        <select id="itemPriorite" class="w-full p-3 border rounded-lg">
                            <option value="moyenne"><?= t('ssc.priority_medium') ?></option>
                            <option value="haute"><?= t('ssc.priority_high') ?></option>
                            <option value="basse"><?= t('ssc.priority_low') ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('ssc.responsible') ?></label>
                        <input type="text" id="itemResponsable"
                               class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="<?= t('ssc.responsible_placeholder') ?>">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                    <?= t('ssc.cancel') ?>
                </button>
                <button onclick="saveItem()" class="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800">
                    <?= t('ssc.save_item') ?>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Donnees
        let data = {
            cesser: <?= json_encode($itemsCesser) ?>,
            commencer: <?= json_encode($itemsCommencer) ?>,
            continuer: <?= json_encode($itemsContinuer) ?>
        };
        const isSubmitted = <?= $isSubmitted ? 'true' : 'false' ?>;
        let saveTimeout = null;

        // Translations
        const trans = {
            noElement: '<?= t('ssc.no_element') ?>',
            addStop: '<?= t('ssc.add_stop') ?>',
            addStart: '<?= t('ssc.add_start') ?>',
            addContinue: '<?= t('ssc.add_continue') ?>',
            editItem: '<?= t('ssc.edit_item') ?>',
            enterDescription: '<?= t('ssc.enter_description') ?>',
            deleteConfirm: '<?= t('ssc.delete_confirm') ?>',
            modifying: '<?= t('ssc.modifying') ?>',
            saving: '<?= t('ssc.saving') ?>',
            saveOk: '<?= t('ssc.save_ok') ?>',
            error: '<?= t('ssc.error') ?>',
            networkError: '<?= t('ssc.network_error') ?>',
            confirmComplete: '<?= t('ssc.confirm_complete') ?>',
            markedComplete: '<?= t('ssc.marked_complete') ?>'
        };

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            renderAll();

            if (!isSubmitted) {
                document.getElementById('projetNom').addEventListener('input', scheduleSave);
                document.getElementById('projetContexte').addEventListener('input', scheduleSave);
                document.getElementById('notes').addEventListener('input', scheduleSave);
            }
        });

        function renderAll() {
            renderList('cesser', 'listCesser', 'red');
            renderList('commencer', 'listCommencer', 'green');
            renderList('continuer', 'listContinuer', 'blue');
        }

        function renderList(category, containerId, color) {
            const container = document.getElementById(containerId);
            const items = data[category];

            if (items.length === 0) {
                container.innerHTML = '<p class="text-gray-400 text-sm italic text-center py-4">' + trans.noElement + '</p>';
                return;
            }

            const colors = {
                red: { bg: 'bg-white', border: 'border-red-200', badge: 'bg-red-100 text-red-800' },
                green: { bg: 'bg-white', border: 'border-green-200', badge: 'bg-green-100 text-green-800' },
                blue: { bg: 'bg-white', border: 'border-blue-200', badge: 'bg-blue-100 text-blue-800' }
            };

            const priorityColors = {
                haute: 'bg-red-100 text-red-800',
                moyenne: 'bg-yellow-100 text-yellow-800',
                basse: 'bg-gray-100 text-gray-800'
            };

            container.innerHTML = items.map((item, index) => `
                <div class="item-card ${colors[color].bg} border ${colors[color].border} rounded-lg p-3">
                    <div class="flex justify-between items-start gap-2">
                        <p class="text-sm font-medium flex-1">${escapeHtml(item.description)}</p>
                        ${!isSubmitted ? `
                            <div class="flex gap-1">
                                <button onclick="editItem('${category}', ${index})" class="text-gray-400 hover:text-blue-600 p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button onclick="deleteItem('${category}', ${index})" class="text-gray-400 hover:text-red-600 p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        ` : ''}
                    </div>
                    ${item.raison ? `<p class="text-xs text-gray-500 mt-2">${escapeHtml(item.raison)}</p>` : ''}
                    <div class="flex gap-2 mt-2 flex-wrap">
                        ${item.priorite ? `<span class="text-xs px-2 py-0.5 rounded ${priorityColors[item.priorite] || priorityColors.moyenne}">${item.priorite}</span>` : ''}
                        ${item.responsable ? `<span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">${escapeHtml(item.responsable)}</span>` : ''}
                    </div>
                </div>
            `).join('');
        }

        function addItem(category) {
            document.getElementById('editCategory').value = category;
            document.getElementById('editIndex').value = -1;
            document.getElementById('itemDescription').value = '';
            document.getElementById('itemRaison').value = '';
            document.getElementById('itemPriorite').value = 'moyenne';
            document.getElementById('itemResponsable').value = '';

            const titles = {
                cesser: trans.addStop,
                commencer: trans.addStart,
                continuer: trans.addContinue
            };
            document.getElementById('modalTitle').textContent = titles[category];
            document.getElementById('modal').classList.remove('hidden');
            document.getElementById('modal').classList.add('flex');
        }

        function editItem(category, index) {
            const item = data[category][index];
            document.getElementById('editCategory').value = category;
            document.getElementById('editIndex').value = index;
            document.getElementById('itemDescription').value = item.description || '';
            document.getElementById('itemRaison').value = item.raison || '';
            document.getElementById('itemPriorite').value = item.priorite || 'moyenne';
            document.getElementById('itemResponsable').value = item.responsable || '';

            document.getElementById('modalTitle').textContent = trans.editItem;
            document.getElementById('modal').classList.remove('hidden');
            document.getElementById('modal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
            document.getElementById('modal').classList.remove('flex');
        }

        function saveItem() {
            const category = document.getElementById('editCategory').value;
            const index = parseInt(document.getElementById('editIndex').value);
            const description = document.getElementById('itemDescription').value.trim();

            if (!description) {
                alert(trans.enterDescription);
                return;
            }

            const item = {
                description: description,
                raison: document.getElementById('itemRaison').value.trim(),
                priorite: document.getElementById('itemPriorite').value,
                responsable: document.getElementById('itemResponsable').value.trim()
            };

            if (index === -1) {
                data[category].push(item);
            } else {
                data[category][index] = item;
            }

            renderAll();
            closeModal();
            scheduleSave();
        }

        function deleteItem(category, index) {
            if (confirm(trans.deleteConfirm)) {
                data[category].splice(index, 1);
                renderAll();
                scheduleSave();
            }
        }

        function scheduleSave() {
            if (saveTimeout) clearTimeout(saveTimeout);
            document.getElementById('saveStatus').textContent = trans.modifying;
            saveTimeout = setTimeout(doSave, 1000);
        }

        function manualSave() {
            if (saveTimeout) clearTimeout(saveTimeout);
            doSave();
        }

        function doSave() {
            document.getElementById('saveStatus').textContent = trans.saving;

            const payload = {
                projet_nom: document.getElementById('projetNom').value,
                projet_contexte: document.getElementById('projetContexte').value,
                items_cesser: data.cesser,
                items_commencer: data.commencer,
                items_continuer: data.continuer,
                notes: document.getElementById('notes').value
            };

            fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    document.getElementById('saveStatus').textContent = trans.saveOk;
                    setTimeout(() => {
                        document.getElementById('saveStatus').textContent = '';
                    }, 2000);
                } else {
                    document.getElementById('saveStatus').textContent = trans.error;
                }
            })
            .catch(() => {
                document.getElementById('saveStatus').textContent = trans.networkError;
            });
        }

        function submitWork() {
            if (!confirm(trans.confirmComplete)) return;

            fetch('api/submit.php', { method: 'POST' })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    alert(trans.markedComplete);
                    location.reload();
                } else {
                    alert(trans.error + ': ' + (result.error || ''));
                }
            });
        }

        function exportExcel() {
            const wb = XLSX.utils.book_new();

            // Feuille Resume
            const resume = [
                ['STOP START CONTINUE - Retrospective'],
                [''],
                ['Projet', document.getElementById('projetNom').value],
                ['Contexte', document.getElementById('projetContexte').value],
                [''],
                ['A CESSER (' + data.cesser.length + ')'],
                ...data.cesser.map((item, i) => [(i+1) + '. ' + item.description, item.raison || '', item.priorite || '', item.responsable || '']),
                [''],
                ['A COMMENCER (' + data.commencer.length + ')'],
                ...data.commencer.map((item, i) => [(i+1) + '. ' + item.description, item.raison || '', item.priorite || '', item.responsable || '']),
                [''],
                ['A CONTINUER (' + data.continuer.length + ')'],
                ...data.continuer.map((item, i) => [(i+1) + '. ' + item.description, item.raison || '', item.priorite || '', item.responsable || '']),
                [''],
                ['Notes', document.getElementById('notes').value]
            ];

            const ws = XLSX.utils.aoa_to_sheet(resume);
            ws['!cols'] = [{ wch: 50 }, { wch: 30 }, { wch: 15 }, { wch: 20 }];
            XLSX.utils.book_append_sheet(wb, ws, 'Retrospective');

            XLSX.writeFile(wb, 'stop-start-continue.xlsx');
        }

        function exportWord() {
            const projetNom = document.getElementById('projetNom').value;
            const projetContexte = document.getElementById('projetContexte').value;
            const notes = document.getElementById('notes').value;

            let html = `
                <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
                <head><meta charset="utf-8"><title>Stop Start Continue</title></head>
                <body style="font-family: Arial, sans-serif;">
                <h1 style="color: #1e40af;">Stop Start Continue - Retrospective</h1>
                <p><strong>Projet:</strong> ${escapeHtml(projetNom)}</p>
                <p><strong>Contexte:</strong> ${escapeHtml(projetContexte)}</p>
                <hr>

                <h2 style="color: #dc2626;">🛑 A CESSER (${data.cesser.length})</h2>
                <ul>
                ${data.cesser.map(item => `
                    <li>
                        <strong>${escapeHtml(item.description)}</strong>
                        ${item.raison ? '<br><em>' + escapeHtml(item.raison) + '</em>' : ''}
                        ${item.priorite ? '<br>Priorite: ' + item.priorite : ''}
                        ${item.responsable ? ' | Responsable: ' + escapeHtml(item.responsable) : ''}
                    </li>
                `).join('')}
                </ul>

                <h2 style="color: #16a34a;">🚀 A COMMENCER (${data.commencer.length})</h2>
                <ul>
                ${data.commencer.map(item => `
                    <li>
                        <strong>${escapeHtml(item.description)}</strong>
                        ${item.raison ? '<br><em>' + escapeHtml(item.raison) + '</em>' : ''}
                        ${item.priorite ? '<br>Priorite: ' + item.priorite : ''}
                        ${item.responsable ? ' | Responsable: ' + escapeHtml(item.responsable) : ''}
                    </li>
                `).join('')}
                </ul>

                <h2 style="color: #2563eb;">✅ A CONTINUER (${data.continuer.length})</h2>
                <ul>
                ${data.continuer.map(item => `
                    <li>
                        <strong>${escapeHtml(item.description)}</strong>
                        ${item.raison ? '<br><em>' + escapeHtml(item.raison) + '</em>' : ''}
                        ${item.priorite ? '<br>Priorite: ' + item.priorite : ''}
                        ${item.responsable ? ' | Responsable: ' + escapeHtml(item.responsable) : ''}
                    </li>
                `).join('')}
                </ul>

                ${notes ? '<hr><h3>Notes</h3><p>' + escapeHtml(notes) + '</p>' : ''}
                </body></html>
            `;

            const blob = new Blob([html], { type: 'application/msword' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'stop-start-continue.doc';
            a.click();
            URL.revokeObjectURL(url);
        }

        function exportJSON() {
            const exportData = {
                projet_nom: document.getElementById('projetNom').value,
                projet_contexte: document.getElementById('projetContexte').value,
                cesser: data.cesser,
                commencer: data.commencer,
                continuer: data.continuer,
                notes: document.getElementById('notes').value,
                exported_at: new Date().toISOString()
            };

            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'stop-start-continue.json';
            a.click();
            URL.revokeObjectURL(url);
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    </script>
    <?= renderLanguageScript() ?>
</body>
</html>
