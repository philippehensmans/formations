<?php
require_once 'config/database.php';

// Verifier authentification
if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $db = getDB();
    $sharedDb = getSharedDB();
    $user = getLoggedUser();
    $sessionId = $_SESSION['current_session_id'];

    // Recuperer les infos du participant
    $participant = [
        'id' => $_SESSION['participant_id'] ?? 0,
        'user_id' => $user['id'],
        'session_id' => $sessionId,
        'prenom' => $user['prenom'] ?? $user['username'],
        'nom' => $user['nom'] ?? '',
        'organisation' => $user['organisation'] ?? '',
        'session_code' => $_SESSION['current_session_code'] ?? '',
        'session_nom' => $_SESSION['current_session_nom'] ?? ''
    ];

    // Recuperer ou creer les donnees de mesure d'impact
    $mesure = getOrCreateMesureImpact($participant['id'], $participant['session_id']);
    $isSubmitted = ($mesure['is_submitted'] ?? 0) == 1;
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

// Récupérer les énoncés pour l'étape 1
$enonces = getEnonces($participant['session_id']);

// Récupérer les définitions et méthodes
$definitions = getDefinitions();
$methodes = getMethodesCollecte();
$criteres = getCriteresIndicateur();

// Décoder les données JSON
$etape1 = json_decode($mesure['etape1_classification'] ?: '{}', true);
$etape2 = json_decode($mesure['etape2_theorie_changement'] ?: '{}', true);
$etape3 = json_decode($mesure['etape3_indicateurs'] ?: '{}', true);
$etape4 = json_decode($mesure['etape4_plan_collecte'] ?: '{}', true);
$etape5 = json_decode($mesure['etape5_synthese'] ?: '{}', true);

$etapeCourante = $mesure['etape_courante'] ?: 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesure d'Impact Social - <?= htmlspecialchars($participant['prenom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .step-content { display: none; }
        .step-content.active { display: block; }
        .drop-zone { min-height: 100px; transition: all 0.2s; }
        .drop-zone.drag-over { background-color: #e0e7ff; border-color: #6366f1; }
        .draggable { cursor: grab; }
        .draggable:active { cursor: grabbing; }
        .draggable.dragging { opacity: 0.5; }
        .category-output { border-left: 4px solid #3b82f6; }
        .category-outcome { border-left: 4px solid #10b981; }
        .category-impact { border-left: 4px solid #8b5cf6; }
        .chain-arrow {
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            position: relative;
        }
        .chain-arrow::after {
            content: '';
            position: absolute;
            right: 0;
            top: -4px;
            border: 5px solid transparent;
            border-left-color: #8b5cf6;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Mesure d'Impact Social</h1>
                        <p class="text-sm text-gray-500">Session: <?= htmlspecialchars($participant['session_code']) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($participant['prenom'] . ' ' . $participant['nom']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($participant['organisation'] ?? '') ?></p>
                    </div>
                    <span id="saveStatus" class="text-xs text-gray-400"></span>
                    <a href="logout.php" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Progress bar -->
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button onclick="goToStep(<?= $i ?>)"
                            class="step-indicator flex items-center gap-2 px-3 py-2 rounded-lg transition-all <?= $i == $etapeCourante ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100' ?>"
                            data-step="<?= $i ?>">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold <?= $i == $etapeCourante ? 'bg-indigo-600 text-white' : 'bg-gray-200' ?>">
                            <?= $i ?>
                        </span>
                        <span class="hidden md:inline text-sm font-medium">
                            <?php
                            $stepNames = ['Classifier', 'Theorie du changement', 'Indicateurs', 'Plan de collecte', 'Synthese'];
                            echo $stepNames[$i-1];
                            ?>
                        </span>
                    </button>
                    <?php if ($i < 5): ?>
                        <div class="flex-1 h-1 bg-gray-200 mx-2 rounded hidden sm:block">
                            <div class="h-full bg-indigo-600 rounded transition-all" style="width: <?= $i < $etapeCourante ? '100%' : '0%' ?>"></div>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <main class="max-w-7xl mx-auto px-4 py-6">
        <?php if ($isSubmitted): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                Travail marque comme termine (modifications toujours possibles)
            </div>
        <?php endif; ?>

        <!-- ÉTAPE 1: Classification -->
        <div id="step1" class="step-content <?= $etapeCourante == 1 ? 'active' : '' ?>">
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-2">Etape 1/5 - Distinguer Outputs, Outcomes et Impact</h2>
                <p class="text-gray-600 mb-6">Glissez chaque enonce dans la bonne categorie pour tester votre comprehension.</p>

                <!-- Score -->
                <div class="mb-6 p-4 bg-indigo-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-indigo-900">Score:</span>
                        <span id="score" class="text-2xl font-bold text-indigo-600">0/<?= count($enonces) ?></span>
                    </div>
                </div>

                <!-- Zones de dépôt -->
                <div class="grid md:grid-cols-3 gap-4 mb-6">
                    <!-- OUTPUT -->
                    <div class="border-2 border-dashed border-blue-300 rounded-xl p-4 bg-blue-50/50">
                        <div class="text-center mb-4">
                            <h3 class="font-bold text-blue-700 text-lg">OUTPUT</h3>
                            <p class="text-sm text-blue-600">(Produit/Realisation)</p>
                            <p class="text-xs text-blue-500 mt-1">Ce qu'on a produit/fait</p>
                        </div>
                        <div id="dropzone-output" class="drop-zone min-h-[150px] space-y-2" data-category="output">
                        </div>
                    </div>

                    <!-- OUTCOME -->
                    <div class="border-2 border-dashed border-emerald-300 rounded-xl p-4 bg-emerald-50/50">
                        <div class="text-center mb-4">
                            <h3 class="font-bold text-emerald-700 text-lg">OUTCOME</h3>
                            <p class="text-sm text-emerald-600">(Effet/Changement)</p>
                            <p class="text-xs text-emerald-500 mt-1">Ce qui a change chez les personnes</p>
                        </div>
                        <div id="dropzone-outcome" class="drop-zone min-h-[150px] space-y-2" data-category="outcome">
                        </div>
                    </div>

                    <!-- IMPACT -->
                    <div class="border-2 border-dashed border-purple-300 rounded-xl p-4 bg-purple-50/50">
                        <div class="text-center mb-4">
                            <h3 class="font-bold text-purple-700 text-lg">IMPACT</h3>
                            <p class="text-sm text-purple-600">(Changement societal)</p>
                            <p class="text-xs text-purple-500 mt-1">Changement durable sur la societe</p>
                        </div>
                        <div id="dropzone-impact" class="drop-zone min-h-[150px] space-y-2" data-category="impact">
                        </div>
                    </div>
                </div>

                <!-- Énoncés à classer -->
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-medium text-gray-700 mb-3">Enonces a classer:</h4>
                    <div id="enonces-container" class="space-y-2">
                        <?php foreach ($enonces as $enonce): ?>
                            <div class="draggable bg-white p-3 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow"
                                 draggable="true"
                                 data-id="<?= $enonce['id'] ?>"
                                 data-correct="<?= $enonce['categorie_correcte'] ?>"
                                 data-explication="<?= htmlspecialchars($enonce['explication']) ?>">
                                <p class="text-sm text-gray-700"><?= htmlspecialchars($enonce['texte']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="verifierEtape1()" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                        Verifier mes reponses →
                    </button>
                </div>
            </div>
        </div>

        <!-- ÉTAPE 2: Théorie du changement -->
        <div id="step2" class="step-content <?= $etapeCourante == 2 ? 'active' : '' ?>">
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-2">Etape 2/5 - Votre Theorie du Changement</h2>
                <p class="text-gray-600 mb-6">Decrivez votre projet et construisez votre chaine de resultats.</p>

                <!-- Description du projet -->
                <div class="mb-8 p-6 bg-gray-50 rounded-xl">
                    <h3 class="font-semibold text-gray-800 mb-4">Description de votre projet/action</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom du projet/action</label>
                            <input type="text" id="projet_nom" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                                   placeholder="Ex: Ateliers d'expression orale pour jeunes"
                                   value="<?= htmlspecialchars($etape2['projet']['nom'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Public cible</label>
                            <input type="text" id="projet_public" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                                   placeholder="Ex: 30 jeunes de 16-25 ans du quartier Nord"
                                   value="<?= htmlspecialchars($etape2['projet']['public_cible'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contexte / Probleme de depart</label>
                            <textarea id="projet_contexte" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                                      placeholder="Decrivez le probleme ou besoin auquel votre action repond..."><?= htmlspecialchars($etape2['projet']['contexte'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Chaîne de résultats visuelle -->
                <div class="mb-8">
                    <h3 class="font-semibold text-gray-800 mb-4">Chaine de resultats</h3>

                    <!-- Moyens, Activités, Outputs -->
                    <div class="grid md:grid-cols-3 gap-4 mb-6">
                        <!-- MOYENS -->
                        <div class="bg-blue-50 rounded-xl p-4">
                            <h4 class="font-semibold text-blue-800 mb-2 flex items-center gap-2">
                                <span class="w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs">1</span>
                                MOYENS (Inputs)
                            </h4>
                            <p class="text-xs text-blue-600 mb-3">Quelles ressources mobilisez-vous?</p>
                            <div id="moyens-container" class="space-y-2">
                                <?php
                                $moyens = $etape2['moyens'] ?? [['id' => 1, 'texte' => '']];
                                foreach ($moyens as $moyen):
                                ?>
                                <div class="flex gap-2">
                                    <input type="text" class="moyen-input flex-1 px-3 py-2 text-sm border rounded-lg"
                                           placeholder="Ex: 2 animateurs, Budget 3000€..."
                                           value="<?= htmlspecialchars($moyen['texte'] ?? '') ?>">
                                    <button onclick="removeItem(this)" class="text-red-400 hover:text-red-600">×</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button onclick="addItem('moyens')" class="mt-2 text-sm text-blue-600 hover:text-blue-800">+ Ajouter</button>
                        </div>

                        <!-- ACTIVITÉS -->
                        <div class="bg-indigo-50 rounded-xl p-4">
                            <h4 class="font-semibold text-indigo-800 mb-2 flex items-center gap-2">
                                <span class="w-6 h-6 bg-indigo-600 text-white rounded-full flex items-center justify-center text-xs">2</span>
                                ACTIVITES
                            </h4>
                            <p class="text-xs text-indigo-600 mb-3">Quelles actions menez-vous?</p>
                            <div id="activites-container" class="space-y-2">
                                <?php
                                $activites = $etape2['activites'] ?? [['id' => 1, 'texte' => '']];
                                foreach ($activites as $activite):
                                ?>
                                <div class="flex gap-2">
                                    <input type="text" class="activite-input flex-1 px-3 py-2 text-sm border rounded-lg"
                                           placeholder="Ex: 12 ateliers de 2h..."
                                           value="<?= htmlspecialchars($activite['texte'] ?? '') ?>">
                                    <button onclick="removeItem(this)" class="text-red-400 hover:text-red-600">×</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button onclick="addItem('activites')" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Ajouter</button>
                        </div>

                        <!-- OUTPUTS -->
                        <div class="bg-cyan-50 rounded-xl p-4">
                            <h4 class="font-semibold text-cyan-800 mb-2 flex items-center gap-2">
                                <span class="w-6 h-6 bg-cyan-600 text-white rounded-full flex items-center justify-center text-xs">3</span>
                                OUTPUTS (Produits)
                            </h4>
                            <p class="text-xs text-cyan-600 mb-3">Quels produits concrets?</p>
                            <div id="outputs-container" class="space-y-2">
                                <?php
                                $outputs = $etape2['outputs'] ?? [['id' => 1, 'texte' => '']];
                                foreach ($outputs as $output):
                                ?>
                                <div class="flex gap-2">
                                    <input type="text" class="output-input flex-1 px-3 py-2 text-sm border rounded-lg"
                                           placeholder="Ex: 30 jeunes ont participe..."
                                           value="<?= htmlspecialchars($output['texte'] ?? '') ?>">
                                    <button onclick="removeItem(this)" class="text-red-400 hover:text-red-600">×</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button onclick="addItem('outputs')" class="mt-2 text-sm text-cyan-600 hover:text-cyan-800">+ Ajouter</button>
                        </div>
                    </div>

                    <!-- OUTCOMES -->
                    <div class="bg-emerald-50 rounded-xl p-6 mb-6">
                        <h4 class="font-semibold text-emerald-800 mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs">4</span>
                            OUTCOMES - Quels changements chez vos beneficiaires?
                        </h4>
                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-emerald-700 mb-1">Court terme</label>
                                <p class="text-xs text-emerald-600 mb-2">Pendant/juste apres l'action</p>
                                <textarea id="outcome_court" rows="3" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-emerald-500"
                                          placeholder="Ex: Les jeunes se sentent plus a l'aise..."><?= htmlspecialchars($etape2['outcomes']['court_terme']['texte'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-emerald-700 mb-1">Moyen terme</label>
                                <p class="text-xs text-emerald-600 mb-2">Quelques mois apres</p>
                                <textarea id="outcome_moyen" rows="3" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-emerald-500"
                                          placeholder="Ex: Les jeunes osent postuler..."><?= htmlspecialchars($etape2['outcomes']['moyen_terme']['texte'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-emerald-700 mb-1">Long terme</label>
                                <p class="text-xs text-emerald-600 mb-2">Changement durable</p>
                                <textarea id="outcome_long" rows="3" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-emerald-500"
                                          placeholder="Ex: Les jeunes sont acteurs de leur parcours..."><?= htmlspecialchars($etape2['outcomes']['long_terme']['texte'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- IMPACT -->
                    <div class="bg-purple-50 rounded-xl p-6 mb-6">
                        <h4 class="font-semibold text-purple-800 mb-2 flex items-center gap-2">
                            <span class="w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs">5</span>
                            IMPACT - A quel changement societal contribuez-vous?
                        </h4>
                        <p class="text-xs text-purple-600 mb-3">Note: Vous contribuez a l'impact, mais ne le produisez pas seul.</p>
                        <textarea id="impact_global" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                                  placeholder="Ex: Ameliorer l'insertion sociale et professionnelle des jeunes du quartier"><?= htmlspecialchars($etape2['impact'] ?? '') ?></textarea>
                    </div>

                    <!-- HYPOTHÈSES -->
                    <div class="bg-amber-50 rounded-xl p-6">
                        <h4 class="font-semibold text-amber-800 mb-2">💡 Hypotheses cles</h4>
                        <p class="text-xs text-amber-600 mb-3">Quelles conditions doivent etre reunies pour que vos activites produisent les effets attendus?</p>
                        <div id="hypotheses-container" class="space-y-2">
                            <?php
                            $hypotheses = $etape2['hypotheses'] ?? [['id' => 1, 'texte' => '']];
                            foreach ($hypotheses as $hypothese):
                            ?>
                            <div class="flex gap-2">
                                <input type="text" class="hypothese-input flex-1 px-3 py-2 text-sm border rounded-lg"
                                       placeholder="Ex: Les jeunes viennent regulierement aux ateliers..."
                                       value="<?= htmlspecialchars($hypothese['texte'] ?? '') ?>">
                                <button onclick="removeItem(this)" class="text-red-400 hover:text-red-600">×</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button onclick="addItem('hypotheses')" class="mt-2 text-sm text-amber-600 hover:text-amber-800">+ Ajouter une hypothese</button>
                    </div>
                </div>

                <div class="flex justify-between">
                    <button onclick="goToStep(1)" class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                        ← Retour
                    </button>
                    <button onclick="saveAndNext(3)" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                        Continuer →
                    </button>
                </div>
            </div>
        </div>

        <!-- ÉTAPE 3: Indicateurs -->
        <div id="step3" class="step-content <?= $etapeCourante == 3 ? 'active' : '' ?>">
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-2">Etape 3/5 - Definir vos Indicateurs</h2>
                <p class="text-gray-600 mb-6">Pour chaque outcome, definissez au moins un indicateur quantitatif ET un indicateur qualitatif.</p>

                <div id="indicateurs-container" class="space-y-6">
                    <!-- Les indicateurs seront générés dynamiquement -->
                </div>

                <!-- Conseil -->
                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-2">💡 Un bon indicateur est:</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• <strong>Pertinent</strong>: mesure bien ce qu'on veut savoir</li>
                        <li>• <strong>Faisable</strong>: collecte realiste avec vos moyens</li>
                        <li>• <strong>Fiable</strong>: donne des resultats coherents</li>
                        <li>• <strong>Utile</strong>: aide a prendre des decisions</li>
                    </ul>
                </div>

                <div class="mt-6 flex justify-between">
                    <button onclick="goToStep(2)" class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                        ← Retour
                    </button>
                    <button onclick="saveAndNext(4)" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                        Continuer →
                    </button>
                </div>
            </div>
        </div>

        <!-- ÉTAPE 4: Plan de collecte -->
        <div id="step4" class="step-content <?= $etapeCourante == 4 ? 'active' : '' ?>">
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-2">Etape 4/5 - Planifier la Collecte de Donnees</h2>
                <p class="text-gray-600 mb-6">Pour chaque indicateur, choisissez une methode de collecte adaptee a vos moyens.</p>

                <div id="plan-collecte-container" class="space-y-6">
                    <!-- Plan généré dynamiquement -->
                </div>

                <!-- Récapitulatif -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3">Recapitulatif du temps de collecte</h4>
                    <div id="recap-temps" class="text-sm text-gray-600">
                        <!-- Calculé dynamiquement -->
                    </div>
                </div>

                <!-- Réalisme -->
                <div class="mt-6 p-4 bg-amber-50 rounded-lg">
                    <h4 class="font-semibold text-amber-800 mb-3">💡 Ce temps vous semble-t-il realiste?</h4>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="realisme" value="faisable" class="realisme-radio">
                            <span class="text-sm">Oui, c'est faisable</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="realisme" value="ambitieux" class="realisme-radio">
                            <span class="text-sm">C'est ambitieux mais on va essayer</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="realisme" value="simplifier" class="realisme-radio">
                            <span class="text-sm">Non, je dois simplifier</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-between">
                    <button onclick="goToStep(3)" class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                        ← Retour
                    </button>
                    <button onclick="saveAndNext(5)" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                        Continuer →
                    </button>
                </div>
            </div>
        </div>

        <!-- ÉTAPE 5: Synthèse -->
        <div id="step5" class="step-content <?= $etapeCourante == 5 ? 'active' : '' ?>">
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-2">Etape 5/5 - Votre Cadre de Mesure d'Impact</h2>
                <p class="text-gray-600 mb-6">Voici la synthese de votre cadre de mesure. Completez les prochaines etapes et la reflexion finale.</p>

                <!-- Fiche synthèse -->
                <div id="synthese-fiche" class="mb-6 p-6 bg-indigo-50 rounded-xl">
                    <!-- Générée dynamiquement -->
                </div>

                <!-- Tableau de bord -->
                <div id="synthese-tableau" class="mb-6">
                    <h3 class="font-semibold text-gray-800 mb-3">📊 Tableau de bord des indicateurs</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border p-2 text-left">Outcome</th>
                                    <th class="border p-2 text-left">Indicateur</th>
                                    <th class="border p-2 text-center">Baseline</th>
                                    <th class="border p-2 text-center">Cible</th>
                                    <th class="border p-2 text-left">Methode</th>
                                    <th class="border p-2 text-left">Frequence</th>
                                </tr>
                            </thead>
                            <tbody id="synthese-tableau-body">
                                <!-- Généré dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Prochaines étapes -->
                <div class="mb-6 p-6 bg-green-50 rounded-xl">
                    <h3 class="font-semibold text-green-800 mb-3">✅ Prochaines etapes</h3>
                    <div id="prochaines-etapes-container" class="space-y-3">
                        <?php
                        $etapes = $etape5['prochaines_etapes'] ?? [['id' => 1, 'action' => '', 'responsable' => '', 'echeance' => '']];
                        foreach ($etapes as $etapeAction):
                        ?>
                        <div class="flex flex-wrap gap-2 items-center bg-white p-3 rounded-lg">
                            <input type="text" class="etape-action flex-1 min-w-[200px] px-3 py-2 text-sm border rounded-lg"
                                   placeholder="Action a realiser..."
                                   value="<?= htmlspecialchars($etapeAction['action'] ?? '') ?>">
                            <input type="text" class="etape-responsable w-32 px-3 py-2 text-sm border rounded-lg"
                                   placeholder="Responsable"
                                   value="<?= htmlspecialchars($etapeAction['responsable'] ?? '') ?>">
                            <input type="date" class="etape-echeance px-3 py-2 text-sm border rounded-lg"
                                   value="<?= htmlspecialchars($etapeAction['echeance'] ?? '') ?>">
                            <button onclick="removeItem(this)" class="text-red-400 hover:text-red-600">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button onclick="addEtapeAction()" class="mt-3 text-sm text-green-600 hover:text-green-800">+ Ajouter une etape</button>
                </div>

                <!-- Réflexion finale -->
                <div class="mb-6 p-6 bg-amber-50 rounded-xl">
                    <h3 class="font-semibold text-amber-800 mb-3">🤔 Reflexion finale</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-amber-700 mb-1">Qu'est-ce qui pourrait empecher d'atteindre vos objectifs?</label>
                            <textarea id="risques" rows="2" class="w-full px-3 py-2 text-sm border rounded-lg"
                                      placeholder="Identifiez les risques potentiels..."><?= htmlspecialchars($etape5['risques_identifies'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-amber-700 mb-2">Comment allez-vous utiliser les donnees collectees?</label>
                            <div class="space-y-2">
                                <?php
                                $utilisations = $etape5['utilisation_donnees'] ?? [];
                                $options = [
                                    'ameliorer_pratiques' => 'Ameliorer nos pratiques',
                                    'rendre_compte_financeurs' => 'Rendre compte aux financeurs',
                                    'communiquer_public' => 'Communiquer vers le grand public',
                                    'motiver_equipe' => 'Motiver l\'equipe et les benevoles'
                                ];
                                foreach ($options as $value => $label):
                                ?>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="utilisation-checkbox" value="<?= $value ?>"
                                           <?= in_array($value, $utilisations) ? 'checked' : '' ?>>
                                    <span class="text-sm"><?= $label ?></span>
                                </label>
                                <?php endforeach; ?>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" class="utilisation-checkbox" value="autre"
                                           <?= in_array('autre', $utilisations) ? 'checked' : '' ?>>
                                    <span class="text-sm">Autre:</span>
                                    <input type="text" id="utilisation-autre" class="flex-1 px-3 py-1 text-sm border rounded"
                                           value="<?= htmlspecialchars($etape5['utilisation_donnees_autre'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <button onclick="goToStep(4)" class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                        ← Retour
                    </button>
                    <div class="flex gap-3">
                        <button onclick="exportPDF()" class="px-6 py-3 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-colors">
                            📥 Exporter PDF
                        </button>
                        <button onclick="submitFinal()" class="px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                            ✓ Marquer comme termine
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bouton aide -->
    <button onclick="toggleHelp()" class="fixed bottom-6 right-6 bg-indigo-600 text-white w-14 h-14 rounded-full shadow-xl hover:bg-indigo-700 text-2xl font-bold z-40 flex items-center justify-center animate-pulse hover:animate-none" title="Aide">
        ?
    </button>

    <!-- Panneau d'aide -->
    <div id="helpPanel" class="fixed inset-y-0 right-0 w-80 bg-white shadow-xl transform translate-x-full transition-transform duration-300 z-50 overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-900">Aide</h3>
                <button onclick="toggleHelp()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>

            <!-- Définitions -->
            <div class="space-y-4">
                <div class="p-4 bg-blue-50 rounded-lg category-output">
                    <h4 class="font-semibold text-blue-800">OUTPUT (Produit)</h4>
                    <p class="text-sm text-blue-700 mt-1"><?= $definitions['output']['definition'] ?></p>
                    <p class="text-xs text-blue-600 mt-2"><strong>Exemples:</strong> <?= implode(', ', array_slice($definitions['output']['exemples'], 0, 3)) ?></p>
                </div>

                <div class="p-4 bg-emerald-50 rounded-lg category-outcome">
                    <h4 class="font-semibold text-emerald-800">OUTCOME (Effet)</h4>
                    <p class="text-sm text-emerald-700 mt-1"><?= $definitions['outcome']['definition'] ?></p>
                    <p class="text-xs text-emerald-600 mt-2"><strong>Exemples:</strong> <?= implode(', ', array_slice($definitions['outcome']['exemples'], 0, 3)) ?></p>
                </div>

                <div class="p-4 bg-purple-50 rounded-lg category-impact">
                    <h4 class="font-semibold text-purple-800">IMPACT (Changement)</h4>
                    <p class="text-sm text-purple-700 mt-1"><?= $definitions['impact']['definition'] ?></p>
                    <p class="text-xs text-purple-600 mt-2"><strong>Exemples:</strong> <?= implode(', ', array_slice($definitions['impact']['exemples'], 0, 3)) ?></p>
                </div>
            </div>

            <!-- Chaîne de résultats -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h4 class="font-semibold text-gray-800 mb-3">La chaine de resultats</h4>
                <div class="text-xs text-gray-600 space-y-1">
                    <p><strong>INPUTS</strong> → Ressources mobilisees</p>
                    <p><strong>ACTIVITES</strong> → Actions menees</p>
                    <p><strong>OUTPUTS</strong> → Produits directs</p>
                    <p><strong>OUTCOMES</strong> → Changements chez les beneficiaires</p>
                    <p><strong>IMPACT</strong> → Changement societal durable</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Données initiales
        let currentStep = <?= $etapeCourante ?>;
        let etape1Data = <?= json_encode($etape1) ?>;
        let etape2Data = <?= json_encode($etape2) ?>;
        let etape3Data = <?= json_encode($etape3) ?>;
        let etape4Data = <?= json_encode($etape4) ?>;
        let etape5Data = <?= json_encode($etape5) ?>;
        const enonces = <?= json_encode($enonces) ?>;
        const methodes = <?= json_encode($methodes) ?>;

        let saveTimeout = null;

        // Navigation entre étapes
        function goToStep(step) {
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.getElementById('step' + step).classList.add('active');

            document.querySelectorAll('.step-indicator').forEach(el => {
                const s = parseInt(el.dataset.step);
                if (s === step) {
                    el.classList.add('bg-indigo-100', 'text-indigo-700');
                    el.classList.remove('text-gray-500');
                    el.querySelector('span').classList.add('bg-indigo-600', 'text-white');
                    el.querySelector('span').classList.remove('bg-gray-200');
                } else {
                    el.classList.remove('bg-indigo-100', 'text-indigo-700');
                    el.classList.add('text-gray-500');
                    el.querySelector('span').classList.remove('bg-indigo-600', 'text-white');
                    el.querySelector('span').classList.add('bg-gray-200');
                }
            });

            currentStep = step;

            // Générer le contenu dynamique selon l'étape
            if (step === 3) generateIndicateursForm();
            if (step === 4) generatePlanCollecte();
            if (step === 5) generateSynthese();

            saveData();
        }

        function saveAndNext(nextStep) {
            collectCurrentStepData();
            goToStep(nextStep);
        }

        // Collecte des données de l'étape courante
        function collectCurrentStepData() {
            if (currentStep === 2) {
                etape2Data = {
                    projet: {
                        nom: document.getElementById('projet_nom').value,
                        public_cible: document.getElementById('projet_public').value,
                        contexte: document.getElementById('projet_contexte').value
                    },
                    moyens: collectItems('moyens'),
                    activites: collectItems('activites'),
                    outputs: collectItems('outputs'),
                    outcomes: {
                        court_terme: { texte: document.getElementById('outcome_court').value },
                        moyen_terme: { texte: document.getElementById('outcome_moyen').value },
                        long_terme: { texte: document.getElementById('outcome_long').value }
                    },
                    impact: document.getElementById('impact_global').value,
                    hypotheses: collectItems('hypotheses'),
                    completed: true
                };
            } else if (currentStep === 3) {
                collectIndicateursData();
            } else if (currentStep === 4) {
                collectPlanCollecteData();
            } else if (currentStep === 5) {
                collectSyntheseData();
            }
        }

        function collectItems(type) {
            const items = [];
            document.querySelectorAll(`.${type.slice(0, -1)}-input, .${type}-input`).forEach((input, index) => {
                if (input.value.trim()) {
                    items.push({ id: index + 1, texte: input.value.trim() });
                }
            });
            return items.length ? items : [{ id: 1, texte: '' }];
        }

        function addItem(type) {
            const container = document.getElementById(type + '-container');
            const className = type === 'hypotheses' ? 'hypothese-input' : type.slice(0, -1) + '-input';
            const div = document.createElement('div');
            div.className = 'flex gap-2';
            div.innerHTML = `
                <input type="text" class="${className} flex-1 px-3 py-2 text-sm border rounded-lg" placeholder="">
                <button onclick="removeItem(this)" class="text-red-400 hover:text-red-600">×</button>
            `;
            container.appendChild(div);
        }

        function removeItem(btn) {
            const container = btn.parentElement.parentElement;
            if (container.children.length > 1) {
                btn.parentElement.remove();
            }
            triggerSave();
        }

        // Étape 1 - Drag and Drop
        function initDragDrop() {
            const draggables = document.querySelectorAll('.draggable');
            const dropzones = document.querySelectorAll('.drop-zone');

            draggables.forEach(draggable => {
                draggable.addEventListener('dragstart', (e) => {
                    draggable.classList.add('dragging');
                    e.dataTransfer.setData('text/plain', draggable.dataset.id);
                });

                draggable.addEventListener('dragend', () => {
                    draggable.classList.remove('dragging');
                });
            });

            dropzones.forEach(zone => {
                zone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    zone.classList.add('drag-over');
                });

                zone.addEventListener('dragleave', () => {
                    zone.classList.remove('drag-over');
                });

                zone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    zone.classList.remove('drag-over');
                    const id = e.dataTransfer.getData('text/plain');
                    const draggable = document.querySelector(`.draggable[data-id="${id}"]`);
                    if (draggable) {
                        zone.appendChild(draggable);
                        updateScore();
                        triggerSave();
                    }
                });
            });
        }

        function updateScore() {
            let correct = 0;
            let total = enonces.length;

            document.querySelectorAll('.drop-zone').forEach(zone => {
                const category = zone.dataset.category;
                zone.querySelectorAll('.draggable').forEach(item => {
                    if (item.dataset.correct === category) {
                        correct++;
                        item.classList.remove('border-red-300', 'bg-red-50');
                        item.classList.add('border-green-300', 'bg-green-50');
                    }
                });
            });

            document.getElementById('score').textContent = correct + '/' + total;

            // Sauvegarder l'état
            etape1Data.reponses = [];
            document.querySelectorAll('.drop-zone').forEach(zone => {
                const category = zone.dataset.category;
                zone.querySelectorAll('.draggable').forEach(item => {
                    etape1Data.reponses.push({
                        enonce_id: parseInt(item.dataset.id),
                        reponse_participant: category,
                        reponse_correcte: item.dataset.correct,
                        correct: item.dataset.correct === category
                    });
                });
            });
            etape1Data.score = correct;
            etape1Data.score_max = total;
        }

        function verifierEtape1() {
            let allCorrect = true;
            document.querySelectorAll('.drop-zone').forEach(zone => {
                const category = zone.dataset.category;
                zone.querySelectorAll('.draggable').forEach(item => {
                    if (item.dataset.correct === category) {
                        item.classList.remove('border-red-300', 'bg-red-50');
                        item.classList.add('border-green-300', 'bg-green-50');
                    } else {
                        item.classList.remove('border-green-300', 'bg-green-50');
                        item.classList.add('border-red-300', 'bg-red-50');
                        allCorrect = false;
                    }
                });
            });

            etape1Data.completed = true;
            saveData();

            if (allCorrect) {
                alert('Excellent! Toutes vos reponses sont correctes!');
                goToStep(2);
            } else {
                alert('Certaines reponses ne sont pas correctes. Les elements en rouge sont mal classes.');
            }
        }

        // Étape 3 - Indicateurs
        function generateIndicateursForm() {
            const container = document.getElementById('indicateurs-container');
            container.innerHTML = '';

            const outcomes = [
                { ref: 'court_terme', label: 'Court terme', texte: etape2Data?.outcomes?.court_terme?.texte || '' },
                { ref: 'moyen_terme', label: 'Moyen terme', texte: etape2Data?.outcomes?.moyen_terme?.texte || '' },
                { ref: 'long_terme', label: 'Long terme', texte: etape2Data?.outcomes?.long_terme?.texte || '' }
            ].filter(o => o.texte.trim());

            if (outcomes.length === 0) {
                container.innerHTML = '<p class="text-gray-500 italic">Veuillez d\'abord definir vos outcomes a l\'etape 2.</p>';
                return;
            }

            outcomes.forEach((outcome, index) => {
                const existingIndicateurs = (etape3Data?.indicateurs || []).filter(i => i.outcome_ref === outcome.ref);
                const quantiData = existingIndicateurs.find(i => i.type === 'quantitatif') || {};
                const qualiData = existingIndicateurs.find(i => i.type === 'qualitatif') || {};

                const html = `
                    <div class="border rounded-xl p-6 bg-gray-50" data-outcome-ref="${outcome.ref}">
                        <div class="mb-4 pb-4 border-b">
                            <span class="text-xs font-medium text-emerald-600 uppercase">Outcome ${outcome.label}</span>
                            <p class="font-medium text-gray-800 mt-1">"${outcome.texte}"</p>
                        </div>

                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- Quantitatif -->
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h5 class="font-semibold text-blue-800 mb-3">📊 Indicateur QUANTITATIF</h5>
                                <p class="text-xs text-blue-600 mb-3">Ce qu'on peut compter</p>
                                <div class="space-y-3">
                                    <input type="text" class="indic-quanti-libelle w-full px-3 py-2 text-sm border rounded-lg"
                                           placeholder="Ex: % de participants declarant se sentir plus confiants"
                                           value="${quantiData.libelle || ''}">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="text-xs text-gray-600">Valeur cible</label>
                                            <input type="number" class="indic-quanti-cible w-full px-3 py-2 text-sm border rounded-lg"
                                                   placeholder="70" value="${quantiData.valeur_cible || ''}">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-600">Unite</label>
                                            <input type="text" class="indic-quanti-unite w-full px-3 py-2 text-sm border rounded-lg"
                                                   placeholder="%" value="${quantiData.unite || '%'}">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Valeur de depart (baseline)</label>
                                        <input type="number" class="indic-quanti-baseline w-full px-3 py-2 text-sm border rounded-lg"
                                               placeholder="Si connue" value="${quantiData.valeur_baseline || ''}">
                                    </div>
                                </div>
                            </div>

                            <!-- Qualitatif -->
                            <div class="bg-emerald-50 rounded-lg p-4">
                                <h5 class="font-semibold text-emerald-800 mb-3">📝 Indicateur QUALITATIF</h5>
                                <p class="text-xs text-emerald-600 mb-3">Ce qu'on peut observer, decrire</p>
                                <div class="space-y-3">
                                    <input type="text" class="indic-quali-libelle w-full px-3 py-2 text-sm border rounded-lg"
                                           placeholder="Ex: Temoignages des jeunes sur leur evolution"
                                           value="${qualiData.libelle || ''}">
                                    <div>
                                        <label class="text-xs text-gray-600">Signes de changement</label>
                                        <textarea class="indic-quali-signes w-full px-3 py-2 text-sm border rounded-lg" rows="3"
                                                  placeholder="Qu'est-ce qui montrerait que le changement a eu lieu?">${qualiData.signes_de_changement || ''}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', html);
            });

            // Ajouter les listeners pour la sauvegarde auto
            container.querySelectorAll('input, textarea').forEach(el => {
                el.addEventListener('input', triggerSave);
            });
        }

        function collectIndicateursData() {
            const indicateurs = [];
            document.querySelectorAll('#indicateurs-container > div').forEach(block => {
                const outcomeRef = block.dataset.outcomeRef;
                const outcomeTexte = block.querySelector('p.font-medium')?.textContent?.replace(/"/g, '') || '';

                // Quantitatif
                const quantiLibelle = block.querySelector('.indic-quanti-libelle')?.value;
                if (quantiLibelle) {
                    indicateurs.push({
                        id: indicateurs.length + 1,
                        outcome_ref: outcomeRef,
                        outcome_texte: outcomeTexte,
                        type: 'quantitatif',
                        libelle: quantiLibelle,
                        valeur_cible: block.querySelector('.indic-quanti-cible')?.value || null,
                        unite: block.querySelector('.indic-quanti-unite')?.value || '%',
                        valeur_baseline: block.querySelector('.indic-quanti-baseline')?.value || null
                    });
                }

                // Qualitatif
                const qualiLibelle = block.querySelector('.indic-quali-libelle')?.value;
                if (qualiLibelle) {
                    indicateurs.push({
                        id: indicateurs.length + 1,
                        outcome_ref: outcomeRef,
                        outcome_texte: outcomeTexte,
                        type: 'qualitatif',
                        libelle: qualiLibelle,
                        signes_de_changement: block.querySelector('.indic-quali-signes')?.value || ''
                    });
                }
            });

            etape3Data = { indicateurs, completed: indicateurs.length > 0 };
        }

        // Étape 4 - Plan de collecte
        function generatePlanCollecte() {
            collectIndicateursData(); // S'assurer que les données sont à jour

            const container = document.getElementById('plan-collecte-container');
            container.innerHTML = '';

            const indicateurs = etape3Data?.indicateurs || [];

            if (indicateurs.length === 0) {
                container.innerHTML = '<p class="text-gray-500 italic">Veuillez d\'abord definir vos indicateurs a l\'etape 3.</p>';
                return;
            }

            const existingPlan = etape4Data?.plan || [];

            indicateurs.forEach((indic, index) => {
                const planData = existingPlan.find(p => p.indicateur_ref === indic.id) || {};

                const methodesOptions = Object.entries(methodes).map(([key, m]) =>
                    `<option value="${key}" ${planData.methode === key ? 'selected' : ''}>${m.icone} ${m.nom}</option>`
                ).join('');

                const html = `
                    <div class="border rounded-xl p-6 bg-gray-50" data-indicateur-id="${indic.id}">
                        <div class="mb-4 pb-4 border-b">
                            <span class="text-xs font-medium ${indic.type === 'quantitatif' ? 'text-blue-600' : 'text-emerald-600'} uppercase">
                                Indicateur ${indic.type}
                            </span>
                            <p class="font-medium text-gray-800 mt-1">"${indic.libelle}"</p>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Methode de collecte</label>
                                <select class="plan-methode w-full px-3 py-2 text-sm border rounded-lg">
                                    <option value="">-- Choisir --</option>
                                    ${methodesOptions}
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Temps estime (minutes)</label>
                                <input type="number" class="plan-temps w-full px-3 py-2 text-sm border rounded-lg"
                                       placeholder="15" value="${planData.temps_estime_minutes || ''}">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quand collecter?</label>
                            <div class="flex flex-wrap gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="plan-moment" value="avant" ${(planData.moments || []).includes('avant') ? 'checked' : ''}>
                                    <span class="text-sm">Avant (baseline)</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="plan-moment" value="pendant" ${(planData.moments || []).includes('pendant') ? 'checked' : ''}>
                                    <span class="text-sm">Pendant</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="plan-moment" value="apres" ${(planData.moments || []).includes('apres') ? 'checked' : ''}>
                                    <span class="text-sm">Juste apres</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="plan-moment" value="suivi" ${(planData.moments || []).includes('suivi') ? 'checked' : ''}>
                                    <span class="text-sm">Suivi (mois apres)</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Qui collecte?</label>
                                <input type="text" class="plan-qui w-full px-3 py-2 text-sm border rounded-lg"
                                       placeholder="Ex: L'animateur principal" value="${planData.qui_collecte || ''}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Aupres de qui?</label>
                                <input type="text" class="plan-aupres w-full px-3 py-2 text-sm border rounded-lg"
                                       placeholder="Ex: Tous les participants" value="${planData.aupres_de_qui || ''}">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Points d'attention</label>
                            <textarea class="plan-attention w-full px-3 py-2 text-sm border rounded-lg" rows="2"
                                      placeholder="Precautions, adaptations necessaires...">${planData.points_attention || ''}</textarea>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', html);
            });

            // Listeners pour sauvegarde auto et calcul du temps
            container.querySelectorAll('input, select, textarea').forEach(el => {
                el.addEventListener('input', () => {
                    updateTempsTotal();
                    triggerSave();
                });
                el.addEventListener('change', () => {
                    updateTempsTotal();
                    triggerSave();
                });
            });

            updateTempsTotal();

            // Charger le réalisme
            const realisme = etape4Data?.realisme;
            if (realisme) {
                document.querySelector(`input[name="realisme"][value="${realisme}"]`)?.click();
            }
        }

        function updateTempsTotal() {
            let total = 0;
            document.querySelectorAll('.plan-temps').forEach(input => {
                total += parseInt(input.value) || 0;
            });

            const hours = Math.floor(total / 60);
            const mins = total % 60;
            const display = hours > 0 ? `${hours}h${mins > 0 ? mins + 'min' : ''}` : `${mins} minutes`;

            document.getElementById('recap-temps').innerHTML = `
                <p class="font-semibold">Temps total estime: ${display}</p>
                <p class="text-xs text-gray-500 mt-1">Sur la duree du projet</p>
            `;
        }

        function collectPlanCollecteData() {
            const plan = [];
            document.querySelectorAll('#plan-collecte-container > div').forEach(block => {
                const indicateurId = parseInt(block.dataset.indicateurId);
                const moments = [];
                block.querySelectorAll('.plan-moment:checked').forEach(cb => moments.push(cb.value));

                plan.push({
                    indicateur_ref: indicateurId,
                    indicateur_libelle: block.querySelector('p.font-medium')?.textContent?.replace(/"/g, '') || '',
                    methode: block.querySelector('.plan-methode')?.value || '',
                    temps_estime_minutes: parseInt(block.querySelector('.plan-temps')?.value) || 0,
                    moments: moments,
                    qui_collecte: block.querySelector('.plan-qui')?.value || '',
                    aupres_de_qui: block.querySelector('.plan-aupres')?.value || '',
                    points_attention: block.querySelector('.plan-attention')?.value || ''
                });
            });

            const realisme = document.querySelector('input[name="realisme"]:checked')?.value || '';

            let tempsTotal = 0;
            plan.forEach(p => tempsTotal += p.temps_estime_minutes);

            etape4Data = { plan, temps_total_estime_minutes: tempsTotal, realisme, completed: plan.length > 0 };
        }

        // Étape 5 - Synthèse
        function generateSynthese() {
            collectIndicateursData();
            collectPlanCollecteData();

            // Fiche synthèse
            const ficheContainer = document.getElementById('synthese-fiche');
            ficheContainer.innerHTML = `
                <h3 class="font-semibold text-indigo-800 mb-3">📋 Fiche Synthese</h3>
                <div class="space-y-2 text-sm">
                    <p><strong>Projet:</strong> ${etape2Data?.projet?.nom || '-'}</p>
                    <p><strong>Public:</strong> ${etape2Data?.projet?.public_cible || '-'}</p>
                    <p><strong>Impact vise:</strong> ${etape2Data?.impact || '-'}</p>
                </div>
            `;

            // Tableau de bord
            const tableBody = document.getElementById('synthese-tableau-body');
            tableBody.innerHTML = '';

            const indicateurs = etape3Data?.indicateurs || [];
            const plan = etape4Data?.plan || [];

            indicateurs.forEach(indic => {
                const planItem = plan.find(p => p.indicateur_ref === indic.id) || {};
                const methodeInfo = methodes[planItem.methode] || {};
                const moments = (planItem.moments || []).join(', ');

                const row = `
                    <tr>
                        <td class="border p-2 text-xs">${indic.outcome_texte?.substring(0, 50)}...</td>
                        <td class="border p-2 text-xs">${indic.libelle}</td>
                        <td class="border p-2 text-center text-xs">${indic.valeur_baseline || '-'}</td>
                        <td class="border p-2 text-center text-xs">${indic.valeur_cible || '-'}${indic.unite || ''}</td>
                        <td class="border p-2 text-xs">${methodeInfo.nom || '-'}</td>
                        <td class="border p-2 text-xs">${moments || '-'}</td>
                    </tr>
                `;
                tableBody.insertAdjacentHTML('beforeend', row);
            });

            // Listeners
            document.querySelectorAll('#step5 input, #step5 textarea').forEach(el => {
                el.addEventListener('input', triggerSave);
            });
        }

        function addEtapeAction() {
            const container = document.getElementById('prochaines-etapes-container');
            const div = document.createElement('div');
            div.className = 'flex flex-wrap gap-2 items-center bg-white p-3 rounded-lg';
            div.innerHTML = `
                <input type="text" class="etape-action flex-1 min-w-[200px] px-3 py-2 text-sm border rounded-lg" placeholder="Action a realiser...">
                <input type="text" class="etape-responsable w-32 px-3 py-2 text-sm border rounded-lg" placeholder="Responsable">
                <input type="date" class="etape-echeance px-3 py-2 text-sm border rounded-lg">
                <button onclick="removeItem(this)" class="text-red-400 hover:text-red-600">×</button>
            `;
            container.appendChild(div);
        }

        function collectSyntheseData() {
            const prochaines = [];
            document.querySelectorAll('#prochaines-etapes-container > div').forEach((div, index) => {
                const action = div.querySelector('.etape-action')?.value;
                if (action) {
                    prochaines.push({
                        id: index + 1,
                        action: action,
                        responsable: div.querySelector('.etape-responsable')?.value || '',
                        echeance: div.querySelector('.etape-echeance')?.value || ''
                    });
                }
            });

            const utilisations = [];
            document.querySelectorAll('.utilisation-checkbox:checked').forEach(cb => {
                utilisations.push(cb.value);
            });

            etape5Data = {
                prochaines_etapes: prochaines,
                risques_identifies: document.getElementById('risques')?.value || '',
                utilisation_donnees: utilisations,
                utilisation_donnees_autre: document.getElementById('utilisation-autre')?.value || '',
                completed: true
            };
        }

        // Sauvegarde
        function triggerSave() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveData, 1000);
            document.getElementById('saveStatus').textContent = 'Modification...';
        }

        function saveData() {
            collectCurrentStepData();

            const data = {
                etape_courante: currentStep,
                etape1_classification: JSON.stringify(etape1Data),
                etape2_theorie_changement: JSON.stringify(etape2Data),
                etape3_indicateurs: JSON.stringify(etape3Data),
                etape4_plan_collecte: JSON.stringify(etape4Data),
                etape5_synthese: JSON.stringify(etape5Data)
            };

            fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    document.getElementById('saveStatus').textContent = 'Sauvegarde ✓';
                    setTimeout(() => {
                        document.getElementById('saveStatus').textContent = '';
                    }, 2000);
                }
            })
            .catch(err => {
                document.getElementById('saveStatus').textContent = 'Erreur de sauvegarde';
            });
        }

        function submitFinal() {
            collectCurrentStepData();

            if (!confirm('Marquer votre travail comme termine? Vous pourrez toujours le modifier.')) return;

            fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    etape_courante: 5,
                    etape1_classification: JSON.stringify(etape1Data),
                    etape2_theorie_changement: JSON.stringify(etape2Data),
                    etape3_indicateurs: JSON.stringify(etape3Data),
                    etape4_plan_collecte: JSON.stringify(etape4Data),
                    etape5_synthese: JSON.stringify(etape5Data),
                    is_submitted: 1
                })
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    alert('Travail marque comme termine!');
                    location.reload();
                }
            });
        }

        // Export PDF
        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            let y = 20;

            doc.setFontSize(18);
            doc.text('Cadre de Mesure d\'Impact', 105, y, { align: 'center' });
            y += 15;

            doc.setFontSize(12);
            doc.text(`Projet: ${etape2Data?.projet?.nom || '-'}`, 20, y);
            y += 8;
            doc.text(`Public: ${etape2Data?.projet?.public_cible || '-'}`, 20, y);
            y += 8;
            doc.text(`Impact vise: ${etape2Data?.impact || '-'}`, 20, y);
            y += 15;

            doc.setFontSize(14);
            doc.text('Indicateurs', 20, y);
            y += 10;

            doc.setFontSize(10);
            (etape3Data?.indicateurs || []).forEach(indic => {
                if (y > 270) { doc.addPage(); y = 20; }
                doc.text(`- ${indic.libelle} (${indic.type})`, 25, y);
                y += 6;
            });

            doc.save('cadre-mesure-impact.pdf');
        }

        // Aide
        function toggleHelp() {
            const panel = document.getElementById('helpPanel');
            panel.classList.toggle('translate-x-full');
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', () => {
            initDragDrop();

            // Restaurer les énoncés classés
            if (etape1Data?.reponses) {
                etape1Data.reponses.forEach(rep => {
                    const item = document.querySelector(`.draggable[data-id="${rep.enonce_id}"]`);
                    const zone = document.getElementById(`dropzone-${rep.reponse_participant}`);
                    if (item && zone) {
                        zone.appendChild(item);
                    }
                });
                updateScore();
            }

            // Auto-save sur les champs de l'étape 2
            document.querySelectorAll('#step2 input, #step2 textarea').forEach(el => {
                el.addEventListener('input', triggerSave);
            });

            // Réalisme listeners
            document.querySelectorAll('.realisme-radio').forEach(radio => {
                radio.addEventListener('change', triggerSave);
            });
        });
    </script>
</body>
</html>
