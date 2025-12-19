<?php
/**
 * Interface de travail - Objectifs SMART (3 etapes)
 */
require_once 'config/database.php';
requireParticipant();

$db = getDB();
$participant = getCurrentParticipant();

if (!$participant) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM objectifs_smart WHERE participant_id = ?");
$stmt->execute([$participant['id']]);
$data = $stmt->fetch();

if (!$data) {
    $stmt = $db->prepare("INSERT INTO objectifs_smart (participant_id, session_id) VALUES (?, ?)");
    $stmt->execute([$participant['id'], $participant['session_id']]);
    $stmt = $db->prepare("SELECT * FROM objectifs_smart WHERE participant_id = ?");
    $stmt->execute([$participant['id']]);
    $data = $stmt->fetch();
}

$etapeCourante = $data['etape_courante'] ?? 1;
$isSubmitted = $data['is_submitted'] == 1;
$objectifsAnalyse = getObjectifsAnalyse();
$objectifsReform = getObjectifsReformulation();
$smartHelp = getSmartHelp();
$exemples = getExemplesParDomaine();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objectifs SMART - <?= sanitize($participant['prenom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        .smart-letter { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 50%; }
        .smart-S { background: #fef3c7; color: #d97706; }
        .smart-M { background: #dbeafe; color: #2563eb; }
        .smart-A { background: #dcfce7; color: #16a34a; }
        .smart-R { background: #fce7f3; color: #db2777; }
        .smart-T { background: #e0e7ff; color: #4f46e5; }
        .step-active { background: #059669; color: white; }
        .step-done { background: #10b981; color: white; }
        .step-pending { background: #e5e7eb; color: #6b7280; }
        .help-tooltip { position: relative; }
        .help-tooltip:hover .tooltip-content { display: block; }
        .tooltip-content { display: none; position: absolute; z-index: 50; background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-emerald-700 text-white shadow-lg">
        <div class="max-w-5xl mx-auto px-4 py-4 flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="text-xl font-bold">Objectifs SMART</h1>
                <p class="text-emerald-200 text-sm"><?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?> | <?= sanitize($participant['session_code']) ?></p>
            </div>
            <div class="flex items-center gap-3">
                <span id="saveStatus" class="text-sm text-emerald-200"></span>
                <button onclick="manualSave()" class="bg-emerald-600 hover:bg-emerald-500 px-4 py-2 rounded text-sm">Sauvegarder</button>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">Deconnexion</a>
            </div>
        </div>
    </header>

    <!-- Progress -->
    <div class="max-w-5xl mx-auto px-4 py-4">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <button onclick="goToStep(1)" class="flex items-center gap-2 px-4 py-2 rounded-lg transition step-btn" data-step="1">
                    <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold step-circle" data-step="1">1</span>
                    <span class="hidden sm:inline">Analyser</span>
                </button>
                <div class="flex-1 h-1 mx-2 bg-gray-200"><div class="h-full bg-emerald-500 transition-all" id="progress1" style="width: 0%"></div></div>
                <button onclick="goToStep(2)" class="flex items-center gap-2 px-4 py-2 rounded-lg transition step-btn" data-step="2">
                    <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold step-circle" data-step="2">2</span>
                    <span class="hidden sm:inline">Reformuler</span>
                </button>
                <div class="flex-1 h-1 mx-2 bg-gray-200"><div class="h-full bg-emerald-500 transition-all" id="progress2" style="width: 0%"></div></div>
                <button onclick="goToStep(3)" class="flex items-center gap-2 px-4 py-2 rounded-lg transition step-btn" data-step="3">
                    <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold step-circle" data-step="3">3</span>
                    <span class="hidden sm:inline">Creer</span>
                </button>
            </div>
        </div>
    </div>

    <?php if ($isSubmitted): ?>
        <div class="max-w-5xl mx-auto px-4 mb-4">
            <div class="bg-green-100 border-l-4 border-green-500 p-4 rounded">
                <p class="text-green-700 font-medium">Travail marque comme termine (modifications toujours possibles)</p>
            </div>
        </div>
    <?php endif; ?>

    <main class="max-w-5xl mx-auto px-4 pb-8">
        <!-- ETAPE 1: Analyser -->
        <div id="step1" class="step-content hidden">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Etape 1 : Analyser des objectifs</h2>
                <p class="text-gray-600 mb-4">Evaluez si chaque objectif respecte les criteres SMART.</p>

                <div id="analyseContainer"></div>

                <div class="mt-6 flex justify-end">
                    <button onclick="goToStep(2)" class="bg-emerald-600 text-white px-6 py-3 rounded-lg hover:bg-emerald-700 font-medium">
                        Passer a l'etape 2 →
                    </button>
                </div>
            </div>
        </div>

        <!-- ETAPE 2: Reformuler -->
        <div id="step2" class="step-content hidden">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Etape 2 : Reformuler des objectifs</h2>
                <p class="text-gray-600 mb-4">Transformez ces objectifs vagues en objectifs SMART complets.</p>

                <div id="reformulationContainer"></div>

                <div class="mt-6 flex justify-between">
                    <button onclick="goToStep(1)" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600">
                        ← Retour
                    </button>
                    <button onclick="goToStep(3)" class="bg-emerald-600 text-white px-6 py-3 rounded-lg hover:bg-emerald-700 font-medium">
                        Passer a l'etape 3 →
                    </button>
                </div>
            </div>
        </div>

        <!-- ETAPE 3: Creer -->
        <div id="step3" class="step-content hidden">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Etape 3 : Creer vos objectifs SMART</h2>
                <p class="text-gray-600 mb-4">Formulez vos propres objectifs en appliquant la methode SMART.</p>

                <div id="creationContainer"></div>

                <button onclick="addCreation()" class="mt-4 bg-emerald-100 text-emerald-700 px-4 py-2 rounded-lg hover:bg-emerald-200 font-medium">
                    + Ajouter un objectif
                </button>

                <div class="mt-6 flex flex-wrap justify-between gap-4">
                    <button onclick="goToStep(2)" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600">
                        ← Retour
                    </button>
                    <div class="flex gap-3">
                        <button onclick="exportExcel()" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700">Excel</button>
                        <button onclick="exportWord()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Word</button>
                        <button onclick="window.print()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Imprimer</button>
                        <?php if (!$isSubmitted): ?>
                            <button onclick="submitWork()" class="bg-emerald-800 text-white px-6 py-3 rounded-lg hover:bg-emerald-900 font-medium">
                                Marquer comme termine
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Help Panel -->
    <div id="helpPanel" class="fixed inset-y-0 right-0 w-80 bg-white shadow-xl transform translate-x-full transition-transform duration-300 z-50">
        <div class="p-4 bg-emerald-700 text-white flex justify-between items-center">
            <h3 class="font-bold">Aide SMART</h3>
            <button onclick="toggleHelp()" class="text-2xl">&times;</button>
        </div>
        <div class="p-4 overflow-y-auto" style="height: calc(100% - 60px);" id="helpContent"></div>
    </div>

    <button onclick="toggleHelp()" class="fixed bottom-6 right-6 bg-emerald-600 text-white w-14 h-14 rounded-full shadow-xl hover:bg-emerald-700 text-2xl font-bold z-40 flex items-center justify-center animate-pulse hover:animate-none" title="Aide SMART">?</button>

    <script>
        // Data
        const objectifsAnalyse = <?= json_encode($objectifsAnalyse) ?>;
        const objectifsReform = <?= json_encode($objectifsReform) ?>;
        const smartHelp = <?= json_encode($smartHelp) ?>;
        const exemples = <?= json_encode($exemples) ?>;

        let etape1 = <?= $data['etape1_analyses'] ?: '[]' ?>;
        let etape2 = <?= $data['etape2_reformulations'] ?: '[]' ?>;
        let etape3 = <?= $data['etape3_creations'] ?: '[]' ?>;
        let currentStep = <?= $etapeCourante ?>;
        let saveTimeout = null;

        // Init
        document.addEventListener('DOMContentLoaded', () => {
            initAnalyses();
            initReformulations();
            initCreations();
            showStep(currentStep);
            updateProgress();
            loadHelpContent();
        });

        function showStep(step) {
            currentStep = step;
            document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('step' + step).classList.remove('hidden');
            updateStepButtons();
            scheduleSave();
        }

        function goToStep(step) {
            showStep(step);
        }

        function updateStepButtons() {
            document.querySelectorAll('.step-circle').forEach(el => {
                const s = parseInt(el.dataset.step);
                el.classList.remove('step-active', 'step-done', 'step-pending');
                if (s === currentStep) el.classList.add('step-active');
                else if (s < currentStep) el.classList.add('step-done');
                else el.classList.add('step-pending');
            });
        }

        function updateProgress() {
            // Step 1 progress
            const total1 = objectifsAnalyse.length * 5;
            let filled1 = 0;
            etape1.forEach(a => {
                if (a.evaluations) {
                    Object.values(a.evaluations).forEach(e => { if (e.reponse) filled1++; });
                }
            });
            document.getElementById('progress1').style.width = Math.round((filled1 / total1) * 100) + '%';

            // Step 2 progress
            const total2 = objectifsReform.length * 5;
            let filled2 = 0;
            etape2.forEach(r => {
                if (r.composantes) {
                    Object.values(r.composantes).forEach(c => { if (c && c.trim()) filled2++; });
                }
            });
            document.getElementById('progress2').style.width = Math.round((filled2 / total2) * 100) + '%';
        }

        // ===== ETAPE 1: ANALYSER =====
        function initAnalyses() {
            if (etape1.length === 0) {
                etape1 = objectifsAnalyse.map(obj => ({
                    objectif_id: obj.id,
                    texte: obj.texte,
                    evaluations: { S: {}, M: {}, A: {}, R: {}, T: {} }
                }));
            }
            renderAnalyses();
        }

        function renderAnalyses() {
            const container = document.getElementById('analyseContainer');
            container.innerHTML = etape1.map((analyse, idx) => {
                const obj = objectifsAnalyse.find(o => o.id === analyse.objectif_id);
                const score = calculateScore(analyse.evaluations);
                return `
                    <div class="border rounded-lg p-4 mb-4 ${obj.niveau === 'smart' ? 'border-green-300 bg-green-50' : 'border-gray-200'}">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <span class="text-xs uppercase text-gray-500 font-medium">${obj.niveau === 'smart' ? 'Exemple SMART' : 'Objectif ' + (idx + 1)}</span>
                                <p class="text-lg font-medium text-gray-800">"${obj.texte}"</p>
                            </div>
                            <div class="text-center ml-4">
                                <div class="text-2xl font-bold ${score >= 4 ? 'text-green-600' : score >= 2 ? 'text-yellow-600' : 'text-red-600'}">${score}/5</div>
                                <div class="text-xs text-gray-500">Score</div>
                            </div>
                        </div>
                        <div class="grid gap-3">
                            ${['S','M','A','R','T'].map(letter => renderCritereAnalyse(idx, letter, analyse.evaluations[letter])).join('')}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderCritereAnalyse(idx, letter, evaluation) {
            const help = smartHelp[letter];
            const val = evaluation?.reponse || '';
            const just = evaluation?.justification || '';
            return `
                <div class="flex items-start gap-3 p-2 bg-gray-50 rounded">
                    <div class="smart-letter smart-${letter}">${letter}</div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-medium text-sm">${help.titre}</span>
                            <span class="help-tooltip cursor-help text-gray-400 text-sm">ⓘ
                                <div class="tooltip-content left-0 top-6">
                                    <p class="text-sm text-gray-600 mb-2">${help.definition}</p>
                                    <p class="text-xs text-red-600">✗ ${help.exemple_non}</p>
                                    <p class="text-xs text-green-600">✓ ${help.exemple_oui}</p>
                                </div>
                            </span>
                        </div>
                        <div class="flex gap-4 mb-2">
                            <label class="flex items-center gap-1 text-sm">
                                <input type="radio" name="analyse_${idx}_${letter}" value="oui" ${val === 'oui' ? 'checked' : ''} onchange="updateAnalyse(${idx}, '${letter}', 'reponse', 'oui')"> Oui
                            </label>
                            <label class="flex items-center gap-1 text-sm">
                                <input type="radio" name="analyse_${idx}_${letter}" value="partiellement" ${val === 'partiellement' ? 'checked' : ''} onchange="updateAnalyse(${idx}, '${letter}', 'reponse', 'partiellement')"> Partiel
                            </label>
                            <label class="flex items-center gap-1 text-sm">
                                <input type="radio" name="analyse_${idx}_${letter}" value="non" ${val === 'non' ? 'checked' : ''} onchange="updateAnalyse(${idx}, '${letter}', 'reponse', 'non')"> Non
                            </label>
                        </div>
                        <input type="text" placeholder="Justification..." value="${escapeHtml(just)}"
                               onchange="updateAnalyse(${idx}, '${letter}', 'justification', this.value)"
                               class="w-full px-2 py-1 text-sm border rounded">
                    </div>
                </div>
            `;
        }

        function updateAnalyse(idx, letter, field, value) {
            if (!etape1[idx].evaluations[letter]) etape1[idx].evaluations[letter] = {};
            etape1[idx].evaluations[letter][field] = value;
            renderAnalyses();
            updateProgress();
            scheduleSave();
        }

        function calculateScore(evaluations) {
            let score = 0;
            ['S','M','A','R','T'].forEach(l => {
                if (evaluations[l]?.reponse === 'oui') score++;
            });
            return score;
        }

        // ===== ETAPE 2: REFORMULER =====
        function initReformulations() {
            if (etape2.length === 0) {
                etape2 = objectifsReform.map(obj => ({
                    objectif_id: obj.id,
                    texte_original: obj.texte_vague,
                    composantes: { S: '', M: '', A: '', R: '', T: '' },
                    objectif_final: ''
                }));
            }
            renderReformulations();
        }

        function renderReformulations() {
            const container = document.getElementById('reformulationContainer');
            container.innerHTML = etape2.map((reform, idx) => {
                const obj = objectifsReform.find(o => o.id === reform.objectif_id);
                return `
                    <div class="border rounded-lg p-4 mb-6">
                        <div class="mb-4">
                            <span class="text-xs uppercase text-gray-500 font-medium">Objectif vague</span>
                            <p class="text-lg font-medium text-red-600">"${obj.texte_vague}"</p>
                            <p class="text-sm text-gray-500 mt-1">💡 ${obj.pistes}</p>
                        </div>
                        <div class="grid gap-3">
                            ${['S','M','A','R','T'].map(letter => renderCritereReform(idx, letter, reform.composantes[letter])).join('')}
                        </div>
                        <div class="mt-4 p-3 bg-green-50 rounded-lg border border-green-200">
                            <label class="block text-sm font-medium text-green-800 mb-1">Objectif SMART reformule :</label>
                            <textarea rows="2" class="w-full p-2 border rounded text-sm"
                                      placeholder="L'objectif se genere automatiquement ou modifiez-le..."
                                      onchange="updateReformFinal(${idx}, this.value)">${escapeHtml(reform.objectif_final || generateSmartObjectif(reform.composantes))}</textarea>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderCritereReform(idx, letter, value) {
            const help = smartHelp[letter];
            const questions = {
                'S': 'Que voulez-vous accomplir exactement ?',
                'M': 'Quel indicateur chiffre ? (%, nombre)',
                'A': 'Quels moyens/actions pour y arriver ?',
                'R': 'Pourquoi cet objectif est pertinent ?',
                'T': 'Pour quand ? Quelle echeance ?'
            };
            return `
                <div class="flex items-start gap-3">
                    <div class="smart-letter smart-${letter}">${letter}</div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">${help.titre} - ${questions[letter]}</label>
                        <input type="text" value="${escapeHtml(value || '')}"
                               onchange="updateReformComposante(${idx}, '${letter}', this.value)"
                               class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
            `;
        }

        function updateReformComposante(idx, letter, value) {
            etape2[idx].composantes[letter] = value;
            etape2[idx].objectif_final = generateSmartObjectif(etape2[idx].composantes);
            renderReformulations();
            updateProgress();
            scheduleSave();
        }

        function updateReformFinal(idx, value) {
            etape2[idx].objectif_final = value;
            scheduleSave();
        }

        function generateSmartObjectif(comp) {
            const parts = [];
            if (comp.S) parts.push(comp.S);
            if (comp.M) parts.push(comp.M);
            if (comp.T) parts.push(comp.T);
            if (comp.A) parts.push('en ' + comp.A.toLowerCase());
            if (comp.R) parts.push('afin de ' + comp.R.toLowerCase());
            return parts.join(', ') + '.';
        }

        // ===== ETAPE 3: CREER =====
        function initCreations() {
            if (etape3.length === 0) {
                etape3.push(createEmptyObjectif());
            }
            renderCreations();
        }

        function createEmptyObjectif() {
            return {
                id: Date.now(),
                contexte: 'professionnel',
                thematique: '',
                composantes: { S: '', M: '', A: '', R: '', T: '' },
                objectif_final: ''
            };
        }

        function addCreation() {
            etape3.push(createEmptyObjectif());
            renderCreations();
            scheduleSave();
        }

        function removeCreation(idx) {
            if (etape3.length > 1) {
                etape3.splice(idx, 1);
                renderCreations();
                scheduleSave();
            }
        }

        function renderCreations() {
            const container = document.getElementById('creationContainer');
            container.innerHTML = etape3.map((creation, idx) => `
                <div class="border-2 border-emerald-200 rounded-lg p-4 mb-6 bg-emerald-50">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-emerald-800">Objectif #${idx + 1}</h3>
                        ${etape3.length > 1 ? `<button onclick="removeCreation(${idx})" class="text-red-500 hover:text-red-700 text-sm">Supprimer</button>` : ''}
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Domaine</label>
                            <select onchange="updateCreation(${idx}, 'contexte', this.value)" class="w-full p-2 border rounded">
                                <option value="professionnel" ${creation.contexte === 'professionnel' ? 'selected' : ''}>Professionnel</option>
                                <option value="personnel" ${creation.contexte === 'personnel' ? 'selected' : ''}>Personnel</option>
                                <option value="associatif" ${creation.contexte === 'associatif' ? 'selected' : ''}>Associatif</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Thematique</label>
                            <input type="text" value="${escapeHtml(creation.thematique)}"
                                   onchange="updateCreation(${idx}, 'thematique', this.value)"
                                   placeholder="Ex: Vente, Sante, Formation..."
                                   class="w-full p-2 border rounded">
                        </div>
                    </div>
                    <div class="grid gap-3">
                        ${['S','M','A','R','T'].map(letter => renderCritereCreation(idx, letter, creation.composantes[letter])).join('')}
                    </div>
                    <div class="mt-4 p-3 bg-white rounded-lg border-2 border-emerald-300">
                        <label class="block text-sm font-medium text-emerald-800 mb-1">✨ Votre objectif SMART :</label>
                        <textarea rows="3" class="w-full p-2 border rounded"
                                  onchange="updateCreationFinal(${idx}, this.value)">${escapeHtml(creation.objectif_final || generateSmartObjectif(creation.composantes))}</textarea>
                    </div>
                </div>
            `).join('');
        }

        function renderCritereCreation(idx, letter, value) {
            const help = smartHelp[letter];
            return `
                <div class="flex items-start gap-3 bg-white p-3 rounded">
                    <div class="smart-letter smart-${letter}">${letter}</div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">${help.titre}</label>
                        <textarea rows="2" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-emerald-500"
                                  placeholder="${help.questions[0]}"
                                  onchange="updateCreationComposante(${idx}, '${letter}', this.value)">${escapeHtml(value || '')}</textarea>
                    </div>
                </div>
            `;
        }

        function updateCreation(idx, field, value) {
            etape3[idx][field] = value;
            renderCreations();
            scheduleSave();
        }

        function updateCreationComposante(idx, letter, value) {
            etape3[idx].composantes[letter] = value;
            etape3[idx].objectif_final = generateSmartObjectif(etape3[idx].composantes);
            renderCreations();
            scheduleSave();
        }

        function updateCreationFinal(idx, value) {
            etape3[idx].objectif_final = value;
            scheduleSave();
        }

        // ===== HELP =====
        function loadHelpContent() {
            let html = '<div class="space-y-4">';
            ['S','M','A','R','T'].forEach(letter => {
                const h = smartHelp[letter];
                html += `
                    <div class="border rounded-lg p-3">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="smart-letter smart-${letter}">${letter}</div>
                            <span class="font-bold">${h.titre}</span>
                        </div>
                        <p class="text-sm text-gray-600 mb-2">${h.definition}</p>
                        <p class="text-xs text-gray-500 mb-1">Questions :</p>
                        <ul class="text-xs text-gray-600 list-disc list-inside mb-2">
                            ${h.questions.map(q => `<li>${q}</li>`).join('')}
                        </ul>
                        <div class="text-xs">
                            <p class="text-red-600">✗ ${h.exemple_non}</p>
                            <p class="text-green-600">✓ ${h.exemple_oui}</p>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            document.getElementById('helpContent').innerHTML = html;
        }

        function toggleHelp() {
            const panel = document.getElementById('helpPanel');
            panel.classList.toggle('translate-x-full');
        }

        // ===== SAVE =====
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
            fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    etape_courante: currentStep,
                    etape1_analyses: etape1,
                    etape2_reformulations: etape2,
                    etape3_creations: etape3
                })
            })
            .then(r => r.json())
            .then(result => {
                document.getElementById('saveStatus').textContent = result.success ? 'Sauvegarde OK' : 'Erreur';
                setTimeout(() => document.getElementById('saveStatus').textContent = '', 2000);
            })
            .catch(() => document.getElementById('saveStatus').textContent = 'Erreur reseau');
        }

        function submitWork() {
            if (!confirm('Marquer comme termine ?')) return;
            fetch('api/submit.php', { method: 'POST' })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    alert('Marque comme termine !');
                    location.reload();
                }
            });
        }

        // ===== EXPORT =====
        function exportExcel() {
            const wb = XLSX.utils.book_new();

            // Sheet 1: Analyses
            const data1 = [['ETAPE 1 - ANALYSES'], ['']];
            etape1.forEach((a, i) => {
                data1.push(['Objectif ' + (i+1), a.texte]);
                ['S','M','A','R','T'].forEach(l => {
                    const e = a.evaluations[l] || {};
                    data1.push([smartHelp[l].titre, e.reponse || '', e.justification || '']);
                });
                data1.push(['']);
            });
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(data1), 'Analyses');

            // Sheet 2: Reformulations
            const data2 = [['ETAPE 2 - REFORMULATIONS'], ['']];
            etape2.forEach((r, i) => {
                data2.push(['Original', r.texte_original]);
                ['S','M','A','R','T'].forEach(l => {
                    data2.push([smartHelp[l].titre, r.composantes[l] || '']);
                });
                data2.push(['SMART', r.objectif_final]);
                data2.push(['']);
            });
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(data2), 'Reformulations');

            // Sheet 3: Creations
            const data3 = [['ETAPE 3 - MES OBJECTIFS'], ['']];
            etape3.forEach((c, i) => {
                data3.push(['Objectif ' + (i+1), c.contexte, c.thematique]);
                ['S','M','A','R','T'].forEach(l => {
                    data3.push([smartHelp[l].titre, c.composantes[l] || '']);
                });
                data3.push(['SMART FINAL', c.objectif_final]);
                data3.push(['']);
            });
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(data3), 'Mes Objectifs');

            XLSX.writeFile(wb, 'objectifs_smart.xlsx');
        }

        function exportWord() {
            let html = `<html><head><meta charset="utf-8"><title>Objectifs SMART</title></head><body style="font-family:Arial;">
            <h1 style="color:#059669;">Mes Objectifs SMART</h1>`;

            html += '<h2>Etape 3 - Mes objectifs crees</h2>';
            etape3.forEach((c, i) => {
                html += `<h3>Objectif ${i+1} (${c.contexte}${c.thematique ? ' - ' + c.thematique : ''})</h3>`;
                html += '<table border="1" cellpadding="5" style="border-collapse:collapse;width:100%">';
                ['S','M','A','R','T'].forEach(l => {
                    html += `<tr><td style="background:#f0fdf4;width:100px"><strong>${smartHelp[l].titre}</strong></td><td>${escapeHtml(c.composantes[l] || '-')}</td></tr>`;
                });
                html += '</table>';
                html += `<p style="background:#dcfce7;padding:10px;border-radius:5px;margin-top:10px"><strong>Objectif SMART :</strong> ${escapeHtml(c.objectif_final)}</p>`;
            });

            html += '</body></html>';
            const blob = new Blob([html], { type: 'application/msword' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'objectifs_smart.doc';
            a.click();
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
