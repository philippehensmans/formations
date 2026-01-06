<?php
require_once 'config.php';
requireLoginWithSession();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('problemtree.title') ?> - <?= sanitize($user['username']) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary: #000000;
            --secondary: #666666;
            --accent: #1e3a8a;
            --bg-main: #f5f5f5;
            --bg-card: #ffffff;
            --border: #e5e5e5;
            --red: #dc2626;
            --orange: #ea580c;
            --amber: #d97706;
            --green: #16a34a;
            --blue: #2563eb;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg-main);
            color: var(--primary);
            line-height: 1.6;
            padding: 16px;
        }

        .user-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .user-info { font-weight: 600; }

        .user-bar a {
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .user-bar a:hover { background: rgba(255,255,255,0.3); }

        .share-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .share-toggle label {
            cursor: pointer;
            font-size: 0.9rem;
        }

        .save-status {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--bg-card);
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 32px;
        }

        header {
            margin-bottom: 32px;
            text-align: center;
            border-bottom: 3px solid var(--accent);
            padding-bottom: 24px;
        }

        header h1 { font-size: 2rem; font-weight: bold; color: var(--primary); margin-bottom: 8px; }
        header p { color: var(--secondary); font-style: italic; }

        .form-group {
            margin-bottom: 24px;
            padding: 20px;
            background: #fafafa;
            border-radius: 4px;
            border: 1px solid var(--border);
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 4px;
            font-size: 1rem;
        }

        .view-toggle {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            justify-content: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .btn:hover { opacity: 0.85; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-outline { background: white; color: var(--primary); border: 2px solid var(--border); }
        .btn-outline.active { background: var(--accent); color: white; border-color: var(--accent); }
        .btn-red { background: var(--red); color: white; }
        .btn-amber { background: var(--amber); color: white; }
        .btn-green { background: var(--green); color: white; }
        .btn-blue { background: var(--blue); color: white; }

        .tree-view {
            display: none;
            margin: 40px 0;
            padding: 40px 20px;
            background: linear-gradient(to bottom, #f0f9ff 0%, #ffffff 30%, #ffffff 70%, #fffbeb 100%);
            border-radius: 8px;
            min-height: 800px;
        }

        .tree-view.active { display: block; }

        .tree-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 60px;
        }

        .consequences-section, .causes-section {
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .tree-node {
            background: white;
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            min-width: 200px;
            max-width: 280px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            position: relative;
        }

        .tree-node:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .tree-node.consequence { border-color: #fecaca; background: #fef2f2; }
        .tree-node.consequence::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: 50%;
            width: 2px;
            height: 30px;
            background: #fecaca;
        }

        .tree-node.central {
            border: 3px solid #fb923c;
            background: #fff7ed;
            min-width: 300px;
            max-width: 400px;
            padding: 24px;
        }

        .tree-node.cause { border-color: #fde68a; background: #fffbeb; }
        .tree-node.cause::before {
            content: '';
            position: absolute;
            top: -30px;
            left: 50%;
            width: 2px;
            height: 30px;
            background: #fde68a;
        }

        .tree-node.objectif { border-color: #bbf7d0; background: #f0fdf4; }
        .tree-node.objectif::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: 50%;
            width: 2px;
            height: 30px;
            background: #bbf7d0;
        }

        .tree-node.moyen { border-color: #bfdbfe; background: #eff6ff; }
        .tree-node.moyen::before {
            content: '';
            position: absolute;
            top: -30px;
            left: 50%;
            width: 2px;
            height: 30px;
            background: #bfdbfe;
        }

        .node-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
            opacity: 0.7;
        }

        .node-content { font-size: 0.95rem; line-height: 1.5; }
        .node-content.editable {
            border: 1px dashed var(--border);
            padding: 8px;
            border-radius: 4px;
            cursor: text;
            min-height: 40px;
        }
        .node-content.editable:hover { border-color: var(--accent); background: rgba(30, 58, 138, 0.05); }
        .node-content.empty { color: var(--secondary); font-style: italic; }
        .central-node { text-align: center; font-size: 1.1rem; font-weight: 600; }

        .add-node-btn {
            background: white;
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 16px;
            min-width: 200px;
            color: var(--secondary);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .add-node-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(30, 58, 138, 0.05);
        }

        .form-view { display: none; }
        .form-view.active { display: block; }

        .section {
            margin-bottom: 32px;
            padding: 24px;
            border-radius: 4px;
            border: 2px solid var(--border);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title { font-size: 1.25rem; font-weight: bold; }

        .section-consequences { background: #fef2f2; border-color: #fecaca; }
        .section-consequences .section-title { color: var(--red); }

        .section-central { background: #fff7ed; border: 3px solid #fb923c; }
        .section-central .section-title { color: var(--orange); text-align: center; font-size: 1.5rem; }

        .section-causes { background: #fffbeb; border-color: #fde68a; }
        .section-causes .section-title { color: var(--amber); }

        .section-objectifs { background: #f0fdf4; border-color: #bbf7d0; }
        .section-objectifs .section-title { color: var(--green); }

        .section-moyens { background: #eff6ff; border-color: #bfdbfe; }
        .section-moyens .section-title { color: var(--blue); }

        .item-list { display: flex; flex-direction: column; gap: 12px; }
        .item-row { display: flex; gap: 8px; align-items: center; }
        .item-row input {
            flex: 1;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 4px;
            font-size: 1rem;
        }

        .btn-remove {
            background: none;
            border: none;
            color: var(--red);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 8px;
        }

        .info-text { font-size: 0.9rem; color: var(--secondary); font-style: italic; margin-top: 4px; }

        textarea.central-input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 4px;
            font-size: 1rem;
            text-align: center;
            font-weight: 600;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border);
            margin-bottom: 32px;
        }

        .tab-button {
            padding: 12px 24px;
            font-weight: 600;
            background: none;
            border: none;
            color: var(--secondary);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }

        .tab-button:hover { color: var(--primary); }
        .tab-button.active { color: var(--accent); border-bottom-color: var(--accent); }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 32px;
            padding-top: 32px;
            border-top: 2px solid var(--border);
        }

        .section-label {
            text-align: center;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 20px;
            font-size: 1.1rem;
        }

        .legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
        .legend-color { width: 20px; height: 20px; border-radius: 4px; border: 2px solid currentColor; }

        @media print {
            .no-print { display: none !important; }
            .tree-view { background: white; }
            .user-bar { display: none; }
        }

        @media (max-width: 768px) {
            .container { padding: 16px; }
            header h1 { font-size: 1.5rem; }
            .tree-node { min-width: 150px; }
        }
    </style>
</head>
<body>
    <?= renderLanguageScript() ?>
    <div style="max-width: 1400px; margin: 0 auto;">
        <div class="user-bar no-print">
            <div class="user-info">
                <?= t('app.connected') ?> : <strong><?= sanitize($user['username']) ?></strong>
                <?php if (isFormateur()): ?>
                    <a href="formateur.php" style="margin-left: 10px;"><?= t('trainer.title') ?></a>
                <?php endif; ?>
            </div>
            <?= renderLanguageSelector('text-sm bg-white/20 text-white px-2 py-1 rounded border border-white/30') ?>
            <div class="share-toggle">
                <input type="checkbox" id="shareToggle" onchange="toggleShare()">
                <label for="shareToggle"><?= t('problemtree.share_trainer') ?></label>
            </div>
            <div class="save-status" id="saveStatus"><?= t('common.loading') ?></div>
            <a href="logout.php"><?= t('auth.logout') ?></a>
        </div>

        <div class="container">
            <header>
                <h1><?= t('problemtree.app_title') ?></h1>
                <p><?= t('problemtree.app_subtitle') ?></p>
            </header>

            <div class="form-group">
                <label for="nomProjet"><?= t('problemtree.project_name') ?></label>
                <input type="text" id="nomProjet" placeholder="<?= t('problemtree.project_placeholder') ?>" oninput="updateTree()">
            </div>

            <div class="form-group">
                <label for="participants"><?= t('problemtree.group_participants') ?></label>
                <input type="text" id="participants" placeholder="<?= t('problemtree.participants_placeholder') ?>" oninput="scheduleAutoSave()">
            </div>

            <div class="view-toggle no-print">
                <button class="btn btn-outline active" onclick="switchView('tree')"><?= t('problemtree.tree_view') ?></button>
                <button class="btn btn-outline" onclick="switchView('form')"><?= t('problemtree.form_view') ?></button>
            </div>

            <div class="tabs no-print">
                <button id="tab-problemes" class="tab-button active" onclick="switchTab('problemes')"><?= t('problemtree.problems_tree') ?></button>
                <button id="tab-solutions" class="tab-button" onclick="switchTab('solutions')"><?= t('problemtree.solutions_tree') ?></button>
            </div>

            <!-- VUE ARBRE - Problèmes -->
            <div id="tree-problemes" class="tree-view active">
                <div class="legend no-print">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #fef2f2; color: #fecaca;"></div>
                        <span><?= t('problemtree.consequences') ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #fff7ed; color: #fb923c;"></div>
                        <span><?= t('problemtree.central_problem') ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #fffbeb; color: #fde68a;"></div>
                        <span><?= t('problemtree.causes') ?></span>
                    </div>
                </div>

                <div class="tree-container">
                    <div class="section-label"><?= t('problemtree.consequences_effects') ?></div>
                    <div class="consequences-section" id="tree-consequences">
                        <button class="add-node-btn" onclick="addConsequence()">+ <?= t('problemtree.add_consequence') ?></button>
                    </div>

                    <div class="tree-node central" onclick="editCentralNode('problemeCentral')">
                        <div class="node-title"><?= t('problemtree.central_problem') ?></div>
                        <div class="node-content central-node editable" id="tree-problemeCentral"><?= t('problemtree.click_define_problem') ?></div>
                    </div>

                    <div class="section-label"><?= t('problemtree.causes_roots') ?></div>
                    <div class="causes-section" id="tree-causes">
                        <button class="add-node-btn" onclick="addCause()">+ <?= t('problemtree.add_cause') ?></button>
                    </div>
                </div>
            </div>

            <!-- VUE ARBRE - Solutions -->
            <div id="tree-solutions" class="tree-view">
                <div class="legend no-print">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f0fdf4; color: #bbf7d0;"></div>
                        <span><?= t('problemtree.objectives') ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #fff7ed; color: #fb923c;"></div>
                        <span><?= t('problemtree.central_objective') ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #eff6ff; color: #bfdbfe;"></div>
                        <span><?= t('problemtree.means') ?></span>
                    </div>
                </div>

                <div class="tree-container">
                    <div class="section-label"><?= t('problemtree.positive_impacts') ?></div>
                    <div class="consequences-section" id="tree-objectifs">
                        <button class="add-node-btn" onclick="addObjectif()">+ <?= t('problemtree.add_objective') ?></button>
                    </div>

                    <div class="tree-node central" onclick="editCentralNode('objectifCentral')">
                        <div class="node-title"><?= t('problemtree.central_objective') ?></div>
                        <div class="node-content central-node editable" id="tree-objectifCentral"><?= t('problemtree.click_define_objective') ?></div>
                    </div>

                    <div class="section-label"><?= t('problemtree.means_actions') ?></div>
                    <div class="causes-section" id="tree-moyens">
                        <button class="add-node-btn" onclick="addMoyen()">+ <?= t('problemtree.add_means') ?></button>
                    </div>
                </div>
            </div>

            <!-- VUE FORMULAIRE - Problèmes -->
            <div id="form-problemes" class="form-view">
                <div class="section section-consequences">
                    <div class="section-header">
                        <div>
                            <div class="section-title"><?= t('problemtree.consequences_effects') ?></div>
                            <div class="info-text"><?= t('problemtree.consequences_desc') ?></div>
                        </div>
                        <button onclick="addConsequence()" class="btn btn-red">+ <?= t('common.add') ?></button>
                    </div>
                    <div id="consequencesList" class="item-list"></div>
                </div>

                <div class="section section-central">
                    <div class="section-title"><?= t('problemtree.central_problem') ?></div>
                    <div class="info-text" style="text-align: center; margin-bottom: 16px;"><?= t('problemtree.central_problem_question') ?></div>
                    <textarea id="problemeCentral" rows="3" class="central-input" placeholder="<?= t('problemtree.project_placeholder') ?>" oninput="updateTree()"></textarea>
                </div>

                <div class="section section-causes">
                    <div class="section-header">
                        <div>
                            <div class="section-title"><?= t('problemtree.causes_roots') ?></div>
                            <div class="info-text"><?= t('problemtree.causes_desc') ?></div>
                        </div>
                        <button onclick="addCause()" class="btn btn-amber">+ <?= t('common.add') ?></button>
                    </div>
                    <div id="causesList" class="item-list"></div>
                </div>
            </div>

            <!-- VUE FORMULAIRE - Solutions -->
            <div id="form-solutions" class="form-view">
                <div class="section section-objectifs">
                    <div class="section-header">
                        <div>
                            <div class="section-title"><?= t('problemtree.objectives') ?></div>
                            <div class="info-text"><?= t('problemtree.objectives_desc') ?></div>
                        </div>
                        <button onclick="addObjectif()" class="btn btn-green">+ <?= t('common.add') ?></button>
                    </div>
                    <div id="objectifsList" class="item-list"></div>
                </div>

                <div class="section section-central">
                    <div class="section-title"><?= t('problemtree.central_objective') ?></div>
                    <div class="info-text" style="text-align: center; margin-bottom: 16px;"><?= t('problemtree.central_objective_reformulation') ?></div>
                    <textarea id="objectifCentral" rows="3" class="central-input" placeholder="<?= t('problemtree.project_placeholder') ?>" oninput="updateTree()"></textarea>
                </div>

                <div class="section section-moyens">
                    <div class="section-header">
                        <div>
                            <div class="section-title"><?= t('problemtree.means') ?></div>
                            <div class="info-text"><?= t('problemtree.means_desc') ?></div>
                        </div>
                        <button onclick="addMoyen()" class="btn btn-blue">+ <?= t('common.add') ?></button>
                    </div>
                    <div id="moyensList" class="item-list"></div>
                </div>
            </div>

            <div class="actions no-print">
                <button onclick="window.print()" class="btn btn-primary"><?= t('common.print') ?> / PDF</button>
                <button onclick="exportJSON()" class="btn btn-outline"><?= t('common.export') ?> JSON</button>
                <button onclick="exportToExcel()" class="btn btn-outline"><?= t('app.export_excel') ?></button>
                <button onclick="resetForm()" class="btn btn-outline"><?= t('app.reset') ?></button>
            </div>
        </div>
    </div>

    <script>
        // Traductions JavaScript
        const jsTranslations = {
            dataLoaded: '<?= t('app.data_loaded') ?>',
            loadError: '<?= t('app.load_error') ?>',
            pendingChanges: '<?= t('app.pending_changes') ?>',
            saving: '<?= t('app.saving') ?>',
            savedAt: '<?= t('app.saved_at') ?>',
            connectionError: '<?= t('app.connection_error') ?>',
            sharedWithTrainer: '<?= t('problemtree.shared_with_trainer') ?>',
            notShared: '<?= t('problemtree.not_shared') ?>',
            clickDefineProb: '<?= t('problemtree.click_define_problem') ?>',
            clickDefineObj: '<?= t('problemtree.click_define_objective') ?>',
            addConsequence: '<?= t('problemtree.add_consequence') ?>',
            addCause: '<?= t('problemtree.add_cause') ?>',
            addObjective: '<?= t('problemtree.add_objective') ?>',
            addMeans: '<?= t('problemtree.add_means') ?>',
            newConsequence: '<?= t('problemtree.new_consequence') ?>',
            newCause: '<?= t('problemtree.new_cause') ?>',
            newObjective: '<?= t('problemtree.new_objective') ?>',
            newMeans: '<?= t('problemtree.new_means') ?>',
            deleteConfirm: '<?= t('problemtree.delete_confirm') ?>',
            resetConfirm: '<?= t('problemtree.reset_confirm') ?>'
        };

        let currentView = 'tree';
        let currentTab = 'problemes';
        let data = { consequences: [], causes: [], objectifs: [], moyens: [] };
        let autoSaveTimeout = null;
        let isShared = false;

        // Chargement initial
        window.addEventListener('load', loadFromServer);

        async function loadFromServer() {
            try {
                const response = await fetch('api.php?action=load');
                const result = await response.json();

                if (result.success && result.data) {
                    document.getElementById('nomProjet').value = result.data.nomProjet || '';
                    document.getElementById('participants').value = result.data.participants || '';
                    document.getElementById('problemeCentral').value = result.data.problemeCentral || '';
                    document.getElementById('objectifCentral').value = result.data.objectifCentral || '';
                    data.consequences = result.data.consequences || [];
                    data.causes = result.data.causes || [];
                    data.objectifs = result.data.objectifs || [];
                    data.moyens = result.data.moyens || [];
                    isShared = result.data.isShared || false;
                    document.getElementById('shareToggle').checked = isShared;
                    updateTree();
                    updateFormLists();
                }
                updateSaveStatus(jsTranslations.dataLoaded);
            } catch (error) {
                console.error('Erreur de chargement:', error);
                updateSaveStatus(jsTranslations.loadError);
            }
        }

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(saveToServer, 1500);
            updateSaveStatus(jsTranslations.pendingChanges);
        }

        async function saveToServer() {
            updateSaveStatus(jsTranslations.saving);
            try {
                const saveData = {
                    nomProjet: document.getElementById('nomProjet').value,
                    participants: document.getElementById('participants').value,
                    problemeCentral: document.getElementById('problemeCentral').value,
                    objectifCentral: document.getElementById('objectifCentral').value,
                    consequences: data.consequences,
                    causes: data.causes,
                    objectifs: data.objectifs,
                    moyens: data.moyens
                };

                const response = await fetch('api.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(saveData)
                });

                const result = await response.json();
                if (result.success) {
                    updateSaveStatus(jsTranslations.savedAt + ' ' + new Date().toLocaleTimeString());
                } else {
                    updateSaveStatus(jsTranslations.error + ': ' + result.error);
                }
            } catch (error) {
                console.error('Erreur de sauvegarde:', error);
                updateSaveStatus(jsTranslations.connectionError);
            }
        }

        async function toggleShare() {
            isShared = document.getElementById('shareToggle').checked;
            try {
                await fetch('api.php?action=share', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ shared: isShared })
                });
                updateSaveStatus(isShared ? jsTranslations.sharedWithTrainer : jsTranslations.notShared);
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        function updateSaveStatus(message) {
            document.getElementById('saveStatus').textContent = message;
        }

        function switchView(view) {
            currentView = view;
            document.querySelectorAll('.view-toggle .btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            if (view === 'tree') {
                document.getElementById('tree-' + currentTab).style.display = 'block';
                document.getElementById('form-' + currentTab).classList.remove('active');
            } else {
                document.getElementById('tree-' + currentTab).style.display = 'none';
                document.getElementById('form-' + currentTab).classList.add('active');
            }
        }

        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            document.querySelectorAll('.tree-view, .form-view').forEach(view => {
                view.style.display = 'none';
                view.classList.remove('active');
            });

            if (currentView === 'tree') {
                document.getElementById('tree-' + tab).style.display = 'block';
                document.getElementById('tree-' + tab).classList.add('active');
            } else {
                document.getElementById('form-' + tab).classList.add('active');
            }
        }

        function editCentralNode(id) {
            const currentText = document.getElementById(id).value;
            const newText = prompt('Modifier le texte:', currentText);
            if (newText !== null) {
                document.getElementById(id).value = newText;
                updateTree();
            }
        }

        function updateTree() {
            const problemeCentral = document.getElementById('problemeCentral').value;
            const treeProblemeCentral = document.getElementById('tree-problemeCentral');
            treeProblemeCentral.textContent = problemeCentral || jsTranslations.clickDefineProb;
            treeProblemeCentral.classList.toggle('empty', !problemeCentral);

            const objectifCentral = document.getElementById('objectifCentral').value;
            const treeObjectifCentral = document.getElementById('tree-objectifCentral');
            treeObjectifCentral.textContent = objectifCentral || jsTranslations.clickDefineObj;
            treeObjectifCentral.classList.toggle('empty', !objectifCentral);

            renderTreeNodes();
            scheduleAutoSave();
        }

        function renderTreeNodes() {
            const labels = {
                consequences: jsTranslations.addConsequence,
                causes: jsTranslations.addCause,
                objectifs: jsTranslations.addObjective,
                moyens: jsTranslations.addMeans
            };
            ['consequences', 'causes', 'objectifs', 'moyens'].forEach(key => {
                const container = document.getElementById('tree-' + key);
                const type = key === 'consequences' ? 'consequence' :
                            key === 'causes' ? 'cause' :
                            key === 'objectifs' ? 'objectif' : 'moyen';

                container.innerHTML = '';
                data[key].forEach((text, index) => {
                    if (text.trim()) {
                        const node = createTreeNode(text, type, index, key);
                        container.appendChild(node);
                    }
                });

                const addBtn = document.createElement('button');
                addBtn.className = 'add-node-btn';
                addBtn.textContent = '+ ' + labels[key];
                addBtn.onclick = () => addItem(key);
                container.appendChild(addBtn);
            });
        }

        function createTreeNode(text, type, index, dataKey) {
            const node = document.createElement('div');
            node.className = 'tree-node ' + type;
            node.style.position = 'relative';

            const content = document.createElement('div');
            content.className = 'node-content editable';
            content.textContent = text;
            content.onclick = () => editNode(dataKey, index);
            node.appendChild(content);

            const deleteBtn = document.createElement('button');
            deleteBtn.textContent = 'x';
            deleteBtn.className = 'btn-remove';
            deleteBtn.style.cssText = 'position: absolute; top: 4px; right: 4px;';
            deleteBtn.onclick = (e) => { e.stopPropagation(); deleteNode(dataKey, index); };
            node.appendChild(deleteBtn);

            return node;
        }

        function addItem(key) {
            const labels = {
                consequences: jsTranslations.newConsequence,
                causes: jsTranslations.newCause,
                objectifs: jsTranslations.newObjective,
                moyens: jsTranslations.newMeans
            };
            const text = prompt(labels[key]);
            if (text && text.trim()) {
                data[key].push(text);
                updateTree();
                updateFormLists();
            }
        }

        function addConsequence() { addItem('consequences'); }
        function addCause() { addItem('causes'); }
        function addObjectif() { addItem('objectifs'); }
        function addMoyen() { addItem('moyens'); }

        function editNode(dataKey, index) {
            const newText = prompt('Modifier le texte:', data[dataKey][index]);
            if (newText !== null) {
                data[dataKey][index] = newText;
                updateTree();
                updateFormLists();
            }
        }

        function deleteNode(dataKey, index) {
            if (confirm(jsTranslations.deleteConfirm)) {
                data[dataKey].splice(index, 1);
                updateTree();
                updateFormLists();
            }
        }

        function updateFormLists() {
            ['consequences', 'causes', 'objectifs', 'moyens'].forEach(key => {
                const list = document.getElementById(key + 'List');
                list.innerHTML = '';
                data[key].forEach((text, index) => {
                    const div = document.createElement('div');
                    div.className = 'item-row';
                    div.innerHTML = `
                        <input type="text" value="${text.replace(/"/g, '&quot;')}"
                               onchange="data.${key}[${index}] = this.value; updateTree()">
                        <button type="button" onclick="data.${key}.splice(${index}, 1); updateTree(); updateFormLists()" class="btn-remove">X</button>
                    `;
                    list.appendChild(div);
                });
            });
        }

        function exportJSON() {
            const formData = {
                nomProjet: document.getElementById('nomProjet').value,
                participants: document.getElementById('participants').value,
                arbreProblemes: {
                    problemeCentral: document.getElementById('problemeCentral').value,
                    consequences: data.consequences,
                    causes: data.causes
                },
                arbreSolutions: {
                    objectifCentral: document.getElementById('objectifCentral').value,
                    objectifs: data.objectifs,
                    moyens: data.moyens
                },
                dateExport: new Date().toISOString()
            };

            const dataStr = JSON.stringify(formData, null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            const nomFichier = formData.nomProjet ?
                formData.nomProjet.replace(/[^a-z0-9]/gi, '_').toLowerCase() :
                'arbre_problemes';
            link.download = nomFichier + '_' + new Date().toISOString().split('T')[0] + '.json';
            link.click();
            URL.revokeObjectURL(url);
        }

        function exportToExcel() {
            const wb = XLSX.utils.book_new();

            const infoData = [
                ['ANALYSE DU CONTEXTE - ARBRE A PROBLEMES & SOLUTIONS'],
                [''],
                ['Projet', document.getElementById('nomProjet').value],
                ['Participants', document.getElementById('participants').value],
                ["Date d'export", new Date().toLocaleDateString('fr-FR')]
            ];

            const problemesData = [
                ['ARBRE A PROBLEMES'],
                [''],
                ['PROBLEME CENTRAL'],
                [document.getElementById('problemeCentral').value],
                [''],
                ['CONSEQUENCES (Effets négatifs)']
            ];
            data.consequences.forEach((c, i) => problemesData.push([(i + 1) + '.', c]));
            problemesData.push([''], ['CAUSES (Racines du problème)']);
            data.causes.forEach((c, i) => problemesData.push([(i + 1) + '.', c]));

            const solutionsData = [
                ['ARBRE A SOLUTIONS'],
                [''],
                ['OBJECTIF CENTRAL'],
                [document.getElementById('objectifCentral').value],
                [''],
                ['OBJECTIFS / IMPACTS POSITIFS']
            ];
            data.objectifs.forEach((o, i) => solutionsData.push([(i + 1) + '.', o]));
            solutionsData.push([''], ['MOYENS / ACTIONS A METTRE EN OEUVRE']);
            data.moyens.forEach((m, i) => solutionsData.push([(i + 1) + '.', m]));

            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(infoData), 'Informations');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(problemesData), 'Arbre a Problemes');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(solutionsData), 'Arbre a Solutions');

            const nomProjet = document.getElementById('nomProjet').value || 'projet';
            XLSX.writeFile(wb, 'arbre_' + nomProjet.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_' + new Date().toISOString().split('T')[0] + '.xlsx');
        }

        function resetForm() {
            if (confirm(jsTranslations.resetConfirm)) {
                document.getElementById('nomProjet').value = '';
                document.getElementById('participants').value = '';
                document.getElementById('problemeCentral').value = '';
                document.getElementById('objectifCentral').value = '';
                data = { consequences: [], causes: [], objectifs: [], moyens: [] };
                updateTree();
                updateFormLists();
            }
        }
    </script>
</body>
</html>
