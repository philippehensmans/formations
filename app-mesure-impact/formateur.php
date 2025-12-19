<?php
session_start();
require_once 'config/database.php';

$db = getDB();
$error = '';
$success = '';

// Gestion de la connexion formateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $password = $_POST['password'] ?? '';

        $stmt = $db->prepare("SELECT * FROM sessions WHERE code = ?");
        $stmt->execute([$code]);
        $session = $stmt->fetch();

        if ($session && ($session['mot_de_passe'] === $password || empty($session['mot_de_passe']))) {
            $_SESSION['formateur_session_id'] = $session['id'];
            $_SESSION['formateur_session_code'] = $session['code'];
        } else {
            $error = "Code ou mot de passe incorrect.";
        }
    } elseif ($_POST['action'] === 'create_session') {
        $nom = trim($_POST['nom'] ?? '');
        $formateur = trim($_POST['formateur_nom'] ?? '');
        $password = $_POST['mot_de_passe'] ?? 'Formation2024!';

        if (empty($nom)) {
            $error = "Le nom de la session est obligatoire.";
        } else {
            $code = generateSessionCode();
            $stmt = $db->prepare("INSERT INTO sessions (code, nom, formateur_nom, mot_de_passe) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $nom, $formateur, $password]);
            $_SESSION['formateur_session_id'] = $db->lastInsertId();
            $_SESSION['formateur_session_code'] = $code;
            $success = "Session creee avec le code: $code";
        }
    } elseif ($_POST['action'] === 'logout') {
        unset($_SESSION['formateur_session_id']);
        unset($_SESSION['formateur_session_code']);
    }
}

// Récupérer la session active
$currentSession = null;
$participants = [];
$stats = [];

if (isset($_SESSION['formateur_session_id'])) {
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$_SESSION['formateur_session_id']]);
    $currentSession = $stmt->fetch();

    if ($currentSession) {
        // Récupérer les participants avec leurs données
        $stmt = $db->prepare("
            SELECT p.*, m.etape_courante, m.etape1_classification, m.etape2_theorie_changement,
                   m.etape3_indicateurs, m.etape4_plan_collecte, m.etape5_synthese,
                   m.completion_percent, m.is_submitted, m.updated_at
            FROM participants p
            LEFT JOIN mesure_impact m ON p.id = m.participant_id
            WHERE p.session_id = ?
            ORDER BY p.nom, p.prenom
        ");
        $stmt->execute([$currentSession['id']]);
        $participants = $stmt->fetchAll();

        // Calculer les statistiques
        $stats = [
            'total' => count($participants),
            'etapes' => [0, 0, 0, 0, 0],
            'submitted' => 0,
            'score_etape1' => [],
            'erreurs_etape1' => [],
            'methodes_collecte' => [],
            'outcomes_mots' => []
        ];

        foreach ($participants as $p) {
            $etape = $p['etape_courante'] ?? 1;
            if ($etape >= 1 && $etape <= 5) {
                $stats['etapes'][$etape - 1]++;
            }
            if ($p['is_submitted']) {
                $stats['submitted']++;
            }

            // Analyser étape 1
            if ($p['etape1_classification']) {
                $e1 = json_decode($p['etape1_classification'], true);
                if (isset($e1['score'])) {
                    $stats['score_etape1'][] = $e1['score'];
                }
                // Analyser les erreurs
                if (isset($e1['reponses'])) {
                    foreach ($e1['reponses'] as $rep) {
                        if (!$rep['correct']) {
                            $key = $rep['enonce_id'];
                            if (!isset($stats['erreurs_etape1'][$key])) {
                                $stats['erreurs_etape1'][$key] = 0;
                            }
                            $stats['erreurs_etape1'][$key]++;
                        }
                    }
                }
            }

            // Analyser étape 2 (outcomes pour nuage de mots)
            if ($p['etape2_theorie_changement']) {
                $e2 = json_decode($p['etape2_theorie_changement'], true);
                foreach (['court_terme', 'moyen_terme', 'long_terme'] as $temporalite) {
                    if (!empty($e2['outcomes'][$temporalite]['texte'])) {
                        $words = preg_split('/\s+/', strtolower($e2['outcomes'][$temporalite]['texte']));
                        foreach ($words as $word) {
                            $word = trim($word, '.,;:!?()[]{}"\'-');
                            if (strlen($word) > 4) {
                                if (!isset($stats['outcomes_mots'][$word])) {
                                    $stats['outcomes_mots'][$word] = 0;
                                }
                                $stats['outcomes_mots'][$word]++;
                            }
                        }
                    }
                }
            }

            // Analyser étape 4 (méthodes de collecte)
            if ($p['etape4_plan_collecte']) {
                $e4 = json_decode($p['etape4_plan_collecte'], true);
                if (isset($e4['plan'])) {
                    foreach ($e4['plan'] as $plan) {
                        if (!empty($plan['methode'])) {
                            if (!isset($stats['methodes_collecte'][$plan['methode']])) {
                                $stats['methodes_collecte'][$plan['methode']] = 0;
                            }
                            $stats['methodes_collecte'][$plan['methode']]++;
                        }
                    }
                }
            }
        }

        // Trier les mots par fréquence
        arsort($stats['outcomes_mots']);
        $stats['outcomes_mots'] = array_slice($stats['outcomes_mots'], 0, 20);
    }
}

// Récupérer les énoncés pour afficher les erreurs
$enonces = getEnonces($currentSession['id'] ?? null);
$enoncesById = [];
foreach ($enonces as $e) {
    $enoncesById[$e['id']] = $e;
}

$methodes = getMethodesCollecte();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formateur - Mesure d'Impact Social</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
    <?php if (!$currentSession): ?>
    <!-- Page de connexion -->
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-xl mb-4">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Espace Formateur</h1>
                <p class="text-gray-600">Mesure d'Impact Social</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm p-6 mb-4">
                <h2 class="font-semibold text-gray-800 mb-4">Rejoindre une session existante</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Code de session</label>
                        <input type="text" name="code" required
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 uppercase"
                               placeholder="ABC123">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input type="password" name="password"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                               placeholder="Mot de passe formateur">
                    </div>
                    <button type="submit" class="w-full py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Acceder
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold text-gray-800 mb-4">Creer une nouvelle session</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create_session">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom de la session</label>
                        <input type="text" name="nom" required
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                               placeholder="Ex: Formation Impact - Janvier 2025">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Votre nom</label>
                        <input type="text" name="formateur_nom"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                               placeholder="Ex: Marie Dupont">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe formateur</label>
                        <input type="password" name="mot_de_passe" value="Formation2024!"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <p class="text-xs text-gray-500 mt-1">Par defaut: Formation2024!</p>
                    </div>
                    <button type="submit" class="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Creer la session
                    </button>
                </form>
            </div>

            <div class="mt-6 text-center">
                <a href="admin_sessions.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                    Administration des sessions
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Dashboard formateur -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900"><?= htmlspecialchars($currentSession['nom']) ?></h1>
                        <p class="text-sm text-gray-500">Code: <span class="font-mono font-bold text-indigo-600"><?= $currentSession['code'] ?></span></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <button onclick="exportExcel()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                        📥 Exporter Excel
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
                            Deconnexion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <!-- Statistiques globales -->
        <div class="grid md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-3xl font-bold text-indigo-600"><?= $stats['total'] ?></div>
                <div class="text-sm text-gray-600">Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-3xl font-bold text-green-600"><?= $stats['submitted'] ?></div>
                <div class="text-sm text-gray-600">Termines</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-3xl font-bold text-blue-600">
                    <?= count($stats['score_etape1']) ? round(array_sum($stats['score_etape1']) / count($stats['score_etape1']), 1) : '-' ?>
                </div>
                <div class="text-sm text-gray-600">Score moyen Etape 1</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-3xl font-bold text-purple-600">
                    <?= count($stats['score_etape1']) ? count($enonces) : '-' ?>
                </div>
                <div class="text-sm text-gray-600">Enonces a classer</div>
            </div>
        </div>

        <!-- Progression par étape -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Progression par etape</h2>
            <div class="space-y-3">
                <?php
                $stepNames = ['Classification', 'Theorie du changement', 'Indicateurs', 'Plan de collecte', 'Synthese'];
                for ($i = 0; $i < 5; $i++):
                    $count = 0;
                    foreach ($participants as $p) {
                        if (($p['etape_courante'] ?? 1) > $i) $count++;
                    }
                    $percent = $stats['total'] > 0 ? round($count / $stats['total'] * 100) : 0;
                ?>
                <div class="flex items-center gap-4">
                    <div class="w-40 text-sm text-gray-600">Etape <?= $i + 1 ?> - <?= $stepNames[$i] ?></div>
                    <div class="flex-1 bg-gray-200 rounded-full h-4">
                        <div class="bg-indigo-600 h-4 rounded-full transition-all" style="width: <?= $percent ?>%"></div>
                    </div>
                    <div class="w-20 text-sm text-gray-600 text-right"><?= $count ?>/<?= $stats['total'] ?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <!-- Erreurs fréquentes étape 1 -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold text-gray-800 mb-4">Erreurs frequentes (Etape 1)</h2>
                <?php if (empty($stats['erreurs_etape1'])): ?>
                    <p class="text-gray-500 italic">Pas encore de donnees</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php
                        arsort($stats['erreurs_etape1']);
                        $top5 = array_slice($stats['erreurs_etape1'], 0, 5, true);
                        foreach ($top5 as $enonceId => $errCount):
                            $enonce = $enoncesById[$enonceId] ?? null;
                            if (!$enonce) continue;
                        ?>
                        <div class="p-3 bg-red-50 rounded-lg">
                            <p class="text-sm text-gray-700">"<?= htmlspecialchars(substr($enonce['texte'], 0, 80)) ?>..."</p>
                            <p class="text-xs text-red-600 mt-1">
                                <?= $errCount ?> erreur(s) - Reponse correcte: <strong><?= strtoupper($enonce['categorie_correcte']) ?></strong>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Méthodes de collecte choisies -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold text-gray-800 mb-4">Methodes de collecte choisies</h2>
                <?php if (empty($stats['methodes_collecte'])): ?>
                    <p class="text-gray-500 italic">Pas encore de donnees</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php
                        $totalMethodes = array_sum($stats['methodes_collecte']);
                        arsort($stats['methodes_collecte']);
                        foreach ($stats['methodes_collecte'] as $methode => $count):
                            $percent = round($count / $totalMethodes * 100);
                            $methodeInfo = $methodes[$methode] ?? ['nom' => $methode, 'icone' => '📋'];
                        ?>
                        <div class="flex items-center gap-3">
                            <span class="text-lg"><?= $methodeInfo['icone'] ?></span>
                            <div class="flex-1">
                                <div class="flex justify-between text-sm">
                                    <span><?= $methodeInfo['nom'] ?></span>
                                    <span class="text-gray-500"><?= $percent ?>%</span>
                                </div>
                                <div class="bg-gray-200 rounded-full h-2 mt-1">
                                    <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= $percent ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nuage de mots outcomes -->
        <?php if (!empty($stats['outcomes_mots'])): ?>
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Mots-cles des outcomes</h2>
            <div class="flex flex-wrap gap-2">
                <?php
                $maxCount = max($stats['outcomes_mots']);
                foreach ($stats['outcomes_mots'] as $word => $count):
                    $size = 0.8 + ($count / $maxCount) * 1.2;
                    $opacity = 0.5 + ($count / $maxCount) * 0.5;
                ?>
                <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full"
                      style="font-size: <?= $size ?>rem; opacity: <?= $opacity ?>">
                    <?= htmlspecialchars($word) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste des participants -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="font-semibold text-gray-800 mb-4">Liste des participants</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-2">Nom</th>
                            <th class="text-left py-3 px-2">Organisation</th>
                            <th class="text-center py-3 px-2">Etape</th>
                            <th class="text-center py-3 px-2">Score E1</th>
                            <th class="text-center py-3 px-2">Completion</th>
                            <th class="text-center py-3 px-2">Statut</th>
                            <th class="text-center py-3 px-2">Derniere modif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $p):
                            $e1 = json_decode($p['etape1_classification'] ?: '{}', true);
                            $score = isset($e1['score']) ? $e1['score'] . '/' . ($e1['score_max'] ?? count($enonces)) : '-';
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-2 font-medium"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></td>
                            <td class="py-3 px-2 text-gray-600"><?= htmlspecialchars($p['organisation'] ?? '-') ?></td>
                            <td class="py-3 px-2 text-center">
                                <span class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">
                                    <?= $p['etape_courante'] ?? 1 ?>/5
                                </span>
                            </td>
                            <td class="py-3 px-2 text-center"><?= $score ?></td>
                            <td class="py-3 px-2 text-center">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-indigo-600 h-2 rounded-full" style="width: <?= $p['completion_percent'] ?? 0 ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-500"><?= $p['completion_percent'] ?? 0 ?>%</span>
                            </td>
                            <td class="py-3 px-2 text-center">
                                <?php if ($p['is_submitted']): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Termine</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-amber-100 text-amber-700 rounded-full text-xs">En cours</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-2 text-center text-gray-500">
                                <?= $p['updated_at'] ? date('H:i', strtotime($p['updated_at'])) : '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($participants)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-500">
                                Aucun participant pour le moment
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const participants = <?= json_encode($participants) ?>;

        function exportExcel() {
            const data = [
                ['Nom', 'Prenom', 'Organisation', 'Etape', 'Score E1', 'Completion %', 'Statut', 'Projet', 'Impact vise']
            ];

            participants.forEach(p => {
                const e1 = JSON.parse(p.etape1_classification || '{}');
                const e2 = JSON.parse(p.etape2_theorie_changement || '{}');

                data.push([
                    p.nom,
                    p.prenom,
                    p.organisation || '',
                    (p.etape_courante || 1) + '/5',
                    e1.score ? e1.score + '/' + (e1.score_max || 12) : '-',
                    (p.completion_percent || 0) + '%',
                    p.is_submitted ? 'Termine' : 'En cours',
                    e2.projet?.nom || '',
                    e2.impact || ''
                ]);
            });

            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Participants');
            XLSX.writeFile(wb, 'mesure-impact-export.xlsx');
        }

        // Auto-refresh toutes les 30 secondes
        setTimeout(() => location.reload(), 30000);
    </script>
    <?php endif; ?>
</body>
</html>
