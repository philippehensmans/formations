<?php
require_once __DIR__ . '/config.php';

if (!isFormateur()) { header('Location: index.php'); exit; }

$appKey = 'app-comm-diagnostic';
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

$defaults = getDefaultData();
$nomOrg = $analyse['nom_organisation'] ?? '';
$s1 = json_decode($analyse['section1_data'] ?? '{}', true) ?: $defaults['section1_data'];
$s2 = json_decode($analyse['section2_data'] ?? '{}', true) ?: $defaults['section2_data'];
$s3 = json_decode($analyse['section3_data'] ?? '{}', true) ?: $defaults['section3_data'];
$s4 = json_decode($analyse['section4_data'] ?? '{}', true) ?: $defaults['section4_data'];
$s5 = json_decode($analyse['section5_data'] ?? '{}', true) ?: $defaults['section5_data'];
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;

$budgetLabels = ['moins_2' => 'Moins de 2%', '2_5' => '2-5%', '5_10' => '5-10%', 'plus_10' => 'Plus de 10%', 'ne_sais_pas' => 'Ne sais pas'];
$ressNonFinLabels = ['benevoles' => 'Benevoles', 'partenariats' => 'Partenariats', 'competences' => 'Competences internes', 'reseaux' => 'Reseaux personnels'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Communication - <?= sanitize($pPrenom) ?> <?= sanitize($pNom) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
        .section-number { display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 50%; background: #0891b2; color: white; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; }
        .score-badge { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 50%; font-weight: 700; font-size: 0.9rem; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-cyan-600 to-teal-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($pPrenom) ?> <?= sanitize($pNom) ?></span>
                <span class="text-cyan-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $isSubmitted ? 'Soumis' : 'Brouillon' ?></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto p-4">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Auto-Diagnostic Communication</h1>
            <div class="text-gray-600">Organisation : <strong><?= sanitize($nomOrg) ?: '<em class="text-gray-400">Non renseigne</em>' ?></strong></div>
        </div>

        <!-- 1. Valeurs et Mission -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="section-number">1</div>
                <h2 class="text-lg font-bold text-gray-800">Valeurs et Mission</h2>
            </div>

            <h3 class="font-semibold text-gray-700 mb-2">Valeurs fondamentales</h3>
            <div class="space-y-2 mb-4">
                <?php for ($i = 0; $i < 3; $i++):
                    $valeur = $s1['valeurs'][$i] ?? '';
                    $score = $s1['valeurs_scores'][$i]['score'] ?? 0;
                    $comm = $s1['valeurs_scores'][$i]['commentaire'] ?? '';
                    $scoreColor = $score >= 4 ? 'bg-green-100 text-green-700' : ($score >= 3 ? 'bg-yellow-100 text-yellow-700' : ($score > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400'));
                ?>
                <div class="flex items-center gap-3 bg-cyan-50 p-3 rounded-lg border border-cyan-200">
                    <span class="score-badge <?= $scoreColor ?>"><?= $score ?: '-' ?></span>
                    <div class="flex-1">
                        <span class="font-medium text-gray-800"><?= sanitize($valeur) ?: '<em class="text-gray-400">Non renseigne</em>' ?></span>
                        <?php if (!empty(trim($comm))): ?>
                        <p class="text-sm text-gray-500 mt-0.5"><?= sanitize($comm) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs text-gray-500">/5</span>
                </div>
                <?php endfor; ?>
            </div>

            <?php if (!empty(trim($s1['exemple_positif'] ?? ''))): ?>
            <div class="mb-3">
                <h3 class="font-semibold text-gray-700 mb-1">Exemple positif</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-green-50 p-3 rounded-lg border border-green-200 text-sm"><?= sanitize($s1['exemple_positif']) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty(trim($s1['exemple_decalage'] ?? ''))): ?>
            <div>
                <h3 class="font-semibold text-gray-700 mb-1">Exemple de decalage</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-red-50 p-3 rounded-lg border border-red-200 text-sm"><?= sanitize($s1['exemple_decalage']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- 2. Contraintes et Ressources -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="section-number">2</div>
                <h2 class="text-lg font-bold text-gray-800">Contraintes et Ressources</h2>
            </div>

            <div class="mb-4">
                <h3 class="font-semibold text-gray-700 mb-1">Budget communication</h3>
                <span class="inline-block bg-cyan-100 text-cyan-800 px-3 py-1 rounded-lg font-medium">
                    <?= sanitize($budgetLabels[$s2['budget'] ?? ''] ?? 'Non renseigne') ?>
                </span>
            </div>

            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div>
                    <h3 class="font-semibold text-red-700 mb-2">Contraintes</h3>
                    <?php foreach (($s2['contraintes'] ?? []) as $i => $c): if (empty(trim($c))) continue; ?>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="bg-red-100 text-red-600 font-bold w-6 h-6 rounded-full flex items-center justify-center text-xs"><?= $i + 1 ?></span>
                        <span class="text-sm text-gray-700"><?= sanitize($c) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <h3 class="font-semibold text-green-700 mb-2">Atouts</h3>
                    <?php foreach (($s2['atouts'] ?? []) as $i => $a): if (empty(trim($a))) continue; ?>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="bg-green-100 text-green-600 font-bold w-6 h-6 rounded-full flex items-center justify-center text-xs"><?= $i + 1 ?></span>
                        <span class="text-sm text-gray-700"><?= sanitize($a) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty(trim($s2['action_efficace'] ?? ''))): ?>
            <div class="mb-4">
                <h3 class="font-semibold text-gray-700 mb-1">Action efficace avec moyens limites</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-emerald-50 p-3 rounded-lg border border-emerald-200 text-sm"><?= sanitize($s2['action_efficace']) ?></p>
            </div>
            <?php endif; ?>

            <?php
            $selRes = $s2['ressources_non_financieres'] ?? [];
            $autreRes = $s2['ressources_autre'] ?? '';
            if (!empty($selRes) || !empty(trim($autreRes))):
            ?>
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">Ressources non-financieres a mobiliser</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($selRes as $r): ?>
                    <span class="bg-cyan-100 text-cyan-700 px-3 py-1 rounded-lg text-sm"><?= sanitize($ressNonFinLabels[$r] ?? $r) ?></span>
                    <?php endforeach; ?>
                    <?php if (!empty(trim($autreRes))): ?>
                    <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-lg text-sm"><?= sanitize($autreRes) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 3. Mobilisation et Engagement -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="section-number">3</div>
                <h2 class="text-lg font-bold text-gray-800">Mobilisation et Engagement</h2>
            </div>

            <h3 class="font-semibold text-gray-700 mb-2">Parties prenantes</h3>
            <div class="space-y-2 mb-4">
                <?php foreach (($s3['parties_prenantes'] ?? []) as $pp):
                    if (empty(trim($pp['nom'] ?? ''))) continue;
                    $eng = $pp['engagement'] ?? 0;
                    $engColor = $eng >= 4 ? 'bg-green-100 text-green-700' : ($eng >= 3 ? 'bg-yellow-100 text-yellow-700' : ($eng > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400'));
                ?>
                <div class="flex items-center gap-3 bg-sky-50 p-3 rounded-lg border border-sky-200">
                    <span class="score-badge <?= $engColor ?>"><?= $eng ?: '-' ?></span>
                    <div class="flex-1">
                        <span class="font-medium text-gray-800"><?= sanitize($pp['nom']) ?></span>
                        <?php if (!empty(trim($pp['actions'] ?? ''))): ?>
                        <p class="text-sm text-gray-500 mt-0.5"><?= sanitize($pp['actions']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs text-gray-500">/5</span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php $transf = $s3['transformation_score'] ?? 0; if ($transf > 0): ?>
            <div class="mb-4">
                <h3 class="font-semibold text-gray-700 mb-1">Capacite de transformation</h3>
                <div class="flex items-center gap-2">
                    <div class="flex-1 bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div class="h-full rounded-full <?= $transf >= 4 ? 'bg-green-500' : ($transf >= 3 ? 'bg-yellow-500' : 'bg-red-500') ?>" style="width: <?= $transf * 20 ?>%"></div>
                    </div>
                    <span class="font-bold text-sm"><?= $transf ?>/5</span>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $obstacles = array_filter($s3['obstacles'] ?? [], fn($o) => !empty(trim($o)));
            if (!empty($obstacles)):
            ?>
            <div class="mb-4">
                <h3 class="font-semibold text-gray-700 mb-2">Obstacles a l'engagement</h3>
                <?php foreach ($obstacles as $i => $o): ?>
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-amber-100 text-amber-600 font-bold w-6 h-6 rounded-full flex items-center justify-center text-xs"><?= $i + 1 ?></span>
                    <span class="text-sm text-gray-700"><?= sanitize($o) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty(trim($s3['exemple_mobilisation'] ?? ''))): ?>
            <div>
                <h3 class="font-semibold text-gray-700 mb-1">Exemple reussi de mobilisation</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-blue-50 p-3 rounded-lg border border-blue-200 text-sm"><?= sanitize($s3['exemple_mobilisation']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- 4. Synthese -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="section-number">4</div>
                <h2 class="text-lg font-bold text-gray-800">Synthese</h2>
            </div>

            <?php if (!empty(trim($s4['force_distinctive'] ?? ''))): ?>
            <div class="mb-3">
                <h3 class="font-semibold text-gray-700 mb-1">Force distinctive</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-cyan-50 p-3 rounded-lg border border-cyan-200 text-sm"><?= sanitize($s4['force_distinctive']) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty(trim($s4['defi_prioritaire'] ?? ''))): ?>
            <div class="mb-3">
                <h3 class="font-semibold text-gray-700 mb-1">Defi prioritaire</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-amber-50 p-3 rounded-lg border border-amber-200 text-sm"><?= sanitize($s4['defi_prioritaire']) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty(trim($s4['articulation'] ?? ''))): ?>
            <div>
                <h3 class="font-semibold text-gray-700 mb-1">Articulation valeurs / contraintes / mobilisation</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-purple-50 p-3 rounded-lg border border-purple-200 text-sm"><?= sanitize($s4['articulation']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- 5. Pistes d'action -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="section-number">5</div>
                <h2 class="text-lg font-bold text-gray-800">Pistes d'Action</h2>
            </div>

            <?php if (!empty(trim($s5['piste_valeurs'] ?? ''))): ?>
            <div class="mb-3">
                <h3 class="font-semibold text-cyan-700 mb-1">Valeurs et mission</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-cyan-50 p-3 rounded-lg border border-cyan-200 text-sm"><?= sanitize($s5['piste_valeurs']) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty(trim($s5['piste_ressources'] ?? ''))): ?>
            <div class="mb-3">
                <h3 class="font-semibold text-emerald-700 mb-1">Optimisation des ressources</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-emerald-50 p-3 rounded-lg border border-emerald-200 text-sm"><?= sanitize($s5['piste_ressources']) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty(trim($s5['piste_mobilisation'] ?? ''))): ?>
            <div>
                <h3 class="font-semibold text-amber-700 mb-1">Mobilisation et engagement</h3>
                <p class="text-gray-700 whitespace-pre-wrap bg-amber-50 p-3 rounded-lg border border-amber-200 text-sm"><?= sanitize($s5['piste_mobilisation']) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
