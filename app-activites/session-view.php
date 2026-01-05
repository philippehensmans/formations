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
                    <td class="p-3 text-gray-600 text-xs max-w-xs truncate" title="${escapeHtml(a.notes_ia || '')}">
                        ${a.notes_ia ? escapeHtml(a.notes_ia) : ''}
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
    </script>
</body>
</html>
