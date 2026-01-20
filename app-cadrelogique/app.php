<?php
/**
 * Interface de travail - Cadre Logique (Multilingue)
 */
require_once 'config/database.php';
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';
requireParticipant();

$db = getDB();
$participant = getCurrentParticipant();
$lang = getCurrentLanguage();

// Verifier que le participant existe
if (!$participant) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Charger le cadre logique
$stmt = $db->prepare("SELECT * FROM cadre_logique WHERE participant_id = ?");
$stmt->execute([$participant['id']]);
$cadre = $stmt->fetch();

if (!$cadre) {
    // Creer si n'existe pas
    $stmt = $db->prepare("INSERT INTO cadre_logique (participant_id, session_id, matrice_data) VALUES (?, ?, ?)");
    $stmt->execute([$participant['id'], $participant['session_id'], json_encode(getEmptyMatrice())]);
    $stmt = $db->prepare("SELECT * FROM cadre_logique WHERE participant_id = ?");
    $stmt->execute([$participant['id']]);
    $cadre = $stmt->fetch();
}

$matrice = json_decode($cadre['matrice_data'], true) ?: getEmptyMatrice();
$isSubmitted = $cadre['is_submitted'] == 1;

// Preparer les traductions pour JavaScript
$jsTranslations = [
    'saving' => t('cadrelogique.saving'),
    'saved' => t('cadrelogique.saved'),
    'save_error' => t('cadrelogique.save_error'),
    'completion' => t('cadrelogique.completion'),
    'submitted' => t('cadrelogique.submitted'),
    'confirm_remove_result' => t('cadrelogique.confirm_remove_result'),
    'confirm_submit' => t('cadrelogique.confirm_submit'),
    'result' => t('cadrelogique.result'),
    'activity' => t('cadrelogique.activity'),
    'add_activity_for' => t('cadrelogique.add_activity') . ' R',
    'result_desc_placeholder' => t('cadrelogique.result_desc_placeholder'),
    'result_indicators_placeholder' => t('cadrelogique.result_indicators_placeholder'),
    'sources_placeholder' => t('cadrelogique.sources_placeholder'),
    'hypotheses_placeholder' => t('cadrelogique.hypotheses_placeholder'),
    'activity_placeholder' => t('cadrelogique.activity_placeholder'),
    'at_least_one_result' => $lang === 'fr' ? 'Il doit y avoir au moins un resultat' :
        ($lang === 'en' ? 'There must be at least one result' :
        ($lang === 'es' ? 'Debe haber al menos un resultado' : 'Obstajati mora vsaj en rezultat')),
    'at_least_one_activity' => $lang === 'fr' ? 'Il doit y avoir au moins une activite par resultat' :
        ($lang === 'en' ? 'There must be at least one activity per result' :
        ($lang === 'es' ? 'Debe haber al menos una actividad por resultado' : 'Na rezultat mora biti vsaj ena aktivnost')),
];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('cadrelogique.title') ?> - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .help-tooltip { display: none; position: absolute; z-index: 100; }
        .help-btn:hover + .help-tooltip { display: block; }
        textarea { min-height: 80px; resize: vertical; }
        .niveau-og { background: linear-gradient(to right, #dbeafe, #e0e7ff); }
        .niveau-os { background: linear-gradient(to right, #dcfce7, #d1fae5); }
        .niveau-r { background: linear-gradient(to right, #fef9c3, #fef3c7); }
        .niveau-a { background: linear-gradient(to right, #fee2e2, #fecaca); }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
            textarea { border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Barre utilisateur -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></span>
                <span class="text-blue-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?></span>
            </div>
            <div class="flex items-center gap-4">
                <?= renderLanguageSelector('text-sm bg-white/20 border-0 rounded px-2 py-1 text-white') ?>
                <button onclick="manualSave()" class="text-sm bg-green-500 hover:bg-green-600 px-3 py-1 rounded font-medium">
                    <?= t('common.save') ?>
                </button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-white/20">
                    <?= $isSubmitted ? t('cadrelogique.submitted') : t('cadrelogique.draft') ?>
                </span>
                <span id="completion" class="text-sm"><?= t('cadrelogique.completion') ?>: <strong><?= $cadre['completion_percent'] ?>%</strong></span>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded"><?= t('trainer.title') ?></a>
                <?php endif; ?>
                <a href="logout.php" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded"><?= t('auth.logout') ?></a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <!-- En-tete du projet -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4"><?= t('cadrelogique.app_title') ?></h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-1"><?= t('cadrelogique.project_title') ?> *</label>
                    <input type="text" id="titre_projet" data-field="titre_projet"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none"
                        value="<?= sanitize($cadre['titre_projet']) ?>"
                        oninput="scheduleAutoSave()">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1"><?= t('cadrelogique.organisation') ?></label>
                    <input type="text" id="organisation" data-field="organisation"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none"
                        value="<?= sanitize($cadre['organisation']) ?>"
                        oninput="scheduleAutoSave()">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1"><?= t('cadrelogique.geo_zone') ?></label>
                    <input type="text" id="zone_geo" data-field="zone_geo"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none"
                        value="<?= sanitize($cadre['zone_geo']) ?>"
                        oninput="scheduleAutoSave()">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1"><?= t('cadrelogique.duration') ?></label>
                    <input type="text" id="duree" data-field="duree"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none"
                        placeholder="<?= t('cadrelogique.duration_placeholder') ?>"
                        value="<?= sanitize($cadre['duree']) ?>"
                        oninput="scheduleAutoSave()">
                </div>
            </div>
        </div>

        <!-- Matrice du cadre logique -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
            <div class="bg-gray-800 text-white p-4">
                <h2 class="text-xl font-bold"><?= t('cadrelogique.matrix') ?></h2>
            </div>

            <!-- En-tetes des colonnes -->
            <div class="grid grid-cols-12 gap-px bg-gray-300 text-sm font-bold">
                <div class="col-span-1 bg-gray-100 p-2 text-center"><?= t('cadrelogique.level') ?></div>
                <div class="col-span-3 bg-gray-100 p-2 relative">
                    <?= t('cadrelogique.narrative') ?>
                    <button class="help-btn ml-1 text-blue-500">?</button>
                    <div class="help-tooltip bg-blue-50 border border-blue-200 p-3 rounded-lg shadow-lg w-80 text-sm font-normal">
                        <?= t('cadrelogique.narrative_help') ?>
                    </div>
                </div>
                <div class="col-span-3 bg-gray-100 p-2 relative">
                    <?= t('cadrelogique.indicators') ?>
                    <button class="help-btn ml-1 text-blue-500">?</button>
                    <div class="help-tooltip bg-blue-50 border border-blue-200 p-3 rounded-lg shadow-lg w-80 text-sm font-normal">
                        <?= t('cadrelogique.indicators_help') ?>
                    </div>
                </div>
                <div class="col-span-2 bg-gray-100 p-2 relative">
                    <?= t('cadrelogique.sources') ?>
                    <button class="help-btn ml-1 text-blue-500">?</button>
                    <div class="help-tooltip bg-blue-50 border border-blue-200 p-3 rounded-lg shadow-lg w-80 text-sm font-normal">
                        <?= t('cadrelogique.sources_help') ?>
                    </div>
                </div>
                <div class="col-span-3 bg-gray-100 p-2 relative">
                    <?= t('cadrelogique.hypotheses') ?>
                    <button class="help-btn ml-1 text-blue-500">?</button>
                    <div class="help-tooltip bg-blue-50 border border-blue-200 p-3 rounded-lg shadow-lg w-80 text-sm font-normal">
                        <?= t('cadrelogique.hypotheses_help') ?>
                    </div>
                </div>
            </div>

            <!-- Objectif Global -->
            <div class="grid grid-cols-12 gap-px bg-gray-300 niveau-og">
                <div class="col-span-1 bg-blue-100 p-2 flex items-center justify-center font-bold text-blue-800 text-sm text-center">
                    <?= t('cadrelogique.global_objective') ?>
                </div>
                <div class="col-span-3 bg-blue-50 p-2">
                    <textarea id="og_description" class="w-full p-2 border rounded resize-y"
                        placeholder="<?= t('cadrelogique.global_desc_placeholder') ?>"
                        oninput="scheduleAutoSave()"><?= sanitize($matrice['objectif_global']['description'] ?? '') ?></textarea>
                </div>
                <div class="col-span-3 bg-blue-50 p-2">
                    <textarea id="og_indicateurs" class="w-full p-2 border rounded resize-y"
                        placeholder="<?= t('cadrelogique.global_indicators_placeholder') ?>"
                        oninput="scheduleAutoSave()"><?= sanitize($matrice['objectif_global']['indicateurs'] ?? '') ?></textarea>
                </div>
                <div class="col-span-2 bg-blue-50 p-2">
                    <textarea id="og_sources" class="w-full p-2 border rounded resize-y"
                        placeholder="<?= t('cadrelogique.sources_placeholder') ?>"
                        oninput="scheduleAutoSave()"><?= sanitize($matrice['objectif_global']['sources'] ?? '') ?></textarea>
                </div>
                <div class="col-span-3 bg-blue-50 p-2">
                    <textarea id="og_hypotheses" class="w-full p-2 border rounded resize-y"
                        placeholder="<?= t('cadrelogique.hypotheses_placeholder') ?>"
                        oninput="scheduleAutoSave()"><?= sanitize($matrice['objectif_global']['hypotheses'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Objectif Specifique -->
            <div class="grid grid-cols-12 gap-px bg-gray-300 niveau-os">
                <div class="col-span-1 bg-green-100 p-2 flex items-center justify-center font-bold text-green-800 text-sm text-center">
                    <?= t('cadrelogique.specific_objective') ?>
                </div>
                <div class="col-span-3 bg-green-50 p-2">
                    <textarea id="os_description" class="w-full p-2 border rounded resize-y"
                        placeholder="<?= t('cadrelogique.specific_desc_placeholder') ?>"
                        oninput="scheduleAutoSave()"><?= sanitize($matrice['objectif_specifique']['description'] ?? '') ?></textarea>
                </div>
                <div class="col-span-3 bg-green-50 p-2">
                    <textarea id="os_indicateurs" class="w-full p-2 border rounded resize-y"
                        placeholder="<?= t('cadrelogique.specific_indicators_placeholder') ?>"
                        oninput="scheduleAutoSave()"><?= sanitize($matrice['objectif_specifique']['indicateurs'] ?? '') ?></textarea>
                </div>
                <div class="col-span-2 bg-green-50 p-2">
                    <textarea id="os_sources" class="w-full p-2 border rounded resize-y"
                        placeholder="<?= t('cadrelogique.sources_placeholder') ?>"
                        oninput="scheduleAutoSave()"><?= sanitize($matrice['objectif_specifique']['sources'] ?? '') ?></textarea>
                </div>
                <div class="col-span-3 bg-green-50 p-2">
                    <textarea id="os_hypotheses" class="w-full p-2 border rounded resize-y"
                        placeholder="<?= t('cadrelogique.hypotheses_critical_placeholder') ?>"
                        oninput="scheduleAutoSave()"><?= sanitize($matrice['objectif_specifique']['hypotheses'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Resultats et Activites (dynamiques) -->
            <div id="resultats-container">
                <!-- Genere par JavaScript -->
            </div>

            <!-- Bouton ajouter resultat -->
            <div class="p-4 bg-gray-50 border-t no-print">
                <button onclick="addResultat()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium">
                    + <?= t('cadrelogique.add_result') ?>
                </button>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6 no-print">
            <div class="flex flex-wrap gap-4 justify-between items-center">
                <div class="flex gap-3">
                    <button onclick="submitCadre()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium">
                        <?= $isSubmitted ? t('cadrelogique.already_submitted') : t('cadrelogique.submit_cadre') ?>
                    </button>
                    <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        <?= t('cadrelogique.print_pdf') ?>
                    </button>
                    <button onclick="exportJSON()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <?= t('cadrelogique.export_json') ?>
                    </button>
                </div>
                <div class="flex gap-3">
                    <select id="templateSelect" class="border-2 rounded-lg px-3 py-2">
                        <option value=""><?= t('cadrelogique.load_template') ?></option>
                        <option value="sante_ist">Exemple: Sante IST</option>
                    </select>
                    <button onclick="loadTemplate()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                        <?= t('cadrelogique.load') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>
    <script>
    // Traductions pour JavaScript
    const T = <?= json_encode($jsTranslations) ?>;

    // Donnees initiales
    let matrice = <?= json_encode($matrice) ?>;
    let autoSaveTimeout = null;

    // Initialisation
    document.addEventListener('DOMContentLoaded', function() {
        renderResultats();
    });

    // Rendu des resultats et activites
    function renderResultats() {
        const container = document.getElementById('resultats-container');
        container.innerHTML = '';

        matrice.resultats.forEach((resultat, rIndex) => {
            const rNum = rIndex + 1;
            const resultatHtml = `
                <div class="resultat-block" data-resultat="${rIndex}">
                    <!-- Resultat -->
                    <div class="grid grid-cols-12 gap-px bg-gray-300 niveau-r">
                        <div class="col-span-1 bg-yellow-100 p-2 flex flex-col items-center justify-center font-bold text-yellow-800 text-sm">
                            <span>R${rNum}</span>
                            <button onclick="removeResultat(${rIndex})" class="mt-2 text-red-500 hover:text-red-700 text-xs no-print">X</button>
                        </div>
                        <div class="col-span-3 bg-yellow-50 p-2">
                            <textarea class="w-full p-2 border rounded resize-y" data-path="resultats.${rIndex}.description"
                                placeholder="${T.result_desc_placeholder}"
                                oninput="updateMatrice(this); scheduleAutoSave()">${sanitizeJS(resultat.description || '')}</textarea>
                        </div>
                        <div class="col-span-3 bg-yellow-50 p-2">
                            <textarea class="w-full p-2 border rounded resize-y" data-path="resultats.${rIndex}.indicateurs"
                                placeholder="${T.result_indicators_placeholder}"
                                oninput="updateMatrice(this); scheduleAutoSave()">${sanitizeJS(resultat.indicateurs || '')}</textarea>
                        </div>
                        <div class="col-span-2 bg-yellow-50 p-2">
                            <textarea class="w-full p-2 border rounded resize-y" data-path="resultats.${rIndex}.sources"
                                placeholder="${T.sources_placeholder}"
                                oninput="updateMatrice(this); scheduleAutoSave()">${sanitizeJS(resultat.sources || '')}</textarea>
                        </div>
                        <div class="col-span-3 bg-yellow-50 p-2">
                            <textarea class="w-full p-2 border rounded resize-y" data-path="resultats.${rIndex}.hypotheses"
                                placeholder="${T.hypotheses_placeholder}"
                                oninput="updateMatrice(this); scheduleAutoSave()">${sanitizeJS(resultat.hypotheses || '')}</textarea>
                        </div>
                    </div>

                    <!-- Activites de ce resultat -->
                    <div class="activites-container" data-resultat="${rIndex}">
                        ${renderActivites(resultat.activites || [], rIndex)}
                    </div>

                    <!-- Bouton ajouter activite -->
                    <div class="pl-12 py-2 bg-red-50 border-b no-print">
                        <button onclick="addActivite(${rIndex})" class="text-sm bg-red-400 hover:bg-red-500 text-white px-3 py-1 rounded">
                            + ${T.add_activity_for}${rNum}
                        </button>
                    </div>
                </div>
            `;
            container.innerHTML += resultatHtml;
        });
    }

    function renderActivites(activites, rIndex) {
        return activites.map((activite, aIndex) => {
            const rNum = rIndex + 1;
            const aNum = aIndex + 1;
            return `
                <div class="grid grid-cols-12 gap-px bg-gray-300 niveau-a">
                    <div class="col-span-1 bg-red-100 p-2 flex flex-col items-center justify-center font-bold text-red-800 text-xs">
                        <span>A${rNum}.${aNum}</span>
                        <button onclick="removeActivite(${rIndex}, ${aIndex})" class="mt-1 text-red-500 hover:text-red-700 text-xs no-print">X</button>
                    </div>
                    <div class="col-span-3 bg-red-50 p-2">
                        <textarea class="w-full p-2 border rounded resize-y text-sm" data-path="resultats.${rIndex}.activites.${aIndex}.description"
                            placeholder="${T.activity_placeholder}"
                            oninput="updateMatrice(this); scheduleAutoSave()">${sanitizeJS(activite.description || '')}</textarea>
                    </div>
                    <div class="col-span-3 bg-red-50 p-2">
                        <textarea class="w-full p-2 border rounded resize-y text-sm" data-path="resultats.${rIndex}.activites.${aIndex}.ressources"
                            placeholder="Ressources/Intrants..."
                            oninput="updateMatrice(this); scheduleAutoSave()">${sanitizeJS(activite.ressources || '')}</textarea>
                    </div>
                    <div class="col-span-2 bg-red-50 p-2">
                        <textarea class="w-full p-2 border rounded resize-y text-sm" data-path="resultats.${rIndex}.activites.${aIndex}.budget"
                            placeholder="Budget..."
                            oninput="updateMatrice(this); scheduleAutoSave()">${sanitizeJS(activite.budget || '')}</textarea>
                    </div>
                    <div class="col-span-3 bg-red-50 p-2">
                        <textarea class="w-full p-2 border rounded resize-y text-sm" data-path="resultats.${rIndex}.activites.${aIndex}.preconditions"
                            placeholder="Preconditions..."
                            oninput="updateMatrice(this); scheduleAutoSave()">${sanitizeJS(activite.preconditions || '')}</textarea>
                    </div>
                </div>
            `;
        }).join('');
    }

    function sanitizeJS(str) {
        return (str || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function updateMatrice(element) {
        const path = element.dataset.path;
        if (!path) return;

        const parts = path.split('.');
        let obj = matrice;
        for (let i = 0; i < parts.length - 1; i++) {
            obj = obj[parts[i]];
        }
        obj[parts[parts.length - 1]] = element.value;
    }

    function addResultat() {
        const newIndex = matrice.resultats.length + 1;
        matrice.resultats.push({
            id: 'R' + newIndex,
            description: '',
            indicateurs: '',
            sources: '',
            hypotheses: '',
            activites: [{
                id: 'A' + newIndex + '.1',
                description: '',
                ressources: '',
                budget: '',
                preconditions: ''
            }]
        });
        renderResultats();
        scheduleAutoSave();
    }

    function removeResultat(index) {
        if (matrice.resultats.length <= 1) {
            alert(T.at_least_one_result);
            return;
        }
        if (confirm(T.confirm_remove_result)) {
            matrice.resultats.splice(index, 1);
            renderResultats();
            scheduleAutoSave();
        }
    }

    function addActivite(rIndex) {
        const aNum = matrice.resultats[rIndex].activites.length + 1;
        matrice.resultats[rIndex].activites.push({
            id: 'A' + (rIndex + 1) + '.' + aNum,
            description: '',
            ressources: '',
            budget: '',
            preconditions: ''
        });
        renderResultats();
        scheduleAutoSave();
    }

    function removeActivite(rIndex, aIndex) {
        if (matrice.resultats[rIndex].activites.length <= 1) {
            alert(T.at_least_one_activity);
            return;
        }
        matrice.resultats[rIndex].activites.splice(aIndex, 1);
        renderResultats();
        scheduleAutoSave();
    }

    function scheduleAutoSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        document.getElementById('saveStatus').textContent = T.saving;
        document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-400 text-yellow-900';
        autoSaveTimeout = setTimeout(saveData, 1000);
    }

    async function saveData() {
        // Mettre a jour objectifs depuis les textareas
        matrice.objectif_global = {
            description: document.getElementById('og_description').value,
            indicateurs: document.getElementById('og_indicateurs').value,
            sources: document.getElementById('og_sources').value,
            hypotheses: document.getElementById('og_hypotheses').value
        };
        matrice.objectif_specifique = {
            description: document.getElementById('os_description').value,
            indicateurs: document.getElementById('os_indicateurs').value,
            sources: document.getElementById('os_sources').value,
            hypotheses: document.getElementById('os_hypotheses').value
        };

        const payload = {
            titre_projet: document.getElementById('titre_projet').value,
            organisation: document.getElementById('organisation').value,
            zone_geo: document.getElementById('zone_geo').value,
            duree: document.getElementById('duree').value,
            matrice_data: matrice
        };

        try {
            const response = await fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result.success) {
                document.getElementById('saveStatus').textContent = T.saved;
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                document.getElementById('completion').innerHTML = T.completion + ': <strong>' + result.completion + '%</strong>';
            } else {
                document.getElementById('saveStatus').textContent = T.save_error;
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
            }
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('saveStatus').textContent = T.save_error;
        }
    }

    async function manualSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        await saveData();
    }

    async function submitCadre() {
        if (!confirm(T.confirm_submit)) return;

        await saveData();

        try {
            const response = await fetch('api/submit.php', { method: 'POST' });
            const result = await response.json();
            if (result.success) {
                document.getElementById('saveStatus').textContent = T.submitted;
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-purple-500 text-white';
                alert(T.submitted + '!');
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function exportJSON() {
        const data = {
            entete: {
                titre_projet: document.getElementById('titre_projet').value,
                organisation: document.getElementById('organisation').value,
                zone_geo: document.getElementById('zone_geo').value,
                duree: document.getElementById('duree').value
            },
            matrice: matrice
        };
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'cadre_logique.json';
        a.click();
    }

    function loadTemplate() {
        const select = document.getElementById('templateSelect');
        if (!select.value) return;
        if (!confirm('Load this example? Your current data will be replaced.')) return;

        // Template Sante IST (simplifie)
        if (select.value === 'sante_ist') {
            document.getElementById('titre_projet').value = 'Bien informes, bien proteges : sensibilisation a la sante sexuelle et aux IST';
            document.getElementById('organisation').value = 'Auberge de Jeunesse [Nom]';
            document.getElementById('zone_geo').value = 'Commune de [Nom], Belgique - 700 jeunes (18-30 ans)';
            document.getElementById('duree').value = '12 mois';

            matrice = {
                objectif_global: {
                    description: 'Contribuer a la reduction de la transmission des IST et a l\'amelioration de la sante sexuelle des jeunes',
                    indicateurs: 'Augmentation du taux de depistage chez les 18-30 ans\nDiminution des nouveaux cas d\'IST',
                    sources: 'Donnees du centre de depistage\nStatistiques regionales',
                    hypotheses: 'Donnees de sante accessibles\nPolitiques favorables a la prevention'
                },
                objectif_specifique: {
                    description: 'Les jeunes frequentant l\'auberge connaissent les IST et accedent facilement au depistage et aux moyens de prevention',
                    indicateurs: '80% connaissent au moins 5 IST\n70% savent ou se faire depister\n90% ont acces aux preservatifs',
                    sources: 'Questionnaires\nEnquetes de satisfaction\nComptage distribution',
                    hypotheses: 'Ouverture des jeunes\nPartenariat avec planning familial'
                },
                resultats: [
                    {
                        id: 'R1',
                        description: 'Les jeunes sont informes sur les IST via des supports adaptes',
                        indicateurs: 'Supports en 5 langues\n100% chambres equipees\n500 jeunes exposes',
                        sources: 'Inventaire supports\nEnquete satisfaction',
                        hypotheses: 'Supports visibles et attractifs',
                        activites: [
                            { id: 'A1.1', description: 'Concevoir des supports multilingues', ressources: 'Graphiste, traducteurs', budget: '1200€', preconditions: 'Contenus valides' },
                            { id: 'A1.2', description: 'Installer l\'affichage preventif', ressources: 'Impression, cadres', budget: '400€', preconditions: 'Supports crees' }
                        ]
                    },
                    {
                        id: 'R2',
                        description: 'Des moyens de prevention sont accessibles gratuitement',
                        indicateurs: '3 distributeurs installes\n5000 preservatifs distribues',
                        sources: 'Comptage distributions\nRegistre reapprovisionnement',
                        hypotheses: 'Approvisionnement regulier',
                        activites: [
                            { id: 'A2.1', description: 'Installer 3 distributeurs gratuits', ressources: 'Distributeurs, installation', budget: '600€', preconditions: 'Emplacements valides' },
                            { id: 'A2.2', description: 'Etablir partenariat fournisseur', ressources: 'Convention, logistique', budget: '200€', preconditions: 'Contact etabli' }
                        ]
                    }
                ]
            };

            document.getElementById('og_description').value = matrice.objectif_global.description;
            document.getElementById('og_indicateurs').value = matrice.objectif_global.indicateurs;
            document.getElementById('og_sources').value = matrice.objectif_global.sources;
            document.getElementById('og_hypotheses').value = matrice.objectif_global.hypotheses;
            document.getElementById('os_description').value = matrice.objectif_specifique.description;
            document.getElementById('os_indicateurs').value = matrice.objectif_specifique.indicateurs;
            document.getElementById('os_sources').value = matrice.objectif_specifique.sources;
            document.getElementById('os_hypotheses').value = matrice.objectif_specifique.hypotheses;

            renderResultats();
            scheduleAutoSave();
        }
    }
    </script>
</body>
</html>
