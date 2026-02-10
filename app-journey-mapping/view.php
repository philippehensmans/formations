<?php
/**
 * Vue detaillee d'un participant - Journey Mapping
 * Accessible par le formateur
 */
require_once __DIR__ . '/config.php';

// Verifier acces formateur
if (!isFormateur()) { header('Location: index.php'); exit; }

$appKey = 'app-journey-mapping';

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) { die("Participant non trouve"); }

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Acces refuse a cette session.");
}

// Recuperer les donnees utilisateur de la base partagee
$sharedDb = getSharedDB();
$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userData = $userStmt->fetch();
$participantPrenom = $userData['prenom'] ?? $participant['prenom'] ?? 'Participant';
$participantNom = $userData['nom'] ?? $participant['nom'] ?? '';

// Charger l'analyse
$stmt = $db->prepare("SELECT * FROM analyses WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$analyse = $stmt->fetch();

$journeyData = json_decode($analyse['journey_data'] ?? '[]', true) ?: [];
$channels = getChannels();
$emotions = getEmotions();
$isSubmitted = ($analyse['is_shared'] ?? 0) == 1;

// Fonction pour obtenir l'emoji d'une emotion
function getEmotionDisplay($emotionKey) {
    $emotions = getEmotions();
    if (isset($emotions[$emotionKey])) {
        return $emotions[$emotionKey]['emoji'] . ' ' . $emotions[$emotionKey]['label'];
    }
    return $emotionKey;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journey Mapping - <?= sanitize($participantPrenom) ?> <?= sanitize($participantNom) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
        body { background: #f3f4f6; }
    </style>
</head>
<body class="min-h-screen">
    <!-- Barre superieure -->
    <div class="bg-gradient-to-r from-cyan-600 to-teal-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($participantPrenom) ?> <?= sanitize($participantNom) ?></span>
                <span class="text-cyan-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm px-3 py-1 rounded-full <?= $isSubmitted ? 'bg-green-500' : 'bg-yellow-500' ?>">
                    <?= $isSubmitted ? 'Soumis' : 'Brouillon' ?>
                </span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <!-- En-tete -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Journey Mapping - Audit de Communication</h1>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-gray-500 text-sm mb-1">Organisation</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($analyse['nom_organisation'] ?? '') ?: '<em class="text-gray-400">Non renseigne</em>' ?></div>
                </div>
                <div>
                    <label class="block text-gray-500 text-sm mb-1">Public cible</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($analyse['public_cible'] ?? '') ?: '<em class="text-gray-400">Non renseigne</em>' ?></div>
                </div>
                <div>
                    <label class="block text-gray-500 text-sm mb-1">Objectif de l'audit</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($analyse['objectif_audit'] ?? '') ?: '<em class="text-gray-400">Non renseigne</em>' ?></div>
                </div>
            </div>
        </div>

        <!-- Statistiques rapides -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <div class="text-3xl font-bold text-cyan-600"><?= count($journeyData) ?></div>
                <div class="text-sm text-gray-500">Etapes</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <?php
                $channelsUsed = [];
                foreach ($journeyData as $step) {
                    if (!empty($step['canal'])) $channelsUsed[$step['canal']] = true;
                }
                ?>
                <div class="text-3xl font-bold text-teal-600"><?= count($channelsUsed) ?></div>
                <div class="text-sm text-gray-500">Canaux</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <?php
                $frictionCount = 0;
                foreach ($journeyData as $step) {
                    if (!empty(trim($step['friction'] ?? ''))) $frictionCount++;
                }
                ?>
                <div class="text-3xl font-bold text-red-500"><?= $frictionCount ?></div>
                <div class="text-sm text-gray-500">Points de friction</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <?php
                $opportunityCount = 0;
                foreach ($journeyData as $step) {
                    if (!empty(trim($step['opportunites'] ?? ''))) $opportunityCount++;
                }
                ?>
                <div class="text-3xl font-bold text-green-500"><?= $opportunityCount ?></div>
                <div class="text-sm text-gray-500">Opportunites</div>
            </div>
        </div>

        <!-- Etapes du parcours -->
        <h2 class="text-xl font-bold text-gray-800 mb-4">Etapes du parcours</h2>
        <?php if (empty($journeyData)): ?>
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-400 mb-6">
                <p>Aucune etape definie</p>
            </div>
        <?php else: ?>
            <?php foreach ($journeyData as $i => $step): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 mb-4 border-l-4 border-cyan-500">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="bg-cyan-600 text-white text-sm font-bold px-3 py-1 rounded-full">Etape <?= $i + 1 ?></span>
                        <h3 class="text-lg font-bold text-gray-800"><?= sanitize($step['titre'] ?? 'Sans titre') ?></h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-500 text-sm mb-1">Canal de communication</label>
                            <div class="px-3 py-2 bg-cyan-50 rounded border border-cyan-200 text-sm">
                                <?= sanitize($channels[$step['canal'] ?? ''] ?? $step['canal'] ?? 'Non defini') ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-500 text-sm mb-1">Point de contact</label>
                            <div class="px-3 py-2 bg-gray-50 rounded border text-sm">
                                <?= sanitize($step['point_contact'] ?? '') ?: '<em class="text-gray-400">-</em>' ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-500 text-sm mb-1">Action de l'utilisateur</label>
                            <div class="px-3 py-2 bg-gray-50 rounded border text-sm whitespace-pre-wrap">
                                <?= sanitize($step['action_utilisateur'] ?? '') ?: '<em class="text-gray-400">-</em>' ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-500 text-sm mb-1">Information recue</label>
                            <div class="px-3 py-2 bg-gray-50 rounded border text-sm whitespace-pre-wrap">
                                <?= sanitize($step['info_recue'] ?? '') ?: '<em class="text-gray-400">-</em>' ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($step['emotions'])): ?>
                    <div class="mb-4">
                        <label class="block text-gray-500 text-sm mb-1">Emotions ressenties</label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($step['emotions'] as $emotion): ?>
                                <span class="px-3 py-1 bg-cyan-100 text-cyan-800 rounded-full text-sm">
                                    <?= getEmotionDisplay($emotion) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php if (!empty(trim($step['friction'] ?? ''))): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <label class="block text-red-700 text-sm font-semibold mb-1">Points de friction</label>
                            <p class="text-sm text-red-800 whitespace-pre-wrap"><?= sanitize($step['friction']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty(trim($step['opportunites'] ?? ''))): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <label class="block text-green-700 text-sm font-semibold mb-1">Opportunites d'amelioration</label>
                            <p class="text-sm text-green-800 whitespace-pre-wrap"><?= sanitize($step['opportunites']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Synthese et recommandations -->
        <?php if (!empty(trim($analyse['synthese'] ?? ''))): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <h2 class="text-xl font-bold text-gray-800 mb-3">Synthese de l'analyse</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['synthese']) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty(trim($analyse['recommandations'] ?? ''))): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <h2 class="text-xl font-bold text-gray-800 mb-3">Recommandations</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['recommandations']) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty(trim($analyse['notes'] ?? ''))): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-4">
            <h2 class="text-xl font-bold text-gray-800 mb-3">Notes</h2>
            <p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
