<?php
require_once __DIR__ . '/config.php';
requireLogin();

// Verifier qu'une session est selectionnee
if (!isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$user = getLoggedUser();
$sessionId = $_SESSION['current_session_id'];
$db = getDB();

// Récupérer le scénario actif
$scenario = getActiveScenario($db, $sessionId);
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌱 <?= t('carbone.title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .emoji-btn {
            font-size: 1.8rem;
            cursor: pointer;
            opacity: 0.3;
            transition: all 0.2s;
            filter: grayscale(100%);
        }
        .emoji-btn:hover { opacity: 0.7; transform: scale(1.1); }
        .emoji-btn.selected {
            opacity: 1;
            filter: grayscale(0%);
            transform: scale(1.1);
        }
        .option-card {
            transition: all 0.3s;
        }
        .option-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }
        .result-bar {
            height: 24px;
            border-radius: 12px;
            transition: width 0.5s ease;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .winner {
            animation: winner 0.5s ease;
        }
        @keyframes winner {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-800 to-green-600 min-h-screen p-4">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-green-800">🌱 <?= t('carbone.title') ?></h1>
                    <p class="text-gray-600"><?= t('carbone.subtitle') ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?= renderLanguageSelector('lang-select') ?>
                    <span class="text-sm text-gray-500">
                        <span id="syncIndicator" class="pulse">🔄</span>
                        <span id="voterCount">0</span> <?= t('carbone.participant') ?>
                    </span>
                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                        <?= htmlspecialchars($user['prenom'] ?? $user['username']) ?>
                    </span>
                    <?php if (isFormateur()): ?>
                    <a href="formateur.php" class="text-green-600 hover:text-green-800 text-sm font-medium"><?= t('trainer.title') ?></a>
                    <?php endif; ?>
                    <a href="logout.php" class="text-gray-500 hover:text-red-500 text-sm"><?= t('auth.logout') ?></a>
                </div>
            </div>
        </div>

        <?php if (!$scenario): ?>
        <!-- Pas de scénario actif -->
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <div class="text-6xl mb-4">⏳</div>
            <h2 class="text-xl font-bold text-gray-700 mb-2"><?= t('carbone.waiting_trainer') ?></h2>
            <p class="text-gray-500"><?= t('carbone.no_scenario') ?></p>
            <p class="text-gray-400 text-sm mt-4"><?= t('carbone.auto_refresh') ?></p>
        </div>
        <?php else: ?>
        <!-- Scénario actif -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-green-800 mb-2"><?= htmlspecialchars($scenario['title']) ?></h2>
            <p class="text-gray-600 bg-green-50 p-4 rounded-lg border-l-4 border-green-500">
                <?= nl2br(htmlspecialchars($scenario['description'])) ?>
            </p>
        </div>

        <!-- Légende des critères -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
            <h3 class="font-bold text-yellow-800 mb-2">📋 <?= t('carbone.how_to_vote') ?></h3>
            <div class="grid md:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="font-medium">🌍 <?= t('carbone.env_impact') ?></span><br>
                    <span class="text-gray-600"><?= t('carbone.env_desc') ?></span>
                </div>
                <div>
                    <span class="font-medium">⭐ <?= t('carbone.quality') ?></span><br>
                    <span class="text-gray-600"><?= t('carbone.quality_desc') ?></span>
                </div>
                <div>
                    <span class="font-medium">⏱️ <?= t('carbone.time_gain') ?></span><br>
                    <span class="text-gray-600"><?= t('carbone.time_desc') ?></span>
                </div>
            </div>
        </div>

        <!-- Les 3 options -->
        <div class="grid md:grid-cols-3 gap-6 mb-6">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <div class="option-card bg-white rounded-xl shadow-lg overflow-hidden" data-option="<?= $i ?>">
                <div class="bg-gradient-to-r <?= $i == 1 ? 'from-blue-500 to-blue-600' : ($i == 2 ? 'from-purple-500 to-purple-600' : 'from-orange-500 to-orange-600') ?> text-white p-4">
                    <h3 class="font-bold text-lg"><?= htmlspecialchars($scenario["option{$i}_name"]) ?></h3>
                </div>
                <div class="p-4">
                    <p class="text-gray-600 text-sm mb-4 min-h-[60px]">
                        <?= htmlspecialchars($scenario["option{$i}_desc"]) ?>
                    </p>

                    <!-- Vote Impact -->
                    <div class="mb-4">
                        <label class="text-sm font-medium text-gray-700 block mb-2">🌍 <?= t('carbone.env_impact') ?></label>
                        <div class="flex gap-2" data-criteria="impact" data-max="3">
                            <span class="emoji-btn" data-value="1">🌍</span>
                            <span class="emoji-btn" data-value="2">🌍</span>
                            <span class="emoji-btn" data-value="3">🌍</span>
                        </div>
                    </div>

                    <!-- Vote Qualité -->
                    <div class="mb-4">
                        <label class="text-sm font-medium text-gray-700 block mb-2">⭐ <?= t('carbone.quality') ?></label>
                        <div class="flex gap-1" data-criteria="qualite" data-max="5">
                            <span class="emoji-btn" data-value="1">⭐</span>
                            <span class="emoji-btn" data-value="2">⭐</span>
                            <span class="emoji-btn" data-value="3">⭐</span>
                            <span class="emoji-btn" data-value="4">⭐</span>
                            <span class="emoji-btn" data-value="5">⭐</span>
                        </div>
                    </div>

                    <!-- Vote Temps -->
                    <div class="mb-2">
                        <label class="text-sm font-medium text-gray-700 block mb-2">⏱️ <?= t('carbone.time_gain') ?></label>
                        <div class="flex gap-2" data-criteria="temps" data-max="3">
                            <span class="emoji-btn" data-value="1">⏱️</span>
                            <span class="emoji-btn" data-value="2">⏱️</span>
                            <span class="emoji-btn" data-value="3">⏱️</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Résultats agrégés -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="font-bold text-green-800 text-lg mb-4">📊 <?= t('carbone.group_results') ?></h3>

            <div class="grid md:grid-cols-3 gap-6" id="resultsGrid">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="border rounded-lg p-4" id="result-<?= $i ?>">
                    <h4 class="font-medium text-gray-800 mb-3"><?= htmlspecialchars($scenario["option{$i}_name"]) ?></h4>

                    <div class="space-y-2 text-sm">
                        <div>
                            <div class="flex justify-between mb-1">
                                <span>🌍 <?= t('carbone.impact') ?></span>
                                <span id="avg-impact-<?= $i ?>">-</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="result-bar bg-red-400 h-2 rounded-full" id="bar-impact-<?= $i ?>" style="width: 0%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between mb-1">
                                <span>⭐ <?= t('carbone.quality_short') ?></span>
                                <span id="avg-qualite-<?= $i ?>">-</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="result-bar bg-yellow-400 h-2 rounded-full" id="bar-qualite-<?= $i ?>" style="width: 0%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between mb-1">
                                <span>⏱️ <?= t('carbone.time') ?></span>
                                <span id="avg-temps-<?= $i ?>">-</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="result-bar bg-blue-400 h-2 rounded-full" id="bar-temps-<?= $i ?>" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t">
                        <div class="flex justify-between items-center">
                            <span class="font-medium"><?= t('carbone.global_score') ?></span>
                            <span class="text-2xl font-bold text-green-600" id="score-<?= $i ?>">-</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            <span id="voters-<?= $i ?>">0</span> <?= t('carbone.vote') ?>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Recommandation -->
            <div class="mt-6 p-4 bg-green-50 rounded-lg border-2 border-green-200" id="recommendation">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">🏆</span>
                    <div>
                        <div class="font-bold text-green-800"><?= t('carbone.group_recommendation') ?></div>
                        <div class="text-green-700" id="recommendationText"><?= t('carbone.waiting_votes') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?= renderLanguageScript() ?>

    <script>
        const scenarioId = <?= $scenario ? $scenario['id'] : 'null' ?>;
        const participantId = <?= $user['id'] ?>;
        let myVotes = {1: {}, 2: {}, 3: {}};
        let lastUpdate = null;

        // Translations for JavaScript
        const trans = {
            scoreWith: '<?= t('carbone.score_with') ?>',
            waitingVotes: '<?= t('carbone.waiting_votes') ?>'
        };

        // Gestion des clics sur les emojis
        document.querySelectorAll('.option-card').forEach(card => {
            const optionNum = parseInt(card.dataset.option);

            card.querySelectorAll('[data-criteria]').forEach(criteriaDiv => {
                const criteria = criteriaDiv.dataset.criteria;
                const maxValue = parseInt(criteriaDiv.dataset.max);

                criteriaDiv.querySelectorAll('.emoji-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const value = parseInt(btn.dataset.value);

                        // Mettre à jour l'affichage
                        criteriaDiv.querySelectorAll('.emoji-btn').forEach((b, idx) => {
                            if (idx < value) {
                                b.classList.add('selected');
                            } else {
                                b.classList.remove('selected');
                            }
                        });

                        // Enregistrer le vote
                        myVotes[optionNum][criteria] = value;
                        saveVote(optionNum);
                    });
                });
            });
        });

        // Sauvegarder un vote
        async function saveVote(optionNum) {
            if (!scenarioId) return;

            const vote = myVotes[optionNum];

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'vote',
                        scenario_id: scenarioId,
                        option_number: optionNum,
                        impact: vote.impact || 0,
                        qualite: vote.qualite || 0,
                        temps: vote.temps || 0
                    })
                });

                const data = await response.json();
                if (data.success) {
                    updateResults(data.results);
                }
            } catch (err) {
                console.error('Erreur:', err);
            }
        }

        // Mettre à jour l'affichage des résultats
        function updateResults(results) {
            if (!results) return;

            let maxScore = 0;
            let bestOption = 0;
            let totalVoters = 0;

            for (let opt = 1; opt <= 3; opt++) {
                const r = results[opt];
                if (!r) continue;

                totalVoters = Math.max(totalVoters, r.voters);

                // Moyennes
                document.getElementById(`avg-impact-${opt}`).textContent = r.impact > 0 ? r.impact.toFixed(1) : '-';
                document.getElementById(`avg-qualite-${opt}`).textContent = r.qualite > 0 ? r.qualite.toFixed(1) : '-';
                document.getElementById(`avg-temps-${opt}`).textContent = r.temps > 0 ? r.temps.toFixed(1) : '-';

                // Barres
                document.getElementById(`bar-impact-${opt}`).style.width = (r.impact / 3 * 100) + '%';
                document.getElementById(`bar-qualite-${opt}`).style.width = (r.qualite / 5 * 100) + '%';
                document.getElementById(`bar-temps-${opt}`).style.width = (r.temps / 3 * 100) + '%';

                // Score et votants
                document.getElementById(`score-${opt}`).textContent = r.score_global > 0 ? r.score_global : '-';
                document.getElementById(`voters-${opt}`).textContent = r.voters;

                // Trouver le meilleur
                if (r.score_global > maxScore) {
                    maxScore = r.score_global;
                    bestOption = opt;
                }

                // Retirer la mise en évidence
                document.getElementById(`result-${opt}`).classList.remove('border-green-500', 'border-2', 'bg-green-50');
            }

            // Mettre en évidence le meilleur
            if (bestOption > 0) {
                const bestCard = document.getElementById(`result-${bestOption}`);
                bestCard.classList.add('border-green-500', 'border-2', 'bg-green-50', 'winner');

                const optionName = document.querySelector(`.option-card[data-option="${bestOption}"] h3`).textContent;
                document.getElementById('recommendationText').textContent =
                    `${optionName} ${trans.scoreWith} ${maxScore}/100`;
            }

            // Nombre de participants
            document.getElementById('voterCount').textContent = totalVoters;
        }

        // Charger mes votes existants
        async function loadMyVotes() {
            if (!scenarioId) return;

            try {
                const response = await fetch(`api.php?action=my_votes&scenario_id=${scenarioId}`);
                const data = await response.json();

                if (data.votes) {
                    data.votes.forEach(vote => {
                        const optNum = vote.option_number;
                        myVotes[optNum] = {
                            impact: vote.impact,
                            qualite: vote.qualite,
                            temps: vote.temps
                        };

                        // Restaurer l'affichage
                        const card = document.querySelector(`.option-card[data-option="${optNum}"]`);
                        if (card) {
                            ['impact', 'qualite', 'temps'].forEach(criteria => {
                                const val = vote[criteria];
                                if (val > 0) {
                                    const div = card.querySelector(`[data-criteria="${criteria}"]`);
                                    div.querySelectorAll('.emoji-btn').forEach((btn, idx) => {
                                        if (idx < val) btn.classList.add('selected');
                                    });
                                }
                            });
                        }
                    });
                }

                if (data.results) {
                    updateResults(data.results);
                }
            } catch (err) {
                console.error('Erreur:', err);
            }
        }

        // Polling pour synchronisation
        async function poll() {
            if (!scenarioId) {
                // Vérifier si un nouveau scénario est apparu
                location.reload();
                return;
            }

            try {
                const response = await fetch(`api.php?action=poll&scenario_id=${scenarioId}&last_update=${lastUpdate || ''}`);
                const data = await response.json();

                if (data.reload) {
                    location.reload();
                    return;
                }

                if (data.results) {
                    updateResults(data.results);
                    lastUpdate = data.timestamp;
                }

                // Indicateur de sync
                const indicator = document.getElementById('syncIndicator');
                indicator.textContent = '✅';
                setTimeout(() => indicator.textContent = '🔄', 500);

            } catch (err) {
                console.error('Erreur polling:', err);
            }
        }

        // Initialisation
        loadMyVotes();
        setInterval(poll, 3000);
    </script>
</body>
</html>
