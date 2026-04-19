<?php
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php'); exit;
}

$appKey = 'app-interviews';
$db = getDB();
$sessionId = (int)$_SESSION['current_session_id'];
$user = getLoggedUser();
$userId = $user['id'];

ensureParticipant($db, $sessionId, $user);

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) { header('Location: login.php'); exit; }

$stmt = $db->prepare("SELECT * FROM fiches WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$fiche = $stmt->fetch() ?: [];
$isSubmitted = ($fiche['is_submitted'] ?? 0) == 1;

$stmt = $db->prepare("SELECT * FROM lignes_reponse WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$lignes = $stmt->fetch() ?: [];
$lignesSubmitted = ($lignes['is_submitted'] ?? 0) == 1;
$qrData = json_decode($lignes['qr_data'] ?? '[]', true) ?: [];
$elementsData = json_decode($lignes['elements_data'] ?? '[]', true) ?: [];
if (empty($qrData)) $qrData = [['question'=>'','reponse'=>''],['question'=>'','reponse'=>''],['question'=>'','reponse'=>'']];
if (empty($elementsData)) $elementsData = [['situation'=>'','formulation'=>''],['situation'=>'','formulation'=>'']];

$stmt = $db->prepare("SELECT * FROM communiques WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$cp = $stmt->fetch() ?: [];
$cpSubmitted = ($cp['is_submitted'] ?? 0) == 1;

$profiles = getJournalisteProfiles();
$aideItems = getAideMemoireItems();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?> — <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-btn { transition: all .15s; }
        .tab-btn.active { background: white; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        .profile-card { cursor: pointer; transition: all .2s; }
        .profile-card:hover { transform: translateY(-2px); }
        .profile-card.selected { ring: 3px; }
        textarea { resize: vertical; }
        .save-indicator { transition: opacity .3s; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<!-- Navbar -->
<div class="bg-gradient-to-r from-rose-600 to-pink-600 text-white p-3 shadow-lg sticky top-0 z-50">
    <div class="max-w-5xl mx-auto flex flex-wrap justify-between items-center gap-2">
        <div>
            <span class="font-semibold"><?= h(APP_NAME) ?></span>
            <span class="text-rose-200 text-sm ml-2"><?= h($session['nom']) ?> (<?= h($session['code']) ?>)</span>
        </div>
        <div class="flex items-center gap-2">
            <span id="saveIndicator" class="save-indicator opacity-0 text-xs bg-white/20 px-2 py-1 rounded">Sauvegardé</span>
            <?php if ($isSubmitted): ?>
            <span class="text-xs bg-green-500 px-3 py-1 rounded-full">Fiche soumise</span>
            <?php endif; ?>
            <?php if (isFormateur()): ?>
            <a href="formateur.php" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Formateur</a>
            <?php endif; ?>
            <?= renderHomeLink('text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded') ?>
            <a href="logout.php" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Déconnexion</a>
        </div>
    </div>
</div>

<!-- Mode selector -->
<div class="max-w-5xl mx-auto px-4 pt-6 pb-2">
    <div class="bg-white rounded-xl shadow p-1 flex gap-1 overflow-x-auto">
        <button class="tab-btn flex-1 whitespace-nowrap py-2 px-3 rounded-lg text-sm font-medium text-gray-600 active" id="tabInterviewe" onclick="switchMode('interviewe')">
            📋 Fiche interviewé·e
        </button>
        <button class="tab-btn flex-1 whitespace-nowrap py-2 px-3 rounded-lg text-sm font-medium text-gray-600" id="tabLignes" onclick="switchMode('lignes')">
            💬 Lignes de réponse
        </button>
        <button class="tab-btn flex-1 whitespace-nowrap py-2 px-3 rounded-lg text-sm font-medium text-gray-600" id="tabCommunique" onclick="switchMode('communique')">
            📰 Communiqué
        </button>
        <button class="tab-btn flex-1 whitespace-nowrap py-2 px-3 rounded-lg text-sm font-medium text-gray-600" id="tabJournaliste" onclick="switchMode('journaliste')">
            🎙️ Journaliste
        </button>
    </div>
</div>

<!-- ==================== MODE INTERVIEWÉ ==================== -->
<div id="modeInterviewe" class="max-w-5xl mx-auto px-4 pb-8">

    <!-- Aide-mémoire -->
    <details class="bg-amber-50 border border-amber-200 rounded-xl mb-4 mt-4">
        <summary class="p-4 font-semibold text-amber-800 cursor-pointer select-none flex items-center gap-2">
            <span>💡</span> Aide-mémoire — Techniques essentielles
        </summary>
        <div class="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php foreach ($aideItems as $item): ?>
            <div class="bg-white rounded-lg p-3 border border-amber-100">
                <div class="font-semibold text-amber-900 text-sm mb-1"><?= h($item['titre']) ?></div>
                <p class="text-sm text-gray-700"><?= h($item['texte']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </details>

    <!-- Fiche de préparation -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
        <h2 class="text-xl font-bold text-gray-800 mb-1">Ma fiche de préparation</h2>
        <p class="text-sm text-gray-500 mb-5">Remplis cette fiche avant ton interview. Elle se sauvegarde automatiquement.</p>

        <?php if ($isSubmitted): ?>
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            Fiche soumise — lecture seule.
        </div>
        <?php endif; ?>

        <!-- Sujet -->
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Sujet / contexte de l'interview
                <span class="text-gray-400 font-normal ml-1">(de quoi va-t-on parler ?)</span>
            </label>
            <textarea id="sujet" rows="2" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-rose-400 focus:border-transparent"
                placeholder="Ex : Le lancement de notre programme de formation en gouvernance locale..."
                <?= $isSubmitted ? 'disabled' : '' ?>><?= h($fiche['sujet'] ?? '') ?></textarea>
        </div>

        <!-- 3 messages clés -->
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-700 mb-3">
                Mes 3 messages clés
                <span class="text-gray-400 font-normal ml-1">(ce que je veux absolument faire passer)</span>
            </label>
            <div class="space-y-3">
                <?php foreach ([1, 2, 3] as $i): ?>
                <div class="flex items-start gap-3">
                    <span class="mt-3 w-7 h-7 rounded-full bg-rose-100 text-rose-700 font-bold text-sm flex items-center justify-center flex-shrink-0"><?= $i ?></span>
                    <textarea id="message<?= $i ?>" rows="2" class="flex-1 border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-rose-400 focus:border-transparent"
                        placeholder="Message clé <?= $i ?>..."
                        <?= $isSubmitted ? 'disabled' : '' ?>><?= h($fiche['message' . $i] ?? '') ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Anecdote -->
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Mon anecdote / exemple concret
                <span class="text-gray-400 font-normal ml-1">(une histoire vraie qui illustre mon message)</span>
            </label>
            <textarea id="anecdote" rows="3" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-rose-400 focus:border-transparent"
                placeholder="Ex : L'an dernier, dans la commune de X, nous avons mis en place..."
                <?= $isSubmitted ? 'disabled' : '' ?>><?= h($fiche['anecdote'] ?? '') ?></textarea>
        </div>

        <!-- À éviter -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Ce que je ne dois PAS dire
                <span class="text-gray-400 font-normal ml-1">(sujets à éviter, formulations risquées)</span>
            </label>
            <textarea id="a_eviter" rows="2" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-rose-400 focus:border-transparent border-red-200 focus:ring-red-300"
                placeholder="Ex : Ne pas mentionner le conflit avec le partenaire Y, éviter les chiffres non vérifiés..."
                <?= $isSubmitted ? 'disabled' : '' ?>><?= h($fiche['a_eviter'] ?? '') ?></textarea>
        </div>

        <?php if (!$isSubmitted): ?>
        <div class="flex flex-wrap gap-3 pt-4 border-t">
            <button onclick="saveFiche(true)" class="bg-rose-600 hover:bg-rose-700 text-white px-6 py-2 rounded-lg font-medium text-sm">
                Soumettre ma fiche
            </button>
            <button onclick="saveFiche(false)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm">
                Sauvegarder (brouillon)
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== MODE LIGNES DE RÉPONSE ==================== -->
<div id="modeLignes" class="max-w-5xl mx-auto px-4 pb-8 hidden">

    <div class="mt-4 mb-4 bg-indigo-50 border border-indigo-200 rounded-xl p-4">
        <p class="text-indigo-900 text-sm">
            Prépare tes <strong>lignes de réponse</strong> : anticipe les questions des journalistes, rédige des réponses concises alignées sur tes messages clés, et prépare des éléments de langage pour les questions difficiles.
        </p>
    </div>

    <?php if ($lignesSubmitted): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">Document soumis — lecture seule.</div>
    <?php endif; ?>

    <!-- Q&R probables -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Questions probables & réponses</h2>
                <p class="text-sm text-gray-500 mt-1">Questions que les journalistes pourraient poser + tes réponses préparées.</p>
            </div>
        </div>
        <div id="qrList" class="space-y-4"></div>
        <?php if (!$lignesSubmitted): ?>
        <button type="button" onclick="addQR()" class="mt-4 text-sm text-indigo-600 hover:text-indigo-800 font-medium">+ Ajouter une question</button>
        <?php endif; ?>
    </div>

    <!-- Éléments de langage -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Éléments de langage — questions difficiles</h2>
                <p class="text-sm text-gray-500 mt-1">Situations délicates ou questions pièges + formulation à utiliser.</p>
            </div>
        </div>
        <div id="elList" class="space-y-4"></div>
        <?php if (!$lignesSubmitted): ?>
        <button type="button" onclick="addEl()" class="mt-4 text-sm text-indigo-600 hover:text-indigo-800 font-medium">+ Ajouter un élément</button>
        <?php endif; ?>
    </div>

    <?php if (!$lignesSubmitted): ?>
    <div class="flex flex-wrap gap-3 pt-4">
        <button onclick="saveLignes(true)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium text-sm">Soumettre</button>
        <button onclick="saveLignes(false)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm">Sauvegarder (brouillon)</button>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== MODE COMMUNIQUÉ ==================== -->
<div id="modeCommunique" class="max-w-5xl mx-auto px-4 pb-8 hidden">

    <div class="mt-4 mb-4 bg-purple-50 border border-purple-200 rounded-xl p-4">
        <p class="text-purple-900 text-sm">
            Rédige un <strong>communiqué de presse</strong> en pyramide inversée : l'essentiel en haut, les détails en bas. Le journaliste doit pouvoir couper les derniers paragraphes sans perdre le message.
        </p>
    </div>

    <?php if ($cpSubmitted): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">Communiqué soumis — lecture seule.</div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
        <!-- Titre -->
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Titre accrocheur
                <span class="text-gray-400 font-normal ml-1">(court, percutant, factuel)</span>
            </label>
            <input type="text" id="cp_titre" maxlength="200" class="w-full border border-gray-300 rounded-lg p-3 text-base font-bold focus:ring-2 focus:ring-purple-400"
                placeholder="Ex : L'association X lance un programme inédit..."
                value="<?= h($cp['titre'] ?? '') ?>" <?= $cpSubmitted ? 'disabled' : '' ?>>
        </div>

        <!-- Chapeau -->
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Chapeau / résumé
                <span class="text-gray-400 font-normal ml-1">(qui, quoi, où, quand, pourquoi — en 3-4 lignes)</span>
            </label>
            <textarea id="cp_chapeau" rows="4" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-purple-400"
                placeholder="Résume l'essentiel de l'info en répondant aux questions fondamentales..."
                <?= $cpSubmitted ? 'disabled' : '' ?>><?= h($cp['chapeau'] ?? '') ?></textarea>
        </div>

        <!-- Corps du texte -->
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                Corps du texte
                <span class="text-gray-400 font-normal ml-1">(du plus important au moins important)</span>
            </label>
            <div class="space-y-3">
                <?php foreach ([1,2,3] as $i): ?>
                <div>
                    <div class="text-xs text-gray-500 mb-1">Paragraphe <?= $i ?>
                        <?= $i === 1 ? '— développe l\'info principale' : ($i === 2 ? '— détails, chiffres, contexte' : '— informations complémentaires') ?>
                    </div>
                    <textarea id="cp_p<?= $i ?>" rows="3" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-purple-400"
                        <?= $cpSubmitted ? 'disabled' : '' ?>><?= h($cp['paragraphe' . $i] ?? '') ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Citation -->
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Citation
                <span class="text-gray-400 font-normal ml-1">(une phrase forte, entre guillemets)</span>
            </label>
            <textarea id="cp_citation" rows="2" class="w-full border border-gray-300 rounded-lg p-3 text-sm italic focus:ring-2 focus:ring-purple-400"
                placeholder="« Cette initiative répond à un besoin concret... »"
                <?= $cpSubmitted ? 'disabled' : '' ?>><?= h($cp['citation'] ?? '') ?></textarea>
            <input type="text" id="cp_citation_source" maxlength="200" class="mt-2 w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-purple-400"
                placeholder="Source de la citation (ex : Marie Dupont, directrice)"
                value="<?= h($cp['citation_source'] ?? '') ?>" <?= $cpSubmitted ? 'disabled' : '' ?>>
        </div>

        <!-- Contact -->
        <div class="mb-5 border-t pt-5">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Coordonnées de contact</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input type="text" id="cp_contact_nom" maxlength="150" class="border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-purple-400"
                    placeholder="Nom complet" value="<?= h($cp['contact_nom'] ?? '') ?>" <?= $cpSubmitted ? 'disabled' : '' ?>>
                <input type="text" id="cp_contact_titre" maxlength="150" class="border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-purple-400"
                    placeholder="Fonction" value="<?= h($cp['contact_titre'] ?? '') ?>" <?= $cpSubmitted ? 'disabled' : '' ?>>
                <input type="email" id="cp_contact_email" maxlength="150" class="border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-purple-400"
                    placeholder="email@exemple.org" value="<?= h($cp['contact_email'] ?? '') ?>" <?= $cpSubmitted ? 'disabled' : '' ?>>
                <input type="text" id="cp_contact_tel" maxlength="50" class="border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-purple-400"
                    placeholder="+32 ..." value="<?= h($cp['contact_tel'] ?? '') ?>" <?= $cpSubmitted ? 'disabled' : '' ?>>
            </div>
        </div>

        <?php if (!$cpSubmitted): ?>
        <div class="flex flex-wrap gap-3 pt-4 border-t">
            <button onclick="saveCP(true)" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-medium text-sm">Soumettre</button>
            <button onclick="saveCP(false)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm">Sauvegarder (brouillon)</button>
            <button onclick="togglePreview()" class="ml-auto bg-purple-100 text-purple-800 hover:bg-purple-200 px-4 py-2 rounded-lg text-sm">👁️ Aperçu</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Preview -->
    <div id="cpPreview" class="bg-white rounded-xl shadow-lg p-8 hidden" style="font-family: Georgia, serif;">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Communiqué de presse</div>
        <h1 id="pvTitre" class="text-2xl font-bold text-gray-900 mb-4"></h1>
        <p id="pvChapeau" class="text-base font-medium text-gray-800 mb-4 pb-4 border-b"></p>
        <div id="pvCorps" class="space-y-3 text-gray-700 text-sm"></div>
        <blockquote id="pvCitation" class="mt-6 pl-4 border-l-4 border-purple-300 italic text-gray-700 hidden"></blockquote>
        <div id="pvContact" class="mt-6 pt-4 border-t text-xs text-gray-600"></div>
    </div>
</div>

<!-- ==================== MODE JOURNALISTE ==================== -->
<div id="modeJournaliste" class="max-w-5xl mx-auto px-4 pb-8 hidden">

    <div class="mt-4 mb-4 bg-blue-50 border border-blue-200 rounded-xl p-4">
        <p class="text-blue-800 text-sm font-medium">Choisis un profil ci-dessous pour conduire ton interview de jeu de rôle.</p>
    </div>

    <!-- Profile selector cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <?php foreach ($profiles as $key => $p): ?>
        <?php
        $colors = [
            'green' => ['bg' => 'bg-green-50 border-green-200', 'head' => 'bg-green-600', 'hover' => 'hover:border-green-400', 'badge' => 'bg-green-100 text-green-800', 'ring' => 'ring-green-400'],
            'amber' => ['bg' => 'bg-amber-50 border-amber-200', 'head' => 'bg-amber-500', 'hover' => 'hover:border-amber-400', 'badge' => 'bg-amber-100 text-amber-800', 'ring' => 'ring-amber-400'],
            'red'   => ['bg' => 'bg-red-50 border-red-200',   'head' => 'bg-red-600',   'hover' => 'hover:border-red-400',   'badge' => 'bg-red-100 text-red-800',   'ring' => 'ring-red-400'],
        ];
        $c = $colors[$p['couleur']];
        ?>
        <div class="profile-card border-2 rounded-xl <?= $c['bg'] ?> <?= $c['hover'] ?> overflow-hidden"
             id="card<?= $key ?>" onclick="selectProfile('<?= $key ?>')">
            <div class="<?= $c['head'] ?> text-white p-3 text-center">
                <div class="text-2xl mb-1"><?= $p['emoji'] ?></div>
                <div class="font-bold"><?= h($p['label']) ?></div>
                <div class="text-sm opacity-90"><?= h($p['nom']) ?></div>
            </div>
            <div class="p-3">
                <div class="text-xs text-gray-600 italic"><?= h($p['posture']) ?></div>
                <div class="mt-2 text-xs font-medium text-gray-500"><?= count($p['questions']) ?> questions</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Profile detail -->
    <?php foreach ($profiles as $key => $p):
        $c = $colors[$p['couleur']];
    ?>
    <div id="detail<?= $key ?>" class="hidden">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-4">
            <div class="<?= $c['head'] ?> text-white p-5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-3xl"><?= $p['emoji'] ?></span>
                    <div>
                        <h2 class="text-xl font-bold"><?= h($p['label']) ?> — <?= h($p['nom']) ?></h2>
                    </div>
                </div>
                <div class="mt-3 bg-white/20 rounded-lg p-3">
                    <div class="text-sm font-semibold mb-1">Ta posture</div>
                    <p class="text-sm"><?= h($p['posture']) ?></p>
                </div>
                <div class="mt-2 text-xs bg-white/10 rounded p-2 italic">
                    ⚠️ <?= h($p['rappel']) ?>
                </div>
            </div>

            <div class="p-5">
                <h3 class="font-bold text-gray-800 mb-4">Tes questions</h3>
                <ol class="space-y-3">
                    <?php foreach ($p['questions'] as $qi => $q): ?>
                    <li class="flex items-start gap-3">
                        <span class="flex-shrink-0 w-7 h-7 rounded-full <?= $c['badge'] ?> text-sm font-bold flex items-center justify-center"><?= $qi + 1 ?></span>
                        <p class="text-gray-800 pt-0.5"><?= h($q) ?></p>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div id="noProfile" class="text-center py-8 text-gray-400">
        Sélectionne un profil pour voir le détail.
    </div>
</div>

<script>
let saveTimer = null;
let currentMode = localStorage.getItem('interviewMode') || 'interviewe';

const MODES = ['interviewe','lignes','communique','journaliste'];
const TAB_IDS = {interviewe:'tabInterviewe', lignes:'tabLignes', communique:'tabCommunique', journaliste:'tabJournaliste'};
const PANEL_IDS = {interviewe:'modeInterviewe', lignes:'modeLignes', communique:'modeCommunique', journaliste:'modeJournaliste'};

function switchMode(mode) {
    if (!MODES.includes(mode)) mode = 'interviewe';
    currentMode = mode;
    localStorage.setItem('interviewMode', mode);
    MODES.forEach(m => {
        document.getElementById(PANEL_IDS[m]).classList.toggle('hidden', m !== mode);
        document.getElementById(TAB_IDS[m]).classList.toggle('active', m === mode);
    });
}

function selectProfile(key) {
    const keys = <?= json_encode(array_keys($profiles)) ?>;
    keys.forEach(k => {
        document.getElementById('detail' + k).classList.add('hidden');
        document.getElementById('card' + k).classList.remove('ring-4');
    });
    document.getElementById('detail' + key).classList.remove('hidden');
    document.getElementById('card' + key).classList.add('ring-4');
    document.getElementById('noProfile').classList.add('hidden');
    localStorage.setItem('selectedProfile', key);
}

function showSaved() {
    const el = document.getElementById('saveIndicator');
    el.textContent = 'Sauvegardé';
    el.classList.remove('opacity-0');
    setTimeout(() => el.classList.add('opacity-0'), 2000);
}

function showError(msg) {
    const el = document.getElementById('saveIndicator');
    el.textContent = msg || 'Erreur';
    el.classList.remove('opacity-0');
}

function scheduleAutoSave() {
    <?php if (!$isSubmitted): ?>
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => saveFiche(false, true), 1500);
    <?php endif; ?>
}

async function postSave(payload, submit) {
    try {
        const r = await fetch('api/save.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });
        const json = await r.json();
        if (json.success) {
            showSaved();
            if (submit) setTimeout(() => location.reload(), 500);
            return true;
        }
        showError(json.error || 'Erreur');
    } catch (e) {
        showError('Erreur réseau');
    }
    return false;
}

async function saveFiche(submit, silent) {
    const data = {
        type: 'fiche',
        sujet: document.getElementById('sujet').value,
        message1: document.getElementById('message1').value,
        message2: document.getElementById('message2').value,
        message3: document.getElementById('message3').value,
        anecdote: document.getElementById('anecdote').value,
        a_eviter: document.getElementById('a_eviter').value,
        submit: !!submit,
    };

    if (submit && !silent) {
        const filled = [data.sujet, data.message1, data.message2, data.message3].some(v => v.trim());
        if (!filled) { alert('Remplis au moins un champ avant de soumettre.'); return; }
        if (!confirm('Soumettre ta fiche ? Tu ne pourras plus la modifier.')) return;
    }
    await postSave(data, submit);
}

// ---------- Lignes de réponse ----------
const initialQR = <?= json_encode($qrData) ?>;
const initialEl = <?= json_encode($elementsData) ?>;
const lignesSubmitted = <?= $lignesSubmitted ? 'true' : 'false' ?>;

function renderQR() {
    const list = document.getElementById('qrList');
    list.innerHTML = '';
    initialQR.forEach((row, i) => {
        const div = document.createElement('div');
        div.className = 'border border-gray-200 rounded-lg p-3 bg-gray-50';
        div.innerHTML = `
            <div class="flex items-start gap-3">
                <span class="mt-2 w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm flex items-center justify-center flex-shrink-0">${i + 1}</span>
                <div class="flex-1 space-y-2">
                    <textarea data-qr-q="${i}" rows="2" placeholder="Question anticipée..." class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-400" ${lignesSubmitted ? 'disabled' : ''}>${escapeHtml(row.question || '')}</textarea>
                    <textarea data-qr-r="${i}" rows="3" placeholder="Ma réponse préparée..." class="w-full border border-gray-300 rounded-lg p-2 text-sm bg-white focus:ring-2 focus:ring-indigo-400" ${lignesSubmitted ? 'disabled' : ''}>${escapeHtml(row.reponse || '')}</textarea>
                </div>
                ${lignesSubmitted ? '' : `<button type="button" onclick="removeQR(${i})" class="text-gray-400 hover:text-red-600 text-sm mt-2">×</button>`}
            </div>`;
        list.appendChild(div);
    });
    bindLignesInputs();
}

function renderEl() {
    const list = document.getElementById('elList');
    list.innerHTML = '';
    initialEl.forEach((row, i) => {
        const div = document.createElement('div');
        div.className = 'border border-gray-200 rounded-lg p-3 bg-gray-50';
        div.innerHTML = `
            <div class="flex items-start gap-3">
                <span class="mt-2 w-7 h-7 rounded-full bg-amber-100 text-amber-700 font-bold text-sm flex items-center justify-center flex-shrink-0">⚠</span>
                <div class="flex-1 space-y-2">
                    <textarea data-el-s="${i}" rows="2" placeholder="Situation / question difficile..." class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-amber-400" ${lignesSubmitted ? 'disabled' : ''}>${escapeHtml(row.situation || '')}</textarea>
                    <textarea data-el-f="${i}" rows="3" placeholder="Formulation à utiliser..." class="w-full border border-gray-300 rounded-lg p-2 text-sm bg-white focus:ring-2 focus:ring-amber-400" ${lignesSubmitted ? 'disabled' : ''}>${escapeHtml(row.formulation || '')}</textarea>
                </div>
                ${lignesSubmitted ? '' : `<button type="button" onclick="removeEl(${i})" class="text-gray-400 hover:text-red-600 text-sm mt-2">×</button>`}
            </div>`;
        list.appendChild(div);
    });
    bindLignesInputs();
}

function escapeHtml(s) { return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function addQR() { initialQR.push({question:'',reponse:''}); renderQR(); }
function addEl() { initialEl.push({situation:'',formulation:''}); renderEl(); }
function removeQR(i) { initialQR.splice(i,1); if (!initialQR.length) initialQR.push({question:'',reponse:''}); renderQR(); scheduleLignesAutoSave(); }
function removeEl(i) { initialEl.splice(i,1); if (!initialEl.length) initialEl.push({situation:'',formulation:''}); renderEl(); scheduleLignesAutoSave(); }

function collectLignes() {
    document.querySelectorAll('[data-qr-q]').forEach(el => { initialQR[+el.dataset.qrQ].question = el.value; });
    document.querySelectorAll('[data-qr-r]').forEach(el => { initialQR[+el.dataset.qrR].reponse  = el.value; });
    document.querySelectorAll('[data-el-s]').forEach(el => { initialEl[+el.dataset.elS].situation   = el.value; });
    document.querySelectorAll('[data-el-f]').forEach(el => { initialEl[+el.dataset.elF].formulation = el.value; });
}

let lignesTimer = null;
function scheduleLignesAutoSave() {
    if (lignesSubmitted) return;
    clearTimeout(lignesTimer);
    lignesTimer = setTimeout(() => saveLignes(false, true), 1500);
}

function bindLignesInputs() {
    document.querySelectorAll('#qrList textarea, #elList textarea').forEach(el => {
        el.addEventListener('input', scheduleLignesAutoSave);
    });
}

async function saveLignes(submit, silent) {
    collectLignes();
    if (submit && !silent) {
        if (!confirm('Soumettre ce document ? Tu ne pourras plus le modifier.')) return;
    }
    await postSave({
        type: 'lignes',
        qr_data: initialQR,
        elements_data: initialEl,
        submit: !!submit,
    }, submit);
}

// ---------- Communiqué ----------
const cpSubmittedJS = <?= $cpSubmitted ? 'true' : 'false' ?>;

function collectCP() {
    const v = id => { const el = document.getElementById(id); return el ? el.value : ''; };
    return {
        type: 'communique',
        titre:           v('cp_titre'),
        chapeau:         v('cp_chapeau'),
        paragraphe1:     v('cp_p1'),
        paragraphe2:     v('cp_p2'),
        paragraphe3:     v('cp_p3'),
        citation:        v('cp_citation'),
        citation_source: v('cp_citation_source'),
        contact_nom:     v('cp_contact_nom'),
        contact_titre:   v('cp_contact_titre'),
        contact_email:   v('cp_contact_email'),
        contact_tel:     v('cp_contact_tel'),
    };
}

let cpTimer = null;
function scheduleCPAutoSave() {
    if (cpSubmittedJS) return;
    clearTimeout(cpTimer);
    cpTimer = setTimeout(() => saveCP(false, true), 1500);
}

async function saveCP(submit, silent) {
    const data = collectCP();
    data.submit = !!submit;
    if (submit && !silent) {
        if (!data.titre.trim()) { alert('Renseigne au moins un titre.'); return; }
        if (!confirm('Soumettre ce communiqué ? Tu ne pourras plus le modifier.')) return;
    }
    try {
        const r = await fetch('api/save.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data),
        });
        const json = await r.json();
        if (json.success) {
            showSaved();
            if (submit) setTimeout(() => location.reload(), 500);
        } else {
            const msg = json.error || 'Erreur de sauvegarde (communiqué)';
            showError(msg);
            if (!silent) alert(msg);
        }
    } catch (e) {
        const msg = 'Erreur réseau : ' + e.message;
        showError(msg);
        if (!silent) alert(msg);
    }
}

function togglePreview() {
    const d = collectCP();
    const pv = document.getElementById('cpPreview');
    pv.classList.toggle('hidden');
    if (pv.classList.contains('hidden')) return;
    document.getElementById('pvTitre').textContent = d.titre || '(Titre)';
    document.getElementById('pvChapeau').textContent = d.chapeau || '';
    const corps = document.getElementById('pvCorps');
    corps.innerHTML = '';
    [d.paragraphe1, d.paragraphe2, d.paragraphe3].forEach(p => {
        if (p.trim()) { const el = document.createElement('p'); el.textContent = p; corps.appendChild(el); }
    });
    const quote = document.getElementById('pvCitation');
    if (d.citation.trim()) {
        quote.classList.remove('hidden');
        quote.innerHTML = '« ' + escapeHtml(d.citation) + ' »' + (d.citation_source ? '<div class="text-xs not-italic text-gray-500 mt-1">— ' + escapeHtml(d.citation_source) + '</div>' : '');
    } else quote.classList.add('hidden');
    const contact = document.getElementById('pvContact');
    const c = [d.contact_nom, d.contact_titre, d.contact_email, d.contact_tel].filter(x => x.trim());
    contact.innerHTML = c.length ? '<strong>Contact presse :</strong> ' + c.map(escapeHtml).join(' · ') : '';
    pv.scrollIntoView({behavior:'smooth'});
}

// Auto-save for Fiche inputs
['sujet','message1','message2','message3','anecdote','a_eviter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', scheduleAutoSave);
});

// Auto-save for Communiqué inputs
document.querySelectorAll('#modeCommunique input, #modeCommunique textarea').forEach(el => {
    el.addEventListener('input', scheduleCPAutoSave);
});

// Render lignes lists
renderQR();
renderEl();

// Init mode & profile from localStorage
switchMode(currentMode);
const savedProfile = localStorage.getItem('selectedProfile');
if (savedProfile && <?= json_encode(array_keys($profiles)) ?>.includes(savedProfile)) {
    selectProfile(savedProfile);
}
</script>
</body>
</html>
