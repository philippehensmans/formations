<?php
/**
 * Vue en lecture seule de l'arbre a problemes d'un participant
 * Accessible par le formateur
 */
require_once __DIR__ . '/config.php';

// Verifier que c'est un formateur
if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-arbreproblemes';

$userId = (int)($_GET['user_id'] ?? 0);
$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$userId || !$sessionId) {
    header('Location: formateur.php');
    exit;
}

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $sessionId)) {
    die("Acces refuse a cette session.");
}

$db = getDB();
$sharedDB = getSharedDB();

// Recuperer les infos utilisateur depuis shared-auth
$stmt = $sharedDB->prepare("SELECT id, username, prenom, nom, organisation FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur non trouve");
}

// Recuperer la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    die("Session non trouvee");
}

// Recuperer l'arbre a problemes
$stmt = $db->prepare("SELECT * FROM arbres WHERE user_id = ? AND session_id = ?");
$stmt->execute([$userId, $sessionId]);
$arbre = $stmt->fetch();

if (!$arbre) {
    $arbre = [
        'nom_projet' => '',
        'participants' => '',
        'probleme_central' => '',
        'consequences' => '[]',
        'causes' => '[]',
        'objectif_central' => '',
        'objectifs' => '[]',
        'moyens' => '[]',
        'is_shared' => 0
    ];
}

$consequences = json_decode($arbre['consequences'] ?? '[]', true) ?: [];
$causes = json_decode($arbre['causes'] ?? '[]', true) ?: [];
$objectifs = json_decode($arbre['objectifs'] ?? '[]', true) ?: [];
$moyens = json_decode($arbre['moyens'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arbre a Problemes - <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
        }
        .tree-box {
            min-height: 80px;
            border: 2px solid;
            border-radius: 8px;
            padding: 12px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Barre de navigation -->
    <div class="bg-gradient-to-r from-amber-600 to-orange-600 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= h($user['prenom']) ?> <?= h($user['nom']) ?></span>
                <span class="text-amber-200 text-sm ml-2"><?= h($session['nom']) ?> (<?= h($session['code']) ?>)</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm px-3 py-1 rounded-full <?= $arbre['is_shared'] ? 'bg-green-500' : 'bg-yellow-500' ?>">
                    <?= $arbre['is_shared'] ? 'Partage' : 'Brouillon' ?>
                </span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $sessionId ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <!-- En-tete du projet -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Arbre a Problemes</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-500 text-sm mb-1">Nom du projet</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= h($arbre['nom_projet']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></div>
                </div>
                <div>
                    <label class="block text-gray-500 text-sm mb-1">Participants</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= h($arbre['participants']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></div>
                </div>
            </div>
        </div>

        <!-- Arbre a Problemes -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Arbre a Problemes</h2>

            <!-- Consequences (effets) -->
            <div class="mb-8">
                <h3 class="text-center font-bold text-red-700 mb-3">CONSEQUENCES (Effets)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <?php if (empty($consequences)): ?>
                        <div class="col-span-3 text-center text-gray-400 italic py-4">Aucune consequence</div>
                    <?php else: ?>
                        <?php foreach ($consequences as $consequence): ?>
                            <div class="tree-box bg-red-50 border-red-300">
                                <p class="text-sm"><?= h($consequence) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fleches vers le bas -->
            <div class="text-center mb-4">
                <div class="text-3xl text-gray-400">↓ ↓ ↓</div>
            </div>

            <!-- Probleme central -->
            <div class="mb-8">
                <h3 class="text-center font-bold text-orange-700 mb-3">PROBLEME CENTRAL</h3>
                <div class="tree-box bg-orange-100 border-orange-500 mx-auto max-w-2xl">
                    <p class="text-center font-semibold"><?= h($arbre['probleme_central']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></p>
                </div>
            </div>

            <!-- Fleches vers le haut -->
            <div class="text-center mb-4">
                <div class="text-3xl text-gray-400">↑ ↑ ↑</div>
            </div>

            <!-- Causes (racines) -->
            <div>
                <h3 class="text-center font-bold text-blue-700 mb-3">CAUSES (Racines)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <?php if (empty($causes)): ?>
                        <div class="col-span-3 text-center text-gray-400 italic py-4">Aucune cause</div>
                    <?php else: ?>
                        <?php foreach ($causes as $cause): ?>
                            <div class="tree-box bg-blue-50 border-blue-300">
                                <p class="text-sm"><?= h($cause) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Arbre a Objectifs -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Arbre a Objectifs</h2>

            <!-- Objectifs (fins) -->
            <div class="mb-8">
                <h3 class="text-center font-bold text-green-700 mb-3">OBJECTIFS (Fins)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <?php if (empty($objectifs)): ?>
                        <div class="col-span-3 text-center text-gray-400 italic py-4">Aucun objectif</div>
                    <?php else: ?>
                        <?php foreach ($objectifs as $objectif): ?>
                            <div class="tree-box bg-green-50 border-green-300">
                                <p class="text-sm"><?= h($objectif) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fleches vers le bas -->
            <div class="text-center mb-4">
                <div class="text-3xl text-gray-400">↓ ↓ ↓</div>
            </div>

            <!-- Objectif central -->
            <div class="mb-8">
                <h3 class="text-center font-bold text-teal-700 mb-3">OBJECTIF CENTRAL</h3>
                <div class="tree-box bg-teal-100 border-teal-500 mx-auto max-w-2xl">
                    <p class="text-center font-semibold"><?= h($arbre['objectif_central']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></p>
                </div>
            </div>

            <!-- Fleches vers le haut -->
            <div class="text-center mb-4">
                <div class="text-3xl text-gray-400">↑ ↑ ↑</div>
            </div>

            <!-- Moyens -->
            <div>
                <h3 class="text-center font-bold text-purple-700 mb-3">MOYENS</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <?php if (empty($moyens)): ?>
                        <div class="col-span-3 text-center text-gray-400 italic py-4">Aucun moyen</div>
                    <?php else: ?>
                        <?php foreach ($moyens as $moyen): ?>
                            <div class="tree-box bg-purple-50 border-purple-300">
                                <p class="text-sm"><?= h($moyen) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
