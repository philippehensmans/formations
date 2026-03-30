<?php
/**
 * Vue en lecture seule - Parties Prenantes
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-parties-prenantes';

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Recuperer le participant
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve");
}

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Acces refuse a cette session.");
}

// Recuperer les infos utilisateur
$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

// Recuperer la cartographie (pas les analyses)
$stmt = $db->prepare("SELECT * FROM cartographie WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$carto = $stmt->fetch();

$stakeholders = $carto ? json_decode($carto['stakeholders_data'] ?? '[]', true) : [];
$categories = getCategories();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parties Prenantes - <?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
        .matrix {
            position: relative;
            width: 100%;
            height: 350px;
            border: 2px solid #000;
            border-radius: 4px;
            background: #f5f5f5;
        }
        .matrix-lines::before, .matrix-lines::after {
            content: '';
            position: absolute;
            background: #000;
        }
        .matrix-lines::before { left: 50%; top: 0; bottom: 0; width: 2px; transform: translateX(-50%); }
        .matrix-lines::after { top: 50%; left: 0; right: 0; height: 2px; transform: translateY(-50%); }
        .quadrant-label {
            position: absolute;
            font-weight: 700;
            color: #666;
            font-size: 0.65rem;
            background: rgba(255,255,255,0.9);
            padding: 3px 6px;
            border-radius: 4px;
        }
        .q1 { top: 6px; right: 6px; }
        .q2 { top: 6px; left: 6px; }
        .q3 { bottom: 6px; left: 6px; }
        .q4 { bottom: 6px; right: 6px; }
        .stakeholder-dot {
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid #fff;
            transform: translate(-50%, -50%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium"><?= h($userInfo['prenom'] ?? '') ?> <?= h($userInfo['nom'] ?? '') ?></span>
                <span class="text-purple-200 text-sm ml-2"><?= h($participant['session_nom']) ?></span>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Cartographie des Parties Prenantes</h1>
            <p class="text-gray-600">Projet: <?= h($carto['titre_projet'] ?? 'Non defini') ?></p>
            <?php if ($carto): ?>
            <div class="flex gap-4 mt-2 text-sm text-gray-500">
                <span>Completion: <strong><?= $carto['completion_percent'] ?>%</strong></span>
                <span>Statut: <strong><?= $carto['is_submitted'] ? 'Soumis' : 'Brouillon' ?></strong></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($stakeholders)): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">
                Aucune partie prenante definie
            </div>
        <?php else: ?>
            <!-- Matrice -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-lg font-bold mb-4">Matrice Influence x Interet</h2>
                <div class="matrix">
                    <div class="matrix-lines"></div>
                    <div class="quadrant-label q1"><strong>Gerer etroitement</strong></div>
                    <div class="quadrant-label q2"><strong>Maintenir satisfait</strong></div>
                    <div class="quadrant-label q3"><strong>Surveiller</strong></div>
                    <div class="quadrant-label q4"><strong>Tenir informe</strong></div>
                    <?php foreach ($stakeholders as $s):
                        $color = $categories[$s['category']]['color'] ?? '#999';
                        $x = (($s['interest'] - 1) / 9) * 100;
                        $y = ((10 - $s['influence']) / 9) * 100;
                    ?>
                        <div class="stakeholder-dot" style="background:<?= $color ?>;left:<?= $x ?>%;top:<?= $y ?>%"
                             title="<?= h($s['name']) ?> - Influence: <?= $s['influence'] ?>/10, Interet: <?= $s['interest'] ?>/10"></div>
                    <?php endforeach; ?>
                </div>
                <!-- Legende -->
                <div class="flex flex-wrap gap-4 mt-4 text-sm">
                    <?php foreach ($categories as $key => $cat): ?>
                        <?php if (array_filter($stakeholders, fn($s) => $s['category'] === $key)): ?>
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full mr-1" style="background:<?= $cat['color'] ?>"></span>
                            <?= $cat['label'] ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Liste -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="text-left p-3">Partie Prenante</th>
                            <th class="text-left p-3">Categorie</th>
                            <th class="text-center p-3">Influence</th>
                            <th class="text-center p-3">Interet</th>
                            <th class="text-left p-3">Quadrant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($stakeholders as $s):
                            $catLabel = $categories[$s['category']]['label'] ?? $s['category'];
                            $catColor = $categories[$s['category']]['color'] ?? '#999';
                            if ($s['influence'] > 5 && $s['interest'] > 5) $quadrant = 'Gerer etroitement';
                            elseif ($s['influence'] > 5) $quadrant = 'Maintenir satisfait';
                            elseif ($s['interest'] <= 5) $quadrant = 'Surveiller';
                            else $quadrant = 'Tenir informe';
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium">
                                    <span class="inline-block w-3 h-3 rounded-full mr-2" style="background:<?= $catColor ?>"></span>
                                    <?= h($s['name']) ?>
                                </td>
                                <td class="p-3 text-gray-600"><?= h($catLabel) ?></td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-1 rounded text-xs <?= $s['influence'] >= 6 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' ?>">
                                        <?= $s['influence'] ?>/10
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-1 rounded text-xs <?= $s['interest'] >= 6 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' ?>">
                                        <?= $s['interest'] ?>/10
                                    </span>
                                </td>
                                <td class="p-3 text-gray-600"><?= $quadrant ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($carto['notes'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold mb-2">Notes</h2>
            <p class="text-gray-700"><?= nl2br(h($carto['notes'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
