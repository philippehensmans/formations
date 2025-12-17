<?php
/**
 * Interface de travail - Stop Start Continue
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

// Charger la retrospective
$stmt = $db->prepare("SELECT * FROM retrospectives WHERE participant_id = ?");
$stmt->execute([$participant['id']]);
$retro = $stmt->fetch();

if (!$retro) {
    $stmt = $db->prepare("INSERT INTO retrospectives (participant_id, session_id) VALUES (?, ?)");
    $stmt->execute([$participant['id'], $participant['session_id']]);
    $stmt = $db->prepare("SELECT * FROM retrospectives WHERE participant_id = ?");
    $stmt->execute([$participant['id']]);
    $retro = $stmt->fetch();
}

$itemsCesser = json_decode($retro['items_cesser'], true) ?: [];
$itemsCommencer = json_decode($retro['items_commencer'], true) ?: [];
$itemsContinuer = json_decode($retro['items_continuer'], true) ?: [];
$isSubmitted = $retro['is_submitted'] == 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stop Start Continue - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title>
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
                    <h1 class="text-xl font-bold">Stop Start Continue</h1>
                    <p class="text-blue-200 text-sm">
                        <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?>
                        <?php if ($participant['organisation']): ?>
                            - <?= sanitize($participant['organisation']) ?>
                        <?php endif; ?>
                        | Session: <?= sanitize($participant['session_code']) ?>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span id="saveStatus" class="text-sm text-blue-200"></span>
                    <?php if (!$isSubmitted): ?>
                        <button onclick="manualSave()" class="bg-blue-700 hover:bg-blue-600 px-4 py-2 rounded text-sm">
                            Sauvegarder
                        </button>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">
                        Deconnexion
                    </a>
                </div>
            </div>
        </div>
    </header>

    <?php if ($isSubmitted): ?>
        <div class="bg-green-100 border-l-4 border-green-500 p-4 max-w-7xl mx-auto mt-4">
            <p class="text-green-700 font-medium">Travail soumis - Consultation seule</p>
        </div>
    <?php endif; ?>

    <main class="max-w-7xl mx-auto p-4">
        <!-- Projet Info -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Informations du projet</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom du projet / programme</label>
                    <input type="text" id="projetNom" value="<?= sanitize($retro['projet_nom']) ?>"
                           <?= $isSubmitted ? 'disabled' : '' ?>
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 <?= $isSubmitted ? 'bg-gray-100' : '' ?>"
                           placeholder="Ex: Programme de formation 2024">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contexte de l'evaluation</label>
                    <input type="text" id="projetContexte" value="<?= sanitize($retro['projet_contexte']) ?>"
                           <?= $isSubmitted ? 'disabled' : '' ?>
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 <?= $isSubmitted ? 'bg-gray-100' : '' ?>"
                           placeholder="Ex: Evaluation annuelle Q4 2024">
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-3">
                Sur la base de votre evaluation, definissez comment votre projet/programme/strategie devrait evoluer.
            </p>
        </div>

        <!-- 3 Colonnes -->
        <div class="grid md:grid-cols-3 gap-6">
            <!-- CESSER (Stop) -->
            <div class="column-stop rounded-lg shadow p-4">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">🛑</span>
                    <div>
                        <h3 class="font-bold text-red-800 text-lg">A CESSER</h3>
                        <p class="text-xs text-red-600">Stop - Arreter</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">
                    Quelles tactiques, methodes de travail et initiatives devez-vous <strong>cesser</strong> car elles ne permettent pas d'atteindre vos objectifs ?
                </p>
                <div id="listCesser" class="space-y-3 mb-4">
                    <!-- Items dynamiques -->
                </div>
                <?php if (!$isSubmitted): ?>
                    <button onclick="addItem('cesser')" class="w-full btn-stop text-white py-2 rounded-lg text-sm font-medium">
                        + Ajouter un element
                    </button>
                <?php endif; ?>
            </div>

            <!-- COMMENCER (Start) -->
            <div class="column-start rounded-lg shadow p-4">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">🚀</span>
                    <div>
                        <h3 class="font-bold text-green-800 text-lg">A COMMENCER</h3>
                        <p class="text-xs text-green-600">Start - Demarrer</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">
                    Quelles <strong>nouvelles</strong> tactiques, methodes de travail et initiatives devez-vous mettre en oeuvre pour atteindre vos objectifs ?
                </p>
                <div id="listCommencer" class="space-y-3 mb-4">
                    <!-- Items dynamiques -->
                </div>
                <?php if (!$isSubmitted): ?>
                    <button onclick="addItem('commencer')" class="w-full btn-start text-white py-2 rounded-lg text-sm font-medium">
                        + Ajouter un element
                    </button>
                <?php endif; ?>
            </div>

            <!-- CONTINUER (Continue) -->
            <div class="column-continue rounded-lg shadow p-4">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">✅</span>
                    <div>
                        <h3 class="font-bold text-blue-800 text-lg">A CONTINUER</h3>
                        <p class="text-xs text-blue-600">Continue - Poursuivre</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">
                    Quelles tactiques, methodes de travail et initiatives devez-vous <strong>poursuivre</strong> ? Quels ajustements y apporter ?
                </p>
                <div id="listContinuer" class="space-y-3 mb-4">
                    <!-- Items dynamiques -->
                </div>
                <?php if (!$isSubmitted): ?>
                    <button onclick="addItem('continuer')" class="w-full btn-continue text-white py-2 rounded-lg text-sm font-medium">
                        + Ajouter un element
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes et Actions -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="font-semibold mb-3">Notes complementaires</h3>
            <textarea id="notes" rows="3" <?= $isSubmitted ? 'disabled' : '' ?>
                      class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 <?= $isSubmitted ? 'bg-gray-100' : '' ?>"
                      placeholder="Ressources necessaires, obstacles potentiels, points d'attention..."><?= sanitize($retro['notes']) ?></textarea>
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
                    Imprimer
                </button>
            </div>
            <?php if (!$isSubmitted): ?>
                <button onclick="submitWork()" class="bg-blue-900 hover:bg-blue-800 text-white px-6 py-3 rounded-lg font-medium">
                    Soumettre le travail
                </button>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Ajout/Edition -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 p-6">
            <h3 id="modalTitle" class="text-lg font-bold mb-4">Ajouter un element</h3>
            <input type="hidden" id="editCategory">
            <input type="hidden" id="editIndex" value="-1">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                    <textarea id="itemDescription" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Decrivez l'element..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Justification / Raison</label>
                    <textarea id="itemRaison" rows="2"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Pourquoi ce changement ?"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priorite</label>
                        <select id="itemPriorite" class="w-full p-3 border rounded-lg">
                            <option value="moyenne">Moyenne</option>
                            <option value="haute">Haute</option>
                            <option value="basse">Basse</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Responsable</label>
                        <input type="text" id="itemResponsable"
                               class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Qui ?">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                    Annuler
                </button>
                <button onclick="saveItem()" class="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800">
                    Enregistrer
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
                container.innerHTML = '<p class="text-gray-400 text-sm italic text-center py-4">Aucun element</p>';
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
                cesser: 'Ajouter - A CESSER',
                commencer: 'Ajouter - A COMMENCER',
                continuer: 'Ajouter - A CONTINUER'
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

            document.getElementById('modalTitle').textContent = 'Modifier l\'element';
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
                alert('Veuillez entrer une description');
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
            if (confirm('Supprimer cet element ?')) {
                data[category].splice(index, 1);
                renderAll();
                scheduleSave();
            }
        }

        function scheduleSave() {
            if (saveTimeout) clearTimeout(saveTimeout);
            document.getElementById('saveStatus').textContent = 'Modifications...';
            saveTimeout = setTimeout(doSave, 1000);
        }

        function manualSave() {
            if (saveTimeout) clearTimeout(saveTimeout);
            doSave();
        }

        function doSave() {
            document.getElementById('saveStatus').textContent = 'Sauvegarde...';

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
                    document.getElementById('saveStatus').textContent = 'Sauvegarde OK';
                    setTimeout(() => {
                        document.getElementById('saveStatus').textContent = '';
                    }, 2000);
                } else {
                    document.getElementById('saveStatus').textContent = 'Erreur!';
                }
            })
            .catch(() => {
                document.getElementById('saveStatus').textContent = 'Erreur reseau';
            });
        }

        function submitWork() {
            if (!confirm('Soumettre votre travail ? Vous ne pourrez plus le modifier.')) return;

            fetch('api/submit.php', { method: 'POST' })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    alert('Travail soumis avec succes !');
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Erreur inconnue'));
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
</body>
</html>
