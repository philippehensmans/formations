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
            <a href="logout.php" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Déconnexion</a>
        </div>
    </div>
</div>

<!-- Mode selector -->
<div class="max-w-5xl mx-auto px-4 pt-6 pb-2">
    <div class="bg-white rounded-xl shadow p-1 flex gap-1 max-w-sm mx-auto">
        <button class="tab-btn flex-1 py-2 px-4 rounded-lg text-sm font-medium text-gray-600 active" id="tabInterviewe" onclick="switchMode('interviewe')">
            Interviewé·e
        </button>
        <button class="tab-btn flex-1 py-2 px-4 rounded-lg text-sm font-medium text-gray-600" id="tabJournaliste" onclick="switchMode('journaliste')">
            Journaliste
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

function switchMode(mode) {
    currentMode = mode;
    localStorage.setItem('interviewMode', mode);
    document.getElementById('modeInterviewe').classList.toggle('hidden', mode !== 'interviewe');
    document.getElementById('modeJournaliste').classList.toggle('hidden', mode !== 'journaliste');
    document.getElementById('tabInterviewe').classList.toggle('active', mode === 'interviewe');
    document.getElementById('tabJournaliste').classList.toggle('active', mode === 'journaliste');
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

async function saveFiche(submit, silent) {
    const data = {
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
        if (!filled) {
            alert('Remplis au moins un champ avant de soumettre.');
            return;
        }
        if (!confirm('Soumettre ta fiche ? Tu ne pourras plus la modifier.')) return;
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
            if (submit) {
                setTimeout(() => location.reload(), 500);
            }
        } else {
            showError(json.error || 'Erreur');
        }
    } catch (e) {
        showError('Erreur réseau');
    }
}

// Bind auto-save to all inputs
document.querySelectorAll('textarea').forEach(el => {
    el.addEventListener('input', scheduleAutoSave);
});

// Init mode & profile from localStorage
switchMode(currentMode);
const savedProfile = localStorage.getItem('selectedProfile');
if (savedProfile && <?= json_encode(array_keys($profiles)) ?>.includes(savedProfile)) {
    selectProfile(savedProfile);
}
</script>
</body>
</html>
