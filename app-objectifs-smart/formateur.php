<?php
/**
 * Interface Formateur - Objectifs SMART
 */
require_once 'config/database.php';
requireFormateur();

$db = getDB();
$sessionId = $_SESSION['formateur_session_id'];
$sessionCode = $_SESSION['formateur_session_code'];
$sessionNom = $_SESSION['formateur_session_nom'];

$stmt = $db->prepare("
    SELECT p.*, o.etape_courante, o.etape1_analyses, o.etape2_reformulations, o.etape3_creations,
           o.completion_percent, o.is_submitted, o.updated_at as smart_updated
    FROM participants p
    LEFT JOIN objectifs_smart o ON p.id = o.participant_id
    WHERE p.session_id = ?
    ORDER BY p.nom, p.prenom
");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();

$smartHelp = getSmartHelp();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formateur - Objectifs SMART</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .smart-letter { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 50%; font-size: 11px; }
        .smart-S { background: #fef3c7; color: #d97706; }
        .smart-M { background: #dbeafe; color: #2563eb; }
        .smart-A { background: #dcfce7; color: #16a34a; }
        .smart-R { background: #fce7f3; color: #db2777; }
        .smart-T { background: #e0e7ff; color: #4f46e5; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-emerald-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold">Objectifs SMART - Formateur</h1>
                <p class="text-emerald-200 text-sm"><?= sanitize($sessionCode) ?> - <?= sanitize($sessionNom) ?></p>
            </div>
            <div class="flex gap-3">
                <button onclick="location.reload()" class="bg-emerald-600 hover:bg-emerald-500 px-4 py-2 rounded text-sm">Actualiser</button>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">Deconnexion</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <?php
            $total = count($participants);
            $etape1 = count(array_filter($participants, fn($p) => ($p['etape_courante'] ?? 1) == 1));
            $etape2 = count(array_filter($participants, fn($p) => ($p['etape_courante'] ?? 1) == 2));
            $etape3 = count(array_filter($participants, fn($p) => ($p['etape_courante'] ?? 1) == 3));
            $submitted = count(array_filter($participants, fn($p) => $p['is_submitted']));
            ?>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-emerald-700"><?= $total ?></div>
                <div class="text-gray-500 text-sm">Participants</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-yellow-600"><?= $etape1 ?></div>
                <div class="text-gray-500 text-sm">Etape 1</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-blue-600"><?= $etape2 ?></div>
                <div class="text-gray-500 text-sm">Etape 2</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-purple-600"><?= $etape3 ?></div>
                <div class="text-gray-500 text-sm">Etape 3</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-green-600"><?= $submitted ?></div>
                <div class="text-gray-500 text-sm">Termines</div>
            </div>
        </div>

        <!-- Participants -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b">
                <h2 class="text-lg font-semibold">Participants</h2>
            </div>

            <?php if (empty($participants)): ?>
                <div class="p-8 text-center text-gray-500">Aucun participant</div>
            <?php else: ?>
                <div class="divide-y">
                    <?php foreach ($participants as $p):
                        $etape = $p['etape_courante'] ?? 1;
                        $analyses = json_decode($p['etape1_analyses'] ?: '[]', true);
                        $reforms = json_decode($p['etape2_reformulations'] ?: '[]', true);
                        $creations = json_decode($p['etape3_creations'] ?: '[]', true);
                    ?>
                        <div class="p-4">
                            <div class="flex flex-wrap justify-between items-start gap-4 mb-3">
                                <div>
                                    <h3 class="font-semibold"><?= sanitize($p['prenom']) ?> <?= sanitize($p['nom']) ?></h3>
                                    <?php if ($p['organisation']): ?>
                                        <span class="text-gray-500 text-sm"><?= sanitize($p['organisation']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm">Etape <?= $etape ?>/3</span>
                                    <span class="text-sm text-gray-500"><?= $p['completion_percent'] ?? 0 ?>%</span>
                                    <?php if ($p['is_submitted']): ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">Termine</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Objectifs crees (Etape 3) -->
                            <?php if (!empty($creations)): ?>
                                <div class="mt-3">
                                    <h4 class="text-sm font-medium text-emerald-700 mb-2">Objectifs crees (<?= count($creations) ?>)</h4>
                                    <div class="space-y-2">
                                        <?php foreach ($creations as $c): ?>
                                            <div class="bg-emerald-50 p-3 rounded text-sm">
                                                <div class="flex gap-2 mb-1">
                                                    <span class="bg-emerald-200 text-emerald-800 px-2 py-0.5 rounded text-xs"><?= sanitize($c['contexte'] ?? '') ?></span>
                                                    <?php if (!empty($c['thematique'])): ?>
                                                        <span class="text-gray-500 text-xs"><?= sanitize($c['thematique']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($c['objectif_final'])): ?>
                                                    <p class="text-gray-700"><?= sanitize($c['objectif_final']) ?></p>
                                                <?php else: ?>
                                                    <div class="flex flex-wrap gap-1">
                                                        <?php foreach (['S','M','A','R','T'] as $l): ?>
                                                            <?php if (!empty($c['composantes'][$l])): ?>
                                                                <span class="smart-letter smart-<?= $l ?>"><?= $l ?></span>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Analyses (Etape 1) Resume -->
                            <?php if (!empty($analyses)):
                                $totalScore = 0;
                                $count = 0;
                                foreach ($analyses as $a) {
                                    if (!empty($a['evaluations'])) {
                                        foreach ($a['evaluations'] as $e) {
                                            if (($e['reponse'] ?? '') === 'oui') $totalScore++;
                                        }
                                        $count++;
                                    }
                                }
                            ?>
                                <div class="mt-2 text-xs text-gray-500">
                                    Etape 1: <?= $count ?> objectifs analyses
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
