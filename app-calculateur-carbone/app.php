<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

$user = getLoggedUser();
if (!$user || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$sessionId = $_SESSION['current_session_id'];
$sessionCode = $_SESSION['current_session_code'] ?? '';
$sessionNom = $_SESSION['current_session_nom'] ?? '';
$participantId = $_SESSION['participant_id'] ?? 0;
$userName = $user['prenom'] ?? $user['username'];

// Charger les estimations
$estimations = getEstimations();
$categories = $estimations['categories'] ?? [];
$useCases = $estimations['use_cases'] ?? [];

// Charger les calculs existants du participant
$stmt = $db->prepare("SELECT * FROM calculs WHERE session_id = ? AND user_id = ? ORDER BY created_at DESC");
$stmt->execute([$sessionId, $user['id']]);
$mesCalculs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer le total CO2 du participant
$totalCO2 = array_sum(array_column($mesCalculs, 'co2_total'));

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .category-btn.active { ring-width: 2px; }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-emerald-600 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold"><?= h(APP_NAME) ?></h1>
                    <p class="text-emerald-200 text-sm"><?= t('carbon.session') ?>: <?= h($sessionNom) ?> (<?= h($sessionCode) ?>)</p>
                </div>
                <div class="flex items-center gap-4">
                    <?= renderLanguageSelector('text-sm border border-emerald-400 rounded px-2 py-1 bg-emerald-700') ?>
                    <span class="text-emerald-200"><?= h($userName) ?></span>
                    <?php if (isFormateur()): ?>
                    <a href="formateur.php" class="bg-emerald-700 hover:bg-emerald-800 px-3 py-1 rounded text-sm">
                        <?= t('trainer.title') ?>
                    </a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-emerald-700 hover:bg-emerald-800 px-3 py-1 rounded text-sm">
                        <?= t('carbon.logout') ?>
                    </a>
                </div>
            </div>
        </div>
    </header>
    <?= renderLanguageScript() ?>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Colonne gauche: Calculateur -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Selection categorie -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= t('carbon.step1_title') ?></h2>
                    <div class="grid grid-cols-3 md:grid-cols-5 gap-2">
                        <?php foreach ($categories as $catId => $cat): ?>
                        <button type="button"
                                onclick="selectCategory('<?= $catId ?>')"
                                id="cat-<?= $catId ?>"
                                class="category-btn p-3 rounded-lg border-2 border-gray-200 hover:border-<?= $cat['color'] ?>-400 transition-all text-center">
                            <span class="text-2xl block"><?= $cat['icon'] ?></span>
                            <span class="text-xs text-gray-600 mt-1 block"><?= h($cat['nom']) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Selection cas d'usage -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= t('carbon.step2_title') ?></h2>
                    <div id="useCasesContainer" class="space-y-2">
                        <p class="text-gray-500 text-center py-8"><?= t('carbon.select_category_first') ?></p>
                    </div>
                </div>

                <!-- Configuration frequence -->
                <div class="bg-white rounded-xl shadow-md p-6" id="configPanel" style="display: none;">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= t('carbon.step3_title') ?></h2>

                    <div class="grid md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2"><?= t('carbon.frequency') ?></label>
                            <select id="frequence" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-emerald-500">
                                <option value="ponctuel"><?= t('carbon.freq_once') ?></option>
                                <option value="quotidien"><?= t('carbon.freq_daily') ?></option>
                                <option value="hebdomadaire"><?= t('carbon.freq_weekly') ?></option>
                                <option value="mensuel"><?= t('carbon.freq_monthly') ?></option>
                                <option value="trimestriel"><?= t('carbon.freq_quarterly') ?></option>
                                <option value="annuel"><?= t('carbon.freq_yearly') ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2"><?= t('carbon.quantity_per_occurrence') ?></label>
                            <input type="number" id="quantite" value="1" min="1" max="100"
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-emerald-500">
                        </div>
                    </div>

                    <!-- Resultat calcul -->
                    <div id="resultatCalcul" class="bg-emerald-50 rounded-lg p-4 mb-4" style="display: none;">
                        <div class="text-center">
                            <p class="text-sm text-emerald-600 mb-1"><?= t('carbon.estimated_annual_impact') ?></p>
                            <p class="text-4xl font-bold text-emerald-700" id="co2Result">0</p>
                            <p class="text-emerald-600" id="co2Unit"><?= t('carbon.grams_co2') ?></p>
                        </div>
                        <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-2 text-center text-xs" id="equivalents"></div>
                    </div>

                    <button onclick="ajouterCalcul()"
                            class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition-colors">
                        <?= t('carbon.add_to_balance') ?>
                    </button>
                </div>
            </div>

            <!-- Colonne droite: Mon bilan -->
            <div class="space-y-6">
                <!-- Total personnel -->
                <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl shadow-md p-6 text-white">
                    <h3 class="text-lg font-semibold mb-2"><?= t('carbon.my_annual_footprint') ?></h3>
                    <p class="text-5xl font-bold" id="totalCO2"><?= number_format($totalCO2, 0, ',', ' ') ?></p>
                    <p class="text-emerald-200"><?= t('carbon.grams_co2_year') ?></p>
                    <div class="mt-4 pt-4 border-t border-emerald-400 text-sm">
                        <p class="flex justify-between"><span><?= t('carbon.in_kg') ?>:</span><span id="totalKg"><?= number_format($totalCO2/1000, 2, ',', ' ') ?></span></p>
                        <p class="flex justify-between"><span><?= t('carbon.km_car') ?>:</span><span id="totalKm"><?= round($totalCO2/1000/0.21, 1) ?></span></p>
                    </div>
                    <a href="export.php?type=participant" class="mt-4 block w-full py-2 bg-white/20 hover:bg-white/30 text-center rounded-lg text-sm transition-colors">
                        <?= t('carbon.export_balance') ?>
                    </a>
                </div>

                <!-- Liste des calculs -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= t('carbon.my_usages') ?></h3>
                    <div id="mesUsages" class="space-y-2 max-h-96 overflow-y-auto">
                        <?php if (empty($mesCalculs)): ?>
                        <p class="text-gray-500 text-center py-4 text-sm"><?= t('carbon.no_usage_recorded') ?></p>
                        <?php else: ?>
                            <?php foreach ($mesCalculs as $calc):
                                $uc = $useCases[$calc['use_case_id']] ?? null;
                            ?>
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded text-sm">
                                <div>
                                    <p class="font-medium"><?= h($uc['nom'] ?? $calc['use_case_id']) ?></p>
                                    <p class="text-xs text-gray-500"><?= h($calc['frequence']) ?> x<?= $calc['quantite'] ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-emerald-600"><?= number_format($calc['co2_total'], 0, ',', ' ') ?>g</p>
                                    <button onclick="supprimerCalcul(<?= $calc['id'] ?>)" class="text-xs text-red-500 hover:text-red-700"><?= t('carbon.delete') ?></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Legende -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm">
                    <h4 class="font-semibold text-amber-800 mb-2"><?= t('carbon.good_to_know') ?></h4>
                    <ul class="text-amber-700 space-y-1 text-xs">
                        <li><?= t('carbon.info_ecologits') ?></li>
                        <li><?= t('carbon.info_email') ?></li>
                        <li><?= t('carbon.info_car') ?></li>
                        <li><?= t('carbon.info_streaming') ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
    const useCases = <?= json_encode($useCases) ?>;
    const categories = <?= json_encode($categories) ?>;
    const T = {
        no_use_case: <?= json_encode(t('carbon.no_use_case_in_category')) ?>,
        kg_co2_year: <?= json_encode(t('carbon.kg_co2_year')) ?>,
        grams_co2_year: <?= json_encode(t('carbon.grams_co2_year')) ?>,
        km_car: <?= json_encode(t('carbon.km_car_short')) ?>,
        emails: <?= json_encode(t('carbon.emails')) ?>,
        streaming: <?= json_encode(t('carbon.streaming_hours')) ?>,
        phone_charges: <?= json_encode(t('carbon.phone_charges')) ?>,
        coffee_cups: <?= json_encode(t('carbon.coffee_cups')) ?>,
        add_error: <?= json_encode(t('carbon.error_adding')) ?>,
        confirm_delete: <?= json_encode(t('carbon.delete_usage')) ?>
    };
    let selectedCategory = null;
    let selectedUseCase = null;

    function selectCategory(catId) {
        selectedCategory = catId;
        selectedUseCase = null;

        // UI update
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50');
        });
        document.getElementById('cat-' + catId).classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50');

        // Filter use cases
        const container = document.getElementById('useCasesContainer');
        const filtered = Object.entries(useCases).filter(([id, uc]) => uc.categorie === catId);

        if (filtered.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">' + T.no_use_case + '</p>';
            return;
        }

        container.innerHTML = filtered.map(([id, uc]) => `
            <button onclick="selectUseCase('${id}')" id="uc-${id}"
                    class="use-case-btn w-full p-3 text-left rounded-lg border-2 border-gray-200 hover:border-emerald-400 transition-all">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-medium text-gray-800">${uc.nom}</p>
                        <p class="text-sm text-gray-500">${uc.description}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-emerald-600 font-semibold">${uc.co2_grammes}g</span>
                        <p class="text-xs text-gray-400">${uc.modele_type}</p>
                    </div>
                </div>
            </button>
        `).join('');

        document.getElementById('configPanel').style.display = 'none';
    }

    function selectUseCase(ucId) {
        selectedUseCase = ucId;

        // UI update
        document.querySelectorAll('.use-case-btn').forEach(btn => {
            btn.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50');
        });
        document.getElementById('uc-' + ucId).classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50');

        document.getElementById('configPanel').style.display = 'block';
        calculerImpact();
    }

    function calculerImpact() {
        if (!selectedUseCase) return;

        const uc = useCases[selectedUseCase];
        const frequence = document.getElementById('frequence').value;
        const quantite = parseInt(document.getElementById('quantite').value) || 1;

        const multiplicateurs = {
            'ponctuel': 1,
            'quotidien': 250,
            'hebdomadaire': 52,
            'mensuel': 12,
            'trimestriel': 4,
            'annuel': 1
        };

        const co2Total = uc.co2_grammes * multiplicateurs[frequence] * quantite;

        // Afficher resultat
        document.getElementById('resultatCalcul').style.display = 'block';

        if (co2Total >= 1000) {
            document.getElementById('co2Result').textContent = (co2Total / 1000).toFixed(2);
            document.getElementById('co2Unit').textContent = T.kg_co2_year;
        } else {
            document.getElementById('co2Result').textContent = Math.round(co2Total);
            document.getElementById('co2Unit').textContent = T.grams_co2_year;
        }

        // Equivalents
        const eq = {
            km_voiture: (co2Total / 1000 / 0.21).toFixed(1),
            emails: Math.round(co2Total / 4),
            streaming: (co2Total / 36).toFixed(1),
            charges: Math.round(co2Total / 8.3),
            cafes: (co2Total / 21).toFixed(1)
        };

        document.getElementById('equivalents').innerHTML = `
            <div class="bg-white p-2 rounded"><span class="block text-lg">${eq.km_voiture}</span>${T.km_car}</div>
            <div class="bg-white p-2 rounded"><span class="block text-lg">${eq.emails}</span>${T.emails}</div>
            <div class="bg-white p-2 rounded"><span class="block text-lg">${eq.streaming}</span>${T.streaming}</div>
            <div class="bg-white p-2 rounded"><span class="block text-lg">${eq.charges}</span>${T.phone_charges}</div>
            <div class="bg-white p-2 rounded"><span class="block text-lg">${eq.cafes}</span>${T.coffee_cups}</div>
        `;
    }

    async function ajouterCalcul() {
        if (!selectedUseCase) return;

        const frequence = document.getElementById('frequence').value;
        const quantite = parseInt(document.getElementById('quantite').value) || 1;

        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_calcul',
                use_case_id: selectedUseCase,
                frequence: frequence,
                quantite: quantite
            })
        });

        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || T.add_error);
        }
    }

    async function supprimerCalcul(id) {
        if (!confirm(T.confirm_delete)) return;

        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_calcul',
                calcul_id: id
            })
        });

        const data = await response.json();
        if (data.success) {
            location.reload();
        }
    }

    // Event listeners
    document.getElementById('frequence').addEventListener('change', calculerImpact);
    document.getElementById('quantite').addEventListener('input', calculerImpact);
    </script>
</body>
</html>
