<?php
require_once __DIR__ . '/config.php';
if (!isFormateur()) { header('Location: login.php'); exit; }

$db = getDB();
$scaleLevels = getScaleLevels();
$na = getNaSettings();
$domains = getAllDomains();
$appTitle = getConfig('app_title', APP_NAME);
$appSubtitle = getConfig('app_subtitle', '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — <?= h($appTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .tab-btn.active { background: #4f46e5; color: white; }
        .pill { padding: 4px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 500; }
        .drag-handle { cursor: move; color: #9ca3af; }
        .card-item { transition: transform 0.15s, box-shadow 0.15s; }
        .card-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-3 shadow-lg sticky top-0 z-50">
        <div class="max-w-6xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium">⚙ Administration</span>
                <span class="text-indigo-200 text-sm ml-2"><?= h($appTitle) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <a href="app.php" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Évaluation</a>
                <a href="formateur.php" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Formateur</a>
                <a href="logout.php" class="text-sm bg-white/10 hover:bg-white/20 px-3 py-1 rounded">Déconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto p-4">
        <div id="flash" class="hidden mb-4 p-3 rounded text-sm"></div>

        <div class="flex flex-wrap gap-2 mb-4">
            <button class="tab-btn active px-4 py-2 rounded font-medium bg-white border border-gray-300" data-tab="general" onclick="switchTab('general')">Général</button>
            <button class="tab-btn px-4 py-2 rounded font-medium bg-white border border-gray-300" data-tab="scale" onclick="switchTab('scale')">Échelle</button>
            <button class="tab-btn px-4 py-2 rounded font-medium bg-white border border-gray-300" data-tab="content" onclick="switchTab('content')">Domaines & questions</button>
            <button class="tab-btn px-4 py-2 rounded font-medium bg-white border border-gray-300" data-tab="import" onclick="switchTab('import')">Import / Export</button>
        </div>

        <!-- Onglet : Général -->
        <div id="tab-general" class="tab-content bg-white rounded-xl shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Titre de l'évaluation</h2>
            <div class="space-y-3 max-w-2xl">
                <label class="block">
                    <span class="text-sm text-gray-700 font-medium">Titre</span>
                    <input type="text" id="meta-title" value="<?= h($appTitle) ?>" class="mt-1 w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="text-sm text-gray-700 font-medium">Sous-titre</span>
                    <input type="text" id="meta-subtitle" value="<?= h($appSubtitle) ?>" class="mt-1 w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500">
                </label>
                <button onclick="saveMeta()" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded font-medium">Enregistrer</button>
            </div>
        </div>

        <!-- Onglet : Échelle -->
        <div id="tab-scale" class="tab-content hidden bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">Niveaux de l'échelle</h2>
                <button onclick="addLevel()" class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded text-sm">+ Ajouter un niveau</button>
            </div>
            <p class="text-sm text-gray-600 mb-4">Modifier les niveaux supprime aussi les ancrages des niveaux disparus. Les réponses déjà données sont conservées telles quelles.</p>
            <div id="levels-list" class="space-y-3"></div>
            <button onclick="saveScale()" class="mt-4 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded font-medium">Enregistrer l'échelle</button>

            <hr class="my-6">

            <h2 class="text-xl font-bold text-gray-800 mb-3">Option « Non applicable »</h2>
            <div class="space-y-3 max-w-2xl">
                <label class="flex items-center gap-2">
                    <input type="checkbox" id="na-enabled" <?= $na['enabled'] ? 'checked' : '' ?> class="w-4 h-4">
                    <span class="text-sm text-gray-700">Activer l'option N/A pour chaque question</span>
                </label>
                <label class="block">
                    <span class="text-sm text-gray-700 font-medium">Libellé</span>
                    <input type="text" id="na-label" value="<?= h($na['label']) ?>" class="mt-1 w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="text-sm text-gray-700 font-medium">Description (facultative)</span>
                    <textarea id="na-description" rows="2" class="mt-1 w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500"><?= h($na['description']) ?></textarea>
                </label>
                <button onclick="saveNa()" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded font-medium">Enregistrer N/A</button>
            </div>
        </div>

        <!-- Onglet : Contenu -->
        <div id="tab-content" class="tab-content hidden">
            <div class="bg-white rounded-xl shadow p-6 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Domaines</h2>
                    <button onclick="openDomainModal()" class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded text-sm">+ Nouveau domaine</button>
                </div>
                <div id="domains-list" class="space-y-3"></div>
            </div>
        </div>

        <!-- Onglet : Import/Export -->
        <div id="tab-import" class="tab-content hidden bg-white rounded-xl shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-3">Export de la configuration</h2>
            <p class="text-sm text-gray-600 mb-3">Télécharger la configuration actuelle (échelle, N/A, domaines, questions, ancrages) au format JSON.</p>
            <button onclick="exportJson()" class="mb-6 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded font-medium">Télécharger la config en JSON</button>

            <hr class="my-6">

            <h2 class="text-xl font-bold text-gray-800 mb-3">Import de configuration</h2>
            <p class="text-sm text-gray-600 mb-2">Coller ci-dessous un JSON (format original avec <code>echelleMaturite</code> + <code>domaines</code>, ou format plat <code>scale/na/domains</code>).</p>
            <textarea id="import-json" rows="14" placeholder='{"echelleMaturite":{"niveaux":[...]},"domaines":[...]}' class="w-full px-3 py-2 border rounded font-mono text-xs focus:ring-2 focus:ring-indigo-500"></textarea>
            <label class="flex items-center gap-2 mt-3">
                <input type="checkbox" id="import-replace" class="w-4 h-4">
                <span class="text-sm text-gray-700">Remplacer la configuration existante (sinon, ajout aux domaines existants)</span>
            </label>
            <button onclick="importJson()" class="mt-3 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded font-medium">Importer</button>
            <p class="text-xs text-gray-500 mt-2">Les réponses des participants sont conservées (mêmes slugs = mêmes questions).</p>
        </div>
    </div>

    <!-- Modal domaine -->
    <div id="modal-domain" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-xl w-full p-6 max-h-[90vh] overflow-y-auto">
            <h3 id="modal-domain-title" class="text-lg font-bold text-gray-800 mb-4">Nouveau domaine</h3>
            <input type="hidden" id="domain-id" value="">
            <div class="space-y-3">
                <label class="block"><span class="text-sm text-gray-700 font-medium">Titre *</span>
                    <input type="text" id="domain-titre" class="mt-1 w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500"></label>
                <label class="block"><span class="text-sm text-gray-700 font-medium">Description</span>
                    <textarea id="domain-description" rows="3" class="mt-1 w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500"></textarea></label>
                <label class="block"><span class="text-sm text-gray-700 font-medium">Slug (identifiant)</span>
                    <input type="text" id="domain-slug" class="mt-1 w-full px-3 py-2 border rounded font-mono text-sm"></label>
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <button onclick="closeDomainModal()" class="px-4 py-2 rounded border">Annuler</button>
                <button onclick="saveDomain()" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded font-medium">Enregistrer</button>
            </div>
        </div>
    </div>

    <!-- Modal question -->
    <div id="modal-question" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
            <h3 id="modal-question-title" class="text-lg font-bold text-gray-800 mb-4">Nouvelle question</h3>
            <input type="hidden" id="question-id" value="">
            <input type="hidden" id="question-domain-id" value="">
            <div class="space-y-3">
                <label class="block"><span class="text-sm text-gray-700 font-medium">Intitulé *</span>
                    <input type="text" id="question-intitule" class="mt-1 w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500"></label>
                <label class="block"><span class="text-sm text-gray-700 font-medium">Question *</span>
                    <textarea id="question-texte" rows="3" class="mt-1 w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500"></textarea></label>
                <label class="block"><span class="text-sm text-gray-700 font-medium">Aide (facultative)</span>
                    <textarea id="question-aide" rows="2" class="mt-1 w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500"></textarea></label>
                <label class="block"><span class="text-sm text-gray-700 font-medium">Slug</span>
                    <input type="text" id="question-slug" class="mt-1 w-full px-3 py-2 border rounded font-mono text-sm"></label>
                <div>
                    <span class="text-sm text-gray-700 font-medium">Ancrages par niveau</span>
                    <div id="anchors-list" class="mt-2 space-y-2"></div>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <button onclick="closeQuestionModal()" class="px-4 py-2 rounded border">Annuler</button>
                <button onclick="saveQuestion()" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded font-medium">Enregistrer</button>
            </div>
        </div>
    </div>

    <script>
        let state = {
            scale: <?= json_encode($scaleLevels, JSON_UNESCAPED_UNICODE) ?>,
            na: <?= json_encode($na, JSON_UNESCAPED_UNICODE) ?>,
            domains: <?= json_encode($domains, JSON_UNESCAPED_UNICODE) ?>,
        };

        function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
        function flash(msg, type = 'info') {
            const el = document.getElementById('flash');
            const cls = { info: 'bg-blue-50 text-blue-800 border border-blue-200', success: 'bg-green-50 text-green-800 border border-green-200', error: 'bg-red-50 text-red-800 border border-red-200' }[type];
            el.className = 'mb-4 p-3 rounded text-sm ' + cls;
            el.textContent = msg;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 4000);
        }
        async function api(action, payload = {}) {
            const res = await fetch('api/admin.php?action=' + action, {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ action, ...payload })
            });
            const data = await res.json();
            if (!res.ok || data.error) throw new Error(data.error || 'Erreur ' + res.status);
            return data;
        }
        async function reload() {
            const data = await api('get');
            state.scale = data.scale; state.na = data.na; state.domains = data.domains;
            renderScale(); renderDomains();
        }

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelector(`.tab-btn[data-tab="${tab}"]`).classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById('tab-' + tab).classList.remove('hidden');
        }

        // === Général ===
        async function saveMeta() {
            try {
                await api('meta', { title: document.getElementById('meta-title').value, subtitle: document.getElementById('meta-subtitle').value });
                flash('Titre enregistré', 'success');
            } catch (e) { flash(e.message, 'error'); }
        }

        // === Échelle ===
        function renderScale() {
            const list = document.getElementById('levels-list');
            list.innerHTML = state.scale.map((l, idx) => `
                <div class="card-item border rounded p-3 flex gap-3 items-start bg-gray-50">
                    <div class="flex flex-col gap-1 shrink-0">
                        <label class="text-xs text-gray-500">Niveau</label>
                        <input type="number" min="1" max="10" value="${l.niveau}" class="lvl-num w-16 px-2 py-1 border rounded" data-idx="${idx}">
                    </div>
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-2">
                        <input type="text" placeholder="Clé (ex: en_veille)" value="${escapeHtml(l.cle || '')}" class="lvl-cle px-2 py-1 border rounded text-sm" data-idx="${idx}">
                        <input type="text" placeholder="Libellé (ex: En veille)" value="${escapeHtml(l.label || '')}" class="lvl-label px-2 py-1 border rounded text-sm" data-idx="${idx}">
                        <textarea placeholder="Description courte" rows="2" class="lvl-desc md:col-span-2 px-2 py-1 border rounded text-sm" data-idx="${idx}">${escapeHtml(l.description || '')}</textarea>
                    </div>
                    <button onclick="removeLevel(${idx})" class="text-red-600 hover:text-red-800 text-sm shrink-0" title="Supprimer">✕</button>
                </div>`).join('') || '<p class="text-gray-500 text-sm">Aucun niveau défini.</p>';
        }
        function collectScale() {
            const rows = document.querySelectorAll('#levels-list .card-item');
            return Array.from(rows).map(r => ({
                niveau: parseInt(r.querySelector('.lvl-num').value) || 0,
                cle: r.querySelector('.lvl-cle').value.trim(),
                label: r.querySelector('.lvl-label').value.trim(),
                description: r.querySelector('.lvl-desc').value.trim(),
            })).filter(l => l.niveau > 0);
        }
        function addLevel() {
            const next = state.scale.length ? Math.max(...state.scale.map(l => parseInt(l.niveau))) + 1 : 1;
            state.scale.push({ niveau: next, cle: 'niveau_' + next, label: 'Niveau ' + next, description: '' });
            renderScale();
        }
        function removeLevel(idx) {
            state.scale = collectScale();
            state.scale.splice(idx, 1);
            renderScale();
        }
        async function saveScale() {
            const levels = collectScale();
            if (!levels.length) { flash('Définissez au moins un niveau', 'error'); return; }
            try { await api('scale_save', { levels }); flash('Échelle enregistrée', 'success'); await reload(); }
            catch (e) { flash(e.message, 'error'); }
        }
        async function saveNa() {
            try {
                await api('na_save', {
                    enabled: document.getElementById('na-enabled').checked,
                    label: document.getElementById('na-label').value,
                    description: document.getElementById('na-description').value,
                });
                flash('Option N/A enregistrée', 'success');
            } catch (e) { flash(e.message, 'error'); }
        }

        // === Domaines ===
        function renderDomains() {
            const root = document.getElementById('domains-list');
            if (!state.domains.length) {
                root.innerHTML = '<p class="text-gray-500 text-sm">Aucun domaine. Créez-en un ou importez un JSON.</p>';
                return;
            }
            root.innerHTML = state.domains.map(d => `
                <div class="card-item border rounded-lg bg-gray-50">
                    <div class="p-3 flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-800">${escapeHtml(d.titre)}</div>
                            <div class="text-xs text-gray-500 font-mono">${escapeHtml(d.slug)} · ${(d.questions||[]).length} question(s)</div>
                        </div>
                        <button onclick="editDomain(${d.id})" class="text-sm px-3 py-1 rounded bg-white border hover:bg-gray-100">Modifier</button>
                        <button onclick="openQuestionModal(${d.id})" class="text-sm px-3 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-500">+ Question</button>
                        <button onclick="deleteDomain(${d.id})" class="text-sm px-3 py-1 rounded text-red-600 hover:bg-red-50">Supprimer</button>
                    </div>
                    <div class="border-t bg-white">
                        ${(d.questions||[]).map(q => `
                            <div class="px-4 py-2 border-b last:border-b-0 flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-800">${escapeHtml(q.intitule)}</div>
                                    <div class="text-xs text-gray-500">${escapeHtml((q.texte || '').substring(0, 110))}${(q.texte||'').length > 110 ? '…' : ''}</div>
                                </div>
                                <button onclick="editQuestion(${q.id})" class="text-xs px-2 py-1 rounded border hover:bg-gray-100">✎</button>
                                <button onclick="deleteQuestion(${q.id})" class="text-xs px-2 py-1 rounded text-red-600 hover:bg-red-50">✕</button>
                            </div>`).join('') || '<div class="px-4 py-3 text-xs text-gray-400 italic">Aucune question. Cliquez « + Question » pour en ajouter.</div>'}
                    </div>
                </div>`).join('');
        }

        function openDomainModal() {
            document.getElementById('modal-domain-title').textContent = 'Nouveau domaine';
            document.getElementById('domain-id').value = '';
            document.getElementById('domain-titre').value = '';
            document.getElementById('domain-description').value = '';
            document.getElementById('domain-slug').value = '';
            document.getElementById('modal-domain').classList.remove('hidden');
        }
        function editDomain(id) {
            const d = state.domains.find(x => x.id === id);
            if (!d) return;
            document.getElementById('modal-domain-title').textContent = 'Modifier le domaine';
            document.getElementById('domain-id').value = id;
            document.getElementById('domain-titre').value = d.titre;
            document.getElementById('domain-description').value = d.description || '';
            document.getElementById('domain-slug').value = d.slug;
            document.getElementById('modal-domain').classList.remove('hidden');
        }
        function closeDomainModal() { document.getElementById('modal-domain').classList.add('hidden'); }
        async function saveDomain() {
            const payload = {
                id: parseInt(document.getElementById('domain-id').value) || 0,
                titre: document.getElementById('domain-titre').value.trim(),
                description: document.getElementById('domain-description').value.trim(),
                slug: document.getElementById('domain-slug').value.trim(),
            };
            if (!payload.titre) { flash('Titre requis', 'error'); return; }
            try { await api('domain_save', payload); closeDomainModal(); await reload(); flash('Domaine enregistré', 'success'); }
            catch (e) { flash(e.message, 'error'); }
        }
        async function deleteDomain(id) {
            if (!confirm('Supprimer ce domaine et toutes ses questions ? (les réponses existantes sont conservées)')) return;
            try { await api('domain_delete', { id }); await reload(); flash('Domaine supprimé', 'success'); }
            catch (e) { flash(e.message, 'error'); }
        }

        // === Questions ===
        function openQuestionModal(domainId) {
            document.getElementById('modal-question-title').textContent = 'Nouvelle question';
            document.getElementById('question-id').value = '';
            document.getElementById('question-domain-id').value = domainId;
            document.getElementById('question-intitule').value = '';
            document.getElementById('question-texte').value = '';
            document.getElementById('question-aide').value = '';
            document.getElementById('question-slug').value = '';
            renderAnchors({});
            document.getElementById('modal-question').classList.remove('hidden');
        }
        function editQuestion(id) {
            let q, domainId;
            for (const d of state.domains) {
                for (const qq of (d.questions || [])) if (qq.id === id) { q = qq; domainId = d.id; break; }
                if (q) break;
            }
            if (!q) return;
            document.getElementById('modal-question-title').textContent = 'Modifier la question';
            document.getElementById('question-id').value = id;
            document.getElementById('question-domain-id').value = domainId;
            document.getElementById('question-intitule').value = q.intitule;
            document.getElementById('question-texte').value = q.texte;
            document.getElementById('question-aide').value = q.aide || '';
            document.getElementById('question-slug').value = q.slug;
            renderAnchors(q.ancrages || {});
            document.getElementById('modal-question').classList.remove('hidden');
        }
        function renderAnchors(current) {
            const root = document.getElementById('anchors-list');
            root.innerHTML = state.scale.map(l => `
                <div class="flex gap-2 items-start">
                    <span class="pill bg-indigo-100 text-indigo-700 shrink-0 mt-2">${l.niveau} · ${escapeHtml(l.label || '')}</span>
                    <textarea rows="2" data-niveau="${l.niveau}" class="anchor-input flex-1 px-2 py-1 border rounded text-sm" placeholder="Description observable">${escapeHtml(current[l.niveau] || '')}</textarea>
                </div>`).join('');
        }
        function closeQuestionModal() { document.getElementById('modal-question').classList.add('hidden'); }
        async function saveQuestion() {
            const payload = {
                id: parseInt(document.getElementById('question-id').value) || 0,
                domain_id: parseInt(document.getElementById('question-domain-id').value) || 0,
                intitule: document.getElementById('question-intitule').value.trim(),
                texte: document.getElementById('question-texte').value.trim(),
                aide: document.getElementById('question-aide').value.trim(),
                slug: document.getElementById('question-slug').value.trim(),
                ancrages: {},
            };
            document.querySelectorAll('.anchor-input').forEach(t => {
                const n = t.dataset.niveau;
                const v = t.value.trim();
                if (v) payload.ancrages[n] = v;
            });
            if (!payload.intitule || !payload.texte) { flash('Intitulé et texte requis', 'error'); return; }
            try { await api('question_save', payload); closeQuestionModal(); await reload(); flash('Question enregistrée', 'success'); }
            catch (e) { flash(e.message, 'error'); }
        }
        async function deleteQuestion(id) {
            if (!confirm('Supprimer cette question ? (les réponses déjà données sont conservées mais ne s\'afficheront plus)')) return;
            try { await api('question_delete', { id }); await reload(); flash('Question supprimée', 'success'); }
            catch (e) { flash(e.message, 'error'); }
        }

        // === Import / Export ===
        async function exportJson() {
            try {
                const res = await api('export');
                const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = `gouvernance-config-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a); a.click(); a.remove();
            } catch (e) { flash(e.message, 'error'); }
        }
        async function importJson() {
            const raw = document.getElementById('import-json').value.trim();
            if (!raw) { flash('Collez du JSON d\'abord', 'error'); return; }
            let data;
            try { data = JSON.parse(raw); }
            catch (e) { flash('JSON invalide : ' + e.message, 'error'); return; }
            const replace = document.getElementById('import-replace').checked;
            try {
                const res = await api('import', { data, replace });
                flash(`Import : ${res.result.domains} domaine(s), ${res.result.questions} question(s), ${res.result.anchors} ancrage(s)`, 'success');
                document.getElementById('import-json').value = '';
                await reload();
                switchTab('content');
            } catch (e) { flash(e.message, 'error'); }
        }

        renderScale();
        renderDomains();
    </script>
</body>
</html>
