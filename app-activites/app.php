<?php
/**
 * Interface principale - Inventaire des Activites
 */
require_once __DIR__ . '/config.php';
requireLoginWithSession();

$user = getLoggedUser();
$db = getDB();
$sessionId = $_SESSION['current_session_id'];

// Verifier que la session existe et est active
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND is_active = 1");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    unset($_SESSION['current_session_id']);
    header('Location: login.php');
    exit;
}

$activites = getActivites($sessionId);
$categories = getCategories();
$frequences = getFrequences();
$priorites = getPriorites();
$stats = getStatistiques($sessionId);
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('act.title') ?> - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect x='4' y='4' width='24' height='24' rx='3' fill='%23f97316'/><path d='M9 12l3 3 5-5' stroke='%23fff' stroke-width='2.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/><line x1='9' y1='20' x2='23' y2='20' stroke='%23fff' stroke-width='2' stroke-linecap='round'/><line x1='9' y1='24' x2='19' y2='24' stroke='%23fff' stroke-width='2' stroke-linecap='round'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .activity-card { transition: all 0.2s; }
        .activity-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gradient-to-br from-teal-50 to-cyan-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-teal-600 to-cyan-600 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold"><?= t('act.title') ?></h1>
                    <p class="text-teal-200 text-sm"><?= htmlspecialchars($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-teal-200"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></span>
                    <?= renderLanguageSelector('bg-teal-500 text-white border-0 rounded px-2 py-1 cursor-pointer text-sm') ?>
                    <?php if (isFormateur()): ?>
                    <a href="formateur.php" class="bg-teal-500 hover:bg-teal-400 px-3 py-1 rounded text-sm">
                        <?= t('trainer.title') ?>
                    </a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-teal-500 hover:bg-teal-400 px-3 py-1 rounded text-sm">
                        <?= t('auth.logout') ?>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
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
        </div>

        <!-- Filters and Add Button -->
        <div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap gap-4 items-center justify-between">
            <div class="flex flex-wrap gap-4">
                <select id="filter-categorie" class="border rounded-lg px-3 py-2 text-sm">
                    <option value=""><?= t('act.all_categories') ?></option>
                    <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>"><?= $cat['icon'] ?> <?= $cat['label'] ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filter-frequence" class="border rounded-lg px-3 py-2 text-sm">
                    <option value=""><?= t('act.all_frequencies') ?></option>
                    <?php foreach ($frequences as $key => $label): ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" id="filter-ia" class="rounded">
                    <?= t('act.only_ai_potential') ?>
                </label>
            </div>
            <div class="flex gap-2">
                <button onclick="exportExcel()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                    📊 Excel
                </button>
                <button onclick="exportActivites()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                    📄 HTML
                </button>
                <button onclick="openModal()" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <?= t('act.add_activity') ?>
                </button>
            </div>
        </div>

        <!-- Activities List -->
        <div id="activities-container" class="space-y-4">
            <?php if (empty($activites)): ?>
                <div class="bg-white rounded-xl shadow p-12 text-center">
                    <div class="text-6xl mb-4">📋</div>
                    <h3 class="text-xl font-medium text-gray-700 mb-2"><?= t('act.no_activities') ?></h3>
                    <p class="text-gray-500 mb-4"><?= t('act.no_activities_desc') ?></p>
                    <button onclick="openModal()" class="bg-teal-600 hover:bg-teal-700 text-white px-6 py-2 rounded-lg">
                        <?= t('act.add_first_activity') ?>
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($activites as $activite):
                    $cat = $categories[$activite['categorie']] ?? $categories['autre'];
                ?>
                <div class="activity-card bg-white rounded-xl shadow p-4"
                     data-id="<?= $activite['id'] ?>"
                     data-categorie="<?= $activite['categorie'] ?>"
                     data-frequence="<?= $activite['frequence'] ?>"
                     data-ia="<?= $activite['potentiel_ia'] ?>">
                    <div class="flex items-start gap-4">
                        <div class="text-3xl"><?= $cat['icon'] ?></div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($activite['nom']) ?></h3>
                                <?php if ($activite['potentiel_ia']): ?>
                                    <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full flex items-center gap-1">
                                        🤖 <?= t('act.ai_badge') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($activite['description']): ?>
                                <p class="text-gray-600 text-sm mb-2"><?= htmlspecialchars($activite['description']) ?></p>
                            <?php endif; ?>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="bg-<?= $cat['color'] ?>-100 text-<?= $cat['color'] ?>-700 px-2 py-1 rounded">
                                    <?= $cat['label'] ?>
                                </span>
                                <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded">
                                    <?= $frequences[$activite['frequence']] ?? $activite['frequence'] ?>
                                </span>
                                <?php if ($activite['temps_estime']): ?>
                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded">
                                        ⏱️ <?= htmlspecialchars($activite['temps_estime']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php $prio = $priorites[$activite['priorite']] ?? $priorites[2]; ?>
                                <span class="bg-<?= $prio['color'] ?>-100 text-<?= $prio['color'] ?>-700 px-2 py-1 rounded">
                                    <?= $prio['label'] ?>
                                </span>
                            </div>
                            <?php if ($activite['notes_ia']): ?>
                                <div class="mt-2 p-2 bg-green-50 rounded text-sm text-green-800">
                                    <strong>💡 <?= t('act.ai_notes') ?>:</strong> <?= htmlspecialchars($activite['notes_ia']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="editActivite(<?= $activite['id'] ?>)" class="text-gray-400 hover:text-teal-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button onclick="deleteActivite(<?= $activite['id'] ?>)" class="text-gray-400 hover:text-red-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Add/Edit Activity -->
    <div id="modal" class="modal fixed inset-0 bg-black/50 items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b">
                <h2 id="modal-title" class="text-xl font-bold text-gray-800"><?= t('act.add_activity') ?></h2>
            </div>
            <form id="activity-form" class="p-6 space-y-4">
                <input type="hidden" id="activity-id" value="">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('act.activity_name') ?> *</label>
                    <input type="text" id="activity-nom" required
                           class="w-full border rounded-lg px-3 py-2"
                           placeholder="<?= t('act.activity_name_placeholder') ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('act.description') ?></label>
                    <textarea id="activity-description" rows="2"
                              class="w-full border rounded-lg px-3 py-2"
                              placeholder="<?= t('act.description_placeholder') ?>"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('act.category') ?></label>
                        <select id="activity-categorie" class="w-full border rounded-lg px-3 py-2">
                            <?php foreach ($categories as $key => $cat): ?>
                                <option value="<?= $key ?>"><?= $cat['icon'] ?> <?= $cat['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('act.frequency') ?></label>
                        <select id="activity-frequence" class="w-full border rounded-lg px-3 py-2">
                            <?php foreach ($frequences as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('act.time_estimate') ?></label>
                        <input type="text" id="activity-temps"
                               class="w-full border rounded-lg px-3 py-2"
                               placeholder="<?= t('act.time_placeholder') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('act.priority') ?></label>
                        <select id="activity-priorite" class="w-full border rounded-lg px-3 py-2">
                            <?php foreach ($priorites as $key => $prio): ?>
                                <option value="<?= $key ?>"><?= $prio['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="bg-green-50 rounded-lg p-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="activity-ia" class="rounded text-green-600">
                        <span class="font-medium text-green-800">🤖 <?= t('act.ai_potential_label') ?></span>
                    </label>
                    <div id="ia-notes-container" class="mt-3 hidden">
                        <label class="block text-sm font-medium text-green-700 mb-1"><?= t('act.ai_how') ?></label>
                        <textarea id="activity-notes-ia" rows="2"
                                  class="w-full border border-green-200 rounded-lg px-3 py-2"
                                  placeholder="<?= t('act.ai_how_placeholder') ?>"></textarea>
                    </div>
                </div>
            </form>
            <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                <button onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <?= t('common.cancel') ?>
                </button>
                <button onclick="saveActivite()" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg">
                    <?= t('common.save') ?>
                </button>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>

    <script>
        const sessionId = <?= $sessionId ?>;
        const activitesData = <?= json_encode($activites) ?>;
        const categories = <?= json_encode($categories) ?>;
        const frequences = <?= json_encode($frequences) ?>;

        // Modal functions
        function openModal(id = null) {
            document.getElementById('modal').classList.add('active');
            document.getElementById('activity-id').value = id || '';
            document.getElementById('modal-title').textContent = id ? <?= json_encode(t('act.edit_activity')) ?> : <?= json_encode(t('act.add_activity')) ?>;

            if (id) {
                const activite = activitesData.find(a => a.id == id);
                if (activite) {
                    document.getElementById('activity-nom').value = activite.nom;
                    document.getElementById('activity-description').value = activite.description || '';
                    document.getElementById('activity-categorie').value = activite.categorie;
                    document.getElementById('activity-frequence').value = activite.frequence;
                    document.getElementById('activity-temps').value = activite.temps_estime || '';
                    document.getElementById('activity-priorite').value = activite.priorite;
                    document.getElementById('activity-ia').checked = activite.potentiel_ia == 1;
                    document.getElementById('activity-notes-ia').value = activite.notes_ia || '';
                    toggleIaNotes();
                }
            } else {
                document.getElementById('activity-form').reset();
                document.getElementById('ia-notes-container').classList.add('hidden');
            }
        }

        function closeModal() {
            document.getElementById('modal').classList.remove('active');
        }

        function editActivite(id) {
            openModal(id);
        }

        // Toggle IA notes visibility
        document.getElementById('activity-ia').addEventListener('change', toggleIaNotes);
        function toggleIaNotes() {
            const container = document.getElementById('ia-notes-container');
            if (document.getElementById('activity-ia').checked) {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }

        // Save activity
        function saveActivite() {
            const nom = document.getElementById('activity-nom').value.trim();
            if (!nom) {
                alert(<?= json_encode(t('auth.fill_required')) ?>);
                return;
            }

            const id = document.getElementById('activity-id').value;
            const data = {
                action: id ? 'update' : 'create',
                id: id,
                session_id: sessionId,
                nom: nom,
                description: document.getElementById('activity-description').value,
                categorie: document.getElementById('activity-categorie').value,
                frequence: document.getElementById('activity-frequence').value,
                temps_estime: document.getElementById('activity-temps').value,
                priorite: document.getElementById('activity-priorite').value,
                potentiel_ia: document.getElementById('activity-ia').checked ? 1 : 0,
                notes_ia: document.getElementById('activity-notes-ia').value
            };

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(result => {
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Erreur');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Erreur: ' + err.message);
            });
        }

        // Delete activity
        function deleteActivite(id) {
            if (confirm(<?= json_encode(t('act.confirm_delete')) ?>)) {
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: id })
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        document.querySelector(`[data-id="${id}"]`).remove();
                    }
                });
            }
        }

        // Filters
        function applyFilters() {
            const categorie = document.getElementById('filter-categorie').value;
            const frequence = document.getElementById('filter-frequence').value;
            const iaOnly = document.getElementById('filter-ia').checked;

            document.querySelectorAll('.activity-card').forEach(card => {
                let show = true;
                if (categorie && card.dataset.categorie !== categorie) show = false;
                if (frequence && card.dataset.frequence !== frequence) show = false;
                if (iaOnly && card.dataset.ia !== '1') show = false;
                card.style.display = show ? '' : 'none';
            });
        }

        document.getElementById('filter-categorie').addEventListener('change', applyFilters);
        document.getElementById('filter-frequence').addEventListener('change', applyFilters);
        document.getElementById('filter-ia').addEventListener('change', applyFilters);

        // Export
        function exportActivites() {
            window.open('api.php?action=export&session_id=' + sessionId, '_blank');
        }

        function exportExcel() {
            window.open('api.php?action=export-excel&session_id=' + sessionId, '_blank');
        }

        // Close modal on backdrop click
        document.getElementById('modal').addEventListener('click', (e) => {
            if (e.target.id === 'modal') closeModal();
        });
    </script>
</body>
</html>
