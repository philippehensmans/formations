<?php
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: index.php'); exit; }

$appKey = 'app-comm-plan';
$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) { die("Participant non trouve"); }

if (!canAccessSession($appKey, $participant['session_id'])) { die("Acces refuse."); }

$sharedDb = getSharedDB();
$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userData = $userStmt->fetch();
$pPrenom = $userData['prenom'] ?? $participant['prenom'] ?? 'Participant';
$pNom = $userData['nom'] ?? $participant['nom'] ?? '';

$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$analyse = $stmt->fetch();

$canaux = json_decode($analyse['canaux_data'] ?? '[]', true) ?: [];
$calendrier = json_decode($analyse['calendrier_data'] ?? '[]', true) ?: [];
$ressources = json_decode($analyse['ressources_data'] ?? '[]', true) ?: [];
$availableCanaux = getCanaux();
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;

$typeLabels = [
    'teasing' => 'Teasing',
    'annonce' => 'Annonce',
    'rappel' => 'Rappel',
    'jour_j' => 'Jour J',
    'relance' => 'Relance',
    'remerciement' => 'Remerciement',
    'bilan' => 'Bilan'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de Communication - <?= sanitize($pPrenom) ?> <?= sanitize($pNom) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
        .section-number { display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 50%; background: #4f46e5; color: white; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($pPrenom) ?> <?= sanitize($pNom) ?></span>
                <span class="text-indigo-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Mini-Plan de Communication</h1>
            <div class="text-gray-600">Organisation : <strong><?= sanitize($analyse['nom_organisation'] ?? '') ?: '<em class="text-gray-400">Non renseigne</em>' ?></strong></div>
        </div>

        <!-- 1. Action -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">1</div>
                <h2 class="text-lg font-bold text-gray-800">L'action a communiquer</h2>
            </div>
            <p class="text-gray-700 whitespace-pre-wrap bg-indigo-50 p-4 rounded-lg border border-indigo-200"><?= sanitize($analyse['action_communiquer'] ?? '') ?: '<em class="text-gray-400">Non renseigne</em>' ?></p>
        </div>

        <!-- 2. Objectif SMART -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">2</div>
                <h2 class="text-lg font-bold text-gray-800">Objectif SMART</h2>
            </div>
            <p class="text-gray-700 whitespace-pre-wrap bg-blue-50 p-4 rounded-lg border border-blue-200"><?= sanitize($analyse['objectif_smart'] ?? '') ?: '<em class="text-gray-400">Non renseigne</em>' ?></p>
        </div>

        <!-- 3. Public prioritaire -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">3</div>
                <h2 class="text-lg font-bold text-gray-800">Public prioritaire</h2>
            </div>
            <p class="text-gray-700 whitespace-pre-wrap bg-purple-50 p-4 rounded-lg border border-purple-200"><?= sanitize($analyse['public_prioritaire'] ?? '') ?: '<em class="text-gray-400">Non renseigne</em>' ?></p>
        </div>

        <!-- 4. Message cle -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">4</div>
                <h2 class="text-lg font-bold text-gray-800">Message cle</h2>
            </div>
            <div class="bg-gradient-to-r from-indigo-50 to-violet-50 p-4 rounded-lg border-2 border-indigo-300">
                <p class="text-lg font-medium text-indigo-900 italic">&laquo; <?= sanitize($analyse['message_cle'] ?? '') ?: '<em class="text-gray-400">Non renseigne</em>' ?> &raquo;</p>
            </div>
        </div>

        <!-- 5. Canaux -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">5</div>
                <h2 class="text-lg font-bold text-gray-800">Canaux (<?= count(array_filter($canaux, fn($c) => !empty($c['canal']))) ?>)</h2>
            </div>
            <?php if (empty($canaux)): ?>
                <p class="text-gray-400 text-center py-4">Aucun canal selectionne</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($canaux as $c):
                        if (empty($c['canal'])) continue;
                        $canalInfo = $availableCanaux[$c['canal']] ?? null;
                    ?>
                    <div class="bg-gray-50 rounded-lg p-4 border">
                        <div class="flex items-center gap-2 mb-2">
                            <?php if ($canalInfo): ?>
                            <span class="text-lg"><?= $canalInfo['icon'] ?></span>
                            <span class="font-bold text-gray-800"><?= $canalInfo['label'] ?></span>
                            <?php else: ?>
                            <span class="font-bold text-gray-800"><?= sanitize($c['canal']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($c['frequence'])): ?>
                            <span class="text-sm bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded"><?= sanitize($c['frequence']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty(trim($c['justification'] ?? ''))): ?>
                        <p class="text-sm text-gray-600"><?= sanitize($c['justification']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 6. Calendrier -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">6</div>
                <h2 class="text-lg font-bold text-gray-800">Calendrier (<?= count(array_filter($calendrier, fn($e) => !empty($e['etape']))) ?> etapes)</h2>
            </div>
            <?php if (empty($calendrier)): ?>
                <p class="text-gray-400 text-center py-4">Aucune etape definie</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($calendrier as $i => $e):
                        if (empty($e['etape']) && empty($e['date'])) continue;
                        $typeColors = [
                            'teasing' => 'border-purple-400 bg-purple-50',
                            'annonce' => 'border-blue-400 bg-blue-50',
                            'rappel' => 'border-amber-400 bg-amber-50',
                            'jour_j' => 'border-green-400 bg-green-50',
                            'relance' => 'border-orange-400 bg-orange-50',
                            'remerciement' => 'border-pink-400 bg-pink-50',
                            'bilan' => 'border-gray-400 bg-gray-50'
                        ];
                        $cls = $typeColors[$e['type'] ?? ''] ?? 'border-gray-300 bg-gray-50';
                    ?>
                    <div class="rounded-lg p-4 border-l-4 <?= $cls ?>">
                        <div class="flex items-center gap-3 mb-1">
                            <?php if (!empty($e['date'])): ?>
                            <span class="font-bold text-sm text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded"><?= sanitize($e['date']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($e['type'])): ?>
                            <span class="text-xs text-gray-600 font-semibold"><?= $typeLabels[$e['type']] ?? sanitize($e['type']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($e['canaux_utilises'])): ?>
                            <span class="text-xs text-gray-500">(<?= sanitize($e['canaux_utilises']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty(trim($e['etape'] ?? ''))): ?>
                        <p class="text-sm text-gray-700"><?= sanitize($e['etape']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 7. Ressources -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">7</div>
                <h2 class="text-lg font-bold text-gray-800">Ressources</h2>
            </div>
            <?php if (empty($ressources)): ?>
                <p class="text-gray-400 text-center py-4">Aucune ressource definie</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="text-left p-2 font-semibold text-gray-600">Qui ?</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Fait quoi ?</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Temps / Budget</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ressources as $r):
                                if (empty($r['qui']) && empty($r['quoi'])) continue;
                            ?>
                            <tr class="border-t">
                                <td class="p-2 text-gray-700"><?= sanitize($r['qui'] ?? '') ?></td>
                                <td class="p-2 text-gray-700"><?= sanitize($r['quoi'] ?? '') ?></td>
                                <td class="p-2 text-gray-700"><?= sanitize($r['temps_budget'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notes -->
        <?php if (!empty(trim($analyse['notes'] ?? ''))): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mt-4">
            <h2 class="text-lg font-bold text-gray-800 mb-3">Notes</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
