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
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <!-- Barre utilisateur -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <span class="font-semibold">Connecte : <strong><?= sanitize($user['username']) ?></strong></span>
                <?php if ($user['is_admin']): ?>
                    <a href="admin.php" class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded text-sm">Interface Formateur</a>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="shareToggle" onchange="toggleShare()" class="w-4 h-4">
                    <span class="text-sm">Partager avec le formateur</span>
                </label>
                <span id="saveStatus" class="text-sm opacity-80">Chargement...</span>
                <a href="logout.php" class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded text-sm">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        <!-- En-tete -->
        <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8 mb-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-4 text-center">
                Cahier des Charges Associatif
            </h1>
            <p class="text-lg text-gray-600 text-center mb-6">
                Outil de planification et de gestion de projets pour associations
            </p>

            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Qu'est-ce qu'un Cahier des Charges ?</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    Le <strong>Cahier des Charges</strong> est un document de reference qui definit precisement
                    les objectifs, les moyens, les contraintes et les resultats attendus d'un projet associatif.
                </p>
                <div class="bg-white rounded-lg p-4">
                    <h3 class="font-bold text-gray-800 mb-3">Pourquoi utiliser un Cahier des Charges ?</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start"><span class="text-green-500 mr-2">OK</span><strong>Clarifier :</strong> Definir precisement les objectifs</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2">OK</span><strong>Organiser :</strong> Structurer votre projet</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2">OK</span><strong>Piloter :</strong> Suivre l'avancement</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2">OK</span><strong>Communiquer :</strong> Partager une vision commune</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Formulaire -->
        <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Construisez votre Cahier des Charges</h2>

            <form id="cahierForm" class="space-y-8">
                <!-- 1. Grandes lignes -->
                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl p-6 border-4 border-blue-200">
                    <h3 class="text-xl font-bold text-blue-800 mb-4">Les Grandes Lignes du Projet</h3>
                    <div class="space-y-4">
                        <input type="text" id="titreProjet" placeholder="Titre du projet"
                               class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg focus:border-blue-500 focus:outline-none" oninput="scheduleAutoSave()">

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-blue-700 mb-1">Date de debut</label>
                                <input type="date" id="dateDebut" class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg focus:border-blue-500 focus:outline-none" oninput="scheduleAutoSave()">
                            </div>
                            <div>
                                <label class="block text-sm text-blue-700 mb-1">Date de fin</label>
                                <input type="date" id="dateFin" class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg focus:border-blue-500 focus:outline-none" oninput="scheduleAutoSave()">
                            </div>
                        </div>

                        <div class="bg-white rounded-lg p-4">
                            <h4 class="font-bold text-blue-700 mb-3">Equipe de projet</h4>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <input type="text" id="chefProjet" placeholder="Chef de projet" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm" oninput="scheduleAutoSave()">
                                <input type="text" id="sponsor" placeholder="Sponsor" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm" oninput="scheduleAutoSave()">
                                <input type="text" id="groupeTravail" placeholder="Groupe de travail" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm" oninput="scheduleAutoSave()">
                                <input type="text" id="benevoles" placeholder="Benevoles" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm" oninput="scheduleAutoSave()">
                            </div>
                            <textarea id="autresActeurs" placeholder="Autres acteurs (membres, coordinations, prestataires)..."
                                      class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg mt-4 text-sm" rows="2" oninput="scheduleAutoSave()"></textarea>
                        </div>

                        <textarea id="objectifStrategique" placeholder="Objectif du plan strategique concerne..."
                                  class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg" rows="2" oninput="scheduleAutoSave()"></textarea>

                        <textarea id="inclusivite" placeholder="En quoi ce projet est-il inclusif ? (diversite, accessibilite...)..."
                                  class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg" rows="2" oninput="scheduleAutoSave()"></textarea>

                        <textarea id="aspectDigital" placeholder="Aspect digital du projet (outils numeriques, communication en ligne...)..."
                                  class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg" rows="2" oninput="scheduleAutoSave()"></textarea>

                        <textarea id="evolution" placeholder="Points d'evolution par rapport a l'an passe..."
                                  class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                    </div>
                </div>

                <!-- 2. Vision -->
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-6 border-4 border-purple-200">
                    <h3 class="text-xl font-bold text-purple-800 mb-4">La Vision du Projet</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-purple-700 mb-2">Description du projet</label>
                            <textarea id="descriptionProjet" placeholder="Decrivez votre approche, la mise en oeuvre, les alliances..."
                                      class="w-full px-4 py-3 border-2 border-purple-300 rounded-lg" rows="5" oninput="scheduleAutoSave()"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-purple-700 mb-2">Objectif du projet</label>
                            <textarea id="objectifProjet" placeholder="Quel probleme ce projet cherche-t-il a resoudre ?"
                                      class="w-full px-4 py-3 border-2 border-purple-300 rounded-lg" rows="4" oninput="scheduleAutoSave()"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-purple-700 mb-2">Logique du projet</label>
                            <textarea id="logiqueProjet" placeholder="Pourquoi ce projet ? Pourquoi maintenant ? Quel est l'impact attendu ?"
                                      class="w-full px-4 py-3 border-2 border-purple-300 rounded-lg" rows="4" oninput="scheduleAutoSave()"></textarea>
                        </div>
                    </div>
                </div>

                <!-- 3. Objectifs -->
                <div class="bg-gradient-to-r from-green-50 to-teal-50 rounded-xl p-6 border-4 border-green-200">
                    <h3 class="text-xl font-bold text-green-800 mb-4">Les Objectifs</h3>
                    <div class="space-y-6">
                        <div class="bg-white rounded-lg p-4 border-l-4 border-yellow-400">
                            <label class="block text-sm font-semibold text-green-700 mb-2">Objectif Global</label>
                            <p class="text-xs text-gray-600 mb-3 italic">L'objectif "lointain", sur lequel nous n'avons pas de prise directe</p>
                            <textarea id="objectifGlobal" placeholder="Ex: Les convictions racistes auront diminue de moitie en 2030"
                                      class="w-full px-4 py-3 border-2 border-green-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                        </div>
                        <div class="bg-white rounded-lg p-4 border-l-4 border-orange-400">
                            <label class="block text-sm font-semibold text-green-700 mb-2">Objectifs Specifiques</label>
                            <p class="text-xs text-gray-600 mb-3 italic">Ce qu'on entend changer dans notre sphere d'influence</p>
                            <div id="objectifsSpecifiquesContainer" class="space-y-2"></div>
                            <button type="button" onclick="ajouterObjectifSpecifique()"
                                    class="mt-3 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                                + Ajouter un Objectif Specifique
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4. Resultats et Indicateurs -->
                <div class="bg-gradient-to-r from-orange-50 to-amber-50 rounded-xl p-6 border-4 border-orange-200">
                    <h3 class="text-xl font-bold text-orange-800 mb-4">Resultats et Indicateurs</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border-2 border-orange-300 text-sm">
                            <thead class="bg-orange-100">
                                <tr>
                                    <th class="border border-orange-300 px-3 py-2 text-left">Objectif</th>
                                    <th class="border border-orange-300 px-3 py-2 text-left">Acteurs</th>
                                    <th class="border border-orange-300 px-3 py-2 text-left">Indicateurs</th>
                                    <th class="border border-orange-300 px-3 py-2 text-left">Delivrables</th>
                                    <th class="border border-orange-300 px-3 py-2 text-left">EXPECT</th>
                                    <th class="border border-orange-300 px-3 py-2 text-left">LIKE</th>
                                    <th class="border border-orange-300 px-3 py-2 text-left">LOVE</th>
                                    <th class="border border-orange-300 px-3 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="resultatsTableBody" class="bg-white"></tbody>
                        </table>
                        <button type="button" onclick="ajouterLigneResultat()"
                                class="mt-4 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm">
                            + Ajouter une Ligne de Resultats
                        </button>
                    </div>
                </div>

                <!-- 5. Contraintes et Risques -->
                <div class="bg-gradient-to-r from-red-50 to-pink-50 rounded-xl p-6 border-4 border-red-200">
                    <h3 class="text-xl font-bold text-red-800 mb-4">Contraintes et Risques</h3>
                    <div class="space-y-4">
                        <div class="bg-white rounded-lg p-4">
                            <label class="block text-sm font-semibold text-red-700 mb-3">Contraintes Principales</label>
                            <div id="contraintesContainer" class="space-y-2"></div>
                            <button type="button" onclick="ajouterContrainte()"
                                    class="mt-3 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm">
                                + Ajouter une Contrainte
                            </button>
                        </div>
                        <div class="bg-white rounded-lg p-4">
                            <label class="block text-sm font-semibold text-red-700 mb-3">Strategies d'Attenuation</label>
                            <div id="strategiesContainer" class="space-y-2"></div>
                            <button type="button" onclick="ajouterStrategie()"
                                    class="mt-3 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm">
                                + Ajouter une Strategie
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 6. Ressources -->
                <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl p-6 border-4 border-indigo-200">
                    <h3 class="text-xl font-bold text-indigo-800 mb-4">Ressources Disponibles</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-indigo-700 mb-2">Budget</label>
                            <input type="text" id="budget" placeholder="Budget total et repartition..."
                                   class="w-full px-4 py-3 border-2 border-indigo-300 rounded-lg" oninput="scheduleAutoSave()">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-indigo-700 mb-2">Ressources Humaines</label>
                            <textarea id="ressourcesHumaines" placeholder="Equipes, competences necessaires, temps alloue..."
                                      class="w-full px-4 py-3 border-2 border-indigo-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-indigo-700 mb-2">Ressources Materielles</label>
                            <textarea id="ressourcesMaterialles" placeholder="Equipements, locaux, logiciels, materiel..."
                                      class="w-full px-4 py-3 border-2 border-indigo-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                        </div>
                    </div>
                </div>

                <!-- 7. Etapes et Pilotage -->
                <div class="bg-gradient-to-r from-teal-50 to-cyan-50 rounded-xl p-6 border-4 border-teal-200">
                    <h3 class="text-xl font-bold text-teal-800 mb-4">Etapes et Pilotage</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-teal-700 mb-3">Etapes et Points de Controle (CoPil)</label>
                            <div id="etapesContainer" class="space-y-2"></div>
                            <button type="button" onclick="ajouterEtape()"
                                    class="mt-3 bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm">
                                + Ajouter une Etape
                            </button>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-teal-700 mb-2">Informations a Communiquer</label>
                            <textarea id="communication" placeholder="Plan de communication, comptes-rendus..."
                                      class="w-full px-4 py-3 border-2 border-teal-300 rounded-lg" rows="3" oninput="scheduleAutoSave()"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Boutons -->
                <div class="flex flex-wrap gap-4 pt-6 border-t-2 border-gray-200">
                    <button type="button" onclick="exporterExcel()"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg">
                        Exporter vers Excel
                    </button>
                    <button type="button" onclick="reinitialiser()"
                            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg shadow-lg">
                        Reinitialiser
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let compteurResultats = 0;
        let autoSaveTimeout = null;
        let isShared = false;

        window.onload = function() {
            loadFromServer();
        };

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

                    // Charger les listes
                    (d.objectifsSpecifiques || []).forEach(v => ajouterObjectifSpecifique(v));
                    (d.contraintes || []).forEach(v => ajouterContrainte(v));
                    (d.strategies || []).forEach(v => ajouterStrategie(v));
                    (d.etapes || []).forEach(v => ajouterEtape(v));
                    (d.resultats || []).forEach(r => ajouterLigneResultat(r));

                    // Ajouter des champs vides si necessaire
                    if (!d.objectifsSpecifiques?.length) for(let i=0;i<3;i++) ajouterObjectifSpecifique();
                    if (!d.contraintes?.length) for(let i=0;i<2;i++) ajouterContrainte();
                    if (!d.strategies?.length) for(let i=0;i<2;i++) ajouterStrategie();
                    if (!d.etapes?.length) for(let i=0;i<3;i++) ajouterEtape();
                    if (!d.resultats?.length) ajouterLigneResultat();
                } else {
                    // Initialiser avec des champs vides
                    for(let i=0;i<3;i++) ajouterObjectifSpecifique();
                    for(let i=0;i<2;i++) ajouterContrainte();
                    for(let i=0;i<2;i++) ajouterStrategie();
                    for(let i=0;i<3;i++) ajouterEtape();
                    ajouterLigneResultat();
                }
                updateSaveStatus('Donnees chargees');
            } catch (error) {
                console.error('Erreur:', error);
                updateSaveStatus('Erreur de chargement');
                // Initialiser quand meme
                for(let i=0;i<3;i++) ajouterObjectifSpecifique();
                for(let i=0;i<2;i++) ajouterContrainte();
                for(let i=0;i<2;i++) ajouterStrategie();
                for(let i=0;i<3;i++) ajouterEtape();
                ajouterLigneResultat();
            }
        }

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(saveToServer, 1500);
            updateSaveStatus('Modifications en attente...');
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
                if (result.success) {
                    updateSaveStatus('Sauvegarde a ' + new Date().toLocaleTimeString());
                } else {
                    updateSaveStatus('Erreur: ' + result.error);
                }
            } catch (error) {
                console.error('Erreur:', error);
                updateSaveStatus('Erreur de connexion');
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
                updateSaveStatus(isShared ? 'Partage avec le formateur' : 'Non partage');
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        function updateSaveStatus(message) {
            document.getElementById('saveStatus').textContent = message;
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
                <textarea placeholder="${placeholder}"
                       class="flex-1 px-3 py-2 border-2 border-gray-300 rounded-lg text-sm"
                       data-type="${type}" rows="2" oninput="scheduleAutoSave()">${value}</textarea>
                <button type="button" onclick="this.parentElement.remove(); scheduleAutoSave();"
                        class="bg-red-400 hover:bg-red-500 text-white px-3 py-2 rounded-lg text-xs h-fit">X</button>
            `;
            container.appendChild(div);
        }

        function ajouterObjectifSpecifique(value = '') {
            creerChamp('objectifsSpecifiquesContainer', 'Ex: Sensibiliser 400 personnes sur le droit d\'asile', 'objectif-specifique', value);
        }

        function ajouterContrainte(value = '') {
            creerChamp('contraintesContainer', 'Ex: Budget limite, delais courts', 'contrainte', value);
        }

        function ajouterStrategie(value = '') {
            creerChamp('strategiesContainer', 'Ex: Recherche de sponsors, planification anticipee', 'strategie', value);
        }

        function ajouterEtape(value = '') {
            creerChamp('etapesContainer', 'Ex: Kick-off meeting (15/01), CoPil #1 (15/03)', 'etape', value);
        }

        function ajouterLigneResultat(data = null) {
            const tbody = document.getElementById('resultatsTableBody');
            const id = ++compteurResultats;
            const tr = document.createElement('tr');
            tr.className = 'border-b border-orange-200';
            tr.innerHTML = `
                <td class="border border-orange-300 px-2 py-2">
                    <input type="text" data-type="resultat-objectif" data-id="${id}" value="${data?.objectif || ''}"
                           placeholder="Objectif..." class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()">
                </td>
                <td class="border border-orange-300 px-2 py-2">
                    <input type="text" data-type="resultat-acteurs" data-id="${id}" value="${data?.acteurs || ''}"
                           placeholder="Acteurs..." class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()">
                </td>
                <td class="border border-orange-300 px-2 py-2">
                    <input type="text" data-type="resultat-indicateurs" data-id="${id}" value="${data?.indicateurs || ''}"
                           placeholder="Indicateurs..." class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()">
                </td>
                <td class="border border-orange-300 px-2 py-2">
                    <input type="text" data-type="resultat-delivrables" data-id="${id}" value="${data?.delivrables || ''}"
                           placeholder="Delivrables..." class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()">
                </td>
                <td class="border border-orange-300 px-2 py-2">
                    <input type="text" data-type="resultat-expect" data-id="${id}" value="${data?.expect || ''}"
                           placeholder="Minimum..." class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()">
                </td>
                <td class="border border-orange-300 px-2 py-2">
                    <input type="text" data-type="resultat-like" data-id="${id}" value="${data?.like || ''}"
                           placeholder="Souhaite..." class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()">
                </td>
                <td class="border border-orange-300 px-2 py-2">
                    <input type="text" data-type="resultat-love" data-id="${id}" value="${data?.love || ''}"
                           placeholder="Optimal..." class="w-full px-2 py-1 border rounded text-xs" oninput="scheduleAutoSave()">
                </td>
                <td class="border border-orange-300 px-2 py-2 text-center">
                    <button type="button" onclick="this.closest('tr').remove(); scheduleAutoSave();"
                            class="bg-red-400 hover:bg-red-500 text-white px-2 py-1 rounded text-xs">X</button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        function collecterDonnees(type) {
            const elements = document.querySelectorAll(`[data-type="${type}"]`);
            return Array.from(elements).map(el => el.value).filter(val => val.trim() !== '');
        }

        function collecterResultats() {
            const resultats = [];
            for (let i = 1; i <= compteurResultats; i++) {
                const objectif = document.querySelector(`[data-type="resultat-objectif"][data-id="${i}"]`)?.value || '';
                const acteurs = document.querySelector(`[data-type="resultat-acteurs"][data-id="${i}"]`)?.value || '';
                const indicateurs = document.querySelector(`[data-type="resultat-indicateurs"][data-id="${i}"]`)?.value || '';
                const delivrables = document.querySelector(`[data-type="resultat-delivrables"][data-id="${i}"]`)?.value || '';
                const expect = document.querySelector(`[data-type="resultat-expect"][data-id="${i}"]`)?.value || '';
                const like = document.querySelector(`[data-type="resultat-like"][data-id="${i}"]`)?.value || '';
                const love = document.querySelector(`[data-type="resultat-love"][data-id="${i}"]`)?.value || '';

                if (objectif || acteurs || indicateurs || delivrables || expect || like || love) {
                    resultats.push({ objectif, acteurs, indicateurs, delivrables, expect, like, love });
                }
            }
            return resultats;
        }

        function reinitialiser() {
            if (confirm('Etes-vous sur de vouloir reinitialiser le formulaire ?')) {
                document.getElementById('cahierForm').reset();
                ['objectifsSpecifiquesContainer', 'contraintesContainer', 'strategiesContainer', 'etapesContainer'].forEach(id => {
                    document.getElementById(id).innerHTML = '';
                });
                document.getElementById('resultatsTableBody').innerHTML = '';
                compteurResultats = 0;
                for(let i=0;i<3;i++) ajouterObjectifSpecifique();
                for(let i=0;i<2;i++) ajouterContrainte();
                for(let i=0;i<2;i++) ajouterStrategie();
                for(let i=0;i<3;i++) ajouterEtape();
                ajouterLigneResultat();
                scheduleAutoSave();
            }
        }

        function exporterExcel() {
            const data = collectAllData();
            const titreProjet = data.titreProjet || 'Projet sans nom';

            const vueEnsemble = [
                ['CAHIER DES CHARGES - VUE D\'ENSEMBLE'],
                [''],
                ['Titre du projet:', titreProjet],
                ['Date de debut:', data.dateDebut],
                ['Date de fin:', data.dateFin],
                [''],
                ['EQUIPE DE PROJET'],
                ['Chef de projet:', data.chefProjet],
                ['Sponsor:', data.sponsor],
                ['Groupe de travail:', data.groupeTravail],
                ['Benevoles:', data.benevoles],
                ['Autres acteurs:', data.autresActeurs],
                [''],
                ['CONTEXTE STRATEGIQUE'],
                ['Objectif strategique:', data.objectifStrategique],
                ['Inclusivite:', data.inclusivite],
                ['Aspect digital:', data.aspectDigital],
                ['Evolution:', data.evolution]
            ];

            const visionObjectifs = [
                ['VISION ET OBJECTIFS DU PROJET'],
                [''],
                ['Description:', data.descriptionProjet],
                ['Objectif:', data.objectifProjet],
                ['Logique:', data.logiqueProjet],
                [''],
                ['OBJECTIF GLOBAL'],
                [data.objectifGlobal],
                [''],
                ['OBJECTIFS SPECIFIQUES']
            ];
            data.objectifsSpecifiques.forEach((obj, idx) => {
                visionObjectifs.push([`Objectif ${idx + 1}`, obj]);
            });

            const resultatsSheet = [
                ['RESULTATS ET INDICATEURS'],
                [''],
                ['Objectif', 'Acteurs', 'Indicateurs', 'Delivrables', 'EXPECT', 'LIKE', 'LOVE']
            ];
            data.resultats.forEach(r => {
                resultatsSheet.push([r.objectif, r.acteurs, r.indicateurs, r.delivrables, r.expect, r.like, r.love]);
            });

            const risquesRessources = [
                ['RISQUES, CONTRAINTES ET RESSOURCES'],
                [''],
                ['CONTRAINTES PRINCIPALES']
            ];
            data.contraintes.forEach((c, idx) => risquesRessources.push([`Contrainte ${idx + 1}`, c]));
            risquesRessources.push([''], ['STRATEGIES D\'ATTENUATION']);
            data.strategies.forEach((s, idx) => risquesRessources.push([`Strategie ${idx + 1}`, s]));
            risquesRessources.push([''], ['RESSOURCES']);
            risquesRessources.push(['Budget', data.budget]);
            risquesRessources.push(['Ressources Humaines', data.ressourcesHumaines]);
            risquesRessources.push(['Ressources Materielles', data.ressourcesMaterialles]);

            const pilotage = [
                ['PILOTAGE ET COMMUNICATION'],
                [''],
                ['ETAPES ET POINTS DE CONTROLE']
            ];
            data.etapes.forEach((e, idx) => pilotage.push([`Etape ${idx + 1}`, e]));
            pilotage.push([''], ['PLAN DE COMMUNICATION'], [data.communication]);

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(vueEnsemble), 'Vue d\'ensemble');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(visionObjectifs), 'Vision et Objectifs');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(resultatsSheet), 'Resultats');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(risquesRessources), 'Risques et Ressources');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(pilotage), 'Pilotage');

            const nomFichier = `CahierDesCharges_${titreProjet.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, nomFichier);
        }
    </script>
</body>
</html>
