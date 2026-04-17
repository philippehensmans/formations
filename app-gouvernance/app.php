<?php
/**
 * Application principale - Évaluateur de Normes de Gouvernance
 */
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user = getLoggedUser();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$sessionId = validateCurrentSession($db);
if (!$sessionId) { header('Location: login.php'); exit; }
$sessionNom = $_SESSION['current_session_nom'] ?? '';

// Charger l'évaluation
$stmt = $db->prepare("SELECT * FROM evaluations WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$evaluation = $stmt->fetch();

if (!$evaluation) {
    $stmt = $db->prepare("INSERT INTO evaluations (user_id, session_id, responses) VALUES (?, ?, '{}')");
    $stmt->execute([$user['id'], $sessionId]);
    $stmt = $db->prepare("SELECT * FROM evaluations WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$user['id'], $sessionId]);
    $evaluation = $stmt->fetch();
}

$responses = json_decode($evaluation['responses'] ?? '{}', true) ?: [];
$isSubmitted = ($evaluation['is_submitted'] ?? 0) == 1;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évaluateur de Gouvernance - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' fill='%233b82f6' rx='6'/><text x='16' y='22' font-size='18' text-anchor='middle' fill='white' font-family='Arial'>G</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background-color: #f5f5f5; line-height: 1.6; }
        .app-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .app-header { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; text-align: center; }
        .app-header h1 { font-size: 2rem; margin-bottom: 8px; }
        .controls { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; transition: all 0.3s; font-size: 0.95rem; }
        .btn-primary { background-color: #3b82f6; color: white; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-primary.active { background-color: #1d4ed8; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); }
        .btn-success { background-color: #10b981; color: white; }
        .btn-success:hover { background-color: #059669; }
        .btn-warning { background-color: #f59e0b; color: white; }
        .btn-warning:hover { background-color: #d97706; }
        .btn-danger { background-color: #ef4444; color: white; }
        .btn-danger:hover { background-color: #dc2626; }
        .section { background: white; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .section-header { padding: 20px; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .section-header:hover { background-color: #f1f5f9; }
        .section-header h2 { font-size: 1.2rem; color: #1f2937; flex: 1; }
        .section-content { display: none; padding: 20px; }
        .section-content.expanded { display: block; }
        .section-toggle { font-size: 1.2rem; color: #6b7280; transition: transform 0.2s; }
        .section-toggle.expanded { transform: rotate(180deg); }
        .subsection { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
        .subsection:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .subsection h3 { color: #374151; margin-bottom: 15px; font-size: 1.05rem; }
        .question { background-color: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 12px; }
        .question p { margin-bottom: 10px; color: #374151; font-size: 0.92rem; }
        .options { display: flex; gap: 10px; flex-wrap: wrap; }
        .option { padding: 8px 18px; border: 1px solid #d1d5db; background: white; border-radius: 5px; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; user-select: none; }
        .option:hover { background-color: #f3f4f6; }
        .option.selected { background-color: #3b82f6; color: white; border-color: #3b82f6; }
        .score-display { font-weight: bold; font-size: 1.05rem; white-space: nowrap; }
        .score-good { color: #10b981; }
        .score-medium { color: #f59e0b; }
        .score-poor { color: #ef4444; }
        .score-empty { color: #9ca3af; }
        .dashboard { display: none; }
        .dashboard.active { display: block; }
        .score-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .score-card h3 { font-size: 1rem; color: #374151; margin-bottom: 10px; }
        .score-card .score-display { font-size: 1.6rem; }
        .overall-score { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; text-align: center; }
        .overall-score h2 { font-size: 1.5rem; margin-bottom: 10px; }
        .overall-score .score-display { color: white !important; font-size: 2.5rem; }
        .score-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .notation { background: white; padding: 20px; border-radius: 10px; margin-top: 30px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .notation h3 { margin-bottom: 15px; color: #374151; }
        .notation p { margin: 5px 0; color: #6b7280; font-size: 0.9rem; }
        .user-bar { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); border-radius: 10px; padding: 12px 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-top: 10px; }
        .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #3b82f6, #8b5cf6); transition: width 0.3s; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Barre utilisateur -->
        <div class="user-bar no-print">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div>
                    <span class="font-medium text-gray-800"><?= h($user['prenom']) ?> <?= h($user['nom']) ?></span>
                    <span class="text-gray-500 text-sm ml-2"><?= h($sessionNom) ?></span>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <?= renderLanguageSelector('text-sm bg-white text-gray-800 px-2 py-1 rounded border border-gray-300') ?>
                    <button onclick="manualSave()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition text-sm">
                        Sauvegarder
                    </button>
                    <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-gray-200">
                        <?= $isSubmitted ? 'Soumis' : 'Brouillon' ?>
                    </span>
                    <span id="completion" class="text-sm text-gray-600">Complété: <strong>0%</strong></span>
                    <?php if (isFormateur()): ?>
                    <a href="formateur.php" class="text-sm bg-purple-600 hover:bg-purple-500 text-white px-3 py-1 rounded transition">Formateur</a>
                    <?php endif; ?>
                    <?= renderHomeLink() ?>
                    <a href="logout.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition">Déconnexion</a>
                </div>
            </div>
        </div>

        <div class="app-header">
            <h1>Évaluateur de Normes de Gouvernance</h1>
            <p>Outil d'auto-évaluation pour organisations</p>
        </div>

        <div class="controls no-print">
            <button id="btn-assessment" class="btn btn-primary active" onclick="showAssessment()">Évaluation</button>
            <button id="btn-dashboard" class="btn btn-primary" onclick="showDashboard()">Tableau de bord</button>
            <button class="btn btn-success" onclick="exportResults()">Exporter résultats</button>
            <?php if (!$isSubmitted): ?>
            <button id="btn-submit" class="btn btn-warning" onclick="submitEvaluation()">Soumettre l'évaluation</button>
            <?php endif; ?>
            <button class="btn btn-danger" onclick="resetAssessment()">Recommencer</button>
        </div>

        <div id="assessment" class="assessment"></div>

        <div id="dashboard" class="dashboard">
            <div class="overall-score">
                <h2>Score Global</h2>
                <div id="overall-score-display" class="score-display">0.0/3.0</div>
                <div class="progress-bar" style="background: rgba(255,255,255,0.2); max-width: 400px; margin: 15px auto 0;">
                    <div id="overall-progress" class="progress-bar-fill" style="width: 0%; background: white;"></div>
                </div>
                <p id="overall-questions-count" class="mt-3 text-sm opacity-90">0 question(s) répondue(s)</p>
            </div>
            <div id="section-scores" class="score-grid"></div>
        </div>

        <div class="notation">
            <h3>Système de notation</h3>
            <p><strong>1</strong> = Éléments développés au minimum / peu développés</p>
            <p><strong>2</strong> = Plans et processus appliqués mais de façon inégale</p>
            <p><strong>3</strong> = Processus correctement appliqués et passés en revue</p>
        </div>
    </div>

    <script>
        let responses = <?= json_encode((object)$responses) ?>;
        let isSubmitted = <?= $isSubmitted ? 'true' : 'false' ?>;
        let autoSaveTimeout = null;

        const sections = <?= json_encode(require __DIR__ . '/sections.php', JSON_UNESCAPED_UNICODE) ?>;

        function generateAssessment() {
            const assessmentDiv = document.getElementById('assessment');
            assessmentDiv.innerHTML = '';

            sections.forEach(section => {
                const sectionDiv = document.createElement('div');
                sectionDiv.className = 'section';

                let subsectionsHtml = section.subsections.map(subsection => {
                    const questionsHtml = subsection.questions.map(question => {
                        let optionsHtml = '';
                        if (question.type === 'scale') {
                            optionsHtml = [1,2,3].map(value => `
                                <div class="option" onclick="selectOption('${question.id}', ${value})" data-question="${question.id}" data-value="${value}">${value}</div>
                            `).join('');
                        } else {
                            optionsHtml = ['yes','no'].map(value => `
                                <div class="option" onclick="selectOption('${question.id}', '${value}')" data-question="${question.id}" data-value="${value}">${value === 'yes' ? 'Oui' : 'Non'}</div>
                            `).join('');
                        }
                        return `
                            <div class="question">
                                <p>${escapeHtml(question.text)}</p>
                                <div class="options">${optionsHtml}</div>
                            </div>
                        `;
                    }).join('');

                    return `
                        <div class="subsection">
                            <h3>${escapeHtml(subsection.title)}</h3>
                            ${questionsHtml}
                        </div>
                    `;
                }).join('');

                sectionDiv.innerHTML = `
                    <div class="section-header" onclick="toggleSection('${section.id}')">
                        <h2>${escapeHtml(section.title)}</h2>
                        <span class="score-display score-empty" id="score-${section.id}">—/3.0</span>
                        <span class="section-toggle" id="toggle-${section.id}">▼</span>
                    </div>
                    <div class="section-content" id="content-${section.id}">
                        ${subsectionsHtml}
                    </div>
                `;
                assessmentDiv.appendChild(sectionDiv);
            });

            // Pré-remplir les réponses
            Object.keys(responses).forEach(qId => {
                const val = responses[qId];
                const el = document.querySelector(`[data-question="${qId}"][data-value="${val}"]`);
                if (el) el.classList.add('selected');
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function toggleSection(sectionId) {
            const content = document.getElementById(`content-${sectionId}`);
            const toggle = document.getElementById(`toggle-${sectionId}`);
            content.classList.toggle('expanded');
            toggle.classList.toggle('expanded');
        }

        function selectOption(questionId, value) {
            if (isSubmitted) {
                alert('L\'évaluation a été soumise. Vous ne pouvez plus la modifier.');
                return;
            }
            responses[questionId] = value;
            document.querySelectorAll(`[data-question="${questionId}"]`).forEach(o => o.classList.remove('selected'));
            const sel = document.querySelector(`[data-question="${questionId}"][data-value="${value}"]`);
            if (sel) sel.classList.add('selected');
            updateScores();
            scheduleAutoSave();
        }

        function questionValue(questionId, type) {
            const r = responses[questionId];
            if (r === undefined) return null;
            if (type === 'scale') return parseInt(r);
            return r === 'yes' ? 3 : 1;
        }

        function calculateSectionScore(section) {
            let totalQuestions = 0;
            let totalScore = 0;
            section.subsections.forEach(sub => {
                sub.questions.forEach(q => {
                    const v = questionValue(q.id, q.type);
                    if (v !== null) {
                        totalQuestions++;
                        totalScore += v;
                    }
                });
            });
            return { score: totalQuestions > 0 ? (totalScore / totalQuestions) : null, count: totalQuestions };
        }

        function calculateOverallScore() {
            let totalQuestions = 0;
            let totalScore = 0;
            let totalPossible = 0;
            sections.forEach(section => {
                section.subsections.forEach(sub => {
                    sub.questions.forEach(q => {
                        totalPossible++;
                        const v = questionValue(q.id, q.type);
                        if (v !== null) {
                            totalQuestions++;
                            totalScore += v;
                        }
                    });
                });
            });
            return {
                score: totalQuestions > 0 ? (totalScore / totalQuestions) : null,
                count: totalQuestions,
                total: totalPossible
            };
        }

        function getScoreClass(score) {
            if (score === null) return 'score-empty';
            if (score >= 2.5) return 'score-good';
            if (score >= 1.5) return 'score-medium';
            return 'score-poor';
        }

        function formatScore(score) {
            return score === null ? '—/3.0' : `${score.toFixed(1)}/3.0`;
        }

        function updateScores() {
            sections.forEach(section => {
                const { score } = calculateSectionScore(section);
                const el = document.getElementById(`score-${section.id}`);
                if (el) {
                    el.textContent = formatScore(score);
                    el.className = `score-display ${getScoreClass(score)}`;
                }
            });

            const { score, count, total } = calculateOverallScore();
            const overallEl = document.getElementById('overall-score-display');
            if (overallEl) {
                overallEl.textContent = formatScore(score);
            }
            const progressEl = document.getElementById('overall-progress');
            if (progressEl) {
                progressEl.style.width = `${total > 0 ? (count / total * 100) : 0}%`;
            }
            const countEl = document.getElementById('overall-questions-count');
            if (countEl) {
                countEl.textContent = `${count}/${total} question(s) répondue(s)`;
            }

            const completionEl = document.getElementById('completion');
            if (completionEl) {
                const pct = total > 0 ? Math.round(count / total * 100) : 0;
                completionEl.innerHTML = `Complété: <strong>${pct}%</strong>`;
            }
        }

        function showAssessment() {
            document.getElementById('assessment').style.display = 'block';
            document.getElementById('dashboard').classList.remove('active');
            document.getElementById('btn-assessment').classList.add('active');
            document.getElementById('btn-dashboard').classList.remove('active');
        }

        function showDashboard() {
            document.getElementById('assessment').style.display = 'none';
            document.getElementById('dashboard').classList.add('active');
            document.getElementById('btn-assessment').classList.remove('active');
            document.getElementById('btn-dashboard').classList.add('active');

            const sectionScoresDiv = document.getElementById('section-scores');
            sectionScoresDiv.innerHTML = '';
            sections.forEach(section => {
                const { score } = calculateSectionScore(section);
                const card = document.createElement('div');
                card.className = 'score-card';
                card.innerHTML = `
                    <h3>${escapeHtml(section.title)}</h3>
                    <div class="score-display ${getScoreClass(score)}">${formatScore(score)}</div>
                `;
                sectionScoresDiv.appendChild(card);
            });
        }

        function exportResults() {
            const { score: overall } = calculateOverallScore();
            let text = `ÉVALUATION DE NORMES DE GOUVERNANCE\n`;
            text += `Participant: <?= addslashes(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?>\n`;
            text += `Session: <?= addslashes($sessionNom) ?>\n`;
            text += `Date d'évaluation: ${new Date().toLocaleDateString('fr-FR')}\n`;
            text += `Score global: ${formatScore(overall)}\n\n`;
            text += `${'='.repeat(60)}\n\n`;

            sections.forEach(section => {
                const { score } = calculateSectionScore(section);
                text += `${section.title.toUpperCase()}\n`;
                text += `Score: ${formatScore(score)}\n`;
                text += `${'-'.repeat(40)}\n\n`;
                section.subsections.forEach(sub => {
                    text += `${sub.title}\n\n`;
                    sub.questions.forEach((q, i) => {
                        text += `${i + 1}. ${q.text}\n`;
                        const r = responses[q.id];
                        let readable = 'Non répondu';
                        if (r !== undefined) {
                            if (q.type === 'scale') readable = `${r}/3`;
                            else readable = r === 'yes' ? 'Oui' : 'Non';
                        }
                        text += `   Réponse: ${readable}\n\n`;
                    });
                });
                text += `\n`;
            });

            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `evaluation-gouvernance-${new Date().toISOString().split('T')[0]}.txt`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function resetAssessment() {
            if (isSubmitted) {
                alert('L\'évaluation a été soumise. Impossible de recommencer.');
                return;
            }
            if (!confirm('Êtes-vous sûr de vouloir recommencer ? Toutes vos réponses seront perdues.')) return;
            responses = {};
            document.querySelectorAll('.option.selected').forEach(o => o.classList.remove('selected'));
            updateScores();
            showAssessment();
            saveData();
        }

        function submitEvaluation() {
            const { count, total } = calculateOverallScore();
            if (count < total) {
                if (!confirm(`Vous avez répondu à ${count}/${total} questions. Soumettre l'évaluation quand même ?`)) return;
            } else {
                if (!confirm('Êtes-vous sûr de vouloir soumettre l\'évaluation ? Vous ne pourrez plus la modifier.')) return;
            }

            fetch('api/submit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ responses })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    isSubmitted = true;
                    document.getElementById('saveStatus').textContent = 'Soumis';
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                    const btn = document.getElementById('btn-submit');
                    if (btn) btn.style.display = 'none';
                    alert('Évaluation soumise avec succès !');
                } else {
                    alert('Erreur: ' + (data.error || 'Impossible de soumettre'));
                }
            })
            .catch(e => alert('Erreur réseau: ' + e.message));
        }

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(saveData, 1500);
        }

        function saveData() {
            if (isSubmitted) return;
            const status = document.getElementById('saveStatus');
            status.textContent = 'Sauvegarde...';
            status.className = 'text-sm px-3 py-1 rounded-full bg-blue-200 text-blue-800';

            fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ responses })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    status.textContent = 'Enregistré';
                    status.className = 'text-sm px-3 py-1 rounded-full bg-green-200 text-green-800';
                    setTimeout(() => {
                        if (!isSubmitted) {
                            status.textContent = 'Brouillon';
                            status.className = 'text-sm px-3 py-1 rounded-full bg-gray-200';
                        }
                    }, 2000);
                } else {
                    status.textContent = 'Erreur';
                    status.className = 'text-sm px-3 py-1 rounded-full bg-red-200 text-red-800';
                }
            })
            .catch(() => {
                status.textContent = 'Erreur réseau';
                status.className = 'text-sm px-3 py-1 rounded-full bg-red-200 text-red-800';
            });
        }

        function manualSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            saveData();
        }

        document.addEventListener('DOMContentLoaded', function() {
            generateAssessment();
            updateScores();
        });
    </script>
</body>
</html>
