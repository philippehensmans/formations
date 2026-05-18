<?php
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: index.php'); exit; }

$appKey = 'app-canevas-animation';
$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) { die("Participant non trouvé"); }

if (!canAccessSession($appKey, $participant['session_id'])) { die("Accès refusé."); }

$sharedDb = getSharedDB();
$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userData = $userStmt->fetch();
$pPrenom = $userData['prenom'] ?? $participant['prenom'] ?? 'Participant';
$pNom = $userData['nom'] ?? $participant['nom'] ?? '';

$stmt = $db->prepare("SELECT * FROM canevas WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$row = $stmt->fetch();
$data = $row ? (json_decode($row['data'] ?? '{}', true) ?: []) : [];
$isSubmitted = $row ? ($row['is_shared'] ?? 0) == 1 : false;

$pointsAttention = getPointsAttention();
$publics = getPublics();
$modalitesEval = getModalitesEval();
$formats = getFormats();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canevas d'animation - <?= h($pPrenom) ?> <?= h($pNom) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } body { background: white; } }
        .section-number { display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 50%; background: #4f46e5; color: white; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-6xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= h($pPrenom) ?> <?= h($pNom) ?></span>
                <span class="text-indigo-200 text-sm ml-2"><?= h($participant['session_nom']) ?> (<?= h($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto p-4">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Animation · Canevas</p>
            <h1 class="text-2xl font-bold text-gray-800">Mon animation IA — <?= isset($formats[$data['format'] ?? '90']) ? $formats[$data['format'] ?? '90'] : '90 minutes' ?></h1>
            <div class="grid md:grid-cols-3 gap-3 mt-4 text-sm text-gray-700">
                <div><strong>Animateur·rice :</strong> <?= h($data['animateur'] ?? '') ?: '<em class="text-gray-400">Non renseigné</em>' ?></div>
                <div><strong>Date et lieu :</strong> <?= h($data['date_lieu'] ?? '') ?: '<em class="text-gray-400">Non renseigné</em>' ?></div>
                <div><strong>Classe / groupe :</strong> <?= h($data['classe_groupe'] ?? '') ?: '<em class="text-gray-400">Non renseigné</em>' ?></div>
            </div>
        </div>

        <!-- 1. Cadrage -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">1</div>
                <h2 class="text-lg font-bold text-gray-800">Cadrage</h2>
            </div>
            <div class="space-y-3 text-sm">
                <div>
                    <strong class="text-gray-600">Public visé :</strong>
                    <span class="inline-block bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded text-xs ml-1"><?= h($publics[$data['public'] ?? ''] ?? 'Non précisé') ?></span>
                </div>
                <?php if (!empty(trim($data['public_precisions'] ?? ''))): ?>
                <p class="text-gray-700 bg-gray-50 p-3 rounded border italic"><?= nl2br(h($data['public_precisions'])) ?></p>
                <?php endif; ?>
                <div class="bg-indigo-50 p-3 rounded-lg border border-indigo-200">
                    <strong class="text-indigo-800 block mb-1">Objectif principal</strong>
                    <p class="text-gray-800"><?= nl2br(h($data['objectif_principal'] ?? '')) ?: '<em class="text-gray-400">Non renseigné</em>' ?></p>
                </div>
                <?php if (!empty(trim($data['objectif_sec_1'] ?? '')) || !empty(trim($data['objectif_sec_2'] ?? ''))): ?>
                <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                    <strong class="text-blue-800 block mb-1">Objectifs secondaires</strong>
                    <ul class="list-disc list-inside text-gray-800 space-y-1">
                        <?php if (!empty(trim($data['objectif_sec_1'] ?? ''))): ?><li><?= h($data['objectif_sec_1']) ?></li><?php endif; ?>
                        <?php if (!empty(trim($data['objectif_sec_2'] ?? ''))): ?><li><?= h($data['objectif_sec_2']) ?></li><?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if (!empty(trim($data['fil_rouge'] ?? ''))): ?>
                <div class="bg-gradient-to-r from-indigo-50 to-violet-50 p-3 rounded-lg border-2 border-indigo-300">
                    <strong class="text-indigo-800 block mb-1">Fil rouge / accroche</strong>
                    <p class="text-indigo-900 italic">&laquo; <?= h($data['fil_rouge']) ?> &raquo;</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Séquençage -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">2</div>
                <h2 class="text-lg font-bold text-gray-800">Séquençage <?= isset($formats[$data['format'] ?? '90']) ? '(' . $formats[$data['format'] ?? '90'] . ')' : '' ?></h2>
            </div>
            <?php $sequences = $data['sequences'] ?? []; ?>
            <?php if (empty($sequences)): ?>
            <p class="text-gray-400 text-center py-4">Aucune séquence définie</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-indigo-50">
                            <th class="text-left p-2 font-semibold text-indigo-700 w-20">Min</th>
                            <th class="text-left p-2 font-semibold text-indigo-700">Objectif</th>
                            <th class="text-left p-2 font-semibold text-indigo-700">Activité / outil</th>
                            <th class="text-left p-2 font-semibold text-indigo-700">Animation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sequences as $s):
                            if (empty($s['min']) && empty($s['objectif']) && empty($s['activite']) && empty($s['animation'])) continue;
                        ?>
                        <tr class="border-t align-top">
                            <td class="p-2 font-mono text-indigo-700 font-semibold"><?= h($s['min'] ?? '') ?></td>
                            <td class="p-2 text-gray-700"><?= nl2br(h($s['objectif'] ?? '')) ?></td>
                            <td class="p-2 text-gray-700"><?= nl2br(h($s['activite'] ?? '')) ?></td>
                            <td class="p-2 text-gray-700"><?= nl2br(h($s['animation'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- 3. Outils -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">3</div>
                <h2 class="text-lg font-bold text-gray-800">Outils utilisés</h2>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-blue-50 p-3 rounded border border-blue-200">
                    <h3 class="font-bold text-blue-800 text-sm mb-2">&#x1F4FA; Projetés</h3>
                    <?php if (!empty(trim($data['outil_projete_1'] ?? ''))): ?>
                    <p class="text-sm text-gray-700 mb-2"><strong>Principal :</strong> <?= h($data['outil_projete_1']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty(trim($data['outil_projete_2'] ?? ''))): ?>
                    <p class="text-sm text-gray-700"><strong>Secondaire :</strong> <?= h($data['outil_projete_2']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="bg-emerald-50 p-3 rounded border border-emerald-200">
                    <h3 class="font-bold text-emerald-800 text-sm mb-2">&#x270B; Manipulés</h3>
                    <?php if (!empty(trim($data['outil_manipule_1'] ?? ''))): ?>
                    <p class="text-sm text-gray-700 mb-2"><strong>N°1 :</strong> <?= h($data['outil_manipule_1']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty(trim($data['outil_manipule_2'] ?? ''))): ?>
                    <p class="text-sm text-gray-700"><strong>N°2 :</strong> <?= h($data['outil_manipule_2']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty(trim($data['plan_b'] ?? ''))): ?>
            <div class="bg-amber-50 border border-amber-200 rounded p-3 mt-3">
                <strong class="text-amber-800 text-sm">&#x1F198; Plan B :</strong>
                <span class="text-sm text-gray-700"><?= h($data['plan_b']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- 4. Points d'attention -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">4</div>
                <h2 class="text-lg font-bold text-gray-800">Points d'attention (<?= count($data['points_coches'] ?? []) ?>/7)</h2>
            </div>
            <?php $pCoches = $data['points_coches'] ?? []; ?>
            <?php if (empty($pCoches)): ?>
            <p class="text-gray-400 text-center py-4">Aucun point sélectionné</p>
            <?php else: ?>
            <div class="grid md:grid-cols-2 gap-3">
                <?php foreach ($pointsAttention as $key => $p): if (!in_array($key, $pCoches)) continue; ?>
                <div class="bg-<?= $p['color'] ?>-50 border-l-4 border-<?= $p['color'] ?>-400 p-3 rounded">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg"><?= $p['icon'] ?></span>
                        <strong class="text-gray-800"><?= h($p['titre']) ?></strong>
                    </div>
                    <p class="text-xs text-gray-600"><?= h($p['description']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 5. Matériel -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">5</div>
                <h2 class="text-lg font-bold text-gray-800">Matériel et préparation</h2>
            </div>
            <div class="grid md:grid-cols-3 gap-3 mb-3">
                <?php
                $matSections = [
                    ['materiel_salle', '&#x1F3EB; Salle', 'blue'],
                    ['materiel_formateur', '&#x1F4BB; Formateur', 'purple'],
                    ['materiel_eleves', '&#x1F393; Élèves', 'emerald'],
                ];
                foreach ($matSections as [$key, $titre, $color]):
                    $items = $data[$key] ?? [];
                ?>
                <div class="bg-<?= $color ?>-50 border border-<?= $color ?>-200 rounded p-3 text-sm">
                    <h3 class="font-bold text-<?= $color ?>-800 mb-1"><?= $titre ?></h3>
                    <?php if (empty($items)): ?>
                    <p class="text-gray-400 italic text-xs">Aucun élément coché</p>
                    <?php else: ?>
                    <ul class="space-y-1">
                        <?php foreach ($items as $i): ?>
                        <li class="text-gray-700">&#x2713; <?= h($i) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty(trim($data['preparation_j1'] ?? ''))): ?>
            <div class="bg-gray-50 border border-gray-200 rounded p-3">
                <strong class="text-gray-800 block mb-1">&#x1F4DD; Préparation J-1</strong>
                <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($data['preparation_j1']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- 6. Évaluation -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="section-number">6</div>
                <h2 class="text-lg font-bold text-gray-800">Évaluation et suivi</h2>
            </div>
            <?php if (!empty($data['modalite_eval'] ?? '')): ?>
            <div class="mb-3">
                <strong class="text-gray-600 text-sm">Modalité d'évaluation à chaud :</strong>
                <span class="inline-block bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded text-sm ml-1"><?= h($modalitesEval[$data['modalite_eval']] ?? '') ?></span>
            </div>
            <?php endif; ?>

            <h3 class="font-semibold text-gray-700 text-sm mb-2">Bilan personnel</h3>
            <div class="grid md:grid-cols-3 gap-3 mb-3">
                <div class="bg-green-50 border border-green-200 rounded p-3">
                    <strong class="text-green-800 text-xs block mb-1">&#x2705; Ce qui a marché</strong>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($data['bilan_marche'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></p>
                </div>
                <div class="bg-red-50 border border-red-200 rounded p-3">
                    <strong class="text-red-800 text-xs block mb-1">&#x26A0;&#xFE0F; Ce qui a coincé</strong>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($data['bilan_coince'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></p>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded p-3">
                    <strong class="text-blue-800 text-xs block mb-1">&#x1F504; Ce que je change</strong>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($data['bilan_change'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></p>
                </div>
            </div>
            <?php if (!empty(trim($data['suivi_enseignant'] ?? ''))): ?>
            <div class="bg-gray-50 border border-gray-200 rounded p-3">
                <strong class="text-gray-800 text-sm block mb-1">Suivi avec l'enseignant·e</strong>
                <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($data['suivi_enseignant']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Notes -->
        <?php if (!empty(trim($data['notes'] ?? ''))): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-3">&#x270F;&#xFE0F; Notes libres</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= h($data['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
