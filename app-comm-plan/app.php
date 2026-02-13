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
$sessionNom = $_SESSION['current_session_nom'] ?? '';
ensureParticipant($db, $sessionId, $user);

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

$canauxData = json_decode($analyse['canaux_data'], true) ?: [];
$calendrierData = json_decode($analyse['calendrier_data'], true) ?: [];
$ressourcesData = json_decode($analyse['ressources_data'], true) ?: [];
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;
$availableCanaux = getCanaux();
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini-Plan de Communication - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect x='4' y='6' width='24' height='20' rx='3' fill='%234f46e5'/><path d='M4 12h24' stroke='%23818cf8' stroke-width='1.5'/><circle cx='9' cy='9' r='1.5' fill='%23c7d2fe'/><circle cx='14' cy='9' r='1.5' fill='%23c7d2fe'/><circle cx='19' cy='9' r='1.5' fill='%23c7d2fe'/><rect x='8' y='15' width='16' height='2' rx='1' fill='%23a5b4fc'/><rect x='8' y='19' width='12' height='2' rx='1' fill='%23a5b4fc'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #312e81 0%, #3730a3 50%, #1e1b4b 100%); min-height: 100vh; }
        @media print { .no-print { display: none !important; } body { background: white; } }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .section-number { display: flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 50%; background: #4f46e5; color: white; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="p-4 md:p-8">
    <!-- Barre utilisateur -->
    <div class="max-w-5xl mx-auto mb-4 bg-white/90 backdrop-blur rounded-lg shadow-lg p-3 no-print">
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
                <a href="formateur.php" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1 rounded transition">Formateur</a>
                <?php endif; ?>
                <a href="logout.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6">
            <div class="text-center mb-6">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Mini-Plan de Communication</h1>
                <p class="text-gray-600 italic">Construisez un plan de communication concret autour d'une action precise</p>
            </div>

            <div class="bg-gradient-to-r from-indigo-50 via-blue-50 to-violet-50 p-6 rounded-lg border-2 border-indigo-200 shadow-md mb-6">
                <p class="text-gray-700 leading-relaxed">
                    Travaillez a partir d'un <strong>cas concret</strong> — idealement votre propre association — pour elaborer un mini-plan de communication autour d'une action precise :
                    un evenement a venir, une campagne de recrutement de benevoles, le lancement d'un nouveau service, une journee portes ouvertes...
                </p>
            </div>

            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">&#x1F3E2; Votre organisation</label>
                <input type="text" id="nomOrganisation"
                    class="w-full px-4 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    placeholder="Nom de votre association ou organisation..."
                    value="<?= h($analyse['nom_organisation'] ?? '') ?>"
                    oninput="scheduleAutoSave()">
            </div>
        </div>

        <!-- ======================== -->
        <!-- 1. L'ACTION A COMMUNIQUER -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">1</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">L'action a communiquer</h2>
                    <p class="text-gray-500 text-sm">Decrivez en une phrase claire ce que vous voulez promouvoir</p>
                </div>
            </div>
            <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
                <textarea id="actionCommuniquer" rows="3"
                    class="w-full px-4 py-2 border-2 border-indigo-300 rounded-md focus:ring-2 focus:ring-indigo-500 text-lg"
                    placeholder="Ex: Organiser une journee portes ouvertes le 15 mars pour faire decouvrir nos activites aux habitants du quartier..."
                    oninput="scheduleAutoSave()"><?= h($analyse['action_communiquer'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 2. OBJECTIF SMART        -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">2</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">L'objectif SMART</h2>
                    <p class="text-gray-500 text-sm">Specifique, Mesurable, Atteignable, Realiste, Temporellement defini</p>
                </div>
            </div>
            <div class="grid grid-cols-5 gap-2 mb-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
                    <div class="text-lg font-bold text-blue-700">S</div>
                    <div class="text-xs text-blue-600">Specifique</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
                    <div class="text-lg font-bold text-green-700">M</div>
                    <div class="text-xs text-green-600">Mesurable</div>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-center">
                    <div class="text-lg font-bold text-amber-700">A</div>
                    <div class="text-xs text-amber-600">Atteignable</div>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-center">
                    <div class="text-lg font-bold text-orange-700">R</div>
                    <div class="text-xs text-orange-600">Realiste</div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 text-center">
                    <div class="text-lg font-bold text-purple-700">T</div>
                    <div class="text-xs text-purple-600">Temporel</div>
                </div>
            </div>
            <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
                <textarea id="objectifSmart" rows="3"
                    class="w-full px-4 py-2 border-2 border-indigo-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                    placeholder="Ex: Attirer 50 visiteurs lors de la journee portes ouvertes du 15 mars, dont au moins 10 nouvelles personnes qui ne connaissaient pas l'association..."
                    oninput="scheduleAutoSave()"><?= h($analyse['objectif_smart'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 3. PUBLIC PRIORITAIRE    -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">3</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Le public prioritaire</h2>
                    <p class="text-gray-500 text-sm">Quel persona ciblez-vous en priorite ? Pourquoi lui/elle ?</p>
                </div>
            </div>
            <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
                <textarea id="publicPrioritaire" rows="4"
                    class="w-full px-4 py-2 border-2 border-indigo-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                    placeholder="Decrivez votre public cible : qui est-il ? Pourquoi le ciblez-vous en priorite pour cette action ? Quel lien a-t-il avec votre organisation ?"
                    oninput="scheduleAutoSave()"><?= h($analyse['public_prioritaire'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 4. MESSAGE CLE           -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">4</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Le message cle</h2>
                    <p class="text-gray-500 text-sm">En une phrase : que voulez-vous que votre public retienne ?</p>
                </div>
            </div>
            <div class="bg-gradient-to-r from-indigo-50 to-violet-50 p-4 rounded-lg border-2 border-indigo-300">
                <input type="text" id="messageCle"
                    class="w-full px-4 py-3 border-2 border-indigo-300 rounded-md focus:ring-2 focus:ring-indigo-500 text-lg font-medium"
                    placeholder="La phrase que votre public doit retenir..."
                    value="<?= h($analyse['message_cle'] ?? '') ?>"
                    oninput="scheduleAutoSave()">
                <p class="text-xs text-indigo-600 mt-2 italic">Astuce : si votre public ne devait retenir qu'une seule chose, ce serait quoi ?</p>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 5. LES CANAUX            -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">5</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Les canaux</h2>
                    <p class="text-gray-500 text-sm">Quels canaux utiliserez-vous ? Pourquoi ceux-la plutot que d'autres ?</p>
                </div>
            </div>

            <div class="flex flex-wrap justify-between items-center mb-4">
                <span id="canalCount" class="text-sm text-gray-500">0 canal/canaux selectionne(s)</span>
                <button onclick="addCanal()" class="no-print bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                    &#x2795; Ajouter un canal
                </button>
            </div>

            <div id="canauxContainer" class="space-y-3"></div>
            <div id="canalEmpty" class="text-center py-8 text-gray-400">
                <p class="text-3xl mb-2">&#x1F4E2;</p>
                <p>Cliquez sur "Ajouter un canal" pour selectionner vos canaux de communication</p>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 6. LE CALENDRIER         -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">6</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Le calendrier</h2>
                    <p class="text-gray-500 text-sm">Quand commence la communication ? Quelles etapes ? (annonce, rappel, relance, remerciement)</p>
                </div>
            </div>

            <div class="flex flex-wrap justify-between items-center mb-4">
                <span id="etapeCount" class="text-sm text-gray-500">0 etape(s)</span>
                <button onclick="addEtape()" class="no-print bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                    &#x2795; Ajouter une etape
                </button>
            </div>

            <div id="calendrierContainer" class="space-y-3"></div>
            <div id="etapeEmpty" class="text-center py-8 text-gray-400">
                <p class="text-3xl mb-2">&#x1F4C5;</p>
                <p>Cliquez sur "Ajouter une etape" pour planifier votre calendrier de communication</p>
            </div>

            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                <strong>Conseil :</strong> Pensez aux grandes etapes : annonce / teasing, rappel, jour J, relance post-evenement, remerciement / bilan.
            </div>
        </div>

        <!-- ======================== -->
        <!-- 7. LES RESSOURCES        -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <div class="flex items-center gap-4 mb-4">
                <div class="section-number">7</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Les ressources necessaires</h2>
                    <p class="text-gray-500 text-sm">Qui fait quoi ? Combien de temps ? Quel budget eventuel ?</p>
                </div>
            </div>

            <div class="flex flex-wrap justify-between items-center mb-4">
                <span id="ressourceCount" class="text-sm text-gray-500">0 ressource(s)</span>
                <button onclick="addRessource()" class="no-print bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition shadow-md">
                    &#x2795; Ajouter une ressource
                </button>
            </div>

            <div id="ressourcesContainer" class="space-y-3"></div>
            <div id="ressourceEmpty" class="text-center py-8 text-gray-400">
                <p class="text-3xl mb-2">&#x1F465;</p>
                <p>Cliquez sur "Ajouter une ressource" pour definir qui fait quoi</p>
            </div>
        </div>

        <!-- ======================== -->
        <!-- NOTES & ACTIONS          -->
        <!-- ======================== -->
        <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 mb-6 fade-in">
            <h2 class="text-xl font-bold text-gray-800 mb-4">&#x270F;&#xFE0F; Notes</h2>
            <textarea id="notes" rows="3"
                class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500"
                placeholder="Notes libres, questions pour le feedback..."
                oninput="scheduleAutoSave()"><?= h($analyse['notes'] ?? '') ?></textarea>

            <div class="no-print flex flex-wrap gap-3 pt-4 mt-4 border-t-2 border-gray-200">
                <button onclick="submitPlan()" class="bg-indigo-600 text-white px-6 py-3 rounded-md hover:bg-indigo-700 transition font-semibold shadow-md">&#x2705; Soumettre au formateur</button>
                <button onclick="exportToExcel()" class="bg-emerald-600 text-white px-6 py-3 rounded-md hover:bg-emerald-700 transition font-semibold shadow-md">&#x1F4CA; Export Excel</button>
                <button onclick="exportJSON()" class="bg-gray-500 text-white px-6 py-3 rounded-md hover:bg-gray-600 transition font-semibold shadow-md">&#x1F4E5; JSON</button>
                <button onclick="window.print()" class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition font-semibold shadow-md">&#x1F5A8;&#xFE0F; Imprimer</button>
            </div>
        </div>
    </div>

    <?= renderLanguageScript() ?>
    <script>
    // Donnees
    let canaux = <?= json_encode($canauxData) ?>;
    let calendrier = <?= json_encode($calendrierData) ?>;
    let ressources = <?= json_encode($ressourcesData) ?>;
    let autoSaveTimeout = null;

    const availableCanaux = <?= json_encode($availableCanaux) ?>;

    // ========================
    // CANAUX
    // ========================
    function renderCanaux() {
        const c = document.getElementById('canauxContainer');
        const empty = document.getElementById('canalEmpty');
        if (canaux.length === 0) { c.innerHTML = ''; empty.style.display = 'block'; updateCanalCount(); return; }
        empty.style.display = 'none';
        c.innerHTML = '';
        canaux.forEach((canal, i) => c.appendChild(createCanalCard(canal, i)));
        updateCanalCount();
    }

    function createCanalCard(canal, index) {
        const div = document.createElement('div');
        div.className = 'card-hover fade-in bg-white rounded-lg border-2 border-gray-100 shadow p-4';

        let options = '<option value="">-- Choisir un canal --</option>';
        for (const [key, c] of Object.entries(availableCanaux)) {
            options += `<option value="${key}"${canal.canal === key ? ' selected' : ''}>${c.icon} ${c.label}</option>`;
        }

        div.innerHTML = `
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <div class="grid md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Canal</label>
                            <select class="w-full px-3 py-2 border rounded-md text-sm bg-white" onchange="updateCanal(${index}, 'canal', this.value)">${options}</select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Frequence / Format</label>
                            <input type="text" value="${esc(canal.frequence || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="Ex: 3 posts, 1 newsletter, 200 flyers..."
                                oninput="updateCanal(${index}, 'frequence', this.value)">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Pourquoi ce canal ? Quel avantage pour toucher votre public ?</label>
                        <textarea rows="2" class="w-full px-3 py-2 border rounded-md text-sm resize-none"
                            placeholder="Justifiez votre choix : pertinence, cout, accessibilite pour le public cible..."
                            oninput="updateCanal(${index}, 'justification', this.value)">${esc(canal.justification || '')}</textarea>
                    </div>
                </div>
                <button onclick="removeCanal(${index})" class="no-print text-red-400 hover:text-red-600 mt-1">&#x2716;</button>
            </div>
        `;
        return div;
    }

    function addCanal() {
        canaux.push({ canal: '', frequence: '', justification: '' });
        renderCanaux();
        scheduleAutoSave();
        setTimeout(() => { const c = document.getElementById('canauxContainer'); c.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    }

    function removeCanal(i) {
        if (!confirm('Supprimer ce canal ?')) return;
        canaux.splice(i, 1);
        renderCanaux();
        scheduleAutoSave();
    }

    function updateCanal(i, field, val) { if (canaux[i]) { canaux[i][field] = val; scheduleAutoSave(); } }
    function updateCanalCount() { document.getElementById('canalCount').textContent = canaux.filter(c => c.canal).length + ' canal/canaux selectionne(s)'; }

    // ========================
    // CALENDRIER
    // ========================
    function renderCalendrier() {
        const c = document.getElementById('calendrierContainer');
        const empty = document.getElementById('etapeEmpty');
        if (calendrier.length === 0) { c.innerHTML = ''; empty.style.display = 'block'; updateEtapeCount(); return; }
        empty.style.display = 'none';
        c.innerHTML = '';
        calendrier.forEach((etape, i) => c.appendChild(createEtapeCard(etape, i)));
        updateEtapeCount();
    }

    function createEtapeCard(etape, index) {
        const div = document.createElement('div');
        const typeColors = {
            'teasing': 'bg-purple-50 border-purple-300',
            'annonce': 'bg-blue-50 border-blue-300',
            'rappel': 'bg-amber-50 border-amber-300',
            'jour_j': 'bg-green-50 border-green-300',
            'relance': 'bg-orange-50 border-orange-300',
            'remerciement': 'bg-pink-50 border-pink-300',
            'bilan': 'bg-gray-50 border-gray-300'
        };
        const cls = typeColors[etape.type] || 'bg-white border-gray-200';
        div.className = 'card-hover fade-in rounded-lg border-2 shadow p-4 ' + cls;

        let typeOptions = '<option value="">-- Type --</option>';
        const types = [
            ['teasing', 'Teasing / Anticipation'],
            ['annonce', 'Annonce principale'],
            ['rappel', 'Rappel'],
            ['jour_j', 'Jour J'],
            ['relance', 'Relance / Suivi'],
            ['remerciement', 'Remerciement'],
            ['bilan', 'Bilan / Resultats']
        ];
        types.forEach(([val, label]) => {
            typeOptions += `<option value="${val}"${etape.type === val ? ' selected' : ''}>${label}</option>`;
        });

        div.innerHTML = `
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <div class="grid md:grid-cols-3 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Date / Periode</label>
                            <input type="text" value="${esc(etape.date || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="Ex: J-21, 22 fevrier, Semaine du 10..."
                                oninput="updateEtape(${index}, 'date', this.value)">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Type d'etape</label>
                            <select class="w-full px-3 py-2 border rounded-md text-sm bg-white" onchange="updateEtapeType(${index}, this.value)">${typeOptions}</select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Canal(aux) utilise(s)</label>
                            <input type="text" value="${esc(etape.canaux_utilises || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="Ex: Facebook + Flyers"
                                oninput="updateEtape(${index}, 'canaux_utilises', this.value)">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Description de l'etape</label>
                        <input type="text" value="${esc(etape.etape || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                            placeholder="Que faites-vous a cette etape ? Quel contenu ?"
                            oninput="updateEtape(${index}, 'etape', this.value)">
                    </div>
                </div>
                <button onclick="removeEtape(${index})" class="no-print text-red-400 hover:text-red-600 mt-1">&#x2716;</button>
            </div>
        `;
        return div;
    }

    function addEtape() {
        calendrier.push({ date: '', type: '', etape: '', canaux_utilises: '' });
        renderCalendrier();
        scheduleAutoSave();
        setTimeout(() => { const c = document.getElementById('calendrierContainer'); c.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    }

    function removeEtape(i) {
        if (!confirm('Supprimer cette etape ?')) return;
        calendrier.splice(i, 1);
        renderCalendrier();
        scheduleAutoSave();
    }

    function updateEtape(i, field, val) { if (calendrier[i]) { calendrier[i][field] = val; scheduleAutoSave(); } }
    function updateEtapeType(i, val) {
        if (!calendrier[i]) return;
        calendrier[i].type = val;
        renderCalendrier();
        scheduleAutoSave();
    }
    function updateEtapeCount() { document.getElementById('etapeCount').textContent = calendrier.length + ' etape(s)'; }

    // ========================
    // RESSOURCES
    // ========================
    function renderRessources() {
        const c = document.getElementById('ressourcesContainer');
        const empty = document.getElementById('ressourceEmpty');
        if (ressources.length === 0) { c.innerHTML = ''; empty.style.display = 'block'; updateRessourceCount(); return; }
        empty.style.display = 'none';
        c.innerHTML = '';
        ressources.forEach((r, i) => c.appendChild(createRessourceCard(r, i)));
        updateRessourceCount();
    }

    function createRessourceCard(r, index) {
        const div = document.createElement('div');
        div.className = 'card-hover fade-in bg-white rounded-lg border-2 border-gray-100 shadow p-4';

        div.innerHTML = `
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <div class="grid md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Qui ?</label>
                            <input type="text" value="${esc(r.qui || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="Ex: Marie (stagiaire), le CA..."
                                oninput="updateRessource(${index}, 'qui', this.value)">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Fait quoi ?</label>
                            <input type="text" value="${esc(r.quoi || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="Ex: Cree les visuels, redige les posts..."
                                oninput="updateRessource(${index}, 'quoi', this.value)">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Temps / Budget</label>
                            <input type="text" value="${esc(r.temps_budget || '')}" class="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="Ex: 2h/semaine, 50 EUR flyers..."
                                oninput="updateRessource(${index}, 'temps_budget', this.value)">
                        </div>
                    </div>
                </div>
                <button onclick="removeRessource(${index})" class="no-print text-red-400 hover:text-red-600 mt-1">&#x2716;</button>
            </div>
        `;
        return div;
    }

    function addRessource() {
        ressources.push({ qui: '', quoi: '', temps_budget: '' });
        renderRessources();
        scheduleAutoSave();
        setTimeout(() => { const c = document.getElementById('ressourcesContainer'); c.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    }

    function removeRessource(i) {
        if (!confirm('Supprimer cette ressource ?')) return;
        ressources.splice(i, 1);
        renderRessources();
        scheduleAutoSave();
    }

    function updateRessource(i, field, val) { if (ressources[i]) { ressources[i][field] = val; scheduleAutoSave(); } }
    function updateRessourceCount() { document.getElementById('ressourceCount').textContent = ressources.length + ' ressource(s)'; }

    // ========================
    // SAUVEGARDE
    // ========================
    function scheduleAutoSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        document.getElementById('saveStatus').textContent = 'Sauvegarde...';
        document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-400 text-yellow-900';
        autoSaveTimeout = setTimeout(saveData, 1000);
    }

    async function saveData() {
        const payload = {
            nom_organisation: document.getElementById('nomOrganisation').value,
            action_communiquer: document.getElementById('actionCommuniquer').value,
            objectif_smart: document.getElementById('objectifSmart').value,
            public_prioritaire: document.getElementById('publicPrioritaire').value,
            message_cle: document.getElementById('messageCle').value,
            canaux_data: canaux,
            calendrier_data: calendrier,
            ressources_data: ressources,
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

    async function submitPlan() {
        if (!confirm('Soumettre votre mini-plan au formateur ?')) return;
        await saveData();
        try {
            const r = await fetch('api/submit.php', { method: 'POST' });
            const res = await r.json();
            if (res.success) {
                document.getElementById('saveStatus').textContent = 'Soumis';
                document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-indigo-500 text-white';
                alert('Plan soumis !');
            }
        } catch (e) { console.error(e); }
    }

    // ========================
    // EXPORTS
    // ========================
    function exportJSON() {
        const data = {
            nom_organisation: document.getElementById('nomOrganisation').value,
            action_communiquer: document.getElementById('actionCommuniquer').value,
            objectif_smart: document.getElementById('objectifSmart').value,
            public_prioritaire: document.getElementById('publicPrioritaire').value,
            message_cle: document.getElementById('messageCle').value,
            canaux: canaux,
            calendrier: calendrier,
            ressources: ressources,
            notes: document.getElementById('notes').value,
            dateExport: new Date().toISOString()
        };
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const link = document.createElement('a'); link.href = URL.createObjectURL(blob);
        link.download = 'plan_comm_' + new Date().toISOString().split('T')[0] + '.json';
        link.click(); URL.revokeObjectURL(link.href);
    }

    function exportToExcel() {
        const wb = XLSX.utils.book_new();
        const nomOrg = document.getElementById('nomOrganisation').value || 'Mon association';

        // Plan principal
        const planData = [
            ['MINI-PLAN DE COMMUNICATION — ' + nomOrg],
            [],
            ['SECTION', 'CONTENU'],
            ['Action a communiquer', document.getElementById('actionCommuniquer').value],
            ['Objectif SMART', document.getElementById('objectifSmart').value],
            ['Public prioritaire', document.getElementById('publicPrioritaire').value],
            ['Message cle', document.getElementById('messageCle').value],
            ['Notes', document.getElementById('notes').value]
        ];
        const ws1 = XLSX.utils.aoa_to_sheet(planData);
        ws1['!cols'] = [{wch: 25}, {wch: 80}];

        // Canaux
        const cData = [['CANAUX DE COMMUNICATION'], [], ['#', 'Canal', 'Frequence / Format', 'Justification']];
        canaux.forEach((c, i) => {
            const label = availableCanaux[c.canal]?.label || c.canal || '';
            cData.push([(i+1).toString(), label, c.frequence || '', c.justification || '']);
        });
        const ws2 = XLSX.utils.aoa_to_sheet(cData);
        ws2['!cols'] = [{wch: 4}, {wch: 25}, {wch: 30}, {wch: 50}];

        // Calendrier
        const calData = [['CALENDRIER'], [], ['#', 'Date / Periode', 'Type', 'Description', 'Canaux']];
        calendrier.forEach((e, i) => {
            calData.push([(i+1).toString(), e.date || '', e.type || '', e.etape || '', e.canaux_utilises || '']);
        });
        const ws3 = XLSX.utils.aoa_to_sheet(calData);
        ws3['!cols'] = [{wch: 4}, {wch: 20}, {wch: 20}, {wch: 50}, {wch: 30}];

        // Ressources
        const resData = [['RESSOURCES'], [], ['#', 'Qui ?', 'Fait quoi ?', 'Temps / Budget']];
        ressources.forEach((r, i) => {
            resData.push([(i+1).toString(), r.qui || '', r.quoi || '', r.temps_budget || '']);
        });
        const ws4 = XLSX.utils.aoa_to_sheet(resData);
        ws4['!cols'] = [{wch: 4}, {wch: 25}, {wch: 40}, {wch: 25}];

        XLSX.utils.book_append_sheet(wb, ws1, 'Plan');
        XLSX.utils.book_append_sheet(wb, ws2, 'Canaux');
        XLSX.utils.book_append_sheet(wb, ws3, 'Calendrier');
        XLSX.utils.book_append_sheet(wb, ws4, 'Ressources');
        XLSX.writeFile(wb, 'plan_comm_' + nomOrg.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_' + new Date().toISOString().split('T')[0] + '.xlsx');
    }

    function esc(t) { const d = document.createElement('div'); d.appendChild(document.createTextNode(t)); return d.innerHTML; }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        renderCanaux();
        renderCalendrier();
        renderRessources();
    });
    </script>
</body>
</html>
