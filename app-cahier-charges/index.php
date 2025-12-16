<?php
require_once 'config.php';
requireLogin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cahier des Charges - <?= sanitize($user['username']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        .step { display: none; }
        .step.active { display: block; }
        .step-indicator { transition: all 0.3s; }
        .step-indicator.completed { background: #10b981; color: white; }
        .step-indicator.current { background: #3b82f6; color: white; transform: scale(1.1); }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <!-- Barre utilisateur -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-3 shadow-lg">
        <div class="max-w-5xl mx-auto flex justify-between items-center flex-wrap gap-3">
            <span class="font-semibold">Connecte : <strong><?= sanitize($user['username']) ?></strong>
                <?php if ($user['is_admin']): ?>
                    <a href="admin.php" class="ml-2 bg-white/20 hover:bg-white/30 px-2 py-1 rounded text-xs">Admin</a>
                <?php endif; ?>
            </span>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer text-sm">
                    <input type="checkbox" id="shareToggle" onchange="toggleShare()" class="w-4 h-4">
                    Partager
                </label>
                <span id="saveStatus" class="text-xs opacity-80">...</span>
                <a href="logout.php" class="bg-white/20 hover:bg-white/30 px-2 py-1 rounded text-xs">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4 sm:p-6">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6 text-center">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2">Cahier des Charges Associatif</h1>
            <p class="text-gray-600">Completez votre cahier des charges etape par etape</p>
        </div>

        <!-- Indicateurs d'etapes -->
        <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
            <div class="flex justify-between items-center overflow-x-auto gap-2" id="stepIndicators">
                <button onclick="goToStep(1)" class="step-indicator current flex flex-col items-center p-2 rounded-lg min-w-[80px]">
                    <span class="text-lg font-bold">1</span>
                    <span class="text-xs">Projet</span>
                </button>
                <div class="flex-1 h-1 bg-gray-200 min-w-[20px]"></div>
                <button onclick="goToStep(2)" class="step-indicator flex flex-col items-center p-2 rounded-lg bg-gray-100 min-w-[80px]">
                    <span class="text-lg font-bold">2</span>
                    <span class="text-xs">Vision</span>
                </button>
                <div class="flex-1 h-1 bg-gray-200 min-w-[20px]"></div>
                <button onclick="goToStep(3)" class="step-indicator flex flex-col items-center p-2 rounded-lg bg-gray-100 min-w-[80px]">
                    <span class="text-lg font-bold">3</span>
                    <span class="text-xs">Objectifs</span>
                </button>
                <div class="flex-1 h-1 bg-gray-200 min-w-[20px]"></div>
                <button onclick="goToStep(4)" class="step-indicator flex flex-col items-center p-2 rounded-lg bg-gray-100 min-w-[80px]">
                    <span class="text-lg font-bold">4</span>
                    <span class="text-xs">Resultats</span>
                </button>
                <div class="flex-1 h-1 bg-gray-200 min-w-[20px]"></div>
                <button onclick="goToStep(5)" class="step-indicator flex flex-col items-center p-2 rounded-lg bg-gray-100 min-w-[80px]">
                    <span class="text-lg font-bold">5</span>
                    <span class="text-xs">Risques</span>
                </button>
                <div class="flex-1 h-1 bg-gray-200 min-w-[20px]"></div>
                <button onclick="goToStep(6)" class="step-indicator flex flex-col items-center p-2 rounded-lg bg-gray-100 min-w-[80px]">
                    <span class="text-lg font-bold">6</span>
                    <span class="text-xs">Ressources</span>
                </button>
                <div class="flex-1 h-1 bg-gray-200 min-w-[20px]"></div>
                <button onclick="goToStep(7)" class="step-indicator flex flex-col items-center p-2 rounded-lg bg-gray-100 min-w-[80px]">
                    <span class="text-lg font-bold">7</span>
                    <span class="text-xs">Pilotage</span>
                </button>
            </div>
        </div>

        <!-- Contenu des etapes -->
        <form id="cahierForm">
            <!-- ETAPE 1: Grandes lignes -->
            <div class="step active" data-step="1">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="bg-gradient-to-r from-blue-500 to-cyan-500 text-white rounded-lg p-4 mb-6">
                        <h2 class="text-xl font-bold">Etape 1/7 : Les Grandes Lignes du Projet</h2>
                        <p class="text-sm opacity-90">Definissez les informations generales de votre projet</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Titre du projet *</label>
                            <input type="text" id="titreProjet" placeholder="Ex: Festival solidaire 2025"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none" oninput="scheduleAutoSave()">
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Date de debut</label>
                                <input type="date" id="dateDebut" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none" oninput="scheduleAutoSave()">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Date de fin</label>
                                <input type="date" id="dateFin" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none" oninput="scheduleAutoSave()">
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-bold text-gray-700 mb-3">Equipe de projet</h4>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <input type="text" id="chefProjet" placeholder="Chef de projet" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm" oninput="scheduleAutoSave()">
                                <input type="text" id="sponsor" placeholder="Sponsor" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm" oninput="scheduleAutoSave()">
                                <input type="text" id="groupeTravail" placeholder="Groupe de travail" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm" oninput="scheduleAutoSave()">
                                <input type="text" id="benevoles" placeholder="Benevoles" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm" oninput="scheduleAutoSave()">
                            </div>
                            <textarea id="autresActeurs" placeholder="Autres acteurs (membres, coordinations, prestataires)..."
                                      class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg mt-3 text-sm" rows="2" oninput="scheduleAutoSave()"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Objectif strategique concerne</label>
                            <textarea id="objectifStrategique" placeholder="A quel objectif du plan strategique ce projet repond-il ?"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="2" oninput="scheduleAutoSave()"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ETAPE 2: Vision -->
            <div class="step" data-step="2">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-lg p-4 mb-6">
                        <h2 class="text-xl font-bold">Etape 2/7 : La Vision du Projet</h2>
                        <p class="text-sm opacity-90">Decrivez votre projet et ses objectifs</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Description du projet</label>
                            <textarea id="descriptionProjet" placeholder="Decrivez votre approche, la mise en oeuvre, les alliances envisagees..."
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="5" oninput="scheduleAutoSave()"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Objectif du projet</label>
                            <textarea id="objectifProjet" placeholder="Quel probleme ce projet cherche-t-il a resoudre ? Comment contribuera-t-il a le resoudre ?"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="4" oninput="scheduleAutoSave()"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Logique du projet</label>
                            <textarea id="logiqueProjet" placeholder="Pourquoi ce projet ? Pourquoi maintenant ? Quel impact attendu ?"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="4" oninput="scheduleAutoSave()"></textarea>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Aspect inclusif</label>
                                <textarea id="inclusivite" placeholder="En quoi ce projet est-il inclusif ?"
                                          class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Aspect digital</label>
                                <textarea id="aspectDigital" placeholder="Outils numeriques utilises ?"
                                          class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Evolution par rapport a l'an passe</label>
                            <textarea id="evolution" placeholder="Si projet recurrent, quels sont les points d'evolution ?"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="2" oninput="scheduleAutoSave()"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ETAPE 3: Objectifs -->
            <div class="step" data-step="3">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="bg-gradient-to-r from-green-500 to-teal-500 text-white rounded-lg p-4 mb-6">
                        <h2 class="text-xl font-bold">Etape 3/7 : Les Objectifs</h2>
                        <p class="text-sm opacity-90">Definissez vos objectifs global et specifiques</p>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-yellow-50 rounded-lg p-4 border-l-4 border-yellow-400">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Objectif Global</label>
                            <p class="text-xs text-gray-500 mb-2 italic">L'objectif "lointain" sur lequel d'autres facteurs ont aussi une influence</p>
                            <textarea id="objectifGlobal" placeholder="Ex: Reduire les discriminations dans notre region d'ici 2030"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                        </div>

                        <div class="bg-orange-50 rounded-lg p-4 border-l-4 border-orange-400">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Objectifs Specifiques</label>
                            <p class="text-xs text-gray-500 mb-3 italic">Ce que VOUS allez changer concretement (mesurable et atteignable)</p>
                            <div id="objectifsSpecifiquesContainer" class="space-y-2"></div>
                            <button type="button" onclick="ajouterObjectifSpecifique()"
                                    class="mt-3 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                                + Ajouter un objectif specifique
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ETAPE 4: Resultats -->
            <div class="step" data-step="4">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-lg p-4 mb-6">
                        <h2 class="text-xl font-bold">Etape 4/7 : Resultats et Indicateurs</h2>
                        <p class="text-sm opacity-90">Definissez comment vous mesurerez votre succes</p>
                    </div>

                    <div class="bg-amber-50 rounded-lg p-3 mb-4 text-sm">
                        <strong>Legende :</strong> EXPECT = minimum acceptable | LIKE = resultat souhaite | LOVE = resultat optimal
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead class="bg-orange-100">
                                <tr>
                                    <th class="border border-orange-300 px-2 py-2 text-left">Objectif</th>
                                    <th class="border border-orange-300 px-2 py-2 text-left">Acteurs</th>
                                    <th class="border border-orange-300 px-2 py-2 text-left">Indicateurs</th>
                                    <th class="border border-orange-300 px-2 py-2 text-left">EXPECT</th>
                                    <th class="border border-orange-300 px-2 py-2 text-left">LIKE</th>
                                    <th class="border border-orange-300 px-2 py-2 text-left">LOVE</th>
                                    <th class="border border-orange-300 px-2 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="resultatsTableBody" class="bg-white"></tbody>
                        </table>
                    </div>
                    <button type="button" onclick="ajouterLigneResultat()"
                            class="mt-4 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm">
                        + Ajouter une ligne
                    </button>
                </div>
            </div>

            <!-- ETAPE 5: Risques -->
            <div class="step" data-step="5">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-lg p-4 mb-6">
                        <h2 class="text-xl font-bold">Etape 5/7 : Contraintes et Risques</h2>
                        <p class="text-sm opacity-90">Identifiez les obstacles et vos strategies pour les surmonter</p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-6">
                        <div class="bg-red-50 rounded-lg p-4">
                            <label class="block text-sm font-semibold text-red-700 mb-3">Contraintes principales</label>
                            <div id="contraintesContainer" class="space-y-2"></div>
                            <button type="button" onclick="ajouterContrainte()"
                                    class="mt-3 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm">
                                + Ajouter
                            </button>
                        </div>

                        <div class="bg-green-50 rounded-lg p-4">
                            <label class="block text-sm font-semibold text-green-700 mb-3">Strategies d'attenuation</label>
                            <div id="strategiesContainer" class="space-y-2"></div>
                            <button type="button" onclick="ajouterStrategie()"
                                    class="mt-3 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                                + Ajouter
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ETAPE 6: Ressources -->
            <div class="step" data-step="6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="bg-gradient-to-r from-indigo-500 to-blue-500 text-white rounded-lg p-4 mb-6">
                        <h2 class="text-xl font-bold">Etape 6/7 : Ressources Disponibles</h2>
                        <p class="text-sm opacity-90">Listez les moyens dont vous disposez</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Budget</label>
                            <input type="text" id="budget" placeholder="Ex: 5000 EUR (subventions: 3000, autofinancement: 2000)"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" oninput="scheduleAutoSave()">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Ressources humaines</label>
                            <textarea id="ressourcesHumaines" placeholder="Ex: 2 salaries (50h), 10 benevoles, 1 stagiaire..."
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Ressources materielles</label>
                            <textarea id="ressourcesMaterialles" placeholder="Ex: Salle de reunion, materiel sono, vehicule..."
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ETAPE 7: Pilotage -->
            <div class="step" data-step="7">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="bg-gradient-to-r from-teal-500 to-cyan-500 text-white rounded-lg p-4 mb-6">
                        <h2 class="text-xl font-bold">Etape 7/7 : Etapes et Pilotage</h2>
                        <p class="text-sm opacity-90">Planifiez le deroulement de votre projet</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Etapes cles et points de controle (CoPil)</label>
                            <div id="etapesContainer" class="space-y-2"></div>
                            <button type="button" onclick="ajouterEtape()"
                                    class="mt-3 bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm">
                                + Ajouter une etape
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Plan de communication</label>
                            <textarea id="communication" placeholder="Comment allez-vous communiquer sur l'avancement ? Quels outils ?"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                        </div>
                    </div>

                    <!-- Resume et export -->
                    <div class="mt-8 pt-6 border-t-2 border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Votre cahier des charges est pret !</h3>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" onclick="exporterExcel()"
                                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
                                Exporter Excel
                            </button>
                            <button type="button" onclick="reinitialiser()"
                                    class="bg-gray-500 hover:bg-gray-600 text-white py-3 px-6 rounded-lg">
                                Reinitialiser
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Navigation -->
        <div class="flex justify-between mt-6">
            <button onclick="previousStep()" id="btnPrev" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-8 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                Precedent
            </button>
            <button onclick="nextStep()" id="btnNext" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg">
                Suivant
            </button>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 7;
        let compteurResultats = 0;
        let autoSaveTimeout = null;
        let isShared = false;

        window.onload = function() {
            loadFromServer();
        };

        function goToStep(step) {
            if (step < 1 || step > totalSteps) return;
            currentStep = step;
            updateStepDisplay();
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                currentStep++;
                updateStepDisplay();
            }
        }

        function previousStep() {
            if (currentStep > 1) {
                currentStep--;
                updateStepDisplay();
            }
        }

        function updateStepDisplay() {
            // Cacher toutes les etapes
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            // Afficher l'etape courante
            document.querySelector(`[data-step="${currentStep}"]`).classList.add('active');

            // Mettre a jour les indicateurs
            document.querySelectorAll('.step-indicator').forEach((ind, idx) => {
                ind.classList.remove('current', 'completed');
                if (idx + 1 < currentStep) {
                    ind.classList.add('completed');
                } else if (idx + 1 === currentStep) {
                    ind.classList.add('current');
                }
            });

            // Mettre a jour les boutons
            document.getElementById('btnPrev').disabled = (currentStep === 1);
            document.getElementById('btnNext').textContent = (currentStep === totalSteps) ? 'Terminer' : 'Suivant';

            // Scroll en haut
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        async function loadFromServer() {
            try {
                const response = await fetch('api.php?action=load');
                const result = await response.json();

                if (result.success && result.data) {
                    const d = result.data;
                    document.getElementById('titreProjet').value = d.titreProjet || '';
                    document.getElementById('dateDebut').value = d.dateDebut || '';
                    document.getElementById('dateFin').value = d.dateFin || '';
                    document.getElementById('chefProjet').value = d.chefProjet || '';
                    document.getElementById('sponsor').value = d.sponsor || '';
                    document.getElementById('groupeTravail').value = d.groupeTravail || '';
                    document.getElementById('benevoles').value = d.benevoles || '';
                    document.getElementById('autresActeurs').value = d.autresActeurs || '';
                    document.getElementById('objectifStrategique').value = d.objectifStrategique || '';
                    document.getElementById('inclusivite').value = d.inclusivite || '';
                    document.getElementById('aspectDigital').value = d.aspectDigital || '';
                    document.getElementById('evolution').value = d.evolution || '';
                    document.getElementById('descriptionProjet').value = d.descriptionProjet || '';
                    document.getElementById('objectifProjet').value = d.objectifProjet || '';
                    document.getElementById('logiqueProjet').value = d.logiqueProjet || '';
                    document.getElementById('objectifGlobal').value = d.objectifGlobal || '';
                    document.getElementById('budget').value = d.budget || '';
                    document.getElementById('ressourcesHumaines').value = d.ressourcesHumaines || '';
                    document.getElementById('ressourcesMaterialles').value = d.ressourcesMaterialles || '';
                    document.getElementById('communication').value = d.communication || '';

                    isShared = d.isShared || false;
                    document.getElementById('shareToggle').checked = isShared;

                    (d.objectifsSpecifiques || []).forEach(v => ajouterObjectifSpecifique(v));
                    (d.contraintes || []).forEach(v => ajouterContrainte(v));
                    (d.strategies || []).forEach(v => ajouterStrategie(v));
                    (d.etapes || []).forEach(v => ajouterEtape(v));
                    (d.resultats || []).forEach(r => ajouterLigneResultat(r));

                    if (!d.objectifsSpecifiques?.length) for(let i=0;i<2;i++) ajouterObjectifSpecifique();
                    if (!d.contraintes?.length) for(let i=0;i<2;i++) ajouterContrainte();
                    if (!d.strategies?.length) for(let i=0;i<2;i++) ajouterStrategie();
                    if (!d.etapes?.length) for(let i=0;i<3;i++) ajouterEtape();
                    if (!d.resultats?.length) ajouterLigneResultat();
                } else {
                    for(let i=0;i<2;i++) ajouterObjectifSpecifique();
                    for(let i=0;i<2;i++) ajouterContrainte();
                    for(let i=0;i<2;i++) ajouterStrategie();
                    for(let i=0;i<3;i++) ajouterEtape();
                    ajouterLigneResultat();
                }
                updateSaveStatus('Charge');
            } catch (error) {
                console.error('Erreur:', error);
                updateSaveStatus('Erreur');
                for(let i=0;i<2;i++) ajouterObjectifSpecifique();
                for(let i=0;i<2;i++) ajouterContrainte();
                for(let i=0;i<2;i++) ajouterStrategie();
                for(let i=0;i<3;i++) ajouterEtape();
                ajouterLigneResultat();
            }
        }

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(saveToServer, 1500);
            updateSaveStatus('...');
        }

        async function saveToServer() {
            updateSaveStatus('Sauvegarde...');
            try {
                const data = collectAllData();
                const response = await fetch('api.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                updateSaveStatus(result.success ? 'Sauvegarde' : 'Erreur');
            } catch (error) {
                updateSaveStatus('Erreur');
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
                updateSaveStatus(isShared ? 'Partage' : 'Prive');
            } catch (error) {}
        }

        function updateSaveStatus(msg) {
            document.getElementById('saveStatus').textContent = msg;
        }

        function collectAllData() {
            return {
                titreProjet: document.getElementById('titreProjet').value,
                dateDebut: document.getElementById('dateDebut').value,
                dateFin: document.getElementById('dateFin').value,
                chefProjet: document.getElementById('chefProjet').value,
                sponsor: document.getElementById('sponsor').value,
                groupeTravail: document.getElementById('groupeTravail').value,
                benevoles: document.getElementById('benevoles').value,
                autresActeurs: document.getElementById('autresActeurs').value,
                objectifStrategique: document.getElementById('objectifStrategique').value,
                inclusivite: document.getElementById('inclusivite').value,
                aspectDigital: document.getElementById('aspectDigital').value,
                evolution: document.getElementById('evolution').value,
                descriptionProjet: document.getElementById('descriptionProjet').value,
                objectifProjet: document.getElementById('objectifProjet').value,
                logiqueProjet: document.getElementById('logiqueProjet').value,
                objectifGlobal: document.getElementById('objectifGlobal').value,
                objectifsSpecifiques: collecterDonnees('objectif-specifique'),
                resultats: collecterResultats(),
                contraintes: collecterDonnees('contrainte'),
                strategies: collecterDonnees('strategie'),
                budget: document.getElementById('budget').value,
                ressourcesHumaines: document.getElementById('ressourcesHumaines').value,
                ressourcesMaterialles: document.getElementById('ressourcesMaterialles').value,
                etapes: collecterDonnees('etape'),
                communication: document.getElementById('communication').value
            };
        }

        function creerChamp(containerId, placeholder, type, value = '') {
            const container = document.getElementById(containerId);
            const div = document.createElement('div');
            div.className = 'flex gap-2';
            div.innerHTML = `
                <textarea placeholder="${placeholder}" class="flex-1 px-3 py-2 border-2 border-gray-300 rounded-lg text-sm"
                       data-type="${type}" rows="2" oninput="scheduleAutoSave()">${value}</textarea>
                <button type="button" onclick="this.parentElement.remove(); scheduleAutoSave();"
                        class="bg-red-400 hover:bg-red-500 text-white px-3 py-1 rounded-lg text-xs h-fit">X</button>
            `;
            container.appendChild(div);
        }

        function ajouterObjectifSpecifique(v='') { creerChamp('objectifsSpecifiquesContainer', 'Ex: Sensibiliser 400 personnes...', 'objectif-specifique', v); }
        function ajouterContrainte(v='') { creerChamp('contraintesContainer', 'Ex: Budget limite, delais courts...', 'contrainte', v); }
        function ajouterStrategie(v='') { creerChamp('strategiesContainer', 'Ex: Recherche de partenaires...', 'strategie', v); }
        function ajouterEtape(v='') { creerChamp('etapesContainer', 'Ex: Lancement (15/01), Bilan (15/06)...', 'etape', v); }

        function ajouterLigneResultat(data = null) {
            const tbody = document.getElementById('resultatsTableBody');
            const id = ++compteurResultats;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="border border-orange-300 p-1"><input type="text" data-type="resultat-objectif" data-id="${id}" value="${data?.objectif || ''}" placeholder="Objectif" class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()"></td>
                <td class="border border-orange-300 p-1"><input type="text" data-type="resultat-acteurs" data-id="${id}" value="${data?.acteurs || ''}" placeholder="Acteurs" class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()"></td>
                <td class="border border-orange-300 p-1"><input type="text" data-type="resultat-indicateurs" data-id="${id}" value="${data?.indicateurs || ''}" placeholder="Indicateurs" class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()"></td>
                <td class="border border-orange-300 p-1"><input type="text" data-type="resultat-expect" data-id="${id}" value="${data?.expect || ''}" placeholder="Min" class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()"></td>
                <td class="border border-orange-300 p-1"><input type="text" data-type="resultat-like" data-id="${id}" value="${data?.like || ''}" placeholder="Souhaite" class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()"></td>
                <td class="border border-orange-300 p-1"><input type="text" data-type="resultat-love" data-id="${id}" value="${data?.love || ''}" placeholder="Optimal" class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()"></td>
                <td class="border border-orange-300 p-1 text-center"><button type="button" onclick="this.closest('tr').remove(); scheduleAutoSave();" class="bg-red-400 hover:bg-red-500 text-white px-2 py-1 rounded text-xs">X</button></td>
            `;
            tbody.appendChild(tr);
        }

        function collecterDonnees(type) {
            return Array.from(document.querySelectorAll(`[data-type="${type}"]`)).map(el => el.value).filter(v => v.trim());
        }

        function collecterResultats() {
            const res = [];
            for (let i = 1; i <= compteurResultats; i++) {
                const obj = document.querySelector(`[data-type="resultat-objectif"][data-id="${i}"]`)?.value || '';
                const act = document.querySelector(`[data-type="resultat-acteurs"][data-id="${i}"]`)?.value || '';
                const ind = document.querySelector(`[data-type="resultat-indicateurs"][data-id="${i}"]`)?.value || '';
                const exp = document.querySelector(`[data-type="resultat-expect"][data-id="${i}"]`)?.value || '';
                const lik = document.querySelector(`[data-type="resultat-like"][data-id="${i}"]`)?.value || '';
                const lov = document.querySelector(`[data-type="resultat-love"][data-id="${i}"]`)?.value || '';
                if (obj || act || ind || exp || lik || lov) res.push({ objectif: obj, acteurs: act, indicateurs: ind, expect: exp, like: lik, love: lov });
            }
            return res;
        }

        function reinitialiser() {
            if (confirm('Reinitialiser le formulaire ?')) {
                document.getElementById('cahierForm').reset();
                ['objectifsSpecifiquesContainer', 'contraintesContainer', 'strategiesContainer', 'etapesContainer'].forEach(id => document.getElementById(id).innerHTML = '');
                document.getElementById('resultatsTableBody').innerHTML = '';
                compteurResultats = 0;
                for(let i=0;i<2;i++) ajouterObjectifSpecifique();
                for(let i=0;i<2;i++) ajouterContrainte();
                for(let i=0;i<2;i++) ajouterStrategie();
                for(let i=0;i<3;i++) ajouterEtape();
                ajouterLigneResultat();
                currentStep = 1;
                updateStepDisplay();
                scheduleAutoSave();
            }
        }

        function exporterExcel() {
            const data = collectAllData();
            const titre = data.titreProjet || 'Projet';
            const wb = XLSX.utils.book_new();

            const vue = [['CAHIER DES CHARGES'], [''], ['Titre:', titre], ['Dates:', data.dateDebut + ' - ' + data.dateFin], ['Chef de projet:', data.chefProjet], [''], ['VISION'], ['Description:', data.descriptionProjet], ['Objectif:', data.objectifProjet], [''], ['OBJECTIFS'], ['Global:', data.objectifGlobal]];
            data.objectifsSpecifiques.forEach((o,i) => vue.push(['Specifique '+(i+1)+':', o]));
            vue.push([''], ['RESSOURCES'], ['Budget:', data.budget], ['RH:', data.ressourcesHumaines], ['Materiel:', data.ressourcesMaterialles]);

            const res = [['RESULTATS ET INDICATEURS'], [''], ['Objectif', 'Acteurs', 'Indicateurs', 'EXPECT', 'LIKE', 'LOVE']];
            data.resultats.forEach(r => res.push([r.objectif, r.acteurs, r.indicateurs, r.expect, r.like, r.love]));

            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(vue), 'Cahier des Charges');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(res), 'Resultats');
            XLSX.writeFile(wb, `CahierDesCharges_${titre.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.xlsx`);
        }
    </script>
</body>
</html>
