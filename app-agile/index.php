<?php
require_once 'config.php';
requireLoginWithSession();

$db = getDB();
$userId = $_SESSION['user_id'];
$sessionId = $_SESSION['current_session_id'];
$user = getCurrentUser();
$username = $user['username'];

// Charger ou creer le projet de l'utilisateur pour cette session
$stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$project = $stmt->fetch();

if (!$project) {
    $stmt = $db->prepare("INSERT INTO projects (user_id, session_id, cards, user_stories, retrospective, sprint) VALUES (?, ?, '[]', '[]', '{\"good\":[],\"improve\":[],\"actions\":[]}', '{\"number\":1,\"start\":\"\",\"end\":\"\",\"goal\":\"\"}')");
    $stmt->execute([$userId, $sessionId]);
    $projectId = $db->lastInsertId();
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
}

// Valeurs par defaut si les champs sont vides
$project['cards'] = $project['cards'] ?: '[]';
$project['user_stories'] = $project['user_stories'] ?: '[]';
$project['retrospective'] = $project['retrospective'] ?: '{"good":[],"improve":[],"actions":[]}';
$project['sprint'] = $project['sprint'] ?: '{"number":1,"start":"","end":"","goal":""}';
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('agile.title') ?> - <?= t('agile.subtitle') ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary: #000000;
            --secondary: #666666;
            --accent: #1e3a8a;
            --bg-main: #f5f5f5;
            --bg-card: #ffffff;
            --border: #e5e5e5;
            --todo: #94a3b8;
            --inprogress: #f59e0b;
            --done: #16a34a;
            --backlog: #6366f1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg-main);
            color: var(--primary);
            line-height: 1.6;
            padding: 16px;
        }

        .user-bar {
            background: var(--accent);
            color: white;
            padding: 12px 24px;
            margin: -16px -16px 16px -16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .user-bar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            background: rgba(255,255,255,0.2);
        }

        .user-bar a:hover {
            background: rgba(255,255,255,0.3);
        }

        .share-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .share-toggle input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--bg-card);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 32px;
        }

        header {
            margin-bottom: 32px;
            text-align: center;
            border-bottom: 3px solid var(--accent);
            padding-bottom: 24px;
        }

        header h1 {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 8px;
        }

        header p {
            color: var(--secondary);
            font-style: italic;
        }

        .info-box {
            background: #f8fafc;
            border-left: 4px solid var(--accent);
            padding: 24px;
            margin-bottom: 32px;
            border-radius: 4px;
        }

        .info-box h2 {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .info-box p {
            color: var(--secondary);
            margin-bottom: 8px;
            line-height: 1.7;
        }

        .info-box ul {
            margin-left: 20px;
            color: var(--secondary);
        }

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
            font-size: 1rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 4px;
            font-size: 1rem;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border);
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 8px;
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

        .tab-button:hover {
            color: var(--primary);
        }

        .tab-button.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .kanban-column {
            background: #fafafa;
            border-radius: 8px;
            padding: 16px;
            min-height: 400px;
        }

        .column-header {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 3px solid;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .column-backlog .column-header {
            border-color: var(--backlog);
            color: var(--backlog);
        }

        .column-todo .column-header {
            border-color: var(--todo);
            color: var(--todo);
        }

        .column-inprogress .column-header {
            border-color: var(--inprogress);
            color: var(--inprogress);
        }

        .column-done .column-header {
            border-color: var(--done);
            color: var(--done);
        }

        .card {
            background: white;
            border: 2px solid var(--border);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            cursor: move;
            transition: all 0.2s;
        }

        .card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary);
        }

        .card-description {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 8px;
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--secondary);
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--border);
        }

        .card-priority {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .priority-high {
            background: #fecaca;
            color: #991b1b;
        }

        .priority-medium {
            background: #fed7aa;
            color: #9a3412;
        }

        .priority-low {
            background: #d1fae5;
            color: #065f46;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            font-size: 0.9rem;
        }

        .btn:hover {
            opacity: 0.85;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-outline {
            background: white;
            color: var(--primary);
            border: 2px solid var(--border);
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .btn-add {
            background: var(--accent);
            color: white;
            width: 100%;
            margin-top: 8px;
        }

        .sprint-header {
            background: #f0f9ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 2px solid #bfdbfe;
        }

        .sprint-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .sprint-metric {
            background: white;
            padding: 16px;
            border-radius: 6px;
            border: 1px solid var(--border);
        }

        .metric-label {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 4px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--accent);
        }

        .story-form {
            background: #fafafa;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 2px solid var(--border);
        }

        .story-form h3 {
            margin-bottom: 16px;
            color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
        }

        .form-field label {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-field input,
        .form-field textarea,
        .form-field select {
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: 4px;
            font-size: 0.95rem;
        }

        .form-field textarea {
            min-height: 80px;
            resize: vertical;
        }

        .retro-columns {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .retro-column {
            background: #fafafa;
            border-radius: 8px;
            padding: 20px;
            border: 2px solid var(--border);
        }

        .retro-column h3 {
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--accent);
        }

        .retro-item {
            background: white;
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 4px;
            border: 1px solid var(--border);
        }

        .retro-input {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: 4px;
            margin-top: 12px;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 32px;
            padding-top: 32px;
            border-top: 2px solid var(--border);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--secondary);
            font-style: italic;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 4px;
        }

        .save-status {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .save-status.saving {
            background: #fef3c7;
            color: #92400e;
        }

        .save-status.saved {
            background: #d1fae5;
            color: #065f46;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            .user-bar {
                display: none !important;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
            header h1 {
                font-size: 1.5rem;
            }
            .kanban-board {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="user-bar no-print">
        <div>
            Connecte : <strong><?= sanitize($username) ?></strong>
        </div>
        <div class="share-toggle">
            <input type="checkbox" id="shareToggle" <?= $project['is_shared'] ? 'checked' : '' ?> onchange="toggleShare()">
            <label for="shareToggle"><?= t('agile.share_trainer') ?></label>
        </div>
        <div>
            <?= renderLanguageSelector('lang-select') ?>
            <span id="saveStatus" class="save-status saved"><?= t('app.saved') ?></span>
            <?php if (isFormateur()): ?>
            <a href="formateur.php" style="background: rgba(16,185,129,0.3);"><?= t('trainer.title') ?></a>
            <?php endif; ?>
            <a href="logout.php"><?= t('auth.logout') ?></a>
        </div>
    </div>
    <?= renderLanguageScript() ?>

    <div class="container">
        <header>
            <h1><?= t('agile.title') ?></h1>
            <p><?= t('agile.subtitle') ?></p>
        </header>

        <div class="info-box">
            <h2><?= t('agile.understand') ?></h2>
            <p><?= t('agile.intro') ?></p>
            <p><strong><?= t('agile.principles') ?> :</strong></p>
            <ul>
                <li><?= t('agile.principle1') ?></li>
                <li><?= t('agile.principle2') ?></li>
                <li><?= t('agile.principle3') ?></li>
                <li><?= t('agile.principle4') ?></li>
            </ul>
        </div>

        <div class="form-group">
            <label for="projectName"><?= t('agile.project_name') ?></label>
            <input
                type="text"
                id="projectName"
                placeholder="<?= t('agile.project_placeholder') ?>"
                value="<?= sanitize($project['project_name']) ?>"
                oninput="scheduleAutoSave()"
            >
        </div>

        <div class="form-group">
            <label for="teamName"><?= t('agile.team_members') ?></label>
            <input
                type="text"
                id="teamName"
                placeholder="<?= t('agile.team_placeholder') ?>"
                value="<?= sanitize($project['team_name']) ?>"
                oninput="scheduleAutoSave()"
            >
        </div>

        <div class="tabs no-print">
            <button class="tab-button active" onclick="switchTab('kanban')">
                <?= t('agile.kanban') ?>
            </button>
            <button class="tab-button" onclick="switchTab('sprint')">
                <?= t('agile.sprint_planning') ?>
            </button>
            <button class="tab-button" onclick="switchTab('stories')">
                <?= t('agile.user_stories') ?>
            </button>
            <button class="tab-button" onclick="switchTab('retro')">
                <?= t('agile.retrospective') ?>
            </button>
        </div>

        <!-- TAB 1: Kanban Board -->
        <div id="tab-kanban" class="tab-content active">
            <div class="info-box">
                <h2><?= t('agile.kanban') ?></h2>
            </div>

            <div class="kanban-board">
                <div class="kanban-column column-backlog">
                    <div class="column-header">
                        <span><?= t('agile.backlog') ?></span>
                        <span class="badge" style="background: var(--backlog); color: white;" id="count-backlog">0</span>
                    </div>
                    <div id="column-backlog" class="column-content"></div>
                    <button class="btn btn-add btn-small no-print" onclick="showAddCardModal('backlog')">
                        + <?= t('agile.add_card') ?>
                    </button>
                </div>

                <div class="kanban-column column-todo">
                    <div class="column-header">
                        <span><?= t('agile.todo') ?></span>
                        <span class="badge" style="background: var(--todo); color: white;" id="count-todo">0</span>
                    </div>
                    <div id="column-todo" class="column-content"></div>
                </div>

                <div class="kanban-column column-inprogress">
                    <div class="column-header">
                        <span><?= t('agile.in_progress') ?></span>
                        <span class="badge" style="background: var(--inprogress); color: white;" id="count-inprogress">0</span>
                    </div>
                    <div id="column-inprogress" class="column-content"></div>
                </div>

                <div class="kanban-column column-done">
                    <div class="column-header">
                        <span><?= t('agile.done') ?></span>
                        <span class="badge" style="background: var(--done); color: white;" id="count-done">0</span>
                    </div>
                    <div id="column-done" class="column-content"></div>
                </div>
            </div>
        </div>

        <!-- TAB 2: Sprint Planning -->
        <div id="tab-sprint" class="tab-content">
            <div class="info-box">
                <h2><?= t('agile.sprint_planning') ?></h2>
            </div>

            <div class="sprint-header">
                <h2><?= t('agile.current_sprint') ?></h2>
                <div class="form-row">
                    <div class="form-field">
                        <label for="sprintNumber"><?= t('agile.sprint_number') ?></label>
                        <input type="number" id="sprintNumber" value="1" min="1" oninput="scheduleAutoSave()">
                    </div>
                    <div class="form-field">
                        <label for="sprintStart"><?= t('agile.start_date') ?></label>
                        <input type="date" id="sprintStart" oninput="scheduleAutoSave()">
                    </div>
                    <div class="form-field">
                        <label for="sprintEnd"><?= t('agile.end_date') ?></label>
                        <input type="date" id="sprintEnd" oninput="scheduleAutoSave()">
                    </div>
                    <div class="form-field">
                        <label for="sprintGoal"><?= t('agile.sprint_goal') ?></label>
                        <input type="text" id="sprintGoal" placeholder="<?= t('agile.sprint_goal_placeholder') ?>" oninput="scheduleAutoSave()">
                    </div>
                </div>

                <div class="sprint-info">
                    <div class="sprint-metric">
                        <div class="metric-label"><?= t('agile.tasks_planned') ?></div>
                        <div class="metric-value" id="metric-planned">0</div>
                    </div>
                    <div class="sprint-metric">
                        <div class="metric-label"><?= t('agile.tasks_in_progress') ?></div>
                        <div class="metric-value" id="metric-inprogress">0</div>
                    </div>
                    <div class="sprint-metric">
                        <div class="metric-label"><?= t('agile.tasks_completed') ?></div>
                        <div class="metric-value" id="metric-done">0</div>
                    </div>
                    <div class="sprint-metric">
                        <div class="metric-label"><?= t('agile.progress') ?></div>
                        <div class="metric-value" id="metric-progress">0%</div>
                    </div>
                </div>
            </div>

            <div id="sprint-tasks-list">
                <h3><?= t('agile.sprint_tasks') ?></h3>
                <div id="sprint-tasks" style="margin-top: 16px;"></div>
            </div>
        </div>

        <!-- TAB 3: User Stories -->
        <div id="tab-stories" class="tab-content">
            <div class="info-box">
                <h2><?= t('agile.user_stories') ?></h2>
                <p><?= t('agile.user_story_desc') ?></p>
            </div>

            <div class="story-form no-print">
                <h3><?= t('agile.create_story') ?></h3>
                <div class="form-row">
                    <div class="form-field">
                        <label><?= t('agile.as_a') ?></label>
                        <input type="text" id="story-role" placeholder="<?= t('agile.as_a_placeholder') ?>">
                    </div>
                    <div class="form-field">
                        <label><?= t('agile.i_want') ?></label>
                        <input type="text" id="story-action" placeholder="<?= t('agile.i_want_placeholder') ?>">
                    </div>
                    <div class="form-field">
                        <label><?= t('agile.so_that') ?></label>
                        <input type="text" id="story-benefit" placeholder="<?= t('agile.so_that_placeholder') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label><?= t('agile.acceptance_criteria') ?></label>
                        <textarea id="story-criteria" placeholder="<?= t('agile.criteria_placeholder') ?>"></textarea>
                    </div>
                    <div class="form-field">
                        <label><?= t('agile.priority') ?></label>
                        <select id="story-priority">
                            <option value="high"><?= t('agile.high') ?></option>
                            <option value="medium" selected><?= t('agile.medium') ?></option>
                            <option value="low"><?= t('agile.low') ?></option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label><?= t('agile.estimation') ?></label>
                        <input type="number" id="story-points" min="1" max="13" value="3">
                    </div>
                </div>

                <button class="btn btn-primary" onclick="addUserStory()">
                    + <?= t('agile.add_story') ?>
                </button>
            </div>

            <div id="stories-list">
                <h3><?= t('agile.stories_created') ?></h3>
                <div id="stories-container" style="margin-top: 16px;"></div>
            </div>
        </div>

        <!-- TAB 4: Retrospective -->
        <div id="tab-retro" class="tab-content">
            <div class="info-box">
                <h2><?= t('agile.retrospective') ?></h2>
                <p><?= t('agile.retro_desc') ?></p>
            </div>

            <div class="retro-columns">
                <div class="retro-column">
                    <h3><?= t('agile.what_went_well') ?></h3>
                    <div id="retro-good"></div>
                    <input
                        type="text"
                        class="retro-input no-print"
                        placeholder="<?= t('agile.add_positive') ?>"
                        onkeypress="if(event.key==='Enter') addRetroItem('good', this.value, this)"
                    >
                </div>

                <div class="retro-column">
                    <h3><?= t('agile.to_improve') ?></h3>
                    <div id="retro-improve"></div>
                    <input
                        type="text"
                        class="retro-input no-print"
                        placeholder="<?= t('agile.add_improvement') ?>"
                        onkeypress="if(event.key==='Enter') addRetroItem('improve', this.value, this)"
                    >
                </div>

                <div class="retro-column">
                    <h3><?= t('agile.next_sprint_actions') ?></h3>
                    <div id="retro-actions"></div>
                    <input
                        type="text"
                        class="retro-input no-print"
                        placeholder="<?= t('agile.add_action') ?>"
                        onkeypress="if(event.key==='Enter') addRetroItem('actions', this.value, this)"
                    >
                </div>
            </div>
        </div>

        <div class="actions no-print">
            <button onclick="manualSave()" class="btn btn-primary"><?= t('app.save') ?></button>
            <button onclick="window.print()" class="btn btn-outline"><?= t('app.print') ?></button>
            <button onclick="exportJSON()" class="btn btn-outline"><?= t('app.export_json') ?></button>
            <button onclick="exportToExcel()" class="btn btn-outline"><?= t('app.export_excel') ?></button>
            <button onclick="resetAll()" class="btn btn-outline"><?= t('app.reset') ?></button>
        </div>
    </div>

    <!-- Modal pour ajouter une carte -->
    <div id="cardModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 32px; border-radius: 8px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 16px;"><?= t('agile.new_task') ?></h3>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;"><?= t('agile.title') ?></label>
                <input type="text" id="modal-title" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 4px;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;"><?= t('agile.description') ?></label>
                <textarea id="modal-description" rows="3" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 4px;"></textarea>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;"><?= t('agile.priority') ?></label>
                <select id="modal-priority" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 4px;">
                    <option value="high"><?= t('agile.high') ?></option>
                    <option value="medium" selected><?= t('agile.medium') ?></option>
                    <option value="low"><?= t('agile.low') ?></option>
                </select>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button onclick="addCard()" class="btn btn-primary" style="flex: 1;"><?= t('app.add') ?></button>
                <button onclick="closeCardModal()" class="btn btn-outline" style="flex: 1;"><?= t('app.cancel') ?></button>
            </div>
        </div>
    </div>

    <script>
        // Translations for JavaScript (using JSON encoding to escape quotes properly)
        const translations = {
            saving: <?= json_encode(t('app.saving')) ?>,
            saved: <?= json_encode(t('app.saved')) ?>,
            error: <?= json_encode(t('app.error')) ?>,
            networkError: <?= json_encode(t('app.network_error')) ?>,
            serverError: <?= json_encode(t('app.server_error')) ?>,
            noTasks: <?= json_encode(t('agile.no_tasks')) ?>,
            noStories: <?= json_encode(t('agile.no_stories')) ?>,
            noItems: <?= json_encode(t('agile.no_items')) ?>,
            noSprintTasks: <?= json_encode(t('agile.no_sprint_tasks')) ?>,
            high: <?= json_encode(t('agile.high')) ?>,
            medium: <?= json_encode(t('agile.medium')) ?>,
            low: <?= json_encode(t('agile.low')) ?>,
            done: <?= json_encode(t('agile.done')) ?>,
            inProgress: <?= json_encode(t('agile.in_progress')) ?>,
            todo: <?= json_encode(t('agile.todo')) ?>,
            titleRequired: <?= json_encode(t('agile.title_required')) ?>,
            fillRequired: <?= json_encode(t('agile.fill_required')) ?>,
            deleteTask: <?= json_encode(t('agile.delete_task')) ?>,
            deleteStory: <?= json_encode(t('agile.delete_story')) ?>,
            resetConfirm: <?= json_encode(t('agile.reset_confirm')) ?>,
            userStory: <?= json_encode(t('agile.user_story')) ?>,
            asA: <?= json_encode(t('agile.as_a_label')) ?>,
            iWant: <?= json_encode(t('agile.i_want_label')) ?>,
            soThat: <?= json_encode(t('agile.so_that_label')) ?>,
            criteria: <?= json_encode(t('agile.acceptance_criteria')) ?>,
            points: <?= json_encode(t('agile.points')) ?>,
            delete: <?= json_encode(t('app.delete')) ?>
        };

        let data = {
            cards: <?= $project['cards'] ?>,
            userStories: <?= $project['user_stories'] ?>,
            retrospective: <?= $project['retrospective'] ?>,
            sprint: <?= $project['sprint'] ?>
        };

        let currentColumn = 'backlog';
        let autoSaveTimeout = null;

        window.addEventListener('load', function() {
            loadSprintData();
            renderKanban();
            renderUserStories();
            renderRetrospective();
            updateSprintMetrics();
        });

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            document.getElementById('saveStatus').className = 'save-status saving';
            document.getElementById('saveStatus').textContent = translations.saving;
            autoSaveTimeout = setTimeout(saveData, 1500);
        }

        async function saveData(showAlert = false) {
            data.sprint.number = document.getElementById('sprintNumber').value;
            data.sprint.start = document.getElementById('sprintStart').value;
            data.sprint.end = document.getElementById('sprintEnd').value;
            data.sprint.goal = document.getElementById('sprintGoal').value;

            const payload = {
                project_name: document.getElementById('projectName').value,
                team_name: document.getElementById('teamName').value,
                cards: data.cards,
                user_stories: data.userStories,
                retrospective: data.retrospective,
                sprint: data.sprint
            };

            try {
                const response = await fetch('api.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const text = await response.text();

                try {
                    const result = JSON.parse(text);
                    if (result.success) {
                        document.getElementById('saveStatus').className = 'save-status saved';
                        document.getElementById('saveStatus').textContent = translations.saved;
                    } else {
                        document.getElementById('saveStatus').className = 'save-status saving';
                        document.getElementById('saveStatus').textContent = translations.error + ': ' + (result.error || 'Echec');
                        if (showAlert) alert(translations.error + ': ' + (result.error || 'Echec'));
                    }
                } catch (parseError) {
                    console.error('Reponse non-JSON:', text);
                    document.getElementById('saveStatus').textContent = translations.serverError;
                    if (showAlert) alert(translations.serverError + ':\n' + text.substring(0, 500));
                }
            } catch (error) {
                console.error('Erreur de sauvegarde:', error);
                document.getElementById('saveStatus').className = 'save-status saving';
                document.getElementById('saveStatus').textContent = translations.networkError;
                if (showAlert) alert(translations.networkError + ': ' + error.message);
            }
        }

        function manualSave() {
            document.getElementById('saveStatus').className = 'save-status saving';
            document.getElementById('saveStatus').textContent = translations.saving;
            saveData(true);
        }

        async function toggleShare() {
            const isShared = document.getElementById('shareToggle').checked;

            try {
                await fetch('api.php?action=share', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ shared: isShared })
                });
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        function loadSprintData() {
            if (data.sprint) {
                document.getElementById('sprintNumber').value = data.sprint.number || 1;
                document.getElementById('sprintStart').value = data.sprint.start || '';
                document.getElementById('sprintEnd').value = data.sprint.end || '';
                document.getElementById('sprintGoal').value = data.sprint.goal || '';
            }
        }

        function switchTab(tab) {
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');

            if (tab === 'sprint') {
                updateSprintMetrics();
            }
        }

        function showAddCardModal(column) {
            currentColumn = column;
            document.getElementById('cardModal').style.display = 'flex';
            document.getElementById('modal-title').value = '';
            document.getElementById('modal-description').value = '';
            document.getElementById('modal-priority').value = 'medium';
        }

        function closeCardModal() {
            document.getElementById('cardModal').style.display = 'none';
        }

        function addCard() {
            const title = document.getElementById('modal-title').value;
            const description = document.getElementById('modal-description').value;
            const priority = document.getElementById('modal-priority').value;

            if (!title.trim()) {
                alert(translations.titleRequired);
                return;
            }

            const card = {
                id: Date.now(),
                title: title,
                description: description,
                priority: priority,
                status: currentColumn,
                createdAt: new Date().toISOString()
            };

            data.cards.push(card);
            scheduleAutoSave();
            renderKanban();
            closeCardModal();
        }

        function renderKanban() {
            const columns = ['backlog', 'todo', 'inprogress', 'done'];

            columns.forEach(column => {
                const columnEl = document.getElementById('column-' + column);
                const cards = data.cards.filter(c => c.status === column);

                if (cards.length === 0) {
                    columnEl.innerHTML = '<div class="empty-state">' + translations.noTasks + '</div>';
                } else {
                    columnEl.innerHTML = cards.map(card => `
                        <div class="card" draggable="true" ondragstart="drag(event)" data-id="${card.id}">
                            <div class="card-title">${escapeHtml(card.title)}</div>
                            ${card.description ? `<div class="card-description">${escapeHtml(card.description)}</div>` : ''}
                            <div class="card-meta">
                                <span class="card-priority priority-${card.priority}">
                                    ${card.priority === 'high' ? translations.high : card.priority === 'medium' ? translations.medium : translations.low}
                                </span>
                                <button class="btn-remove no-print" onclick="deleteCard(${card.id})" style="background: none; border: none; color: #dc2626; cursor: pointer;">X</button>
                            </div>
                        </div>
                    `).join('');
                }

                document.getElementById('count-' + column).textContent = cards.length;

                columnEl.addEventListener('dragover', allowDrop);
                columnEl.addEventListener('drop', drop);
                columnEl.dataset.column = column;
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function drag(event) {
            event.dataTransfer.setData("cardId", event.target.dataset.id);
        }

        function allowDrop(event) {
            event.preventDefault();
        }

        function drop(event) {
            event.preventDefault();
            const cardId = parseInt(event.dataTransfer.getData("cardId"));
            const newColumn = event.currentTarget.dataset.column;

            const card = data.cards.find(c => c.id === cardId);
            if (card) {
                card.status = newColumn;
                scheduleAutoSave();
                renderKanban();
                updateSprintMetrics();
            }
        }

        function deleteCard(id) {
            if (confirm(translations.deleteTask)) {
                data.cards = data.cards.filter(c => c.id !== id);
                scheduleAutoSave();
                renderKanban();
                updateSprintMetrics();
            }
        }

        function addUserStory() {
            const role = document.getElementById('story-role').value;
            const action = document.getElementById('story-action').value;
            const benefit = document.getElementById('story-benefit').value;
            const criteria = document.getElementById('story-criteria').value;
            const priority = document.getElementById('story-priority').value;
            const points = document.getElementById('story-points').value;

            if (!role || !action || !benefit) {
                alert(translations.fillRequired);
                return;
            }

            const story = {
                id: Date.now(),
                role: role,
                action: action,
                benefit: benefit,
                criteria: criteria,
                priority: priority,
                points: points,
                createdAt: new Date().toISOString()
            };

            data.userStories.push(story);
            scheduleAutoSave();
            renderUserStories();

            document.getElementById('story-role').value = '';
            document.getElementById('story-action').value = '';
            document.getElementById('story-benefit').value = '';
            document.getElementById('story-criteria').value = '';
            document.getElementById('story-priority').value = 'medium';
            document.getElementById('story-points').value = '3';
        }

        function renderUserStories() {
            const container = document.getElementById('stories-container');

            if (data.userStories.length === 0) {
                container.innerHTML = '<div class="empty-state">' + translations.noStories + '</div>';
                return;
            }

            container.innerHTML = data.userStories.map((story, index) => `
                <div class="card" style="margin-bottom: 16px;">
                    <div class="card-title">
                        ${translations.userStory} #${index + 1}
                        <span class="card-priority priority-${story.priority}" style="float: right;">
                            ${story.priority === 'high' ? translations.high : story.priority === 'medium' ? translations.medium : translations.low}
                        </span>
                    </div>
                    <div class="card-description" style="margin: 12px 0; padding: 12px; background: #f9fafb; border-radius: 4px;">
                        <strong>${translations.asA}</strong> ${escapeHtml(story.role)}, <strong>${translations.iWant}</strong> ${escapeHtml(story.action)}
                        <strong>${translations.soThat}</strong> ${escapeHtml(story.benefit)}
                    </div>
                    ${story.criteria ? `
                        <div style="margin: 12px 0;">
                            <strong>${translations.criteria} :</strong>
                            <div style="white-space: pre-line; margin-top: 8px; color: var(--secondary);">${escapeHtml(story.criteria)}</div>
                        </div>
                    ` : ''}
                    <div class="card-meta">
                        <span>${story.points} ${translations.points}</span>
                        <button class="no-print" onclick="deleteUserStory(${story.id})" style="background: none; border: none; color: #dc2626; cursor: pointer;">${translations.delete}</button>
                    </div>
                </div>
            `).join('');
        }

        function deleteUserStory(id) {
            if (confirm(translations.deleteStory)) {
                data.userStories = data.userStories.filter(s => s.id !== id);
                scheduleAutoSave();
                renderUserStories();
            }
        }

        function addRetroItem(type, value, input) {
            if (!value.trim()) return;

            data.retrospective[type].push(value);
            scheduleAutoSave();
            renderRetrospective();
            input.value = '';
        }

        function renderRetrospective() {
            ['good', 'improve', 'actions'].forEach(type => {
                const container = document.getElementById('retro-' + type);
                const items = data.retrospective[type];

                if (items.length === 0) {
                    container.innerHTML = '<div class="empty-state">' + translations.noItems + '</div>';
                } else {
                    container.innerHTML = items.map((item, index) => `
                        <div class="retro-item">
                            ${escapeHtml(item)}
                            <button class="no-print" onclick="deleteRetroItem('${type}', ${index})" style="float: right; background: none; border: none; color: #dc2626; cursor: pointer;">x</button>
                        </div>
                    `).join('');
                }
            });
        }

        function deleteRetroItem(type, index) {
            data.retrospective[type].splice(index, 1);
            scheduleAutoSave();
            renderRetrospective();
        }

        function updateSprintMetrics() {
            const planned = data.cards.filter(c => c.status === 'todo').length;
            const inprogress = data.cards.filter(c => c.status === 'inprogress').length;
            const done = data.cards.filter(c => c.status === 'done').length;
            const total = planned + inprogress + done;
            const progress = total > 0 ? Math.round((done / total) * 100) : 0;

            document.getElementById('metric-planned').textContent = planned;
            document.getElementById('metric-inprogress').textContent = inprogress;
            document.getElementById('metric-done').textContent = done;
            document.getElementById('metric-progress').textContent = progress + '%';

            const sprintTasks = document.getElementById('sprint-tasks');
            const activeTasks = data.cards.filter(c => c.status !== 'backlog');

            if (activeTasks.length === 0) {
                sprintTasks.innerHTML = '<div class="empty-state">' + translations.noSprintTasks + '</div>';
            } else {
                sprintTasks.innerHTML = activeTasks.map(card => `
                    <div class="card" style="margin-bottom: 12px;">
                        <div class="card-title">${escapeHtml(card.title)}</div>
                        ${card.description ? `<div class="card-description">${escapeHtml(card.description)}</div>` : ''}
                        <div class="card-meta">
                            <span class="badge" style="background: ${
                                card.status === 'done' ? 'var(--done)' :
                                card.status === 'inprogress' ? 'var(--inprogress)' :
                                'var(--todo)'
                            }; color: white;">
                                ${card.status === 'done' ? translations.done :
                                  card.status === 'inprogress' ? translations.inProgress :
                                  translations.todo}
                            </span>
                            <span class="card-priority priority-${card.priority}">
                                ${card.priority === 'high' ? translations.high : card.priority === 'medium' ? translations.medium : translations.low}
                            </span>
                        </div>
                    </div>
                `).join('');
            }
        }

        function exportJSON() {
            const exportData = {
                project: {
                    name: document.getElementById('projectName').value,
                    team: document.getElementById('teamName').value
                },
                ...data,
                exportDate: new Date().toISOString()
            };

            const dataStr = JSON.stringify(exportData, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);

            const link = document.createElement('a');
            link.href = url;
            const filename = exportData.project.name ?
                exportData.project.name.replace(/[^a-z0-9]/gi, '_').toLowerCase() :
                'agile_project';
            link.download = `${filename}_${new Date().toISOString().split('T')[0]}.json`;
            link.click();

            URL.revokeObjectURL(url);
        }

        function exportToExcel() {
            const wb = XLSX.utils.book_new();

            const infoData = [
                ['PROJET AGILE'],
                [''],
                ['Projet', document.getElementById('projectName').value],
                ['Equipe', document.getElementById('teamName').value],
                ['Date d\'export', new Date().toLocaleDateString('fr-FR')]
            ];
            const wsInfo = XLSX.utils.aoa_to_sheet(infoData);

            const kanbanData = [
                ['KANBAN BOARD'],
                [''],
                ['Statut', 'Titre', 'Description', 'Priorite']
            ];
            data.cards.forEach(card => {
                kanbanData.push([
                    card.status,
                    card.title,
                    card.description,
                    card.priority
                ]);
            });
            const wsKanban = XLSX.utils.aoa_to_sheet(kanbanData);

            const storiesData = [
                ['USER STORIES'],
                [''],
                ['Role', 'Action', 'Benefice', 'Priorite', 'Points']
            ];
            data.userStories.forEach(story => {
                storiesData.push([
                    story.role,
                    story.action,
                    story.benefit,
                    story.priority,
                    story.points
                ]);
            });
            const wsStories = XLSX.utils.aoa_to_sheet(storiesData);

            XLSX.utils.book_append_sheet(wb, wsInfo, 'Informations');
            XLSX.utils.book_append_sheet(wb, wsKanban, 'Kanban');
            XLSX.utils.book_append_sheet(wb, wsStories, 'User Stories');

            const filename = `agile_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, filename);
        }

        function resetAll() {
            if (confirm(translations.resetConfirm)) {
                data = {
                    cards: [],
                    userStories: [],
                    retrospective: {
                        good: [],
                        improve: [],
                        actions: []
                    },
                    sprint: {
                        number: 1,
                        start: '',
                        end: '',
                        goal: ''
                    }
                };
                document.getElementById('projectName').value = '';
                document.getElementById('teamName').value = '';
                document.getElementById('sprintNumber').value = '1';
                document.getElementById('sprintStart').value = '';
                document.getElementById('sprintEnd').value = '';
                document.getElementById('sprintGoal').value = '';

                scheduleAutoSave();
                renderKanban();
                renderUserStories();
                renderRetrospective();
                updateSprintMetrics();
            }
        }
    </script>
</body>
</html>
