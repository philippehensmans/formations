<?php
/**
 * Vue en lecture seule du cadre logique d'un participant
 * Accessible par le formateur
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

$lang = getCurrentLanguage();

// Verifier acces formateur
if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-cadrelogique';

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();

// Recuperer le participant
$stmt = $db->prepare("
    SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id
    FROM participants p
    JOIN sessions s ON p.session_id = s.id
    WHERE p.id = ?
");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die(t('cadrelogique.participant_not_found'));
}

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $participant['session_id'])) {
    die(t('cadrelogique.access_denied'));
}

// Recuperer le cadre logique
$stmt = $db->prepare("SELECT * FROM cadre_logique WHERE participant_id = ?");
$stmt->execute([$participantId]);
$cadre = $stmt->fetch();

if (!$cadre) {
    $matrice = getEmptyMatrice();
    $cadre = [
        'titre_projet' => '',
        'organisation' => '',
        'zone_geo' => '',
        'duree' => '',
        'completion_percent' => 0,
        'is_submitted' => 0
    ];
} else {
    $matrice = json_decode($cadre['matrice_data'], true) ?: getEmptyMatrice();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('cadrelogique.title') ?> - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .niveau-og { background: linear-gradient(to right, #dbeafe, #e0e7ff); }
        .niveau-os { background: linear-gradient(to right, #dcfce7, #d1fae5); }
        .niveau-r { background: linear-gradient(to right, #fef9c3, #fef3c7); }
        .niveau-a { background: linear-gradient(to right, #fee2e2, #fecaca); }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Barre de navigation -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></span>
                <span class="text-indigo-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <?= renderLanguageSelector('text-sm bg-white/20 rounded px-2 py-1 text-white border-0') ?>
                <span class="text-sm px-3 py-1 rounded-full <?= $cadre['is_submitted'] ? 'bg-green-500' : 'bg-yellow-500' ?>">
                    <?= $cadre['is_submitted'] ? t('cadrelogique.submitted') : t('cadrelogique.draft') ?>
                </span>
                <span class="text-sm"><?= t('cadrelogique.completion') ?>: <strong><?= $cadre['completion_percent'] ?>%</strong></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded"><?= t('common.print') ?></button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded"><?= t('common.back') ?></a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <!-- En-tete du projet -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4"><?= t('cadrelogique.title') ?></h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-500 text-sm mb-1"><?= t('cadrelogique.project_title') ?></label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($cadre['titre_projet']) ?: '<em class="text-gray-400">' . t('common.not_specified') . '</em>' ?></div>
                </div>
                <div>
                    <label class="block text-gray-500 text-sm mb-1"><?= t('cadrelogique.organisation') ?></label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($cadre['organisation']) ?: '<em class="text-gray-400">' . t('common.not_specified') . '</em>' ?></div>
                </div>
                <div>
                    <label class="block text-gray-500 text-sm mb-1"><?= t('cadrelogique.geo_zone') ?></label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($cadre['zone_geo']) ?: '<em class="text-gray-400">' . t('common.not_specified') . '</em>' ?></div>
                </div>
                <div>
                    <label class="block text-gray-500 text-sm mb-1"><?= t('cadrelogique.duration') ?></label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($cadre['duree']) ?: '<em class="text-gray-400">' . t('common.not_specified') . '</em>' ?></div>
                </div>
            </div>
        </div>

        <!-- Matrice du cadre logique -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
            <div class="bg-gray-800 text-white p-4">
                <h2 class="text-xl font-bold"><?= t('cadrelogique.matrix') ?></h2>
            </div>

            <!-- En-tetes des colonnes -->
            <div class="grid grid-cols-12 gap-px bg-gray-300 text-sm font-bold">
                <div class="col-span-1 bg-gray-100 p-2 text-center"><?= t('cadrelogique.level') ?></div>
                <div class="col-span-3 bg-gray-100 p-2"><?= t('cadrelogique.narrative') ?></div>
                <div class="col-span-3 bg-gray-100 p-2"><?= t('cadrelogique.indicators') ?></div>
                <div class="col-span-2 bg-gray-100 p-2"><?= t('cadrelogique.sources') ?></div>
                <div class="col-span-3 bg-gray-100 p-2"><?= t('cadrelogique.hypotheses') ?></div>
            </div>

            <!-- Objectif Global -->
            <div class="grid grid-cols-12 gap-px bg-gray-300 niveau-og">
                <div class="col-span-1 bg-blue-100 p-2 flex items-center justify-center font-bold text-blue-800 text-sm">
                    <?= t('cadrelogique.global_objective') ?>
                </div>
                <div class="col-span-3 bg-blue-50 p-2">
                    <div class="text-sm whitespace-pre-wrap"><?= sanitize($matrice['objectif_global']['description'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                </div>
                <div class="col-span-3 bg-blue-50 p-2">
                    <div class="text-sm whitespace-pre-wrap"><?= sanitize($matrice['objectif_global']['indicateurs'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                </div>
                <div class="col-span-2 bg-blue-50 p-2">
                    <div class="text-sm whitespace-pre-wrap"><?= sanitize($matrice['objectif_global']['sources'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                </div>
                <div class="col-span-3 bg-blue-50 p-2">
                    <div class="text-sm whitespace-pre-wrap"><?= sanitize($matrice['objectif_global']['hypotheses'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                </div>
            </div>

            <!-- Objectif Specifique -->
            <div class="grid grid-cols-12 gap-px bg-gray-300 niveau-os">
                <div class="col-span-1 bg-green-100 p-2 flex items-center justify-center font-bold text-green-800 text-sm">
                    <?= t('cadrelogique.specific_objective') ?>
                </div>
                <div class="col-span-3 bg-green-50 p-2">
                    <div class="text-sm whitespace-pre-wrap"><?= sanitize($matrice['objectif_specifique']['description'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                </div>
                <div class="col-span-3 bg-green-50 p-2">
                    <div class="text-sm whitespace-pre-wrap"><?= sanitize($matrice['objectif_specifique']['indicateurs'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                </div>
                <div class="col-span-2 bg-green-50 p-2">
                    <div class="text-sm whitespace-pre-wrap"><?= sanitize($matrice['objectif_specifique']['sources'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                </div>
                <div class="col-span-3 bg-green-50 p-2">
                    <div class="text-sm whitespace-pre-wrap"><?= sanitize($matrice['objectif_specifique']['hypotheses'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                </div>
            </div>

            <!-- Resultats et Activites -->
            <?php foreach ($matrice['resultats'] ?? [] as $rIndex => $resultat): ?>
                <?php $rNum = $rIndex + 1; ?>
                <!-- Resultat -->
                <div class="grid grid-cols-12 gap-px bg-gray-300 niveau-r">
                    <div class="col-span-1 bg-yellow-100 p-2 flex items-center justify-center font-bold text-yellow-800 text-sm">
                        R<?= $rNum ?>
                    </div>
                    <div class="col-span-3 bg-yellow-50 p-2">
                        <div class="text-sm whitespace-pre-wrap"><?= sanitize($resultat['description'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                    </div>
                    <div class="col-span-3 bg-yellow-50 p-2">
                        <div class="text-sm whitespace-pre-wrap"><?= sanitize($resultat['indicateurs'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                    </div>
                    <div class="col-span-2 bg-yellow-50 p-2">
                        <div class="text-sm whitespace-pre-wrap"><?= sanitize($resultat['sources'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                    </div>
                    <div class="col-span-3 bg-yellow-50 p-2">
                        <div class="text-sm whitespace-pre-wrap"><?= sanitize($resultat['hypotheses'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                    </div>
                </div>

                <!-- Activites -->
                <?php foreach ($resultat['activites'] ?? [] as $aIndex => $activite): ?>
                    <?php $aNum = $aIndex + 1; ?>
                    <div class="grid grid-cols-12 gap-px bg-gray-300 niveau-a">
                        <div class="col-span-1 bg-red-100 p-2 flex items-center justify-center font-bold text-red-800 text-xs">
                            A<?= $rNum ?>.<?= $aNum ?>
                        </div>
                        <div class="col-span-3 bg-red-50 p-2">
                            <div class="text-sm whitespace-pre-wrap"><?= sanitize($activite['description'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                        </div>
                        <div class="col-span-3 bg-red-50 p-2">
                            <div class="text-sm whitespace-pre-wrap"><?= sanitize($activite['ressources'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                        </div>
                        <div class="col-span-2 bg-red-50 p-2">
                            <div class="text-sm whitespace-pre-wrap"><?= sanitize($activite['budget'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                        </div>
                        <div class="col-span-3 bg-red-50 p-2">
                            <div class="text-sm whitespace-pre-wrap"><?= sanitize($activite['preconditions'] ?? '') ?: '<em class="text-gray-400">-</em>' ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?= renderLanguageScript() ?>
</body>
</html>
