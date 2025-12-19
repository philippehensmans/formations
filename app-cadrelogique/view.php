<?php
/**
 * Vue en lecture seule du cadre logique d'un participant
 * Accessible par le formateur
 */

// Charger shared-auth pour l'authentification formateur
require_once __DIR__ . '/../shared-auth/config.php';

// Charger la config locale pour les donnees
require_once 'config/database.php';

// Verifier que c'est un formateur
if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();

// Recuperer le participant
$stmt = $db->prepare("
    SELECT p.*, s.code as session_code, s.nom as session_nom
    FROM participants p
    JOIN sessions s ON p.session_id = s.id
    WHERE p.id = ?
");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve");
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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadre Logique - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title>
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
                <span class="text-sm px-3 py-1 rounded-full <?= $cadre['is_submitted'] ? 'bg-green-500' : 'bg-yellow-500' ?>">
                    <?= $cadre['is_submitted'] ? 'Soumis' : 'Brouillon' ?>
                </span>
                <span class="text-sm">Completion: <strong><?= $cadre['completion_percent'] ?>%</strong></span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <!-- En-tete du projet -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Cadre Logique</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-500 text-sm mb-1">Titre du projet</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($cadre['titre_projet']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></div>
                </div>
                <div>
                    <label class="block text-gray-500 text-sm mb-1">Organisation porteuse</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($cadre['organisation']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></div>
                </div>
                <div>
                    <label class="block text-gray-500 text-sm mb-1">Zone geographique / Beneficiaires</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($cadre['zone_geo']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></div>
                </div>
                <div>
                    <label class="block text-gray-500 text-sm mb-1">Duree prevue</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($cadre['duree']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></div>
                </div>
            </div>
        </div>

        <!-- Matrice du cadre logique -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
            <div class="bg-gray-800 text-white p-4">
                <h2 class="text-xl font-bold">Matrice du Cadre Logique</h2>
            </div>

            <!-- En-tetes des colonnes -->
            <div class="grid grid-cols-12 gap-px bg-gray-300 text-sm font-bold">
                <div class="col-span-1 bg-gray-100 p-2 text-center">Niveau</div>
                <div class="col-span-3 bg-gray-100 p-2">Description narrative</div>
                <div class="col-span-3 bg-gray-100 p-2">Indicateurs (IOV)</div>
                <div class="col-span-2 bg-gray-100 p-2">Sources verification</div>
                <div class="col-span-3 bg-gray-100 p-2">Hypotheses / Risques</div>
            </div>

            <!-- Objectif Global -->
            <div class="grid grid-cols-12 gap-px bg-gray-300 niveau-og">
                <div class="col-span-1 bg-blue-100 p-2 flex items-center justify-center font-bold text-blue-800 text-sm">
                    Objectif<br>Global
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
                    Objectif<br>Specifique
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
</body>
</html>
