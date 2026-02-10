<?php
/**
 * Application - Carte d'identite du Projet
 */
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user = getLoggedUser();
$sessionId = $_SESSION['current_session_id'];

// Recuperer ou creer la fiche
$stmt = $db->prepare("SELECT * FROM fiches WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$fiche = $stmt->fetch();

if (!$fiche) {
    $stmt = $db->prepare("INSERT INTO fiches (user_id, session_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $sessionId]);
    $fiche = [
        'id' => $db->lastInsertId(),
        'titre' => '',
        'objectifs' => '',
        'public_cible' => '',
        'territoire' => '',
        'partenaires' => '[]',
        'ressources_humaines' => '',
        'ressources_materielles' => '',
        'ressources_financieres' => '',
        'calendrier' => '',
        'resultats' => '',
        'notes' => '',
        'is_shared' => 0
    ];
}

$partenaires = json_decode($fiche['partenaires'] ?? '[]', true) ?: [];
$isSubmitted = !empty($fiche['is_shared']);
$lang = getCurrentLanguage();

// Recuperer infos session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('cip.title') ?> - <?= h($session['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .field-card { transition: all 0.2s ease; }
        .field-card:focus-within { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="p-4 md:p-8">
    <!-- Header -->
    <header class="max-w-4xl mx-auto mb-4 no-print">
        <div class="bg-white/90 backdrop-blur rounded-lg shadow-lg px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🗂️</span>
                <div>
                    <div class="font-bold text-gray-800"><?= h($user['prenom'] ?? $user['username']) ?> <?= h($user['nom'] ?? '') ?></div>
                    <div class="text-sm text-gray-500">Session: <?= h($session['code'] ?? '') ?></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <?= renderLanguageSelector('text-sm border rounded px-2 py-1') ?>
                <span id="saveStatus" class="text-sm text-gray-500"></span>
                <a href="login.php?logout=1" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded text-sm">
                    <?= t('auth.logout') ?>
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-2xl p-6 md:p-8">
        <div class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">🗂️ <?= t('cip.title') ?></h1>
            <p class="text-gray-600 italic"><?= t('cip.subtitle') ?></p>
        </div>

        <form id="projectForm" class="space-y-6">
            <!-- Titre du projet -->
            <div class="field-card bg-purple-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    🏷️ <?= t('cip.project_title') ?>
                </label>
                <input
                    type="text"
                    id="titre"
                    value="<?= h($fiche['titre'] ?? '') ?>"
                    class="w-full px-4 py-2 border-2 border-purple-200 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="<?= t('cip.project_title_placeholder') ?>"
                    <?= $isSubmitted ? 'readonly' : '' ?>
                >
            </div>

            <!-- Objectifs -->
            <div class="field-card bg-blue-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    🎯 <?= t('cip.objectives') ?>
                </label>
                <p class="text-sm text-gray-600 mb-2 italic"><?= t('cip.objectives_hint') ?></p>
                <textarea
                    id="objectifs"
                    rows="4"
                    class="w-full px-4 py-2 border-2 border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="<?= t('cip.objectives_placeholder') ?>"
                    <?= $isSubmitted ? 'readonly' : '' ?>
                ><?= h($fiche['objectifs'] ?? '') ?></textarea>
            </div>

            <!-- Public cible -->
            <div class="field-card bg-green-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    👥 <?= t('cip.target_audience') ?>
                </label>
                <p class="text-sm text-gray-600 mb-2 italic"><?= t('cip.target_audience_hint') ?></p>
                <textarea
                    id="publicCible"
                    rows="3"
                    class="w-full px-4 py-2 border-2 border-green-200 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    placeholder="<?= t('cip.target_audience_placeholder') ?>"
                    <?= $isSubmitted ? 'readonly' : '' ?>
                ><?= h($fiche['public_cible'] ?? '') ?></textarea>
            </div>

            <!-- Zone d'action -->
            <div class="field-card bg-yellow-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    📍 <?= t('cip.territory') ?>
                </label>
                <input
                    type="text"
                    id="territoire"
                    value="<?= h($fiche['territoire'] ?? '') ?>"
                    class="w-full px-4 py-2 border-2 border-yellow-200 rounded-md focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                    placeholder="<?= t('cip.territory_placeholder') ?>"
                    <?= $isSubmitted ? 'readonly' : '' ?>
                >
            </div>

            <!-- Partenaires -->
            <div class="field-card bg-pink-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    🤝 <?= t('cip.partners') ?>
                </label>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border-2 border-pink-200 mb-3">
                        <thead class="bg-pink-100">
                            <tr>
                                <th class="border border-pink-200 px-3 py-2 text-left text-sm font-semibold"><?= t('cip.partner_name') ?></th>
                                <th class="border border-pink-200 px-3 py-2 text-left text-sm font-semibold"><?= t('cip.partner_role') ?></th>
                                <th class="border border-pink-200 px-3 py-2 text-left text-sm font-semibold"><?= t('cip.partner_contact') ?></th>
                                <?php if (!$isSubmitted): ?>
                                <th class="border border-pink-200 px-3 py-2 text-center text-sm font-semibold no-print">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="partenairesList">
                            <?php if (empty($partenaires)): ?>
                            <tr class="partenaire-row">
                                <td class="border border-pink-200 p-2">
                                    <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded partenaire-structure" placeholder="<?= t('cip.partner_name_placeholder') ?>" <?= $isSubmitted ? 'readonly' : '' ?>>
                                </td>
                                <td class="border border-pink-200 p-2">
                                    <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded partenaire-role" placeholder="<?= t('cip.partner_role_placeholder') ?>" <?= $isSubmitted ? 'readonly' : '' ?>>
                                </td>
                                <td class="border border-pink-200 p-2">
                                    <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded partenaire-contact" placeholder="<?= t('cip.partner_contact_placeholder') ?>" <?= $isSubmitted ? 'readonly' : '' ?>>
                                </td>
                                <?php if (!$isSubmitted): ?>
                                <td class="border border-pink-200 p-2 text-center no-print">
                                    <button type="button" onclick="removePartenaireRow(this)" class="text-red-500 hover:text-red-700 text-sm">❌</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($partenaires as $p): ?>
                            <tr class="partenaire-row">
                                <td class="border border-pink-200 p-2">
                                    <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded partenaire-structure" value="<?= h($p['structure'] ?? '') ?>" placeholder="<?= t('cip.partner_name_placeholder') ?>" <?= $isSubmitted ? 'readonly' : '' ?>>
                                </td>
                                <td class="border border-pink-200 p-2">
                                    <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded partenaire-role" value="<?= h($p['role'] ?? '') ?>" placeholder="<?= t('cip.partner_role_placeholder') ?>" <?= $isSubmitted ? 'readonly' : '' ?>>
                                </td>
                                <td class="border border-pink-200 p-2">
                                    <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded partenaire-contact" value="<?= h($p['contact'] ?? '') ?>" placeholder="<?= t('cip.partner_contact_placeholder') ?>" <?= $isSubmitted ? 'readonly' : '' ?>>
                                </td>
                                <?php if (!$isSubmitted): ?>
                                <td class="border border-pink-200 p-2 text-center no-print">
                                    <button type="button" onclick="removePartenaireRow(this)" class="text-red-500 hover:text-red-700 text-sm">❌</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!$isSubmitted): ?>
                <button
                    type="button"
                    onclick="addPartenaireRow()"
                    class="no-print bg-pink-500 text-white px-4 py-2 rounded-md hover:bg-pink-600 transition text-sm"
                >
                    ➕ <?= t('cip.add_partner') ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Ressources -->
            <div class="field-card bg-indigo-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-3">
                    💰 <?= t('cip.resources') ?>
                </label>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('cip.resources_human') ?> :</label>
                        <input
                            type="text"
                            id="ressourcesHumaines"
                            value="<?= h($fiche['ressources_humaines'] ?? '') ?>"
                            class="w-full px-3 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500"
                            placeholder="<?= t('cip.resources_human_placeholder') ?>"
                            <?= $isSubmitted ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('cip.resources_material') ?> :</label>
                        <input
                            type="text"
                            id="ressourcesMaterielles"
                            value="<?= h($fiche['ressources_materielles'] ?? '') ?>"
                            class="w-full px-3 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500"
                            placeholder="<?= t('cip.resources_material_placeholder') ?>"
                            <?= $isSubmitted ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= t('cip.resources_financial') ?> :</label>
                        <input
                            type="text"
                            id="ressourcesFinancieres"
                            value="<?= h($fiche['ressources_financieres'] ?? '') ?>"
                            class="w-full px-3 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500"
                            placeholder="<?= t('cip.resources_financial_placeholder') ?>"
                            <?= $isSubmitted ? 'readonly' : '' ?>
                        >
                    </div>
                </div>
            </div>

            <!-- Calendrier -->
            <div class="field-card bg-orange-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    🗓️ <?= t('cip.calendar') ?>
                </label>
                <input
                    type="text"
                    id="calendrier"
                    value="<?= h($fiche['calendrier'] ?? '') ?>"
                    class="w-full px-4 py-2 border-2 border-orange-200 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    placeholder="<?= t('cip.calendar_placeholder') ?>"
                    <?= $isSubmitted ? 'readonly' : '' ?>
                >
            </div>

            <!-- Resultats attendus -->
            <div class="field-card bg-teal-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    📈 <?= t('cip.expected_results') ?>
                </label>
                <p class="text-sm text-gray-600 mb-2 italic"><?= t('cip.expected_results_hint') ?></p>
                <textarea
                    id="resultats"
                    rows="4"
                    class="w-full px-4 py-2 border-2 border-teal-200 rounded-md focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                    placeholder="<?= t('cip.expected_results_placeholder') ?>"
                    <?= $isSubmitted ? 'readonly' : '' ?>
                ><?= h($fiche['resultats'] ?? '') ?></textarea>
            </div>

            <!-- Notes -->
            <div class="field-card bg-gray-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    ✏️ <?= t('cip.notes') ?>
                </label>
                <textarea
                    id="notes"
                    rows="3"
                    class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500 focus:border-transparent"
                    placeholder="<?= t('cip.notes_placeholder') ?>"
                    <?= $isSubmitted ? 'readonly' : '' ?>
                ><?= h($fiche['notes'] ?? '') ?></textarea>
            </div>

            <!-- Boutons d'action -->
            <div class="no-print flex flex-wrap gap-3 pt-4 border-t-2 border-gray-200">
                <button
                    type="button"
                    onclick="exportToExcel()"
                    class="bg-emerald-600 text-white px-6 py-3 rounded-md hover:bg-emerald-700 transition font-semibold shadow-md"
                >
                    📊 <?= t('common.export_excel') ?>
                </button>
                <button
                    type="button"
                    onclick="window.print()"
                    class="bg-purple-600 text-white px-6 py-3 rounded-md hover:bg-purple-700 transition font-semibold shadow-md"
                >
                    🖨️ <?= t('common.print') ?>
                </button>
                <?php if (!$isSubmitted): ?>
                <button
                    type="button"
                    onclick="submitFiche()"
                    class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition font-semibold shadow-md ml-auto"
                >
                    ✅ <?= t('common.share_trainer') ?>
                </button>
                <?php else: ?>
                <span class="ml-auto bg-green-100 text-green-800 px-4 py-3 rounded-md font-semibold">
                    ✅ <?= t('common.shared') ?>
                </span>
                <?php endif; ?>
            </div>
        </form>

        <div class="no-print mt-6 p-4 bg-blue-100 border-l-4 border-blue-500 rounded-md">
            <p class="text-sm text-blue-800">
                <strong>💡 <?= t('common.tip') ?> :</strong> <?= t('cip.autosave_info') ?>
            </p>
        </div>
    </div>

    <?= renderLanguageScript() ?>

    <script>
        const isSubmitted = <?= $isSubmitted ? 'true' : 'false' ?>;
        let saveTimeout = null;

        // Autosave on input
        document.querySelectorAll('input, textarea').forEach(el => {
            el.addEventListener('input', scheduleSave);
        });

        function scheduleSave() {
            if (isSubmitted) return;
            document.getElementById('saveStatus').textContent = '<?= t('common.editing') ?>...';
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveForm, 1500);
        }

        function getPartenaires() {
            const rows = document.querySelectorAll('.partenaire-row');
            const partenaires = [];
            rows.forEach(row => {
                const structure = row.querySelector('.partenaire-structure')?.value || '';
                const role = row.querySelector('.partenaire-role')?.value || '';
                const contact = row.querySelector('.partenaire-contact')?.value || '';
                if (structure || role || contact) {
                    partenaires.push({ structure, role, contact });
                }
            });
            return partenaires;
        }

        function addPartenaireRow() {
            const tbody = document.getElementById('partenairesList');
            const row = document.createElement('tr');
            row.className = 'partenaire-row';
            row.innerHTML = `
                <td class="border border-pink-200 p-2">
                    <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded partenaire-structure" placeholder="<?= t('cip.partner_name_placeholder') ?>">
                </td>
                <td class="border border-pink-200 p-2">
                    <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded partenaire-role" placeholder="<?= t('cip.partner_role_placeholder') ?>">
                </td>
                <td class="border border-pink-200 p-2">
                    <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded partenaire-contact" placeholder="<?= t('cip.partner_contact_placeholder') ?>">
                </td>
                <td class="border border-pink-200 p-2 text-center no-print">
                    <button type="button" onclick="removePartenaireRow(this)" class="text-red-500 hover:text-red-700 text-sm">❌</button>
                </td>
            `;
            tbody.appendChild(row);
            row.querySelectorAll('input').forEach(el => el.addEventListener('input', scheduleSave));
        }

        function removePartenaireRow(btn) {
            const tbody = document.getElementById('partenairesList');
            if (tbody.children.length > 1) {
                btn.closest('tr').remove();
                scheduleSave();
            }
        }

        async function saveForm() {
            if (isSubmitted) return;

            const data = {
                titre: document.getElementById('titre').value,
                objectifs: document.getElementById('objectifs').value,
                public_cible: document.getElementById('publicCible').value,
                territoire: document.getElementById('territoire').value,
                partenaires: getPartenaires(),
                ressources_humaines: document.getElementById('ressourcesHumaines').value,
                ressources_materielles: document.getElementById('ressourcesMaterielles').value,
                ressources_financieres: document.getElementById('ressourcesFinancieres').value,
                calendrier: document.getElementById('calendrier').value,
                resultats: document.getElementById('resultats').value,
                notes: document.getElementById('notes').value
            };

            try {
                document.getElementById('saveStatus').textContent = '<?= t('common.saving') ?>...';
                const response = await fetch('api/save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('saveStatus').textContent = '<?= t('common.saved') ?> ✓';
                    setTimeout(() => {
                        document.getElementById('saveStatus').textContent = '';
                    }, 2000);
                } else {
                    document.getElementById('saveStatus').textContent = '<?= t('common.error') ?>';
                }
            } catch (e) {
                document.getElementById('saveStatus').textContent = '<?= t('common.error') ?>';
                console.error(e);
            }
        }

        async function submitFiche() {
            if (!confirm('<?= t('common.confirm_share') ?>')) return;

            await saveForm();

            try {
                const response = await fetch('api/save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ submit: true })
                });
                const result = await response.json();
                if (result.success) {
                    alert('<?= t('common.shared_success') ?>');
                    location.reload();
                }
            } catch (e) {
                alert('<?= t('common.error') ?>');
            }
        }

        function exportToExcel() {
            const wb = XLSX.utils.book_new();

            const data = [
                ['🗂️ CARTE D\'IDENTITE DU PROJET'],
                [''],
                ['Section', 'Contenu'],
                ['🏷️ Titre du projet', document.getElementById('titre').value],
                [''],
                ['🎯 Objectifs du projet', document.getElementById('objectifs').value],
                [''],
                ['👥 Public(s) cible(s)', document.getElementById('publicCible').value],
                [''],
                ['📍 Zone d\'action / Territoire', document.getElementById('territoire').value],
                [''],
                ['🗓️ Calendrier previsionnel', document.getElementById('calendrier').value],
                [''],
                ['📈 Resultats attendus', document.getElementById('resultats').value],
                [''],
                ['✏️ Notes complementaires', document.getElementById('notes').value]
            ];

            const wsInfo = XLSX.utils.aoa_to_sheet(data);
            wsInfo['!cols'] = [{ wch: 30 }, { wch: 80 }];

            // Partenaires
            const partenaires = getPartenaires();
            const partData = [
                ['🤝 PARTENAIRES IMPLIQUES'],
                [''],
                ['Structure / Organisme', 'Role ou contribution', 'Contact de reference']
            ];
            partenaires.forEach(p => partData.push([p.structure, p.role, p.contact]));

            const wsPart = XLSX.utils.aoa_to_sheet(partData);
            wsPart['!cols'] = [{ wch: 30 }, { wch: 40 }, { wch: 30 }];

            // Ressources
            const resData = [
                ['💰 RESSOURCES MOBILISABLES'],
                [''],
                ['Type de ressource', 'Description'],
                ['Humaines', document.getElementById('ressourcesHumaines').value],
                ['Materielles', document.getElementById('ressourcesMaterielles').value],
                ['Financieres', document.getElementById('ressourcesFinancieres').value]
            ];

            const wsRes = XLSX.utils.aoa_to_sheet(resData);
            wsRes['!cols'] = [{ wch: 20 }, { wch: 80 }];

            XLSX.utils.book_append_sheet(wb, wsInfo, 'Informations generales');
            XLSX.utils.book_append_sheet(wb, wsPart, 'Partenaires');
            XLSX.utils.book_append_sheet(wb, wsRes, 'Ressources');

            const titre = document.getElementById('titre').value || 'projet';
            const filename = `carte_identite_${titre.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, filename);
        }
    </script>
</body>
</html>
