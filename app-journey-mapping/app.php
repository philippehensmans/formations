<?php
/**
 * Interface de travail - Journey Mapping - Audit de Communication
 */
require_once __DIR__ . '/config.php';

// Verifier l'authentification
if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user = getLoggedUser();

// Verifier que l'utilisateur existe en base
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$sessionId = $_SESSION['current_session_id'];
ensureParticipant($db, $sessionId, $user);
$sessionNom = $_SESSION['current_session_nom'] ?? '';

// Charger l'analyse Journey Mapping
$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$analyse = $stmt->fetch();

if (!$analyse) {
    $stmt = $db->prepare("INSERT INTO analyses (user_id, session_id, journey_data) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $sessionId, '[]']);
    $stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$user['id'], $sessionId]);
    $analyse = $stmt->fetch();
}

$journeyData = json_decode($analyse['journey_data'], true) ?: [];
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;
$channels = getChannels();
$emotions = getEmotions();
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journey Mapping - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><path d='M4 24 Q8 8 16 16 Q24 24 28 8' stroke='%230891b2' stroke-width='3' fill='none'/><circle cx='4' cy='24' r='3' fill='%230891b2'/><circle cx='16' cy='16' r='3' fill='%2306b6d4'/><circle cx='28' cy='8' r='3' fill='%2367e8f9'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #0e7490 0%, #164e63 50%, #1e3a5f 100%); min-height: 100vh; }
        @media print { .no-print { display: none !important; } body { background: white; } }
        .step-card { transition: all 0.3s ease; }
        .step-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .emotion-btn { transition: all 0.2s ease; cursor: pointer; }
        .emotion-btn:hover { transform: scale(1.15); }
        .emotion-btn.selected { background-color: #0891b2; color: white; transform: scale(1.1); box-shadow: 0 2px 8px rgba(8,145,178,0.4); }
        .timeline-connector { position: relative; }
        .timeline-connector::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -1.5rem;
            width: 2px;
            height: 1.5rem;
            background: linear-gradient(to bottom, #06b6d4, #0891b2);
        }
        .timeline-connector:last-child::after { display: none; }
        .drag-handle { cursor: grab; }
        .drag-handle:active { cursor: grabbing; }
        .dragging { opacity: 0.5; }
        .drag-over { border: 2px dashed #06b6d4 !important; }
        .emotion-curve { height: 120px; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="p-4 md:p-8">
    <!-- Barre utilisateur -->
    <div class="max-w-6xl mx-auto mb-4 bg-white/90 backdrop-blur rounded-lg shadow-lg p-3 no-print">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium text-gray-800"><?= h($user['prenom']) ?> <?= h($user['nom']) ?></span>
                <span class="text-gray-500 text-sm ml-2"><?= h($sessionNom) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('text-sm bg-white/20 text-gray-800 px-2 py-1 rounded border border-gray-300') ?>
                <button onclick="manualSave()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">
                    Sauvegarder
                </button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-gray-200">
                    <?= $isSubmitted ? 'Soumis' : 'Brouillon' ?>
                </span>
                <span id="completion" class="text-sm text-gray-600">Completion: <strong>0%</strong></span>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="text-sm bg-cyan-600 hover:bg-cyan-500 text-white px-3 py-1 rounded transition">Formateur</a>
                <?php endif; ?>
                <a href="logout.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
            <div class="mb-8 text-center">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Journey Mapping - Audit de Communication</h1>
                <p class="text-gray-600 italic">Cartographie visuelle des parcours d'information de vos publics cibles</p>
            </div>

            <!-- Introduction methodologique -->
            <div class="mb-6 bg-gradient-to-r from-cyan-50 via-teal-50 to-blue-50 p-6 rounded-lg border-2 border-cyan-200 shadow-md">
                <h2 class="text-xl font-bold text-cyan-800 mb-3 flex items-center">
                    <span class="text-2xl mr-2">&#x1F5FA;&#xFE0F;</span> Le Journey Mapping en Communication
                </h2>
                <div class="space-y-3 text-gray-700">
                    <p class="leading-relaxed">
                        <strong>Definition :</strong> Methode visuelle qui cartographie l'experience complete d'une personne interagissant avec votre organisation, depuis le premier contact jusqu'a l'engagement durable.
                    </p>
                    <p class="leading-relaxed">
                        <strong>Application en audit :</strong> Analyse systematique des parcours d'information a travers differents canaux de communication. Chaque etape revele comment vos publics cibles vivent leur interaction avec votre organisation.
                    </p>
                    <div class="grid md:grid-cols-3 gap-3 mt-3">
                        <div class="bg-white/70 p-3 rounded-lg border border-cyan-200">
                            <p class="font-semibold text-cyan-800 mb-1">&#x1F50D; Identification</p>
                            <p class="text-sm">Reperer les ruptures, redondances et lacunes dans le flux informationnel</p>
                        </div>
                        <div class="bg-white/70 p-3 rounded-lg border border-teal-200">
                            <p class="font-semibold text-teal-800 mb-1">&#x1F465; Perspective utilisateur</p>
                            <p class="text-sm">Comprendre l'experience reelle vecue par vos publics cibles</p>
                        </div>
                        <div class="bg-white/70 p-3 rounded-lg border border-blue-200">
                            <p class="font-semibold text-blue-800 mb-1">&#x1F3AF; Priorisation</p>
                            <p class="text-sm">Base objective pour prioriser les ameliorations communicationnelles</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations du projet -->
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div class="bg-gradient-to-r from-cyan-50 to-teal-50 p-4 rounded-lg">
                    <label class="block text-lg font-semibold text-gray-800 mb-2">
                        &#x1F3E2; Organisation analysee
                    </label>
                    <input type="text" id="nomOrganisation"
                        class="w-full px-4 py-2 border-2 border-cyan-200 rounded-md focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
                        placeholder="Nom de l'organisation..."
                        value="<?= h($analyse['nom_organisation'] ?? '') ?>"
                        oninput="scheduleAutoSave()">
                </div>
                <div class="bg-gradient-to-r from-teal-50 to-blue-50 p-4 rounded-lg">
                    <label class="block text-lg font-semibold text-gray-800 mb-2">
                        &#x1F465; Public cible analyse
                    </label>
                    <input type="text" id="publicCible"
                        class="w-full px-4 py-2 border-2 border-teal-200 rounded-md focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                        placeholder="Ex: Nouveaux benevoles, donateurs, beneficiaires..."
                        value="<?= h($analyse['public_cible'] ?? '') ?>"
                        oninput="scheduleAutoSave()">
                </div>
            </div>
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg mb-4">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    &#x1F3AF; Objectif de l'audit
                </label>
                <textarea id="objectifAudit" rows="2"
                    class="w-full px-4 py-2 border-2 border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Quel est l'objectif de cet audit de communication ? Que cherchez-vous a evaluer ou ameliorer ?"
                    oninput="scheduleAutoSave()"><?= h($analyse['objectif_audit'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Section des etapes du parcours -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
            <div class="flex flex-wrap justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">&#x1F6A9; Etapes du parcours</h2>
                <div class="flex gap-2">
                    <span id="stepCount" class="text-sm text-gray-500 self-center">0 etape(s)</span>
                    <button onclick="addStep()" class="no-print bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                        &#x2795; Ajouter une etape
                    </button>
                </div>
            </div>

            <div id="stepsContainer" class="space-y-6">
                <!-- Les etapes seront ajoutees ici dynamiquement -->
            </div>

            <div id="emptyState" class="text-center py-12 text-gray-400">
                <p class="text-5xl mb-4">&#x1F5FA;&#xFE0F;</p>
                <p class="text-lg">Aucune etape dans le parcours</p>
                <p class="text-sm">Cliquez sur "Ajouter une etape" pour commencer la cartographie</p>
            </div>
        </div>

        <!-- Courbe emotionnelle -->
        <div id="emotionCurveSection" class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6" style="display:none;">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">&#x1F4C8; Courbe emotionnelle du parcours</h2>
            <p class="text-gray-600 text-sm mb-4">Visualisation de l'experience emotionnelle a travers les differentes etapes du parcours</p>
            <div class="overflow-x-auto">
                <canvas id="emotionCanvas" class="w-full" height="200"></canvas>
            </div>
            <div id="emotionLegend" class="flex flex-wrap gap-3 mt-4 text-sm"></div>
        </div>

        <!-- Synthese et recommandations -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
            <div class="mb-6 bg-gradient-to-r from-cyan-50 to-teal-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    &#x1F4CB; Synthese de l'analyse
                </label>
                <p class="text-sm text-gray-600 mb-3 italic">
                    Quels sont les principaux constats de votre journey mapping ? Quels patterns observez-vous dans le parcours ?
                </p>
                <textarea id="synthese" rows="5"
                    class="w-full px-4 py-2 border-2 border-cyan-300 rounded-md focus:ring-2 focus:ring-cyan-500"
                    placeholder="Resumez vos observations principales : points forts du parcours, lacunes identifiees, coherence entre les canaux..."
                    oninput="scheduleAutoSave()"><?= h($analyse['synthese'] ?? '') ?></textarea>
            </div>

            <div class="mb-6 bg-gradient-to-r from-teal-50 to-green-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    &#x1F4A1; Recommandations
                </label>
                <p class="text-sm text-gray-600 mb-3 italic">
                    Quelles ameliorations prioritaires proposez-vous pour optimiser le parcours de communication ?
                </p>
                <textarea id="recommandations" rows="5"
                    class="w-full px-4 py-2 border-2 border-teal-300 rounded-md focus:ring-2 focus:ring-teal-500"
                    placeholder="Listez vos recommandations : actions prioritaires, canaux a renforcer, messages a clarifier, nouvelles etapes a creer..."
                    oninput="scheduleAutoSave()"><?= h($analyse['recommandations'] ?? '') ?></textarea>
            </div>

            <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    &#x270F;&#xFE0F; Notes complementaires
                </label>
                <textarea id="notes" rows="3"
                    class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500"
                    placeholder="Notes libres, observations supplementaires..."
                    oninput="scheduleAutoSave()"><?= h($analyse['notes'] ?? '') ?></textarea>
            </div>

            <!-- Boutons d'action -->
            <div class="no-print flex flex-wrap gap-3 pt-4 border-t-2 border-gray-200">
                <button type="button" onclick="submitAnalyse()"
                    class="bg-cyan-600 text-white px-6 py-3 rounded-md hover:bg-cyan-700 transition font-semibold shadow-md">
                    &#x2705; Soumettre l'analyse
                </button>
                <button type="button" onclick="exportToExcel()"
                    class="bg-emerald-600 text-white px-6 py-3 rounded-md hover:bg-emerald-700 transition font-semibold shadow-md">
                    &#x1F4CA; Export Excel
                </button>
                <button type="button" onclick="exportToWord()"
                    class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition font-semibold shadow-md">
                    &#x1F4C4; Export Word
                </button>
                <button type="button" onclick="exportJSON()"
                    class="bg-gray-500 text-white px-6 py-3 rounded-md hover:bg-gray-600 transition font-semibold shadow-md">
                    &#x1F4E5; JSON
                </button>
                <button type="button" onclick="window.print()"
                    class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition font-semibold shadow-md">
                    &#x1F5A8;&#xFE0F; Imprimer
                </button>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>
    <script>
        // Donnees initiales
        let steps = <?= json_encode($journeyData) ?>;
        let autoSaveTimeout = null;
        let dragSrcIndex = null;

        // Canaux disponibles
        const channels = <?= json_encode($channels) ?>;

        // Emotions disponibles
        const emotions = <?= json_encode($emotions) ?>;

        // Valeurs emotionnelles pour la courbe
        const emotionValues = {
            'satisfaction': 5,
            'enthousiasme': 6,
            'surprise_positive': 5,
            'questionnement': 3,
            'indifference': 2,
            'confusion': 1,
            'inquietude': 0,
            'frustration': -1
        };

        const emotionColors = {
            'satisfaction': '#22c55e',
            'enthousiasme': '#ec4899',
            'surprise_positive': '#a855f7',
            'questionnement': '#f59e0b',
            'indifference': '#6b7280',
            'confusion': '#f97316',
            'inquietude': '#ef4444',
            'frustration': '#dc2626'
        };

        // Initialiser l'affichage
        document.addEventListener('DOMContentLoaded', function() {
            renderSteps();
            updateStepCount();
        });

        function renderSteps() {
            const container = document.getElementById('stepsContainer');
            const emptyState = document.getElementById('emptyState');

            if (steps.length === 0) {
                container.innerHTML = '';
                emptyState.style.display = 'block';
                document.getElementById('emotionCurveSection').style.display = 'none';
                return;
            }

            emptyState.style.display = 'none';
            container.innerHTML = '';

            steps.forEach((step, index) => {
                const card = createStepCard(step, index);
                container.appendChild(card);
            });

            updateEmotionCurve();
            updateStepCount();
        }

        function createStepCard(step, index) {
            const div = document.createElement('div');
            div.className = 'step-card timeline-connector fade-in bg-gradient-to-r from-white to-cyan-50/30 rounded-xl border-2 border-cyan-100 shadow-lg p-5';
            div.dataset.index = index;
            div.draggable = true;

            // Drag events
            div.addEventListener('dragstart', handleDragStart);
            div.addEventListener('dragover', handleDragOver);
            div.addEventListener('dragleave', handleDragLeave);
            div.addEventListener('drop', handleDrop);
            div.addEventListener('dragend', handleDragEnd);

            // Channel options
            let channelOptions = '<option value="">-- Selectionnez un canal --</option>';
            for (const [key, label] of Object.entries(channels)) {
                const selected = step.canal === key ? 'selected' : '';
                channelOptions += `<option value="${key}" ${selected}>${label}</option>`;
            }

            // Emotion buttons
            let emotionBtns = '';
            const selectedEmotions = step.emotions || [];
            for (const [key, data] of Object.entries(emotions)) {
                const isSelected = selectedEmotions.includes(key) ? 'selected' : '';
                emotionBtns += `<button type="button" class="emotion-btn ${isSelected} px-3 py-2 rounded-lg border-2 border-gray-200 text-sm" data-emotion="${key}" onclick="toggleEmotion(${index}, '${key}', this)" title="${data.label}">${data.emoji} ${data.label}</button>`;
            }

            div.innerHTML = `
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <span class="drag-handle text-gray-400 text-lg no-print" title="Glisser pour reordonner">&#x2630;</span>
                        <span class="bg-cyan-600 text-white text-sm font-bold px-3 py-1 rounded-full">Etape ${index + 1}</span>
                    </div>
                    <button onclick="removeStep(${index})" class="no-print text-red-400 hover:text-red-600 transition" title="Supprimer cette etape">&#x2716;</button>
                </div>

                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Titre de l'etape</label>
                        <input type="text" value="${escapeHtml(step.titre || '')}"
                            class="w-full px-3 py-2 border-2 border-cyan-200 rounded-md focus:ring-2 focus:ring-cyan-500 text-sm"
                            placeholder="Ex: Decouverte de l'organisation, Premier contact..."
                            oninput="updateStepField(${index}, 'titre', this.value)">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Canal de communication</label>
                        <select class="w-full px-3 py-2 border-2 border-cyan-200 rounded-md focus:ring-2 focus:ring-cyan-500 text-sm bg-white"
                            onchange="updateStepField(${index}, 'canal', this.value)">
                            ${channelOptions}
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Point de contact specifique</label>
                    <input type="text" value="${escapeHtml(step.point_contact || '')}"
                        class="w-full px-3 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500 text-sm"
                        placeholder="Ex: Page d'accueil, Post Facebook, Email de bienvenue..."
                        oninput="updateStepField(${index}, 'point_contact', this.value)">
                </div>

                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">&#x1F464; Action de l'utilisateur</label>
                        <textarea rows="2"
                            class="w-full px-3 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500 text-sm resize-none"
                            placeholder="Que fait la personne a cette etape ? Que cherche-t-elle ?"
                            oninput="updateStepField(${index}, 'action_utilisateur', this.value)">${escapeHtml(step.action_utilisateur || '')}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">&#x1F4E8; Information recue</label>
                        <textarea rows="2"
                            class="w-full px-3 py-2 border-2 border-gray-200 rounded-md focus:ring-2 focus:ring-cyan-500 text-sm resize-none"
                            placeholder="Quelle information la personne recoit-elle ? Est-elle claire ?"
                            oninput="updateStepField(${index}, 'info_recue', this.value)">${escapeHtml(step.info_recue || '')}</textarea>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Emotions ressenties</label>
                    <div class="flex flex-wrap gap-2">
                        ${emotionBtns}
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-red-700 mb-1">&#x26A0;&#xFE0F; Points de friction</label>
                        <textarea rows="2"
                            class="w-full px-3 py-2 border-2 border-red-200 rounded-md focus:ring-2 focus:ring-red-400 text-sm resize-none bg-red-50/50"
                            placeholder="Quels sont les problemes rencontres ? Ruptures dans le parcours ?"
                            oninput="updateStepField(${index}, 'friction', this.value)">${escapeHtml(step.friction || '')}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-green-700 mb-1">&#x1F4A1; Opportunites d'amelioration</label>
                        <textarea rows="2"
                            class="w-full px-3 py-2 border-2 border-green-200 rounded-md focus:ring-2 focus:ring-green-400 text-sm resize-none bg-green-50/50"
                            placeholder="Comment optimiser cette etape ? Quelles ameliorations proposer ?"
                            oninput="updateStepField(${index}, 'opportunites', this.value)">${escapeHtml(step.opportunites || '')}</textarea>
                    </div>
                </div>
            `;

            return div;
        }

        function addStep() {
            steps.push({
                titre: '',
                canal: '',
                point_contact: '',
                action_utilisateur: '',
                info_recue: '',
                emotions: [],
                friction: '',
                opportunites: ''
            });
            renderSteps();
            scheduleAutoSave();

            // Scroll vers la nouvelle etape
            setTimeout(() => {
                const container = document.getElementById('stepsContainer');
                const lastCard = container.lastElementChild;
                if (lastCard) lastCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }

        function removeStep(index) {
            if (!confirm('Supprimer cette etape du parcours ?')) return;
            steps.splice(index, 1);
            renderSteps();
            scheduleAutoSave();
        }

        function updateStepField(index, field, value) {
            if (steps[index]) {
                steps[index][field] = value;
                scheduleAutoSave();
            }
        }

        function toggleEmotion(index, emotion, btn) {
            if (!steps[index].emotions) steps[index].emotions = [];
            const idx = steps[index].emotions.indexOf(emotion);
            if (idx > -1) {
                steps[index].emotions.splice(idx, 1);
                btn.classList.remove('selected');
            } else {
                steps[index].emotions.push(emotion);
                btn.classList.add('selected');
            }
            scheduleAutoSave();
            updateEmotionCurve();
        }

        function updateStepCount() {
            document.getElementById('stepCount').textContent = steps.length + ' etape(s)';
        }

        // Drag & Drop
        function handleDragStart(e) {
            dragSrcIndex = parseInt(e.currentTarget.dataset.index);
            e.currentTarget.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            e.currentTarget.classList.add('drag-over');
        }

        function handleDragLeave(e) {
            e.currentTarget.classList.remove('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('drag-over');
            const targetIndex = parseInt(e.currentTarget.dataset.index);
            if (dragSrcIndex !== null && dragSrcIndex !== targetIndex) {
                const movedStep = steps.splice(dragSrcIndex, 1)[0];
                steps.splice(targetIndex, 0, movedStep);
                renderSteps();
                scheduleAutoSave();
            }
        }

        function handleDragEnd(e) {
            e.currentTarget.classList.remove('dragging');
            dragSrcIndex = null;
        }

        // Emotion Curve
        function updateEmotionCurve() {
            const section = document.getElementById('emotionCurveSection');
            if (steps.length < 2) {
                section.style.display = 'none';
                return;
            }
            section.style.display = 'block';

            const canvas = document.getElementById('emotionCanvas');
            const ctx = canvas.getContext('2d');

            // Resize canvas
            canvas.width = canvas.parentElement.clientWidth;
            canvas.height = 200;

            const width = canvas.width;
            const height = canvas.height;
            const padding = { top: 20, right: 30, bottom: 40, left: 30 };
            const plotWidth = width - padding.left - padding.right;
            const plotHeight = height - padding.top - padding.bottom;

            ctx.clearRect(0, 0, width, height);

            // Calculer les valeurs emotionnelles moyennes par etape
            const stepValues = steps.map(step => {
                if (!step.emotions || step.emotions.length === 0) return null;
                let sum = 0;
                step.emotions.forEach(e => { sum += (emotionValues[e] || 3); });
                return sum / step.emotions.length;
            });

            // Grille de fond
            ctx.strokeStyle = '#e5e7eb';
            ctx.lineWidth = 1;
            for (let i = 0; i <= 4; i++) {
                const y = padding.top + (plotHeight * i / 4);
                ctx.beginPath();
                ctx.moveTo(padding.left, y);
                ctx.lineTo(width - padding.right, y);
                ctx.stroke();
            }

            // Labels gauche
            ctx.fillStyle = '#9ca3af';
            ctx.font = '11px sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText('Positif', padding.left - 5, padding.top + 10);
            ctx.fillText('Neutre', padding.left - 5, padding.top + plotHeight / 2 + 4);
            ctx.fillText('Negatif', padding.left - 5, padding.top + plotHeight);

            // Dessiner la courbe
            const xStep = plotWidth / (steps.length - 1);

            // Zone de gradient sous la courbe
            const hasValues = stepValues.some(v => v !== null);
            if (hasValues) {
                const gradient = ctx.createLinearGradient(0, padding.top, 0, height - padding.bottom);
                gradient.addColorStop(0, 'rgba(6, 182, 212, 0.2)');
                gradient.addColorStop(1, 'rgba(6, 182, 212, 0.02)');

                ctx.beginPath();
                let firstPoint = true;
                stepValues.forEach((val, i) => {
                    if (val === null) return;
                    const x = padding.left + (i * xStep);
                    const y = padding.top + plotHeight - ((val + 1) / 7 * plotHeight);
                    if (firstPoint) {
                        ctx.moveTo(x, y);
                        firstPoint = false;
                    } else {
                        ctx.lineTo(x, y);
                    }
                });

                // Fermer la zone
                for (let i = stepValues.length - 1; i >= 0; i--) {
                    if (stepValues[i] !== null) {
                        ctx.lineTo(padding.left + (i * xStep), height - padding.bottom);
                        break;
                    }
                }
                for (let i = 0; i < stepValues.length; i++) {
                    if (stepValues[i] !== null) {
                        ctx.lineTo(padding.left + (i * xStep), height - padding.bottom);
                        break;
                    }
                }
                ctx.closePath();
                ctx.fillStyle = gradient;
                ctx.fill();

                // Ligne de la courbe
                ctx.beginPath();
                firstPoint = true;
                stepValues.forEach((val, i) => {
                    if (val === null) return;
                    const x = padding.left + (i * xStep);
                    const y = padding.top + plotHeight - ((val + 1) / 7 * plotHeight);
                    if (firstPoint) {
                        ctx.moveTo(x, y);
                        firstPoint = false;
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                ctx.strokeStyle = '#0891b2';
                ctx.lineWidth = 3;
                ctx.stroke();

                // Points
                stepValues.forEach((val, i) => {
                    if (val === null) return;
                    const x = padding.left + (i * xStep);
                    const y = padding.top + plotHeight - ((val + 1) / 7 * plotHeight);

                    ctx.beginPath();
                    ctx.arc(x, y, 6, 0, Math.PI * 2);
                    ctx.fillStyle = val >= 4 ? '#22c55e' : val >= 2 ? '#f59e0b' : '#ef4444';
                    ctx.fill();
                    ctx.strokeStyle = 'white';
                    ctx.lineWidth = 2;
                    ctx.stroke();
                });
            }

            // Labels etapes en bas
            ctx.fillStyle = '#6b7280';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            steps.forEach((step, i) => {
                const x = padding.left + (i * xStep);
                const label = step.titre ? (step.titre.length > 15 ? step.titre.substring(0, 15) + '...' : step.titre) : `Etape ${i + 1}`;
                ctx.fillText(label, x, height - 5);
            });

            // Legende
            const legendDiv = document.getElementById('emotionLegend');
            legendDiv.innerHTML = '';
            for (const [key, data] of Object.entries(emotions)) {
                const color = emotionColors[key];
                legendDiv.innerHTML += `<span class="flex items-center gap-1"><span style="color:${color}">${data.emoji}</span> ${data.label}</span>`;
            }
        }

        // Sauvegarde automatique
        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            document.getElementById('saveStatus').textContent = 'Sauvegarde...';
            document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-400 text-yellow-900';
            autoSaveTimeout = setTimeout(saveData, 1000);
        }

        async function saveData() {
            const payload = {
                nom_organisation: document.getElementById('nomOrganisation').value,
                objectif_audit: document.getElementById('objectifAudit').value,
                public_cible: document.getElementById('publicCible').value,
                journey_data: steps,
                synthese: document.getElementById('synthese').value,
                recommandations: document.getElementById('recommandations').value,
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
                    document.getElementById('saveStatus').textContent = 'Sauvegarde';
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                    document.getElementById('completion').innerHTML = 'Completion: <strong>' + result.completion + '%</strong>';
                } else {
                    document.getElementById('saveStatus').textContent = 'Erreur';
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
                }
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('saveStatus').textContent = 'Erreur reseau';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
            }
        }

        async function manualSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            await saveData();
        }

        async function submitAnalyse() {
            if (!confirm('Soumettre votre analyse Journey Mapping au formateur ? Vous pourrez toujours la modifier apres.')) return;
            await saveData();

            try {
                const response = await fetch('api/submit.php', { method: 'POST' });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('saveStatus').textContent = 'Soumis';
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-cyan-500 text-white';
                    alert('Analyse Journey Mapping soumise avec succes !');
                } else {
                    alert(result.error || 'Erreur lors de la soumission');
                }
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        // Exports
        function exportJSON() {
            const data = {
                nom_organisation: document.getElementById('nomOrganisation').value,
                objectif_audit: document.getElementById('objectifAudit').value,
                public_cible: document.getElementById('publicCible').value,
                journey_steps: steps,
                synthese: document.getElementById('synthese').value,
                recommandations: document.getElementById('recommandations').value,
                notes: document.getElementById('notes').value,
                dateExport: new Date().toISOString()
            };

            const dataStr = JSON.stringify(data, null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            const nom = data.nom_organisation ? data.nom_organisation.replace(/[^a-z0-9]/gi, '_').toLowerCase() : 'journey_mapping';
            link.download = `journey_mapping_${nom}_${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            URL.revokeObjectURL(url);
        }

        function exportToExcel() {
            const wb = XLSX.utils.book_new();
            const nomOrg = document.getElementById('nomOrganisation').value || 'Journey Mapping';

            // Feuille 1: Informations generales
            const infoData = [
                ['JOURNEY MAPPING - AUDIT DE COMMUNICATION'],
                [''],
                ['Organisation', document.getElementById('nomOrganisation').value],
                ['Public cible', document.getElementById('publicCible').value],
                ['Objectif de l\'audit', document.getElementById('objectifAudit').value],
                ['Date d\'export', new Date().toLocaleDateString('fr-FR')],
                ['Nombre d\'etapes', steps.length.toString()]
            ];
            const wsInfo = XLSX.utils.aoa_to_sheet(infoData);
            wsInfo['!cols'] = [{ wch: 25 }, { wch: 60 }];

            // Feuille 2: Etapes du parcours
            const stepsData = [
                ['ETAPES DU PARCOURS'],
                [''],
                ['#', 'Titre', 'Canal', 'Point de contact', 'Action utilisateur', 'Information recue', 'Emotions', 'Points de friction', 'Opportunites']
            ];
            steps.forEach((step, i) => {
                const emotionLabels = (step.emotions || []).map(e => emotions[e] ? emotions[e].label : e).join(', ');
                stepsData.push([
                    (i + 1).toString(),
                    step.titre || '',
                    channels[step.canal] || step.canal || '',
                    step.point_contact || '',
                    step.action_utilisateur || '',
                    step.info_recue || '',
                    emotionLabels,
                    step.friction || '',
                    step.opportunites || ''
                ]);
            });
            const wsSteps = XLSX.utils.aoa_to_sheet(stepsData);
            wsSteps['!cols'] = [
                { wch: 4 }, { wch: 25 }, { wch: 20 }, { wch: 25 },
                { wch: 35 }, { wch: 35 }, { wch: 25 }, { wch: 35 }, { wch: 35 }
            ];

            // Feuille 3: Synthese & Recommandations
            const syntheseData = [
                ['SYNTHESE & RECOMMANDATIONS'],
                [''],
                ['SYNTHESE DE L\'ANALYSE'],
                [document.getElementById('synthese').value || ''],
                [''],
                ['RECOMMANDATIONS'],
                [document.getElementById('recommandations').value || ''],
                [''],
                ['NOTES COMPLEMENTAIRES'],
                [document.getElementById('notes').value || '']
            ];
            const wsSynthese = XLSX.utils.aoa_to_sheet(syntheseData);
            wsSynthese['!cols'] = [{ wch: 100 }];

            XLSX.utils.book_append_sheet(wb, wsInfo, 'Informations');
            XLSX.utils.book_append_sheet(wb, wsSteps, 'Parcours');
            XLSX.utils.book_append_sheet(wb, wsSynthese, 'Synthese');

            const filename = `journey_mapping_${nomOrg.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, filename);
        }

        function exportToWord() {
            const nomOrg = document.getElementById('nomOrganisation').value || 'Journey Mapping';
            const publicCible = document.getElementById('publicCible').value;
            const objectif = document.getElementById('objectifAudit').value;
            const synthese = document.getElementById('synthese').value;
            const recommandations = document.getElementById('recommandations').value;
            const notes = document.getElementById('notes').value;

            let stepsHtml = '';
            steps.forEach((step, i) => {
                const canalLabel = channels[step.canal] || step.canal || 'Non defini';
                const emotionLabels = (step.emotions || []).map(e => {
                    const data = emotions[e];
                    return data ? data.emoji + ' ' + data.label : e;
                }).join(', ');

                stepsHtml += `
                    <div style="border: 2px solid #0891b2; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <h3 style="color: #0891b2; margin-top: 0;">Etape ${i + 1} : ${step.titre || 'Sans titre'}</h3>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                            <tr><td style="padding: 5px; font-weight: bold; width: 180px;">Canal :</td><td style="padding: 5px;">${canalLabel}</td></tr>
                            <tr><td style="padding: 5px; font-weight: bold;">Point de contact :</td><td style="padding: 5px;">${step.point_contact || '-'}</td></tr>
                            <tr><td style="padding: 5px; font-weight: bold;">Action utilisateur :</td><td style="padding: 5px;">${step.action_utilisateur || '-'}</td></tr>
                            <tr><td style="padding: 5px; font-weight: bold;">Information recue :</td><td style="padding: 5px;">${step.info_recue || '-'}</td></tr>
                            <tr><td style="padding: 5px; font-weight: bold;">Emotions :</td><td style="padding: 5px;">${emotionLabels || '-'}</td></tr>
                        </table>
                        ${step.friction ? `<p style="color: #dc2626;"><strong>Points de friction :</strong> ${step.friction}</p>` : ''}
                        ${step.opportunites ? `<p style="color: #16a34a;"><strong>Opportunites :</strong> ${step.opportunites}</p>` : ''}
                    </div>
                `;
            });

            const html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Journey Mapping - ${nomOrg}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
                        h1 { color: #0e7490; border-bottom: 3px solid #0891b2; padding-bottom: 10px; }
                        h2 { color: #164e63; margin-top: 30px; }
                        table { border-collapse: collapse; width: 100%; margin: 15px 0; }
                        td, th { border: 1px solid #ddd; padding: 10px; text-align: left; }
                        th { background-color: #ecfeff; }
                        .synthese { background-color: #ecfeff; padding: 15px; border-radius: 8px; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <h1>Journey Mapping - Audit de Communication</h1>

                    <table>
                        <tr><th>Organisation</th><td>${nomOrg}</td></tr>
                        <tr><th>Public cible</th><td>${publicCible}</td></tr>
                        <tr><th>Objectif de l'audit</th><td>${objectif}</td></tr>
                        <tr><th>Nombre d'etapes</th><td>${steps.length}</td></tr>
                        <tr><th>Date</th><td>${new Date().toLocaleDateString('fr-FR')}</td></tr>
                    </table>

                    <h2>Etapes du Parcours</h2>
                    ${stepsHtml || '<p><em>Aucune etape definie</em></p>'}

                    <div class="synthese">
                        <h2 style="margin-top: 10px;">Synthese de l'analyse</h2>
                        <p>${synthese ? synthese.replace(/\n/g, '<br>') : '<em>Non renseigne</em>'}</p>
                    </div>

                    <h2>Recommandations</h2>
                    <p>${recommandations ? recommandations.replace(/\n/g, '<br>') : '<em>Non renseigne</em>'}</p>

                    ${notes ? `
                    <h2>Notes Complementaires</h2>
                    <p>${notes.replace(/\n/g, '<br>')}</p>
                    ` : ''}
                </body>
                </html>
            `;

            const blob = new Blob([html], { type: 'application/msword' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `journey_mapping_${nomOrg.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_${new Date().toISOString().split('T')[0]}.doc`;
            link.click();
            URL.revokeObjectURL(url);
        }

        // Utilitaire d'echappement HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    </script>
</body>
</html>
