<?php
/**
 * Edition des questions - Questionnaire IA
 * Permet au formateur de modifier les questions du questionnaire
 */
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isFormateur()) {
    header('Location: formateur.php');
    exit;
}

$appKey = 'app-questionnaire-ia';

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();

if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse a cette session.");
}

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: formateur.php');
    exit;
}

// Initialiser les questions par defaut si necessaire
initDefaultQuestions($sessionId);

$questions = getQuestions($sessionId);

// Compter les reponses existantes
$stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM reponses WHERE session_id = ?");
$stmt->execute([$sessionId]);
$existingResponses = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le questionnaire - <?= h($session['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>📋</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-sky-50 to-blue-100 min-h-screen">
    <header class="bg-gradient-to-r from-sky-600 to-blue-700 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold">📋 Modifier le questionnaire</h1>
                    <p class="text-sky-200 text-sm"><?= h($session['nom']) ?> - <?= $session['code'] ?></p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="session-view.php?id=<?= $sessionId ?>" class="bg-sky-500 hover:bg-sky-400 px-3 py-1 rounded text-sm">Vue Session</a>
                    <a href="formateur.php?session=<?= $sessionId ?>" class="bg-sky-500 hover:bg-sky-400 px-3 py-1 rounded text-sm">Retour</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8">
        <?php if ($existingResponses > 0): ?>
        <div class="bg-amber-50 border border-amber-300 rounded-xl p-4 mb-6">
            <p class="text-amber-800 text-sm">
                <strong>Attention:</strong> <?= $existingResponses ?> participant(s) ont deja repondu.
                Modifier les questions supprimera toutes les reponses existantes.
            </p>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-bold text-gray-800">Questions du questionnaire</h2>
                <button onclick="addQuestion()" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Ajouter une question
                </button>
            </div>

            <div id="questionsList" class="space-y-4">
                <!-- Les questions seront generees par JavaScript -->
            </div>

            <div class="mt-8 flex justify-end gap-4">
                <a href="session-view.php?id=<?= $sessionId ?>" class="px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-xl font-medium">Annuler</a>
                <button onclick="saveQuestions()" class="px-6 py-3 bg-sky-600 hover:bg-sky-700 text-white rounded-xl font-medium flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Enregistrer les questions
                </button>
            </div>
        </div>
    </main>

    <script>
        const sessionId = <?= $sessionId ?>;
        const existingResponses = <?= $existingResponses ?>;

        // Charger les questions existantes
        let questions = <?= json_encode(array_map(function($q) {
            return [
                'type' => $q['type'],
                'label' => $q['label'],
                'options' => $q['type'] === 'radio' ? (json_decode($q['options'], true) ?: []) : [],
                'obligatoire' => (bool)$q['obligatoire']
            ];
        }, $questions)) ?>;

        function renderQuestions() {
            const container = document.getElementById('questionsList');
            container.innerHTML = '';

            questions.forEach((q, index) => {
                const div = document.createElement('div');
                div.className = 'bg-gray-50 rounded-xl p-5 border-2 border-gray-200';
                div.innerHTML = `
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-8 h-8 bg-sky-100 text-sky-700 rounded-full font-bold text-sm">${index + 1}</span>
                            <select onchange="changeType(${index}, this.value)"
                                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 bg-white">
                                <option value="radio" ${q.type === 'radio' ? 'selected' : ''}>Choix unique</option>
                                <option value="text" ${q.type === 'text' ? 'selected' : ''}>Texte court</option>
                                <option value="textarea" ${q.type === 'textarea' ? 'selected' : ''}>Texte long</option>
                            </select>
                            <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer">
                                <input type="checkbox" ${q.obligatoire ? 'checked' : ''} onchange="toggleRequired(${index}, this.checked)">
                                Obligatoire
                            </label>
                        </div>
                        <div class="flex gap-2">
                            ${index > 0 ? `<button onclick="moveQuestion(${index}, -1)" class="p-1.5 hover:bg-gray-200 rounded text-gray-500" title="Monter"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></button>` : ''}
                            ${index < questions.length - 1 ? `<button onclick="moveQuestion(${index}, 1)" class="p-1.5 hover:bg-gray-200 rounded text-gray-500" title="Descendre"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>` : ''}
                            <button onclick="removeQuestion(${index})" class="p-1.5 hover:bg-red-100 rounded text-red-500" title="Supprimer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <input type="text" value="${escapeHtml(q.label)}" onchange="updateLabel(${index}, this.value)"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-transparent text-sm"
                               placeholder="Intitule de la question">
                    </div>
                    ${q.type === 'radio' ? renderRadioOptions(index, q.options) : ''}
                `;
                container.appendChild(div);
            });
        }

        function renderRadioOptions(qIndex, options) {
            let html = '<div class="pl-11 space-y-2">';
            html += '<div class="text-xs text-gray-500 font-medium mb-1">Options de reponse :</div>';
            options.forEach((opt, optIndex) => {
                html += `
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 border-2 border-gray-300 rounded-full shrink-0"></span>
                        <input type="text" value="${escapeHtml(opt)}" onchange="updateOption(${qIndex}, ${optIndex}, this.value)"
                               class="flex-1 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-sky-500">
                        <button onclick="removeOption(${qIndex}, ${optIndex})" class="p-1 hover:bg-red-100 rounded text-red-400 text-xs">✕</button>
                    </div>`;
            });
            html += `<button onclick="addOption(${qIndex})" class="text-sky-600 hover:text-sky-800 text-sm flex items-center gap-1 mt-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Ajouter une option
                     </button>`;
            html += '</div>';
            return html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/"/g, '&quot;');
        }

        function addQuestion() {
            questions.push({ type: 'text', label: '', options: [], obligatoire: false });
            renderQuestions();
            // Scroll to bottom and focus
            const items = document.querySelectorAll('#questionsList > div');
            const last = items[items.length - 1];
            last.scrollIntoView({ behavior: 'smooth' });
            setTimeout(() => last.querySelector('input[type="text"]')?.focus(), 300);
        }

        function removeQuestion(index) {
            if (questions.length <= 1) { alert('Le questionnaire doit contenir au moins une question.'); return; }
            if (!confirm('Supprimer cette question ?')) return;
            questions.splice(index, 1);
            renderQuestions();
        }

        function moveQuestion(index, direction) {
            const newIndex = index + direction;
            [questions[index], questions[newIndex]] = [questions[newIndex], questions[index]];
            renderQuestions();
        }

        function changeType(index, type) {
            questions[index].type = type;
            if (type === 'radio' && questions[index].options.length === 0) {
                questions[index].options = ['Option 1', 'Option 2'];
            }
            renderQuestions();
        }

        function updateLabel(index, value) {
            questions[index].label = value;
        }

        function toggleRequired(index, checked) {
            questions[index].obligatoire = checked;
        }

        function updateOption(qIndex, optIndex, value) {
            questions[qIndex].options[optIndex] = value;
        }

        function addOption(qIndex) {
            questions[qIndex].options.push('Nouvelle option');
            renderQuestions();
        }

        function removeOption(qIndex, optIndex) {
            if (questions[qIndex].options.length <= 2) { alert('Une question a choix unique doit avoir au moins 2 options.'); return; }
            questions[qIndex].options.splice(optIndex, 1);
            renderQuestions();
        }

        async function saveQuestions() {
            // Validation
            for (let i = 0; i < questions.length; i++) {
                if (!questions[i].label.trim()) {
                    alert(`La question ${i + 1} n'a pas d'intitule.`);
                    return;
                }
                if (questions[i].type === 'radio' && questions[i].options.length < 2) {
                    alert(`La question ${i + 1} (choix unique) doit avoir au moins 2 options.`);
                    return;
                }
            }

            if (existingResponses > 0) {
                if (!confirm(`Attention : ${existingResponses} participant(s) ont deja repondu. Sauvegarder les questions supprimera toutes les reponses existantes. Continuer ?`)) {
                    return;
                }
            }

            try {
                const response = await fetch('api/save-questions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_id: sessionId, questions: questions })
                });
                const result = await response.json();

                if (result.success) {
                    alert('Questions enregistrees avec succes !');
                    window.location.href = 'session-view.php?id=' + sessionId;
                } else {
                    alert('Erreur: ' + (result.error || 'Erreur inconnue'));
                }
            } catch (e) {
                alert('Erreur de connexion');
                console.error(e);
            }
        }

        // Rendu initial
        renderQuestions();
    </script>
</body>
</html>
