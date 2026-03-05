<?php
/**
 * Interface principale - Questionnaire IA
 * Formulaire de questionnaire configurable pour les participants
 */
require_once __DIR__ . '/config.php';
requireLoginWithSession();

$user = getLoggedUser();
$db = getDB();
$sessionId = validateCurrentSession($db);
if (!$sessionId) { header('Location: login.php'); exit; }

// Initialiser les questions par defaut si necessaire
initDefaultQuestions($sessionId);

// Recuperer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

// Recuperer les questions
$questions = getQuestions($sessionId);

// Recuperer les reponses existantes
$reponses = getReponses($user['id'], $sessionId);
$reponsesByQuestion = [];
foreach ($reponses as $r) {
    $reponsesByQuestion[$r['question_id']] = $r;
}

$totalReponses = count(array_filter($reponsesByQuestion, fn($r) => !empty($r['contenu'])));
$isShared = !empty($reponses) && $reponses[0]['is_shared'] == 1;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionnaire IA - Fiche Participant</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>📋</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
        .radio-option { transition: all 0.2s ease; }
        .radio-option:hover { transform: scale(1.02); }
        .radio-option.selected { box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.4); }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-50 to-blue-100 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-sky-600 to-blue-700 text-white p-4 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-3xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold">📋 Questionnaire IA</h1>
                <p class="text-sky-200 text-sm"><?= h($user['prenom']) ?> <?= h($user['nom']) ?> - <?= h($session['nom'] ?? 'Session') ?></p>
            </div>
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('sky') ?>
                <span class="bg-white/20 px-3 py-1 rounded-full text-sm">
                    <?= $totalReponses ?>/<?= count($questions) ?> reponses
                    <?php if ($isShared): ?>
                    <span class="ml-1 text-green-300">✓</span>
                    <?php endif; ?>
                </span>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg">Formateur</a>
                <?php endif; ?>
                <a href="logout.php" class="bg-red-500/80 hover:bg-red-600 px-4 py-2 rounded-lg">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto p-6">
        <!-- Sujet de la session -->
        <?php if (!empty($session['sujet'])): ?>
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-2">Contexte de la formation</h2>
            <p class="text-gray-700"><?= nl2br(h($session['sujet'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form id="questionnaireForm" class="space-y-6">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <div class="flex items-center gap-3 mb-6">
                    <span class="text-3xl">🤖</span>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Fiche Participant — Carte de mon rapport a l'IA</h2>
                        <p class="text-gray-500 text-sm">Repondez aux questions ci-dessous puis partagez vos reponses</p>
                    </div>
                </div>

                <div class="space-y-8">
                    <?php foreach ($questions as $i => $q):
                        $existingResponse = $reponsesByQuestion[$q['id']]['contenu'] ?? '';
                        $num = $i + 1;
                    ?>
                    <div class="question-block" data-question-id="<?= $q['id'] ?>">
                        <label class="block text-sm font-bold text-gray-800 mb-3">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-sky-100 text-sky-700 rounded-full text-sm mr-2"><?= $num ?></span>
                            <?= h($q['label']) ?>
                            <?php if ($q['obligatoire']): ?>
                            <span class="text-red-500 ml-1">*</span>
                            <?php endif; ?>
                        </label>

                        <?php if ($q['type'] === 'radio'):
                            $options = json_decode($q['options'], true) ?: [];
                        ?>
                        <div class="flex flex-wrap gap-3">
                            <?php foreach ($options as $opt): ?>
                            <label class="radio-option cursor-pointer px-4 py-2 rounded-lg border-2 text-sm font-medium
                                          <?= $existingResponse === $opt ? 'border-sky-500 bg-sky-50 text-sky-700 selected' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-sky-300' ?>">
                                <input type="radio" name="q_<?= $q['id'] ?>" value="<?= h($opt) ?>"
                                       class="sr-only" onchange="selectRadio(this)"
                                       <?= $existingResponse === $opt ? 'checked' : '' ?>>
                                <?= h($opt) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <?php elseif ($q['type'] === 'textarea'): ?>
                        <textarea name="q_<?= $q['id'] ?>" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-transparent text-sm"
                                  placeholder="Votre reponse..."><?= h($existingResponse) ?></textarea>

                        <?php else: ?>
                        <input type="text" name="q_<?= $q['id'] ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-transparent text-sm"
                               placeholder="Votre reponse..."
                               value="<?= h($existingResponse) ?>">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="flex flex-col sm:flex-row gap-4">
                <button type="button" onclick="saveResponses(false)"
                        class="flex-1 bg-white hover:bg-gray-50 text-gray-700 border-2 border-gray-300 px-6 py-3 rounded-xl font-medium flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    Enregistrer (brouillon)
                </button>
                <button type="button" onclick="saveResponses(true)"
                        class="flex-1 bg-sky-600 hover:bg-sky-700 text-white px-6 py-3 rounded-xl font-medium flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Enregistrer et partager
                </button>
            </div>

            <?php if ($isShared): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
                <p class="text-green-700 text-sm font-medium">✓ Vos reponses ont ete partagees avec le formateur</p>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        function selectRadio(radio) {
            // Update visual state of all options in the same group
            const name = radio.name;
            document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                const label = r.closest('.radio-option');
                if (r.checked) {
                    label.classList.add('border-sky-500', 'bg-sky-50', 'text-sky-700', 'selected');
                    label.classList.remove('border-gray-200', 'bg-gray-50', 'text-gray-600');
                } else {
                    label.classList.remove('border-sky-500', 'bg-sky-50', 'text-sky-700', 'selected');
                    label.classList.add('border-gray-200', 'bg-gray-50', 'text-gray-600');
                }
            });
        }

        async function saveResponses(share) {
            const responses = [];
            document.querySelectorAll('.question-block').forEach(block => {
                const qId = block.dataset.questionId;
                const radio = block.querySelector('input[type="radio"]:checked');
                const textarea = block.querySelector('textarea');
                const textInput = block.querySelector('input[type="text"]');

                let value = '';
                if (radio) value = radio.value;
                else if (textarea) value = textarea.value.trim();
                else if (textInput) value = textInput.value.trim();

                responses.push({ question_id: parseInt(qId), contenu: value });
            });

            try {
                const response = await fetch('api/save-responses.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ responses: responses, share: share })
                });
                const result = await response.json();

                if (result.success) {
                    if (share) {
                        alert('Vos reponses ont ete enregistrees et partagees avec le formateur !');
                    } else {
                        alert('Vos reponses ont ete enregistrees en brouillon.');
                    }
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Erreur inconnue'));
                }
            } catch (e) {
                alert('Erreur de connexion');
                console.error(e);
            }
        }
    </script>
    <?= renderLanguageScript() ?>
</body>
</html>
