<?php
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php'); exit;
}

$db = getDB();
$user = getLoggedUser();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$sessionId = validateCurrentSession($db);
if (!$sessionId) { header('Location: login.php'); exit; }
$sessionNom = $_SESSION['current_session_nom'] ?? '';

$stmt = $db->prepare("SELECT * FROM evaluations WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$evaluation = $stmt->fetch();
if (!$evaluation) {
    $db->prepare("INSERT INTO evaluations (user_id, session_id, responses) VALUES (?, ?, '{}')")
       ->execute([$user['id'], $sessionId]);
    $stmt->execute([$user['id'], $sessionId]);
    $evaluation = $stmt->fetch();
}
$responses = json_decode($evaluation['responses'] ?? '{}', true) ?: [];
$isSubmitted = ($evaluation['is_submitted'] ?? 0) == 1;

$scaleLevels = getScaleLevels();
$maxLevel = count($scaleLevels) ? max(array_map(fn($l) => (int)$l['niveau'], $scaleLevels)) : 4;
$na = getNaSettings();
$domains = getAllDomains();
$appTitle = getConfig('app_title', APP_NAME);
$appSubtitle = getConfig('app_subtitle', 'Outil d\'auto-évaluation');
$isEmpty = empty($domains);
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($appTitle) ?> - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' fill='%233b82f6' rx='6'/><text x='16' y='22' font-size='18' text-anchor='middle' fill='white' font-family='Arial'>G</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; }
        .gradient-hdr { background: linear-gradient(135deg, #3b82f6, #8b5cf6); }
        .section-hdr { cursor: pointer; transition: background 0.15s; }
        .section-hdr:hover { background: #f1f5f9; }
        .section-content { display: none; }
        .section-content.open { display: block; }
        .toggle-icon { transition: transform 0.2s; }
        .toggle-icon.open { transform: rotate(180deg); }
        .question-card { background: #f9fafb; border-radius: 8px; padding: 14px 16px; margin-bottom: 12px; }
        .opt { padding: 8px 10px; border: 2px solid #d1d5db; background: white; border-radius: 8px; cursor: pointer; transition: all 0.15s; min-width: 110px; text-align: center; user-select: none; font-size: 0.85rem; display: flex; flex-direction: column; align-items: center; gap: 2px; }
        .opt:hover { background: #f9fafb; }
        .opt .num { font-size: 1.05rem; font-weight: 700; color: #374151; }
        .opt .lbl { font-size: 0.75rem; color: #6b7280; }
        .opt.selected.lv-1 { background: #fef2f2; border-color: #ef4444; } .opt.selected.lv-1 .num { color: #b91c1c; }
        .opt.selected.lv-2 { background: #fff7ed; border-color: #f97316; } .opt.selected.lv-2 .num { color: #c2410c; }
        .opt.selected.lv-3 { background: #eff6ff; border-color: #3b82f6; } .opt.selected.lv-3 .num { color: #1d4ed8; }
        .opt.selected.lv-4 { background: #f0fdf4; border-color: #22c55e; } .opt.selected.lv-4 .num { color: #15803d; }
        .opt.selected.lv-5 { background: #faf5ff; border-color: #a855f7; } .opt.selected.lv-5 .num { color: #7e22ce; }
        .opt.selected.lv-na { background: #f3f4f6; border-color: #9ca3af; } .opt.selected.lv-na .num { color: #4b5563; }
        .anchor-box { display: none; margin-top: 10px; padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; line-height: 1.5; }
        .anchor-box.show { display: block; }
        .anchor-1 { background: #fef2f2; color: #991b1b; border-left: 3px solid #ef4444; }
        .anchor-2 { background: #fff7ed; color: #9a3412; border-left: 3px solid #f97316; }
        .anchor-3 { background: #eff6ff; color: #1e40af; border-left: 3px solid #3b82f6; }
        .anchor-4 { background: #f0fdf4; color: #166534; border-left: 3px solid #22c55e; }
        .anchor-5 { background: #faf5ff; color: #6b21a8; border-left: 3px solid #a855f7; }
        .score-good { color: #16a34a; }
        .score-medium { color: #f59e0b; }
        .score-poor { color: #dc2626; }
        .score-empty { color: #9ca3af; }
        details.aide-block summary { cursor: pointer; color: #0369a1; font-size: 0.82rem; user-select: none; display: inline-flex; align-items: center; gap: 4px; }
        details.aide-block summary:hover { color: #0c4a6e; }
        details.aide-block p { margin-top: 8px; padding: 10px 12px; background: #f0f9ff; border-left: 3px solid #38bdf8; border-radius: 0 6px 6px 0; color: #0c4a6e; font-size: 0.85rem; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="min-h-screen">
    <div class="gradient-hdr text-white p-3 shadow-lg sticky top-0 z-50 no-print">
        <div class="max-w-5xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= h($user['prenom']) ?> <?= h($user['nom']) ?></span>
                <span class="text-blue-200 text-sm ml-2"><?= h($sessionNom) ?></span>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <?= renderLanguageSelector('text-sm bg-white/10 text-white px-2 py-1 rounded border border-white/20') ?>
                <button onclick="manualSave()" class="text-sm bg-green-500 hover:bg-green-400 px-4 py-1.5 rounded font-medium transition">Sauvegarder</button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-white/20"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <span id="completion" class="text-sm">0%</span>
                <?php if (isFormateur()): ?>
                <a href="admin-config.php" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded transition">⚙ Config</a>
                <a href="formateur.php" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded transition">Formateur</a>
                <?php endif; ?>
                <?= renderHomeLink() ?>
                <a href="logout.php" class="text-sm bg-white/10 hover:bg-white/20 px-3 py-1 rounded transition">Déconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4">
        <div class="gradient-hdr text-white rounded-xl p-6 mb-5 text-center shadow">
            <h1 class="text-2xl md:text-3xl font-bold"><?= h($appTitle) ?></h1>
            <?php if ($appSubtitle): ?><p class="opacity-90 mt-1"><?= h($appSubtitle) ?></p><?php endif; ?>
        </div>

        <?php if ($isEmpty): ?>
        <div class="bg-white border-l-4 border-amber-400 p-6 rounded shadow mb-5">
            <h2 class="font-bold text-amber-800 mb-2">Contenu non configuré</h2>
            <p class="text-gray-700 mb-3">Aucun domaine n'est encore défini dans cette évaluation.</p>
            <?php if (isFormateur()): ?>
            <a href="admin-config.php" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded transition">Configurer les questions</a>
            <?php else: ?>
            <p class="text-gray-600 text-sm">Contactez votre formateur·rice pour activer le contenu.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>

        <div class="bg-white rounded-xl shadow p-5 mb-5 no-print">
            <h3 class="font-bold text-gray-800 mb-3">Échelle de maturité</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                <?php foreach ($scaleLevels as $lvl): ?>
                <div class="flex gap-2">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs shrink-0"><?= (int)$lvl['niveau'] ?></span>
                    <div><strong><?= h($lvl['label']) ?></strong> — <span class="text-gray-600"><?= h($lvl['description']) ?></span></div>
                </div>
                <?php endforeach; ?>
                <?php if ($na['enabled']): ?>
                <div class="flex gap-2 md:col-span-2">
                    <span class="inline-flex items-center justify-center px-2 h-7 rounded-full bg-gray-200 text-gray-700 font-bold text-xs shrink-0">N/A</span>
                    <div><strong><?= h($na['label']) ?></strong> — <span class="text-gray-600"><?= h($na['description']) ?></span></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-5 no-print">
            <button id="btn-assessment" onclick="showAssessment()" class="bg-indigo-600 text-white px-4 py-2 rounded font-medium hover:bg-indigo-500 transition">Évaluation</button>
            <button id="btn-dashboard" onclick="showDashboard()" class="bg-white border-2 border-indigo-600 text-indigo-700 px-4 py-2 rounded font-medium hover:bg-indigo-50 transition">Tableau de bord</button>
            <button onclick="exportResults()" class="bg-emerald-600 text-white px-4 py-2 rounded font-medium hover:bg-emerald-500 transition">Exporter</button>
            <?php if (!$isSubmitted): ?>
            <button id="btn-submit" onclick="submitEvaluation()" class="bg-amber-500 text-white px-4 py-2 rounded font-medium hover:bg-amber-400 transition">Soumettre</button>
            <?php endif; ?>
            <button onclick="resetAssessment()" class="bg-red-500 text-white px-4 py-2 rounded font-medium hover:bg-red-400 transition">Recommencer</button>
        </div>

        <div id="assessment"></div>

        <div id="dashboard" class="hidden">
            <div class="gradient-hdr text-white rounded-xl p-6 mb-5 text-center shadow">
                <h2 class="text-xl font-bold mb-2">Score global</h2>
                <div id="overall-score" class="text-4xl font-bold">—</div>
                <p id="overall-count" class="mt-2 opacity-90 text-sm">0 / 0 question(s) répondue(s)</p>
            </div>
            <div id="domain-scores" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
        </div>

        <?php endif; ?>
    </div>

    <script>
        const domains = <?= json_encode($domains, JSON_UNESCAPED_UNICODE) ?>;
        const scaleLevels = <?= json_encode($scaleLevels, JSON_UNESCAPED_UNICODE) ?>;
        const naSettings = <?= json_encode($na, JSON_UNESCAPED_UNICODE) ?>;
        const maxLevel = <?= (int)$maxLevel ?>;
        let responses = <?= json_encode((object)$responses, JSON_UNESCAPED_UNICODE) ?>;
        let isSubmitted = <?= $isSubmitted ? 'true' : 'false' ?>;
        let autoSaveTimeout = null;

        const questionMap = {};
        domains.forEach(d => (d.questions || []).forEach(q => { questionMap[q.slug] = q; }));

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function scoreClass(score) {
            if (score === null) return 'score-empty';
            const frac = score / maxLevel;
            if (frac >= 0.80) return 'score-good';
            if (frac >= 0.55) return 'score-medium';
            return 'score-poor';
        }
        function formatScore(score) {
            return score === null ? `—/${maxLevel}.0` : `${score.toFixed(1)}/${maxLevel}.0`;
        }

        function questionValue(slug) {
            const r = responses[slug];
            if (r === undefined || r === null || r === 'na') return null;
            const v = parseInt(r);
            return (v >= 1 && v <= maxLevel) ? v : null;
        }

        function domainScore(domain) {
            let sum = 0, count = 0;
            (domain.questions || []).forEach(q => {
                const v = questionValue(q.slug);
                if (v !== null) { sum += v; count++; }
            });
            return { score: count > 0 ? sum / count : null, count };
        }

        function overallStats() {
            let sum = 0, scored = 0, answered = 0, total = 0;
            domains.forEach(d => (d.questions || []).forEach(q => {
                total++;
                const r = responses[q.slug];
                if (r !== undefined && r !== null) {
                    answered++;
                    const v = questionValue(q.slug);
                    if (v !== null) { sum += v; scored++; }
                }
            }));
            return { score: scored > 0 ? sum / scored : null, answered, total };
        }

        function render() {
            const root = document.getElementById('assessment');
            if (!root) return;
            root.innerHTML = '';
            domains.forEach(d => {
                const card = document.createElement('div');
                card.className = 'bg-white rounded-xl shadow mb-4 overflow-hidden';
                card.innerHTML = `
                    <div class="section-hdr px-5 py-4 flex items-center justify-between gap-3 border-b" onclick="toggleSection('${d.slug}')">
                        <div class="flex-1 min-w-0">
                            <h2 class="font-bold text-gray-800 text-lg">${escapeHtml(d.titre)}</h2>
                            ${d.description ? `<p class="text-gray-500 text-sm mt-1">${escapeHtml(d.description)}</p>` : ''}
                        </div>
                        <span id="score-${d.slug}" class="font-bold score-empty shrink-0">—/${maxLevel}.0</span>
                        <span id="toggle-${d.slug}" class="toggle-icon text-gray-400 shrink-0">▼</span>
                    </div>
                    <div id="content-${d.slug}" class="section-content p-5">
                        ${(d.questions || []).map(q => renderQuestion(q)).join('')}
                    </div>`;
                root.appendChild(card);
            });
            updateScores();
        }

        function renderQuestion(q) {
            const opts = scaleLevels.map(l => {
                const v = parseInt(l.niveau);
                const sel = responses[q.slug] == v ? 'selected' : '';
                const anchor = (q.ancrages && q.ancrages[v]) ? q.ancrages[v] : '';
                return `
                    <div class="opt lv-${v} ${sel}" data-question="${q.slug}" data-value="${v}" onclick="selectOption('${q.slug}', ${v})" title="${escapeHtml(anchor)}">
                        <span class="num">${v}</span>
                        <span class="lbl">${escapeHtml(l.label || '')}</span>
                    </div>`;
            }).join('');
            const naOpt = naSettings.enabled ? `
                <div class="opt lv-na ${responses[q.slug] === 'na' ? 'selected' : ''}" data-question="${q.slug}" data-value="na" onclick="selectOption('${q.slug}', 'na')">
                    <span class="num">N/A</span>
                    <span class="lbl">${escapeHtml(naSettings.label || '')}</span>
                </div>` : '';

            const currentAnchor = (responses[q.slug] && responses[q.slug] !== 'na' && q.ancrages && q.ancrages[responses[q.slug]])
                ? `<div class="anchor-box show anchor-${responses[q.slug]}" id="anchor-${q.slug}">${escapeHtml(q.ancrages[responses[q.slug]])}</div>`
                : `<div class="anchor-box" id="anchor-${q.slug}"></div>`;

            return `
                <div class="question-card">
                    <div class="font-semibold text-gray-800 mb-1">${escapeHtml(q.intitule)}</div>
                    <p class="text-gray-700 text-sm mb-2">${escapeHtml(q.texte)}</p>
                    ${q.aide ? `<details class="aide-block mb-2"><summary>ℹ️ Aide</summary><p>${escapeHtml(q.aide)}</p></details>` : ''}
                    <div class="flex flex-wrap gap-2">${opts}${naOpt}</div>
                    ${currentAnchor}
                </div>`;
        }

        function toggleSection(slug) {
            document.getElementById(`content-${slug}`).classList.toggle('open');
            document.getElementById(`toggle-${slug}`).classList.toggle('open');
        }

        function selectOption(slug, value) {
            if (isSubmitted) { alert("L'évaluation a été soumise."); return; }
            responses[slug] = value;
            document.querySelectorAll(`[data-question="${slug}"]`).forEach(e => e.classList.remove('selected'));
            const el = document.querySelector(`[data-question="${slug}"][data-value="${value}"]`);
            if (el) el.classList.add('selected');

            const anchor = document.getElementById(`anchor-${slug}`);
            if (anchor) {
                const q = questionMap[slug];
                if (value !== 'na' && q && q.ancrages && q.ancrages[value]) {
                    anchor.textContent = q.ancrages[value];
                    anchor.className = `anchor-box show anchor-${value}`;
                } else {
                    anchor.textContent = '';
                    anchor.className = 'anchor-box';
                }
            }
            updateScores();
            scheduleAutoSave();
        }

        function updateScores() {
            domains.forEach(d => {
                const { score } = domainScore(d);
                const el = document.getElementById(`score-${d.slug}`);
                if (el) {
                    el.textContent = formatScore(score);
                    el.className = `font-bold ${scoreClass(score)} shrink-0`;
                }
            });
            const { score, answered, total } = overallStats();
            const pct = total > 0 ? Math.round(answered / total * 100) : 0;
            const c = document.getElementById('completion');
            if (c) c.textContent = pct + '%';
            const os = document.getElementById('overall-score');
            if (os) os.textContent = formatScore(score);
            const oc = document.getElementById('overall-count');
            if (oc) oc.textContent = `${answered} / ${total} question(s) répondue(s)`;
        }

        function showAssessment() {
            document.getElementById('assessment')?.classList.remove('hidden');
            document.getElementById('dashboard')?.classList.add('hidden');
        }

        function showDashboard() {
            document.getElementById('assessment')?.classList.add('hidden');
            const dash = document.getElementById('dashboard');
            if (!dash) return;
            dash.classList.remove('hidden');
            const grid = document.getElementById('domain-scores');
            grid.innerHTML = '';
            domains.forEach(d => {
                const { score } = domainScore(d);
                const card = document.createElement('div');
                card.className = 'bg-white rounded-xl shadow p-5 text-center';
                card.innerHTML = `
                    <h3 class="text-sm text-gray-700 mb-2">${escapeHtml(d.titre)}</h3>
                    <div class="text-2xl font-bold ${scoreClass(score)}">${formatScore(score)}</div>`;
                grid.appendChild(card);
            });
        }

        function exportResults() {
            const { score: overall } = overallStats();
            let txt = `ÉVALUATION — ${<?= json_encode($appTitle) ?>}\n`;
            txt += `Participant: <?= addslashes(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?>\n`;
            txt += `Session: <?= addslashes($sessionNom) ?>\n`;
            txt += `Date: ${new Date().toLocaleDateString('fr-FR')}\n`;
            txt += `Score global: ${formatScore(overall)}\n\n${'='.repeat(60)}\n\n`;
            domains.forEach(d => {
                const { score } = domainScore(d);
                txt += `${d.titre.toUpperCase()}\n${formatScore(score)}\n${'-'.repeat(40)}\n\n`;
                (d.questions || []).forEach((q, i) => {
                    txt += `${i+1}. ${q.intitule}\n   ${q.texte}\n`;
                    const r = responses[q.slug];
                    let reponse = 'Non répondu';
                    if (r === 'na') reponse = 'N/A';
                    else if (r !== undefined) {
                        const lvl = scaleLevels.find(l => parseInt(l.niveau) === parseInt(r));
                        reponse = `${r} — ${lvl ? lvl.label : ''}`;
                        if (q.ancrages && q.ancrages[r]) reponse += `\n   ↳ ${q.ancrages[r]}`;
                    }
                    txt += `   Réponse: ${reponse}\n\n`;
                });
                txt += `\n`;
            });
            const blob = new Blob([txt], { type: 'text/plain;charset=utf-8' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `evaluation-${new Date().toISOString().split('T')[0]}.txt`;
            document.body.appendChild(a); a.click(); a.remove();
        }

        function resetAssessment() {
            if (isSubmitted) { alert('Évaluation soumise, impossible de recommencer.'); return; }
            if (!confirm('Effacer toutes les réponses ?')) return;
            responses = {};
            render();
            saveData();
        }

        function submitEvaluation() {
            const { answered, total } = overallStats();
            const msg = answered < total
                ? `Vous avez répondu à ${answered}/${total} questions. Soumettre quand même ?`
                : 'Soumettre définitivement l\'évaluation ? Vous ne pourrez plus la modifier.';
            if (!confirm(msg)) return;
            saveData(() => {
                fetch('api/submit.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: '{}' })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            isSubmitted = true;
                            const s = document.getElementById('saveStatus');
                            s.textContent = 'Soumis';
                            s.className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                            document.getElementById('btn-submit')?.remove();
                            alert('Évaluation soumise avec succès.');
                        } else alert('Erreur : ' + (res.error || 'inconnue'));
                    });
            });
        }

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => saveData(), 1500);
        }

        function saveData(cb) {
            if (isSubmitted) { if (cb) cb(); return; }
            setStatus('Sauvegarde…', 'bg-blue-200 text-blue-800');
            fetch('api/save.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ responses })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    setStatus('Enregistré', 'bg-green-200 text-green-800');
                    setTimeout(() => { if (!isSubmitted) setStatus('Brouillon', 'bg-white/20'); }, 2000);
                    if (cb) cb();
                } else setStatus('Erreur', 'bg-red-200 text-red-800');
            })
            .catch(() => setStatus('Erreur réseau', 'bg-red-200 text-red-800'));
        }

        function manualSave() { if (autoSaveTimeout) clearTimeout(autoSaveTimeout); saveData(); }

        function setStatus(t, cls) {
            const el = document.getElementById('saveStatus');
            if (el) { el.textContent = t; el.className = `text-sm px-3 py-1 rounded-full ${cls}`; }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!<?= $isEmpty ? 'true' : 'false' ?>) render();
        });
    </script>
    <?= renderLanguageScript() ?>
</body>
</html>
