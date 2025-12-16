<?php
/**
 * Interface de travail - Analyse PESTEL
 */
require_once 'config/database.php';
requireParticipant();

$db = getDB();
$participant = getCurrentParticipant();

// Charger l'analyse PESTEL
$stmt = $db->prepare("SELECT * FROM analyse_pestel WHERE participant_id = ?");
$stmt->execute([$participant['id']]);
$analyse = $stmt->fetch();

if (!$analyse) {
    $stmt = $db->prepare("INSERT INTO analyse_pestel (participant_id, session_id, pestel_data) VALUES (?, ?, ?)");
    $stmt->execute([$participant['id'], $participant['session_id'], json_encode(getEmptyPestel())]);
    $stmt = $db->prepare("SELECT * FROM analyse_pestel WHERE participant_id = ?");
    $stmt->execute([$participant['id']]);
    $analyse = $stmt->fetch();
}

$pestelData = json_decode($analyse['pestel_data'], true) ?: getEmptyPestel();
$isSubmitted = $analyse['is_submitted'] == 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse PESTEL - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .pestel-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        @media (max-width: 768px) { .pestel-grid { grid-template-columns: 1fr; } }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="p-4 md:p-8">
    <!-- Barre utilisateur -->
    <div class="max-w-6xl mx-auto mb-4 bg-white/90 backdrop-blur rounded-lg shadow-lg p-3 no-print">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium text-gray-800"><?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></span>
                <span class="text-gray-500 text-sm ml-2"><?= sanitize($participant['session_nom']) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="manualSave()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">
                    Sauvegarder
                </button>
                <span id="saveStatus" class="text-sm px-3 py-1 rounded-full bg-gray-200">
                    <?= $isSubmitted ? 'Soumis' : 'Brouillon' ?>
                </span>
                <span id="completion" class="text-sm text-gray-600">Completion: <strong><?= $analyse['completion_percent'] ?>%</strong></span>
                <a href="logout.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto bg-white rounded-lg shadow-2xl p-6 md:p-8">
        <div class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Analyse PESTEL</h1>
            <p class="text-gray-600 italic">Politique - Economique - Socioculturel - Technologique - Environnemental - Legal</p>
        </div>

        <!-- Introduction methodologique -->
        <div class="mb-6 bg-gradient-to-r from-blue-50 via-teal-50 to-green-50 p-6 rounded-lg border-2 border-teal-200 shadow-md">
            <h2 class="text-xl font-bold text-teal-800 mb-3 flex items-center">
                <span class="text-2xl mr-2">📚</span> Qu'est-ce que l'analyse PESTEL ?
            </h2>
            <div class="space-y-4 text-gray-700">
                <p class="leading-relaxed">
                    L'analyse <strong>PESTEL</strong> est un outil strategique qui permet d'analyser les <strong>facteurs macro-environnementaux</strong>
                    externes qui peuvent influencer un projet, une organisation ou un secteur d'activite.
                </p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-red-50 border-l-4 border-red-500 p-3 rounded">
                        <p class="font-semibold text-red-800 mb-1">🏛️ Politique</p>
                        <p class="text-sm text-red-700">Stabilite politique, politiques gouvernementales, reglementations publiques</p>
                    </div>
                    <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded">
                        <p class="font-semibold text-green-800 mb-1">💰 Economique</p>
                        <p class="text-sm text-green-700">Croissance economique, inflation, taux de change, pouvoir d'achat</p>
                    </div>
                    <div class="bg-purple-50 border-l-4 border-purple-500 p-3 rounded">
                        <p class="font-semibold text-purple-800 mb-1">👥 Socioculturel</p>
                        <p class="text-sm text-purple-700">Demographie, valeurs sociales, tendances culturelles, education</p>
                    </div>
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                        <p class="font-semibold text-blue-800 mb-1">🔬 Technologique</p>
                        <p class="text-sm text-blue-700">Innovations, R&D, automatisation, infrastructure technologique</p>
                    </div>
                    <div class="bg-teal-50 border-l-4 border-teal-500 p-3 rounded">
                        <p class="font-semibold text-teal-800 mb-1">🌱 Environnemental</p>
                        <p class="text-sm text-teal-700">Changement climatique, durabilite, ressources naturelles, ecologie</p>
                    </div>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded">
                        <p class="font-semibold text-amber-800 mb-1">⚖️ Legal</p>
                        <p class="text-sm text-amber-700">Lois et reglementations, normes, propriete intellectuelle, droit du travail</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations du projet -->
        <div class="mb-6 bg-gradient-to-r from-purple-50 to-indigo-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                📋 Projet / Organisation / Secteur Analyse
            </label>
            <input type="text" id="nomProjet"
                class="w-full px-4 py-2 border-2 border-purple-200 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                placeholder="Ex: Lancement d'un service de mobilite durable, Secteur de la sante..."
                value="<?= sanitize($analyse['nom_projet']) ?>"
                oninput="scheduleAutoSave()">
        </div>

        <div class="mb-6 bg-gradient-to-r from-indigo-50 to-blue-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                👥 Participants a l'Analyse
            </label>
            <input type="text" id="participantsAnalyse"
                class="w-full px-4 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                placeholder="Noms des participants"
                value="<?= sanitize($analyse['participants_analyse']) ?>"
                oninput="scheduleAutoSave()">
        </div>

        <div class="mb-6 bg-gradient-to-r from-blue-50 to-teal-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                🎯 Zone Geographique / Marche Concerne
            </label>
            <input type="text" id="zone"
                class="w-full px-4 py-2 border-2 border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Ex: Europe, France, Region Bruxelles-Capitale..."
                value="<?= sanitize($analyse['zone']) ?>"
                oninput="scheduleAutoSave()">
        </div>

        <!-- Matrice PESTEL -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">🌍 Analyse des Facteurs PESTEL</h2>

            <div class="pestel-grid">
                <!-- POLITIQUE -->
                <div class="bg-red-50 p-5 rounded-lg border-2 border-red-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-red-800 flex items-center">
                            <span class="text-2xl mr-2">🏛️</span> Politique
                        </h3>
                        <button type="button" onclick="addItem('politique', 'red')"
                            class="no-print bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-red-700 mb-3 italic">Stabilite politique, politiques publiques, relations internationales</p>
                    <div id="politiqueList" class="space-y-2">
                        <?php foreach ($pestelData['politique'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="politique">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-red-300 rounded-md focus:ring-2 focus:ring-red-500 text-sm resize-none"
                                placeholder="Ex: Nouvelle politique de soutien aux energies renouvelables..."
                                oninput="scheduleAutoSave()"><?= sanitize($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-red-700 hover:text-red-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ECONOMIQUE -->
                <div class="bg-green-50 p-5 rounded-lg border-2 border-green-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-green-800 flex items-center">
                            <span class="text-2xl mr-2">💰</span> Economique
                        </h3>
                        <button type="button" onclick="addItem('economique', 'green')"
                            class="no-print bg-green-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-green-700 mb-3 italic">Croissance, inflation, emploi, pouvoir d'achat</p>
                    <div id="economiqueList" class="space-y-2">
                        <?php foreach ($pestelData['economique'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="economique">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-green-300 rounded-md focus:ring-2 focus:ring-green-500 text-sm resize-none"
                                placeholder="Ex: Ralentissement economique reduisant les budgets disponibles..."
                                oninput="scheduleAutoSave()"><?= sanitize($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-green-700 hover:text-green-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SOCIOCULTUREL -->
                <div class="bg-purple-50 p-5 rounded-lg border-2 border-purple-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-purple-800 flex items-center">
                            <span class="text-2xl mr-2">👥</span> Socioculturel
                        </h3>
                        <button type="button" onclick="addItem('socioculturel', 'purple')"
                            class="no-print bg-purple-600 text-white px-3 py-1 rounded-md hover:bg-purple-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-purple-700 mb-3 italic">Demographie, valeurs, modes de vie, education</p>
                    <div id="socioculturelList" class="space-y-2">
                        <?php foreach ($pestelData['socioculturel'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="socioculturel">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-purple-300 rounded-md focus:ring-2 focus:ring-purple-500 text-sm resize-none"
                                placeholder="Ex: Vieillissement de la population augmentant la demande..."
                                oninput="scheduleAutoSave()"><?= sanitize($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-purple-700 hover:text-purple-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- TECHNOLOGIQUE -->
                <div class="bg-blue-50 p-5 rounded-lg border-2 border-blue-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-blue-800 flex items-center">
                            <span class="text-2xl mr-2">🔬</span> Technologique
                        </h3>
                        <button type="button" onclick="addItem('technologique', 'blue')"
                            class="no-print bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-blue-700 mb-3 italic">Innovation, R&D, automatisation, digitalisation</p>
                    <div id="technologiqueList" class="space-y-2">
                        <?php foreach ($pestelData['technologique'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="technologique">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-blue-300 rounded-md focus:ring-2 focus:ring-blue-500 text-sm resize-none"
                                placeholder="Ex: Developpement de l'IA transformant les pratiques..."
                                oninput="scheduleAutoSave()"><?= sanitize($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-blue-700 hover:text-blue-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ENVIRONNEMENTAL -->
                <div class="bg-teal-50 p-5 rounded-lg border-2 border-teal-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-teal-800 flex items-center">
                            <span class="text-2xl mr-2">🌱</span> Environnemental
                        </h3>
                        <button type="button" onclick="addItem('environnemental', 'teal')"
                            class="no-print bg-teal-600 text-white px-3 py-1 rounded-md hover:bg-teal-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-teal-700 mb-3 italic">Climat, durabilite, ressources naturelles, ecologie</p>
                    <div id="environnementalList" class="space-y-2">
                        <?php foreach ($pestelData['environnemental'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="environnemental">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-teal-300 rounded-md focus:ring-2 focus:ring-teal-500 text-sm resize-none"
                                placeholder="Ex: Pression croissante pour reduire l'empreinte carbone..."
                                oninput="scheduleAutoSave()"><?= sanitize($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-teal-700 hover:text-teal-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- LEGAL -->
                <div class="bg-amber-50 p-5 rounded-lg border-2 border-amber-300 shadow-md">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-bold text-amber-800 flex items-center">
                            <span class="text-2xl mr-2">⚖️</span> Legal
                        </h3>
                        <button type="button" onclick="addItem('legal', 'amber')"
                            class="no-print bg-amber-600 text-white px-3 py-1 rounded-md hover:bg-amber-700 transition text-sm">➕</button>
                    </div>
                    <p class="text-sm text-amber-700 mb-3 italic">Lois, reglementations, normes, propriete intellectuelle</p>
                    <div id="legalList" class="space-y-2">
                        <?php foreach ($pestelData['legal'] ?? [''] as $item): ?>
                        <div class="pestel-item flex gap-2" data-category="legal">
                            <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-amber-300 rounded-md focus:ring-2 focus:ring-amber-500 text-sm resize-none"
                                placeholder="Ex: Nouvelle reglementation RGPD sur la protection des donnees..."
                                oninput="scheduleAutoSave()"><?= sanitize($item) ?></textarea>
                            <button type="button" onclick="removeItem(this)" class="no-print text-amber-700 hover:text-amber-900 px-2">❌</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Synthese -->
        <div class="mb-6 bg-gradient-to-r from-indigo-50 to-purple-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                🎯 Synthese : Facteurs Cles & Implications
            </label>
            <p class="text-sm text-gray-600 mb-3 italic">
                Quels sont les 3 a 5 facteurs PESTEL les plus impactants pour votre projet ? Quelles implications strategiques ?
            </p>
            <textarea id="synthese" rows="5"
                class="w-full px-4 py-2 border-2 border-indigo-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                placeholder="Ex: Les tendances technologiques (IA, automatisation) et environnementales (neutralite carbone) creent une opportunite majeure..."
                oninput="scheduleAutoSave()"><?= sanitize($analyse['synthese']) ?></textarea>
        </div>

        <!-- Notes -->
        <div class="mb-6 bg-gray-50 p-4 rounded-lg">
            <label class="block text-lg font-semibold text-gray-800 mb-2">
                ✏️ Notes Complementaires
            </label>
            <textarea id="notes" rows="3"
                class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500"
                placeholder="Observations, sources, precisions..."
                oninput="scheduleAutoSave()"><?= sanitize($analyse['notes']) ?></textarea>
        </div>

        <!-- Boutons d'action -->
        <div class="no-print flex flex-wrap gap-3 pt-4 border-t-2 border-gray-200">
            <button type="button" onclick="submitAnalyse()"
                class="bg-purple-600 text-white px-6 py-3 rounded-md hover:bg-purple-700 transition font-semibold shadow-md">
                ✅ Soumettre
            </button>
            <button type="button" onclick="exportJSON()"
                class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition font-semibold shadow-md">
                📥 Exporter JSON
            </button>
            <button type="button" onclick="window.print()"
                class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition font-semibold shadow-md">
                🖨️ Imprimer
            </button>
        </div>
    </div>

    <script>
        let autoSaveTimeout = null;

        function addItem(category, color) {
            const list = document.getElementById(category + 'List');
            const item = document.createElement('div');
            item.className = 'pestel-item flex gap-2';
            item.dataset.category = category;
            item.innerHTML = `
                <textarea rows="2" class="flex-1 px-3 py-2 border-2 border-${color}-300 rounded-md focus:ring-2 focus:ring-${color}-500 text-sm resize-none"
                    placeholder="Nouveau facteur..."
                    oninput="scheduleAutoSave()"></textarea>
                <button type="button" onclick="removeItem(this)" class="no-print text-${color}-700 hover:text-${color}-900 px-2">❌</button>
            `;
            list.appendChild(item);
            scheduleAutoSave();
        }

        function removeItem(button) {
            const parent = button.parentElement;
            const grandParent = parent.parentElement;
            if (grandParent.children.length > 1) {
                parent.remove();
                scheduleAutoSave();
            } else {
                alert('Vous devez conserver au moins un element par categorie.');
            }
        }

        function getCategoryValues(category) {
            const items = document.querySelectorAll(`[data-category="${category}"] textarea`);
            const values = [];
            items.forEach(textarea => {
                values.push(textarea.value);
            });
            return values;
        }

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            document.getElementById('saveStatus').textContent = 'Sauvegarde...';
            document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-yellow-400 text-yellow-900';
            autoSaveTimeout = setTimeout(saveData, 1000);
        }

        async function saveData() {
            const payload = {
                nom_projet: document.getElementById('nomProjet').value,
                participants_analyse: document.getElementById('participantsAnalyse').value,
                zone: document.getElementById('zone').value,
                pestel_data: {
                    politique: getCategoryValues('politique'),
                    economique: getCategoryValues('economique'),
                    socioculturel: getCategoryValues('socioculturel'),
                    technologique: getCategoryValues('technologique'),
                    environnemental: getCategoryValues('environnemental'),
                    legal: getCategoryValues('legal')
                },
                synthese: document.getElementById('synthese').value,
                notes: document.getElementById('notes').value
            };

            try {
                const response = await fetch('api/save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('saveStatus').textContent = 'Enregistre';
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-green-500 text-white';
                    document.getElementById('completion').innerHTML = 'Completion: <strong>' + result.completion + '%</strong>';
                } else {
                    document.getElementById('saveStatus').textContent = 'Erreur';
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-red-500 text-white';
                }
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('saveStatus').textContent = 'Erreur reseau';
            }
        }

        async function manualSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            await saveData();
        }

        async function submitAnalyse() {
            if (!confirm('Soumettre votre analyse PESTEL ?')) return;
            await saveData();

            try {
                const response = await fetch('api/submit.php', { method: 'POST' });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('saveStatus').textContent = 'Soumis';
                    document.getElementById('saveStatus').className = 'text-sm px-3 py-1 rounded-full bg-purple-500 text-white';
                    alert('Analyse PESTEL soumise avec succes !');
                } else {
                    alert(result.error || 'Erreur lors de la soumission');
                }
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        function exportJSON() {
            const data = {
                nom_projet: document.getElementById('nomProjet').value,
                participants: document.getElementById('participantsAnalyse').value,
                zone: document.getElementById('zone').value,
                pestel: {
                    politique: getCategoryValues('politique'),
                    economique: getCategoryValues('economique'),
                    socioculturel: getCategoryValues('socioculturel'),
                    technologique: getCategoryValues('technologique'),
                    environnemental: getCategoryValues('environnemental'),
                    legal: getCategoryValues('legal')
                },
                synthese: document.getElementById('synthese').value,
                notes: document.getElementById('notes').value,
                dateExport: new Date().toISOString()
            };

            const dataStr = JSON.stringify(data, null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            const nom = data.nom_projet ? data.nom_projet.replace(/[^a-z0-9]/gi, '_').toLowerCase() : 'pestel';
            link.download = `pestel_${nom}_${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
