<?php
/**
 * Vue complète des activités d'une session (pour formateur)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

// Vérifier si formateur connecté
if (!isLoggedIn() || !isFormateur()) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sessionId = (int)($_GET['id'] ?? 0);

if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

// Récupérer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: formateur.php');
    exit;
}

// Récupérer les activités
$activites = getActivites($sessionId);
$categories = getCategories();
$frequences = getFrequences();
$priorites = getPriorites();
$stats = getStatistiques($sessionId);

// Préparer les données pour le tri côté client
$activitesJson = [];
foreach ($activites as $a) {
    $cat = $categories[$a['categorie']] ?? $categories['autre'];
    $prio = $priorites[$a['priorite']] ?? $priorites[2];
    $activitesJson[] = [
        'id' => $a['id'],
        'nom' => $a['nom'],
        'description' => $a['description'],
        'categorie' => $a['categorie'],
        'categorie_label' => $cat['label'],
        'categorie_icon' => $cat['icon'],
        'categorie_color' => $cat['color'],
        'frequence' => $a['frequence'],
        'frequence_label' => $frequences[$a['frequence']] ?? $a['frequence'],
        'temps_estime' => $a['temps_estime'],
        'priorite' => $a['priorite'],
        'priorite_label' => $prio['label'],
        'priorite_color' => $prio['color'],
        'potentiel_ia' => (int)$a['potentiel_ia'],
        'notes_ia' => $a['notes_ia'],
        'created_at' => $a['created_at']
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('act.title') ?> - <?= htmlspecialchars($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sortable { cursor: pointer; user-select: none; }
        .sortable:hover { background-color: #f3f4f6; }
        .sort-asc::after { content: ' ▲'; font-size: 0.7em; }
        .sort-desc::after { content: ' ▼'; font-size: 0.7em; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-teal-50 to-cyan-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-teal-600 to-cyan-600 text-white shadow-lg no-print">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold"><?= t('act.title') ?></h1>
                    <p class="text-teal-200 text-sm"><?= htmlspecialchars($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?= renderLanguageSelector('bg-teal-500 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <button onclick="window.print()" class="bg-teal-500 hover:bg-teal-400 px-3 py-1 rounded text-sm">
                        🖨️ <?= t('common.print') ?? 'Imprimer' ?>
                    </button>
                    <a href="api.php?action=export-excel&session_id=<?= $sessionId ?>" class="bg-teal-500 hover:bg-teal-400 px-3 py-1 rounded text-sm">
                        📊 Excel
                    </a>
                    <a href="api.php?action=export&session_id=<?= $sessionId ?>" class="bg-teal-500 hover:bg-teal-400 px-3 py-1 rounded text-sm">
                        📄 HTML
                    </a>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-teal-500 hover:bg-teal-400 px-3 py-1 rounded text-sm">
                        ← <?= t('common.back') ?>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Modal edition notes_ia -->
    <div id="edit-notes-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center no-print">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl mx-4">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold text-gray-800">💡 Comment l'IA peut aider</h2>
                <p id="edit-notes-activity" class="text-sm text-gray-500 mt-1"></p>
            </div>
            <div class="p-6">
                <textarea id="edit-notes-text" rows="8"
                    class="w-full border rounded-lg px-3 py-2 text-sm"
                    placeholder="Description du soutien que l'IA peut apporter..."></textarea>
                <p class="text-xs text-gray-500 mt-2">Vous pouvez modifier le texte genere par l'IA avant de le sauvegarder.</p>
            </div>
            <div class="p-6 border-t bg-gray-50 flex justify-between gap-3">
                <button onclick="regenerateNotes()" class="px-4 py-2 text-teal-700 hover:bg-teal-50 rounded-lg text-sm flex items-center gap-2">
                    🔄 Regenerer avec l'IA
                </button>
                <div class="flex gap-3">
                    <button onclick="closeEditModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg text-sm">
                        <?= t('common.cancel') ?>
                    </button>
                    <button onclick="saveNotes()" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm">
                        <?= t('common.save') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= $stats['total'] ?></div>
                <div class="text-gray-500 text-sm"><?= t('act.total_activities') ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-purple-600"><?= count($stats['par_categorie']) ?></div>
                <div class="text-gray-500 text-sm"><?= t('act.categories_used') ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $stats['avec_potentiel_ia'] ?></div>
                <div class="text-gray-500 text-sm"><?= t('act.ai_potential') ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-orange-600">
                    <?= $stats['total'] > 0 ? round(($stats['avec_potentiel_ia'] / $stats['total']) * 100) : 0 ?>%
                </div>
                <div class="text-gray-500 text-sm"><?= t('act.ai_percentage') ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $stats['total'] - $stats['avec_potentiel_ia'] ?></div>
                <div class="text-gray-500 text-sm"><?= t('act.without_ai') ?? 'Sans potentiel IA' ?></div>
            </div>
        </div>

        <!-- Breakdown by category -->
        <?php if (!empty($stats['par_categorie'])): ?>
        <div class="bg-white rounded-xl shadow p-6 mb-8">
            <h2 class="text-lg font-bold text-gray-800 mb-4"><?= t('act.by_category') ?? 'Par catégorie' ?></h2>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach ($stats['par_categorie'] as $catKey => $count):
                    $cat = $categories[$catKey] ?? $categories['autre'];
                ?>
                <div class="flex items-center gap-2 p-3 bg-<?= $cat['color'] ?>-50 rounded-lg">
                    <span class="text-2xl"><?= $cat['icon'] ?></span>
                    <div>
                        <div class="font-bold text-<?= $cat['color'] ?>-700"><?= $count ?></div>
                        <div class="text-xs text-<?= $cat['color'] ?>-600"><?= $cat['label'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 no-print">
            <div class="flex flex-wrap gap-4 items-center">
                <div>
                    <label class="text-sm text-gray-600 mr-2"><?= t('act.category') ?>:</label>
                    <select id="filter-categorie" class="border rounded-lg px-3 py-2 text-sm">
                        <option value=""><?= t('act.all_categories') ?></option>
                        <?php foreach ($categories as $key => $cat): ?>
                            <option value="<?= $key ?>"><?= $cat['icon'] ?> <?= $cat['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600 mr-2"><?= t('act.frequency') ?>:</label>
                    <select id="filter-frequence" class="border rounded-lg px-3 py-2 text-sm">
                        <option value=""><?= t('act.all_frequencies') ?></option>
                        <?php foreach ($frequences as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600 mr-2"><?= t('act.priority') ?>:</label>
                    <select id="filter-priorite" class="border rounded-lg px-3 py-2 text-sm">
                        <option value=""><?= t('common.all') ?></option>
                        <?php foreach ($priorites as $key => $prio): ?>
                            <option value="<?= $key ?>"><?= $prio['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" id="filter-ia" class="rounded">
                        <?= t('act.only_ai_potential') ?>
                    </label>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" id="filter-no-ia" class="rounded">
                        <?= t('act.without_ai_only') ?? 'Sans potentiel IA' ?>
                    </label>
                </div>
                <div class="flex-1"></div>
                <div class="text-sm text-gray-500">
                    <span id="count-display"><?= count($activites) ?></span> <?= t('act.total_activities') ?>
                </div>
            </div>
        </div>

        <!-- Banniere generation IA -->
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-xl p-4 mb-6 no-print">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="text-3xl">🤖</div>
                    <div>
                        <h3 class="font-bold text-purple-900">Generation automatique "Comment l'IA peut aider"</h3>
                        <p class="text-sm text-purple-700">Laissez l'IA decrire les types de soutien qu'elle peut apporter aux participants pour chaque activite. Vous pourrez ensuite editer chaque texte genere.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="generateBatch(true)" id="btn-batch-empty"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                        ✨ Generer pour les activites sans description
                    </button>
                    <button onclick="generateBatch(false)" id="btn-batch-all"
                        class="bg-white border border-purple-300 hover:bg-purple-50 text-purple-700 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                        🔄 Regenerer toutes
                    </button>
                </div>
            </div>
            <div id="batch-progress" class="mt-3 hidden">
                <div class="bg-white rounded-lg p-3 border border-purple-200">
                    <div class="flex items-center justify-between text-sm text-purple-800">
                        <span id="batch-status">Generation en cours...</span>
                        <span id="batch-counter"></span>
                    </div>
                    <div class="mt-2 bg-purple-100 rounded-full h-2 overflow-hidden">
                        <div id="batch-bar" class="bg-purple-600 h-full transition-all" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table View -->
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="activities-table">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left p-3 sortable" data-sort="nom"><?= t('act.activity_name') ?></th>
                            <th class="text-left p-3 sortable" data-sort="categorie_label"><?= t('act.category') ?></th>
                            <th class="text-left p-3 sortable" data-sort="frequence"><?= t('act.frequency') ?></th>
                            <th class="text-left p-3 sortable" data-sort="priorite"><?= t('act.priority') ?></th>
                            <th class="text-center p-3 sortable" data-sort="potentiel_ia"><?= t('act.ai_potential') ?></th>
                            <th class="text-left p-3"><?= t('act.ai_notes') ?></th>
                            <th class="text-center p-3 no-print">Actions IA</th>
                        </tr>
                    </thead>
                    <tbody id="activities-tbody">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AI Summary -->
        <?php
        $iaActivites = array_filter($activites, fn($a) => $a['potentiel_ia']);
        if (!empty($iaActivites)):
        ?>
        <div class="bg-green-50 rounded-xl shadow p-6 mt-8">
            <h2 class="text-lg font-bold text-green-800 mb-4">🤖 <?= t('act.ai_summary') ?? 'Résumé potentiel IA' ?></h2>
            <div class="space-y-3">
                <?php foreach ($iaActivites as $a): ?>
                <div class="bg-white rounded-lg p-3 border border-green-200">
                    <div class="font-medium text-green-900"><?= htmlspecialchars($a['nom']) ?></div>
                    <?php if ($a['notes_ia']): ?>
                    <div class="text-sm text-green-700 mt-1">💡 <?= htmlspecialchars($a['notes_ia']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <?= renderLanguageScript() ?>

    <script>
        const activites = <?= json_encode($activitesJson) ?>;
        let currentSort = { field: 'nom', dir: 'asc' };
        let filteredActivites = [...activites];

        function renderTable() {
            const tbody = document.getElementById('activities-tbody');
            tbody.innerHTML = '';

            filteredActivites.forEach(a => {
                const row = document.createElement('tr');
                row.className = 'border-b hover:bg-gray-50';
                row.dataset.id = a.id;
                const hasNotes = !!(a.notes_ia && a.notes_ia.trim());
                row.innerHTML = `
                    <td class="p-3">
                        <div class="font-medium text-gray-800">${escapeHtml(a.nom)}</div>
                        ${a.description ? `<div class="text-xs text-gray-500 mt-1">${escapeHtml(a.description)}</div>` : ''}
                    </td>
                    <td class="p-3">
                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-${a.categorie_color}-100 text-${a.categorie_color}-700 rounded text-xs">
                            ${a.categorie_icon} ${escapeHtml(a.categorie_label)}
                        </span>
                    </td>
                    <td class="p-3 text-gray-600">${escapeHtml(a.frequence_label)}</td>
                    <td class="p-3">
                        <span class="px-2 py-1 bg-${a.priorite_color}-100 text-${a.priorite_color}-700 rounded text-xs">
                            ${escapeHtml(a.priorite_label)}
                        </span>
                    </td>
                    <td class="p-3 text-center">
                        ${a.potentiel_ia ? '<span class="text-green-600 text-lg">✓</span>' : '<span class="text-gray-300">—</span>'}
                    </td>
                    <td class="p-3 text-gray-700 text-xs max-w-md" data-notes-cell="${a.id}">
                        ${hasNotes ? `<div class="whitespace-pre-wrap">${escapeHtml(a.notes_ia)}</div>` : '<span class="text-gray-400 italic">Non genere</span>'}
                    </td>
                    <td class="p-3 text-center no-print whitespace-nowrap">
                        <button onclick="generateSingle(${a.id})" title="${hasNotes ? 'Regenerer avec IA' : 'Generer avec IA'}"
                            class="text-purple-600 hover:text-purple-800 px-2">
                            ${hasNotes ? '🔄' : '✨'}
                        </button>
                        <button onclick="openEditModal(${a.id})" title="Editer"
                            class="text-gray-500 hover:text-teal-600 px-2" ${hasNotes ? '' : 'disabled style="opacity:0.3;cursor:not-allowed;"'}>
                            ✏️
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });

            document.getElementById('count-display').textContent = filteredActivites.length;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function applyFilters() {
            const categorie = document.getElementById('filter-categorie').value;
            const frequence = document.getElementById('filter-frequence').value;
            const priorite = document.getElementById('filter-priorite').value;
            const iaOnly = document.getElementById('filter-ia').checked;
            const noIaOnly = document.getElementById('filter-no-ia').checked;

            filteredActivites = activites.filter(a => {
                if (categorie && a.categorie !== categorie) return false;
                if (frequence && a.frequence !== frequence) return false;
                if (priorite && a.priorite != priorite) return false;
                if (iaOnly && !a.potentiel_ia) return false;
                if (noIaOnly && a.potentiel_ia) return false;
                return true;
            });

            applySort();
            renderTable();
        }

        function applySort() {
            const { field, dir } = currentSort;
            filteredActivites.sort((a, b) => {
                let valA = a[field];
                let valB = b[field];

                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();

                if (valA < valB) return dir === 'asc' ? -1 : 1;
                if (valA > valB) return dir === 'asc' ? 1 : -1;
                return 0;
            });
        }

        // Sort handlers
        document.querySelectorAll('.sortable').forEach(th => {
            th.addEventListener('click', () => {
                const field = th.dataset.sort;
                if (currentSort.field === field) {
                    currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort = { field, dir: 'asc' };
                }

                document.querySelectorAll('.sortable').forEach(el => {
                    el.classList.remove('sort-asc', 'sort-desc');
                });
                th.classList.add(currentSort.dir === 'asc' ? 'sort-asc' : 'sort-desc');

                applySort();
                renderTable();
            });
        });

        // Filter handlers
        document.getElementById('filter-categorie').addEventListener('change', applyFilters);
        document.getElementById('filter-frequence').addEventListener('change', applyFilters);
        document.getElementById('filter-priorite').addEventListener('change', applyFilters);
        document.getElementById('filter-ia').addEventListener('change', function() {
            if (this.checked) document.getElementById('filter-no-ia').checked = false;
            applyFilters();
        });
        document.getElementById('filter-no-ia').addEventListener('change', function() {
            if (this.checked) document.getElementById('filter-ia').checked = false;
            applyFilters();
        });

        // Initial render
        renderTable();

        // ============================================================
        // Generation IA de notes_ia
        // ============================================================
        const sessionId = <?= $sessionId ?>;
        let currentEditId = null;

        function updateActivityNotes(id, notes) {
            const a = activites.find(x => x.id == id);
            if (a) a.notes_ia = notes;
            const fa = filteredActivites.find(x => x.id == id);
            if (fa) fa.notes_ia = notes;
            renderTable();
        }

        async function generateSingle(activityId) {
            const row = document.querySelector(`tr[data-id="${activityId}"]`);
            const cell = document.querySelector(`[data-notes-cell="${activityId}"]`);
            if (cell) cell.innerHTML = '<span class="text-purple-600 italic">⏳ Generation en cours...</span>';

            try {
                const res = await fetch('api/ai-notes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'generate', activity_id: activityId })
                });
                const data = await res.json();
                if (!res.ok || data.error) throw new Error(data.error || 'Erreur HTTP ' + res.status);
                updateActivityNotes(activityId, data.notes_ia);
            } catch (err) {
                alert('Erreur lors de la generation : ' + err.message);
                renderTable();
            }
        }

        async function generateBatch(onlyEmpty) {
            const confirmMsg = onlyEmpty
                ? 'Generer "Comment l\'IA peut aider" pour toutes les activites qui n\'en ont pas encore ? Cela appelle l\'API pour chaque activite concernee.'
                : 'Regenerer le texte pour TOUTES les activites (y compris celles qui en ont deja) ? Les textes existants seront remplaces.';
            if (!confirm(confirmMsg)) return;

            const btnEmpty = document.getElementById('btn-batch-empty');
            const btnAll = document.getElementById('btn-batch-all');
            btnEmpty.disabled = true;
            btnAll.disabled = true;
            btnEmpty.style.opacity = '0.5';
            btnAll.style.opacity = '0.5';

            const progress = document.getElementById('batch-progress');
            const status = document.getElementById('batch-status');
            const counter = document.getElementById('batch-counter');
            const bar = document.getElementById('batch-bar');
            progress.classList.remove('hidden');
            status.textContent = 'Generation en cours (cela peut prendre plusieurs secondes par activite)...';
            counter.textContent = '';
            bar.style.width = '10%';

            try {
                const res = await fetch('api/ai-notes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'generate_batch', session_id: sessionId, only_empty: onlyEmpty })
                });
                const data = await res.json();
                if (!res.ok || data.error) throw new Error(data.error || 'Erreur HTTP ' + res.status);

                bar.style.width = '100%';
                (data.results || []).forEach(r => updateActivityNotes(r.id, r.notes_ia));

                let msg = `${data.count} activite(s) generee(s) avec succes.`;
                if (data.errors && data.errors.length) {
                    msg += `\n\n${data.errors.length} erreur(s) :\n` + data.errors.map(e => `- ${e.nom} : ${e.error}`).join('\n');
                }
                status.textContent = msg;
                counter.textContent = `${data.count} / ${data.count + (data.errors?.length || 0)}`;
                setTimeout(() => progress.classList.add('hidden'), 4000);
            } catch (err) {
                status.textContent = 'Erreur : ' + err.message;
                bar.style.width = '100%';
                bar.classList.add('bg-red-600');
            } finally {
                btnEmpty.disabled = false;
                btnAll.disabled = false;
                btnEmpty.style.opacity = '1';
                btnAll.style.opacity = '1';
            }
        }

        // Modal edition
        function openEditModal(activityId) {
            const a = activites.find(x => x.id == activityId);
            if (!a || !a.notes_ia) return;
            currentEditId = activityId;
            document.getElementById('edit-notes-activity').textContent = a.nom;
            document.getElementById('edit-notes-text').value = a.notes_ia || '';
            const modal = document.getElementById('edit-notes-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeEditModal() {
            const modal = document.getElementById('edit-notes-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            currentEditId = null;
        }

        async function saveNotes() {
            if (!currentEditId) return;
            const notes = document.getElementById('edit-notes-text').value;
            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_notes_ia', id: currentEditId, notes_ia: notes })
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Erreur');
                updateActivityNotes(currentEditId, notes);
                closeEditModal();
            } catch (err) {
                alert('Erreur lors de la sauvegarde : ' + err.message);
            }
        }

        async function regenerateNotes() {
            if (!currentEditId) return;
            if (!confirm('Regenerer le texte avec l\'IA ? Les modifications non sauvegardees seront perdues.')) return;
            const textarea = document.getElementById('edit-notes-text');
            const original = textarea.value;
            textarea.value = 'Generation en cours...';
            textarea.disabled = true;
            try {
                const res = await fetch('api/ai-notes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'generate', activity_id: currentEditId })
                });
                const data = await res.json();
                if (!res.ok || data.error) throw new Error(data.error || 'Erreur');
                textarea.value = data.notes_ia;
                updateActivityNotes(currentEditId, data.notes_ia);
            } catch (err) {
                textarea.value = original;
                alert('Erreur : ' + err.message);
            } finally {
                textarea.disabled = false;
            }
        }

        // Close modal on backdrop click
        document.getElementById('edit-notes-modal').addEventListener('click', (e) => {
            if (e.target.id === 'edit-notes-modal') closeEditModal();
        });
    </script>
</body>
</html>
