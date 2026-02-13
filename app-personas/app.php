<?php
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user = getLoggedUser();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$sessionId = validateCurrentSession($db);
if (!$sessionId) { header('Location: login.php'); exit; }
ensureParticipant($db, $sessionId, $user);
$sessionNom = $_SESSION['current_session_nom'] ?? '';

$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$analyse = $stmt->fetch();

if (!$analyse) {
    $stmt = $db->prepare("INSERT INTO analyses (user_id, session_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $sessionId]);
    $stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$user['id'], $sessionId]);
    $analyse = $stmt->fetch();
}

$stakeholdersData = json_decode($analyse['stakeholders_data'], true) ?: [];
$personasData = json_decode($analyse['personas_data'], true) ?: [];
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;
$families = getPublicFamilies();
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publics & Personas - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='10' r='6' fill='%23e11d48'/><circle cx='8' cy='22' r='4' fill='%23f43f5e'/><circle cx='24' cy='22' r='4' fill='%23fb7185'/><path d='M4 30 Q16 20 28 30' stroke='%23e11d48' stroke-width='2' fill='none'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #9f1239 0%, #881337 50%, #4c0519 100%); min-height: 100vh; }
        @media print { .no-print { display: none !important; } body { background: white; } }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .tab-btn { transition: all 0.2s; }
        .tab-btn.active { background: white; color: #e11d48; font-weight: 700; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .priority-high { border-left: 4px solid #ef4444; }
        .priority-medium { border-left: 4px solid #f59e0b; }
        .priority-low { border-left: 4px solid #6b7280; }
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
                <button onclick="manualSave()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">Sauvegarder</button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-gray-200"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <span id="completion" class="text-sm text-gray-600">Completion: <strong>0%</strong></span>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="text-sm bg-rose-600 hover:bg-rose-500 text-white px-3 py-1 rounded transition">Formateur</a>
                <?php endif; ?>
                <a href="logout.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
            <div class="text-center mb-6">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Definir et connaitre ses publics</h1>
                <p class="text-gray-600 italic">Cartographie des parties prenantes & creation de personas</p>
            </div>

            <!-- Introduction -->
            <div class="bg-gradient-to-r from-rose-50 via-pink-50 to-red-50 p-6 rounded-lg border-2 border-rose-200 shadow-md mb-6">
                <p class="text-gray-700 leading-relaxed">
                    Vous ne parlez pas a <strong>un</strong> public, vous parlez a <strong>des</strong> publics. Meme la plus petite association a plusieurs publics — et c'est une bonne nouvelle, parce que ca veut dire plusieurs leviers d'action.
                    Le probleme, c'est qu'on les melange souvent. Cet outil vous aide a les identifier, les prioriser, et leur donner un visage concret.
                </p>
            </div>

            <!-- Organisation -->
            <div class="bg-gradient-to-r from-rose-50 to-pink-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">&#x1F3E2; Votre organisation</label>
                <input type="text" id="nomOrganisation"
                    class="w-full px-4 py-2 border-2 border-rose-200 rounded-md focus:ring-2 focus:ring-rose-500 focus:border-transparent"
                    placeholder="Nom de votre association ou organisation..."
                    value="<?= h($analyse['nom_organisation'] ?? '') ?>"
                    oninput="scheduleAutoSave()">
            </div>
        </div>

        <!-- Onglets -->
        <div class="flex gap-2 mb-4 no-print">
            <button onclick="switchTab('stakeholders')" id="tab-stakeholders" class="tab-btn active flex-1 py-3 rounded-lg text-sm font-medium bg-white/30 text-white text-center">
                &#x1F5FA;&#xFE0F; Partie 1 — Carte des publics
            </button>
            <button onclick="switchTab('personas')" id="tab-personas" class="tab-btn flex-1 py-3 rounded-lg text-sm font-medium bg-white/30 text-white text-center">
                &#x1F464; Partie 2 — Personas
            </button>
            <button onclick="switchTab('synthese')" id="tab-synthese" class="tab-btn flex-1 py-3 rounded-lg text-sm font-medium bg-white/30 text-white text-center">
                &#x1F4CB; Synthese
            </button>
        </div>

        <!-- ======================== -->
        <!-- TAB 1: CARTE DES PUBLICS -->
        <!-- ======================== -->
        <div id="panel-stakeholders" class="tab-panel">
            <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">&#x1F5FA;&#xFE0F; Carte des parties prenantes</h2>
                    <p class="text-gray-600 text-sm">Placez tous les groupes de personnes qui ont un lien avec votre organisation. Pour chaque public, repondez aux trois questions cles.</p>
                </div>

                <!-- 7 familles de publics -->
                <div class="grid md:grid-cols-4 gap-3 mb-6">
                    <?php foreach ($families as $fKey => $fDef): if ($fKey === 'autre') continue; ?>
                    <div class="bg-<?= $fDef['color'] ?>-50 border border-<?= $fDef['color'] ?>-200 rounded-lg p-3 text-center">
                        <div class="text-2xl mb-1"><?= $fDef['icon'] ?></div>
                        <div class="font-semibold text-<?= $fDef['color'] ?>-800 text-sm"><?= $fDef['label'] ?></div>
                        <div class="text-xs text-<?= $fDef['color'] ?>-600 mt-1"><?= $fDef['description'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex flex-wrap justify-between items-center mb-4">
                    <span id="stakeholderCount" class="text-sm text-gray-500">0 public(s) identifie(s)</span>
                    <button onclick="addStakeholder()" class="no-print bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                        &#x2795; Ajouter un public
                    </button>
                </div>

                <div id="stakeholdersContainer" class="space-y-4"></div>
                <div id="stakeholderEmpty" class="text-center py-10 text-gray-400">
                    <p class="text-4xl mb-3">&#x1F5FA;&#xFE0F;</p>
                    <p>Cliquez sur "Ajouter un public" pour commencer votre carte</p>
                </div>
            </div>
        </div>

        <!-- ======================== -->
        <!-- TAB 2: PERSONAS          -->
        <!-- ======================== -->
        <div id="panel-personas" class="tab-panel" style="display:none;">
            <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">&#x1F464; Creation de personas</h2>
                    <p class="text-gray-600 text-sm mb-3">
                        Un persona, c'est un portrait fictif mais realiste d'une personne representative d'un de vos publics. Creez 2 a 3 personas pour vos publics prioritaires.
                    </p>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                        <strong>Le test du persona reussi :</strong> "Si je devais ecrire un post Facebook pour cette personne, est-ce que je saurais quoi ecrire, comment l'ecrire, et ou le poster ?" Si oui, votre persona est bon.
                    </div>
                </div>

                <div class="flex flex-wrap justify-between items-center mb-4">
                    <span id="personaCount" class="text-sm text-gray-500">0 persona(s)</span>
                    <button onclick="addPersona()" class="no-print bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                        &#x2795; Creer un persona
                    </button>
                </div>

                <div id="personasContainer" class="space-y-6"></div>
                <div id="personaEmpty" class="text-center py-10 text-gray-400">
                    <p class="text-4xl mb-3">&#x1F464;</p>
                    <p>Cliquez sur "Creer un persona" pour donner un visage a vos publics</p>
                </div>
            </div>
        </div>

        <!-- ======================== -->
        <!-- TAB 3: SYNTHESE          -->
        <!-- ======================== -->
        <div id="panel-synthese" class="tab-panel" style="display:none;">
            <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">&#x1F4CB; Synthese & Constats</h2>

                <!-- Stats rapides -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-rose-50 rounded-xl p-4 text-center border border-rose-200">
                        <div id="statStakeholders" class="text-3xl font-bold text-rose-600">0</div>
                        <div class="text-sm text-gray-500">Publics identifies</div>
                    </div>
                    <div class="bg-pink-50 rounded-xl p-4 text-center border border-pink-200">
                        <div id="statPersonas" class="text-3xl font-bold text-pink-600">0</div>
                        <div class="text-sm text-gray-500">Personas crees</div>
                    </div>
                    <div class="bg-red-50 rounded-xl p-4 text-center border border-red-200">
                        <div id="statHighPriority" class="text-3xl font-bold text-red-600">0</div>
                        <div class="text-sm text-gray-500">Priorite haute</div>
                    </div>
                    <div class="bg-amber-50 rounded-xl p-4 text-center border border-amber-200">
                        <div id="statFamilies" class="text-3xl font-bold text-amber-600">0</div>
                        <div class="text-sm text-gray-500">Familles couvertes</div>
                    </div>
                </div>

                <div class="mb-6 bg-gradient-to-r from-rose-50 to-pink-50 p-4 rounded-lg">
                    <label class="block text-lg font-semibold text-gray-800 mb-2">Synthese de votre analyse</label>
                    <p class="text-sm text-gray-600 mb-3 italic">
                        Quels desequilibres constatez-vous ? Beaucoup d'energie sur certains publics et des trous beants pour d'autres ? Ce n'est pas un probleme — c'est un diagnostic.
                    </p>
                    <textarea id="synthese" rows="6"
                        class="w-full px-4 py-2 border-2 border-rose-300 rounded-md focus:ring-2 focus:ring-rose-500"
                        placeholder="Resumez vos constats : quels publics sont bien couverts ? Lesquels sont negliges ? Quels choix conscients faites-vous ?"
                        oninput="scheduleAutoSave()"><?= h($analyse['synthese'] ?? '') ?></textarea>
                </div>

                <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                    <label class="block text-lg font-semibold text-gray-800 mb-2">&#x270F;&#xFE0F; Notes</label>
                    <textarea id="notes" rows="3"
                        class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500"
                        placeholder="Notes libres..."
                        oninput="scheduleAutoSave()"><?= h($analyse['notes'] ?? '') ?></textarea>
                </div>

                <div class="no-print flex flex-wrap gap-3 pt-4 border-t-2 border-gray-200">
                    <button onclick="submitAnalyse()" class="bg-rose-600 text-white px-6 py-3 rounded-md hover:bg-rose-700 transition font-semibold shadow-md">&#x2705; Soumettre</button>
                    <button onclick="exportToExcel()" class="bg-emerald-600 text-white px-6 py-3 rounded-md hover:bg-emerald-700 transition font-semibold shadow-md">&#x1F4CA; Export Excel</button>
                    <button onclick="exportJSON()" class="bg-gray-500 text-white px-6 py-3 rounded-md hover:bg-gray-600 transition font-semibold shadow-md">&#x1F4E5; JSON</button>
                    <button onclick="window.print()" class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition font-semibold shadow-md">&#x1F5A8;&#xFE0F; Imprimer</button>
                </div>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>
    <script>
    // Donnees
    let stakeholders = <?= json_encode($stakeholdersData) ?>;
    let personas = <?= json_encode($personasData) ?>;
    let autoSaveTimeout = null;

    const families = <?= json_encode($families) ?>;

    // Onglets
    function switchTab(tab) {
        document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('panel-' + tab).style.display = 'block';
        document.getElementById('tab-' + tab).classList.add('active');
        if (tab === 'synthese') updateStats();
    }

    // ========================
    // STAKEHOLDERS
    // ========================
    function renderStakeholders() {
        const c = document.getElementById('stakeholdersContainer');
        const empty = document.getElementById('stakeholderEmpty');
        if (stakeholders.length === 0) { c.innerHTML = ''; empty.style.display = 'block'; updateStakeholderCount(); return; }
        empty.style.display = 'none';
        c.innerHTML = '';
        stakeholders.forEach((s, i) => c.appendChild(createStakeholderCard(s, i)));
        updateStakeholderCount();
    }

    function createStakeholderCard(s, index) {
        const div = document.createElement('div');
        const priority = s.priorite || 'medium';
        div.className = 'card-hover fade-in bg-white rounded-xl border-2 border-gray-100 shadow-lg p-5 priority-' + priority;

        let familyOptions = '';
        for (const [key, f] of Object.entries(families)) {
            familyOptions += '<option value="' + key + '"' + (s.famille === key ? ' selected' : '') + '>' + f.icon + ' ' + f.label + '</option>';
        }

        let priorityOptions = '';
        [['high', 'Haute — Public prioritaire'], ['medium', 'Moyenne'], ['low', 'Basse — Pas prioritaire pour l\'instant']].forEach(([v, l]) => {
            priorityOptions += '<option value="' + v + '"' + (priority === v ? ' selected' : '') + '>' + l + '</option>';
        });

        div.innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <span class="bg-rose-600 text-white text-xs font-bold px-3 py-1 rounded-full">Public ${index + 1}</span>
                    <input type="text" value="${esc(s.nom || '')}"
                        class="text-lg font-bold text-gray-800 border-0 border-b-2 border-transparent focus:border-rose-400 focus:outline-none bg-transparent"
                        placeholder="Nom de ce public..."
                        oninput="updateSH(${index}, 'nom', this.value)">
                </div>
                <button onclick="removeStakeholder(${index})" class="no-print text-red-400 hover:text-red-600">&#x2716;</button>
            </div>
            <div class="grid md:grid-cols-3 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Famille de public</label>
                    <select class="w-full px-2 py-1.5 border rounded text-sm bg-white" onchange="updateSH(${index}, 'famille', this.value)">${familyOptions}</select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Priorite</label>
                    <select class="w-full px-2 py-1.5 border rounded text-sm bg-white" onchange="updateSHPriority(${index}, this.value)">${priorityOptions}</select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Sous-groupe / precision</label>
                    <input type="text" value="${esc(s.sous_groupe || '')}" class="w-full px-2 py-1.5 border rounded text-sm" placeholder="Ex: Habitues, occasionnels..."
                        oninput="updateSH(${index}, 'sous_groupe', this.value)">
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-3">
                <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                    <label class="block text-xs font-semibold text-blue-800 mb-1">&#x2753; Que veut ce public ?</label>
                    <textarea rows="3" class="w-full px-2 py-1 border rounded text-sm resize-none" placeholder="Qu'attend-il de vous ?"
                        oninput="updateSH(${index}, 'attentes', this.value)">${esc(s.attentes || '')}</textarea>
                </div>
                <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                    <label class="block text-xs font-semibold text-green-800 mb-1">&#x1F4CD; Ou est-il ?</label>
                    <textarea rows="3" class="w-full px-2 py-1 border rounded text-sm resize-none" placeholder="Ou le trouver, physiquement et numeriquement ?"
                        oninput="updateSH(${index}, 'localisation', this.value)">${esc(s.localisation || '')}</textarea>
                </div>
                <div class="bg-amber-50 p-3 rounded-lg border border-amber-200">
                    <label class="block text-xs font-semibold text-amber-800 mb-1">&#x1F4E3; Comment lui parle-t-on ?</label>
                    <textarea rows="3" class="w-full px-2 py-1 border rounded text-sm resize-none" placeholder="Communication actuelle. Est-ce adapte ?"
                        oninput="updateSH(${index}, 'communication_actuelle', this.value)">${esc(s.communication_actuelle || '')}</textarea>
                </div>
            </div>
        `;
        return div;
    }

    function addStakeholder() {
        stakeholders.push({ nom: '', famille: 'beneficiaires', sous_groupe: '', priorite: 'medium', attentes: '', localisation: '', communication_actuelle: '' });
        renderStakeholders();
        scheduleAutoSave();
        setTimeout(() => { const c = document.getElementById('stakeholdersContainer'); c.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    }

    function removeStakeholder(i) {
        if (!confirm('Supprimer ce public ?')) return;
        stakeholders.splice(i, 1);
        renderStakeholders();
        scheduleAutoSave();
    }

    function updateSH(i, field, val) { if (stakeholders[i]) { stakeholders[i][field] = val; scheduleAutoSave(); } }
    function updateSHPriority(i, val) {
        if (!stakeholders[i]) return;
        stakeholders[i].priorite = val;
        renderStakeholders();
        scheduleAutoSave();
    }
    function updateStakeholderCount() { document.getElementById('stakeholderCount').textContent = stakeholders.length + ' public(s) identifie(s)'; }

    // ========================
    // PERSONAS
    // ========================
    function renderPersonas() {
        const c = document.getElementById('personasContainer');
        const empty = document.getElementById('personaEmpty');
        if (personas.length === 0) { c.innerHTML = ''; empty.style.display = 'block'; updatePersonaCount(); return; }
        empty.style.display = 'none';
        c.innerHTML = '';
        personas.forEach((p, i) => c.appendChild(createPersonaCard(p, i)));
        updatePersonaCount();
    }

    function createPersonaCard(p, index) {
        const div = document.createElement('div');
        div.className = 'card-hover fade-in bg-gradient-to-br from-white to-rose-50/30 rounded-xl border-2 border-rose-100 shadow-lg p-5';

        let familyOptions = '';
        for (const [key, f] of Object.entries(families)) {
            familyOptions += '<option value="' + key + '"' + (p.type_public === key ? ' selected' : '') + '>' + f.icon + ' ' + f.label + '</option>';
        }

        // Aussi ajouter les stakeholders nommes comme options
        let stakeholderOptions = '<option value="">-- Depuis la carte --</option>';
        stakeholders.forEach(s => {
            if (s.nom) {
                const sel = p.type_public_custom === s.nom ? ' selected' : '';
                stakeholderOptions += '<option value="' + esc(s.nom) + '"' + sel + '>' + esc(s.nom) + '</option>';
            }
        });

        div.innerHTML = `
            <div class="flex items-center justify-between mb-4">
                <span class="bg-rose-600 text-white text-sm font-bold px-3 py-1 rounded-full">Persona ${index + 1}</span>
                <button onclick="removePersona(${index})" class="no-print text-red-400 hover:text-red-600">&#x2716;</button>
            </div>

            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Prenom fictif *</label>
                    <input type="text" value="${esc(p.prenom || '')}"
                        class="w-full px-3 py-2 border-2 border-rose-200 rounded-md text-lg font-bold focus:ring-2 focus:ring-rose-500"
                        placeholder="Ex: Nadia, Marc, Sophie..."
                        oninput="updateP(${index}, 'prenom', this.value)">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Age</label>
                    <input type="text" value="${esc(p.age || '')}"
                        class="w-full px-3 py-2 border rounded-md text-sm"
                        placeholder="Ex: 38 ans"
                        oninput="updateP(${index}, 'age', this.value)">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Type de public</label>
                    <select class="w-full px-2 py-2 border rounded-md text-sm bg-white"
                        onchange="updateP(${index}, 'type_public', this.value)">
                        ${familyOptions}
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-500 mb-1">&#x1F4DD; Situation</label>
                <textarea rows="2" class="w-full px-3 py-2 border rounded-md text-sm resize-none"
                    placeholder="Ou habite-t-il/elle ? Situation familiale, professionnelle ? Creez une image mentale precise..."
                    oninput="updateP(${index}, 'situation', this.value)">${esc(p.situation || '')}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-500 mb-1">&#x1F3E2; Rapport a l'organisation</label>
                <textarea rows="2" class="w-full px-3 py-2 border rounded-md text-sm resize-none"
                    placeholder="Comment cette personne vous connait-elle ? Deja beneficiaire ? En a entendu parler ? Ne vous connait pas ?"
                    oninput="updateP(${index}, 'rapport_org', this.value)">${esc(p.rapport_org || '')}</textarea>
            </div>

            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                    <label class="block text-xs font-semibold text-blue-800 mb-1">&#x1F3AF; Besoins et attentes</label>
                    <textarea rows="3" class="w-full px-2 py-1 border rounded text-sm resize-none bg-white"
                        placeholder="Que cherche cette personne dans sa vie ? Pas seulement chez vous..."
                        oninput="updateP(${index}, 'besoins', this.value)">${esc(p.besoins || '')}</textarea>
                </div>
                <div class="bg-purple-50 p-3 rounded-lg border border-purple-200">
                    <label class="block text-xs font-semibold text-purple-800 mb-1">&#x1F4F1; Habitudes medias</label>
                    <textarea rows="3" class="w-full px-2 py-1 border rounded text-sm resize-none bg-white"
                        placeholder="Ou s'informe-t-elle ? Reseaux sociaux, presse, bouche-a-oreille, affichage ?"
                        oninput="updateP(${index}, 'habitudes_medias', this.value)">${esc(p.habitudes_medias || '')}</textarea>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                    <label class="block text-xs font-semibold text-green-800 mb-1">&#x2764;&#xFE0F; Ce qui le/la touche</label>
                    <textarea rows="2" class="w-full px-2 py-1 border rounded text-sm resize-none bg-white"
                        placeholder="Temoignages ? Chiffres ? Proximite ? Humour ? Urgence ?"
                        oninput="updateP(${index}, 'ce_qui_touche', this.value)">${esc(p.ce_qui_touche || '')}</textarea>
                </div>
                <div class="bg-red-50 p-3 rounded-lg border border-red-200">
                    <label class="block text-xs font-semibold text-red-800 mb-1">&#x1F6AB; Ce qui le/la rebute</label>
                    <textarea rows="2" class="w-full px-2 py-1 border rounded text-sm resize-none bg-white"
                        placeholder="Jargon institutionnel ? Sollicitations repetees ? Ton condescendant ?"
                        oninput="updateP(${index}, 'ce_qui_rebute', this.value)">${esc(p.ce_qui_rebute || '')}</textarea>
                </div>
            </div>

            <div class="bg-gradient-to-r from-rose-50 to-pink-50 p-3 rounded-lg border-2 border-rose-200">
                <label class="block text-xs font-semibold text-rose-800 mb-1">&#x1F4AC; Le message ideal</label>
                <p class="text-xs text-rose-600 mb-1 italic">Si cette personne ne retient qu'une seule phrase de toute votre communication, ce serait laquelle ?</p>
                <input type="text" value="${esc(p.message_ideal || '')}"
                    class="w-full px-3 py-2 border-2 border-rose-200 rounded-md text-sm focus:ring-2 focus:ring-rose-500 font-medium"
                    placeholder="La phrase cle pour ce persona..."
                    oninput="updateP(${index}, 'message_ideal', this.value)">
            </div>
        `;
        return div;
    }

    function addPersona() {
        personas.push({ prenom: '', age: '', type_public: 'beneficiaires', situation: '', rapport_org: '', besoins: '', habitudes_medias: '', ce_qui_touche: '', ce_qui_rebute: '', message_ideal: '' });
        renderPersonas();
        scheduleAutoSave();
        setTimeout(() => { const c = document.getElementById('personasContainer'); c.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    }

    function removePersona(i) {
        if (!confirm('Supprimer ce persona ?')) return;
        personas.splice(i, 1);
        renderPersonas();
        scheduleAutoSave();
    }

    function updateP(i, field, val) { if (personas[i]) { personas[i][field] = val; scheduleAutoSave(); } }
    function updatePersonaCount() { document.getElementById('personaCount').textContent = personas.length + ' persona(s)'; }

    // Stats
    function updateStats() {
        document.getElementById('statStakeholders').textContent = stakeholders.length;
        document.getElementById('statPersonas').textContent = personas.length;
        document.getElementById('statHighPriority').textContent = stakeholders.filter(s => s.priorite === 'high').length;
        const fams = new Set();
        stakeholders.forEach(s => { if (s.famille) fams.add(s.famille); });
        document.getElementById('statFamilies').textContent = fams.size;
    }

    // Sauvegarde
    function scheduleAutoSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        document.getElementById('saveStatus').textContent = 'Sauvegarde...';
        document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-400 text-yellow-900';
        autoSaveTimeout = setTimeout(saveData, 1000);
    }

    async function saveData() {
        const payload = {
            nom_organisation: document.getElementById('nomOrganisation').value,
            contexte: '',
            stakeholders_data: stakeholders,
            personas_data: personas,
            synthese: document.getElementById('synthese').value,
            notes: document.getElementById('notes').value
        };
        try {
            const r = await fetch('api/save.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const res = await r.json();
            if (res.success) {
                document.getElementById('saveStatus').textContent = 'Sauvegarde';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                document.getElementById('completion').innerHTML = 'Completion: <strong>' + res.completion + '%</strong>';
            } else {
                document.getElementById('saveStatus').textContent = 'Erreur';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
            }
        } catch (e) {
            document.getElementById('saveStatus').textContent = 'Erreur reseau';
            document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
        }
    }

    async function manualSave() { if (autoSaveTimeout) clearTimeout(autoSaveTimeout); await saveData(); }

    async function submitAnalyse() {
        if (!confirm('Soumettre votre analyse au formateur ?')) return;
        await saveData();
        try {
            const r = await fetch('api/submit.php', { method: 'POST' });
            const res = await r.json();
            if (res.success) {
                document.getElementById('saveStatus').textContent = 'Soumis';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-rose-500 text-white';
                alert('Analyse soumise !');
            }
        } catch (e) { console.error(e); }
    }

    // Exports
    function exportJSON() {
        const data = { nom_organisation: document.getElementById('nomOrganisation').value, stakeholders, personas, synthese: document.getElementById('synthese').value, notes: document.getElementById('notes').value, dateExport: new Date().toISOString() };
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const link = document.createElement('a'); link.href = URL.createObjectURL(blob);
        link.download = 'publics_personas_' + new Date().toISOString().split('T')[0] + '.json';
        link.click(); URL.revokeObjectURL(link.href);
    }

    function exportToExcel() {
        const wb = XLSX.utils.book_new();
        const nomOrg = document.getElementById('nomOrganisation').value || 'Mon association';

        // Carte des publics
        const shData = [['CARTE DES PUBLICS — ' + nomOrg], [], ['#', 'Nom', 'Famille', 'Sous-groupe', 'Priorite', 'Que veut ce public ?', 'Ou est-il ?', 'Comment lui parle-t-on ?']];
        stakeholders.forEach((s, i) => {
            shData.push([(i+1).toString(), s.nom||'', families[s.famille]?.label||s.famille||'', s.sous_groupe||'', s.priorite||'', s.attentes||'', s.localisation||'', s.communication_actuelle||'']);
        });
        const ws1 = XLSX.utils.aoa_to_sheet(shData);
        ws1['!cols'] = [{wch:4},{wch:25},{wch:20},{wch:20},{wch:12},{wch:35},{wch:35},{wch:35}];

        // Personas
        const pData = [['PERSONAS — ' + nomOrg], [], ['#', 'Prenom', 'Age', 'Type de public', 'Situation', 'Rapport a l\'org', 'Besoins/attentes', 'Habitudes medias', 'Ce qui touche', 'Ce qui rebute', 'Message ideal']];
        personas.forEach((p, i) => {
            pData.push([(i+1).toString(), p.prenom||'', p.age||'', families[p.type_public]?.label||'', p.situation||'', p.rapport_org||'', p.besoins||'', p.habitudes_medias||'', p.ce_qui_touche||'', p.ce_qui_rebute||'', p.message_ideal||'']);
        });
        const ws2 = XLSX.utils.aoa_to_sheet(pData);
        ws2['!cols'] = [{wch:4},{wch:15},{wch:8},{wch:20},{wch:35},{wch:30},{wch:35},{wch:30},{wch:30},{wch:30},{wch:40}];

        // Synthese
        const sData = [['SYNTHESE'], [], [document.getElementById('synthese').value || ''], [], ['NOTES'], [document.getElementById('notes').value || '']];
        const ws3 = XLSX.utils.aoa_to_sheet(sData);
        ws3['!cols'] = [{wch:100}];

        XLSX.utils.book_append_sheet(wb, ws1, 'Carte des publics');
        XLSX.utils.book_append_sheet(wb, ws2, 'Personas');
        XLSX.utils.book_append_sheet(wb, ws3, 'Synthese');
        XLSX.writeFile(wb, 'publics_personas_' + nomOrg.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_' + new Date().toISOString().split('T')[0] + '.xlsx');
    }

    function esc(t) { const d = document.createElement('div'); d.appendChild(document.createTextNode(t)); return d.innerHTML; }

    // Init
    document.addEventListener('DOMContentLoaded', () => { renderStakeholders(); renderPersonas(); });
    </script>
</body>
</html>
