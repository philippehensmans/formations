<?php
require_once 'config.php';
requireLoginWithSession();
requireAdmin();

$db = getDB();

// Statistiques
$stmt = $db->query("SELECT COUNT(*) as count FROM projects WHERE user_id IN (SELECT id FROM users WHERE is_admin = 0)");
$totalProjects = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 0");
$totalParticipants = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM projects WHERE is_shared = 1 AND user_id IN (SELECT id FROM users WHERE is_admin = 0)");
$sharedProjects = $stmt->fetch()['count'];

// Afficher tous ou seulement partages
$showAll = isset($_GET['all']);

// Liste des projets
if ($showAll) {
    $stmt = $db->query("
        SELECT p.*, u.username
        FROM projects p
        JOIN users u ON p.user_id = u.id
        WHERE u.is_admin = 0
        ORDER BY p.updated_at DESC
    ");
} else {
    $stmt = $db->query("
        SELECT p.*, u.username
        FROM projects p
        JOIN users u ON p.user_id = u.id
        WHERE u.is_admin = 0 AND p.is_shared = 1
        ORDER BY p.updated_at DESC
    ");
}
$projects = $stmt->fetchAll();

// Projet selectionne
$selectedProject = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("
        SELECT p.*, u.username
        FROM projects p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$_GET['view']]);
    $selectedProject = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Formation Agile</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-blue-800 to-indigo-800 text-white p-4">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="text-xl font-bold">Formation Methode Agile</h1>
                <p class="text-blue-200 text-sm">Interface Formateur</p>
            </div>
            <div class="flex items-center gap-4">
                <span>Connecte : <strong><?= sanitize($_SESSION['username']) ?></strong></span>
                <a href="logout.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-4">
        <!-- Statistiques -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl p-6 shadow">
                <div class="text-3xl font-bold text-blue-600"><?= $totalParticipants ?></div>
                <div class="text-gray-600">Participants</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow">
                <div class="text-3xl font-bold text-green-600"><?= $totalProjects ?></div>
                <div class="text-gray-600">Projets</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow">
                <div class="text-3xl font-bold text-purple-600"><?= $sharedProjects ?></div>
                <div class="text-gray-600">Projets partages</div>
            </div>
        </div>

        <!-- Actions de nettoyage -->
        <?php if ($totalProjects > 0 || $totalParticipants > 0): ?>
        <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 mb-6">
            <h3 class="font-bold text-red-800 mb-3">Nettoyage apres formation</h3>
            <div class="flex flex-wrap gap-3">
                <?php if ($totalProjects > 0): ?>
                <button onclick="deleteAllProjects(false)" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded text-sm">
                    Supprimer tous les projets (<?= $totalProjects ?>)
                </button>
                <?php endif; ?>
                <?php if ($totalParticipants > 0): ?>
                <button onclick="deleteAllProjects(true)" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">
                    Supprimer projets + participants (<?= $totalParticipants ?>)
                </button>
                <?php endif; ?>
            </div>
            <p class="text-xs text-red-600 mt-2">Attention : ces actions sont irreversibles !</p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Liste des projets -->
            <div class="bg-white rounded-xl shadow p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="font-bold text-lg">Projets</h2>
                    <div class="flex gap-1">
                        <a href="?" class="text-sm px-3 py-1 rounded <?= !$showAll ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?>">
                            Partages
                        </a>
                        <a href="?all=1" class="text-sm px-3 py-1 rounded <?= $showAll ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?>">
                            Tous
                        </a>
                    </div>
                </div>

                <?php if (empty($projects)): ?>
                    <div class="text-center py-8 text-gray-500">
                        Aucun projet <?= $showAll ? '' : 'partage' ?>
                    </div>
                <?php else: ?>
                    <div class="divide-y max-h-96 overflow-y-auto">
                        <?php foreach ($projects as $project): ?>
                            <div class="flex items-center hover:bg-gray-50 <?= ($selectedProject && $selectedProject['id'] == $project['id']) ? 'bg-blue-50 border-l-4 border-blue-600' : '' ?>">
                                <a href="?view=<?= $project['id'] ?><?= $showAll ? '&all=1' : '' ?>" class="block p-4 flex-1">
                                    <div class="font-semibold"><?= sanitize($project['username']) ?></div>
                                    <div class="text-sm text-gray-600"><?= sanitize($project['project_name'] ?: 'Sans titre') ?></div>
                                    <div class="text-xs text-gray-400 mt-1 flex gap-2">
                                        <span><?= date('d/m/Y H:i', strtotime($project['updated_at'])) ?></span>
                                        <?php if ($project['is_shared']): ?>
                                            <span class="bg-green-100 text-green-700 px-2 rounded">Partage</span>
                                        <?php else: ?>
                                            <span class="bg-red-100 text-red-700 px-2 rounded">Non partage</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <button onclick="event.stopPropagation(); deleteProject(<?= $project['id'] ?>, '<?= addslashes(sanitize($project['username'])) ?>')" class="p-2 mr-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded" title="Supprimer ce projet">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Detail du projet -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow p-4">
                <?php if ($selectedProject): ?>
                    <?php
                    $cards = json_decode($selectedProject['cards'], true) ?: [];
                    $userStories = json_decode($selectedProject['user_stories'], true) ?: [];
                    $retrospective = json_decode($selectedProject['retrospective'], true) ?: ['good' => [], 'improve' => [], 'actions' => []];
                    $sprint = json_decode($selectedProject['sprint'], true) ?: ['number' => 1, 'start' => '', 'end' => '', 'goal' => ''];

                    $todoCount = count(array_filter($cards, fn($c) => $c['status'] === 'todo'));
                    $inprogressCount = count(array_filter($cards, fn($c) => $c['status'] === 'inprogress'));
                    $doneCount = count(array_filter($cards, fn($c) => $c['status'] === 'done'));
                    $backlogCount = count(array_filter($cards, fn($c) => $c['status'] === 'backlog'));
                    ?>
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-xl font-bold"><?= sanitize($selectedProject['project_name'] ?: 'Sans titre') ?></h2>
                            <p class="text-gray-600">Par <?= sanitize($selectedProject['username']) ?></p>
                            <?php if ($selectedProject['team_name']): ?>
                                <p class="text-sm text-gray-500">Equipe : <?= sanitize($selectedProject['team_name']) ?></p>
                            <?php endif; ?>
                        </div>
                        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Imprimer
                        </button>
                    </div>

                    <!-- Metriques Sprint -->
                    <div class="bg-blue-50 rounded-lg p-4 mb-6">
                        <h3 class="font-bold text-blue-800 mb-3">Sprint #<?= sanitize($sprint['number']) ?></h3>
                        <?php if ($sprint['goal']): ?>
                            <p class="text-sm text-blue-700 mb-3">Objectif : <?= sanitize($sprint['goal']) ?></p>
                        <?php endif; ?>
                        <div class="grid grid-cols-4 gap-4 text-center">
                            <div class="bg-white rounded p-2">
                                <div class="text-2xl font-bold text-indigo-600"><?= $backlogCount ?></div>
                                <div class="text-xs text-gray-600">Backlog</div>
                            </div>
                            <div class="bg-white rounded p-2">
                                <div class="text-2xl font-bold text-slate-600"><?= $todoCount ?></div>
                                <div class="text-xs text-gray-600">A faire</div>
                            </div>
                            <div class="bg-white rounded p-2">
                                <div class="text-2xl font-bold text-amber-600"><?= $inprogressCount ?></div>
                                <div class="text-xs text-gray-600">En cours</div>
                            </div>
                            <div class="bg-white rounded p-2">
                                <div class="text-2xl font-bold text-green-600"><?= $doneCount ?></div>
                                <div class="text-xs text-gray-600">Termine</div>
                            </div>
                        </div>
                    </div>

                    <!-- Kanban -->
                    <?php if (!empty($cards)): ?>
                    <div class="mb-6">
                        <h3 class="font-bold text-gray-800 mb-3">Tableau Kanban</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                            <?php
                            $columns = [
                                'backlog' => ['label' => 'Backlog', 'color' => 'indigo'],
                                'todo' => ['label' => 'A faire', 'color' => 'slate'],
                                'inprogress' => ['label' => 'En cours', 'color' => 'amber'],
                                'done' => ['label' => 'Termine', 'color' => 'green']
                            ];
                            foreach ($columns as $status => $col):
                                $columnCards = array_filter($cards, fn($c) => $c['status'] === $status);
                            ?>
                            <div class="bg-gray-50 rounded p-2">
                                <div class="font-semibold text-sm mb-2 text-<?= $col['color'] ?>-600 border-b-2 border-<?= $col['color'] ?>-400 pb-1">
                                    <?= $col['label'] ?> (<?= count($columnCards) ?>)
                                </div>
                                <?php foreach ($columnCards as $card): ?>
                                <div class="bg-white border rounded p-2 mb-2 text-sm">
                                    <div class="font-medium"><?= sanitize($card['title']) ?></div>
                                    <?php if (!empty($card['description'])): ?>
                                        <div class="text-xs text-gray-500 mt-1"><?= sanitize($card['description']) ?></div>
                                    <?php endif; ?>
                                    <span class="text-xs px-2 py-0.5 rounded mt-1 inline-block <?= $card['priority'] === 'high' ? 'bg-red-100 text-red-700' : ($card['priority'] === 'medium' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700') ?>">
                                        <?= $card['priority'] === 'high' ? 'Haute' : ($card['priority'] === 'medium' ? 'Moyenne' : 'Basse') ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($columnCards)): ?>
                                <div class="text-xs text-gray-400 italic text-center py-2">Vide</div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- User Stories -->
                    <?php if (!empty($userStories)): ?>
                    <div class="mb-6">
                        <h3 class="font-bold text-gray-800 mb-3">User Stories (<?= count($userStories) ?>)</h3>
                        <div class="space-y-3">
                            <?php foreach ($userStories as $index => $story): ?>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-semibold">User Story #<?= $index + 1 ?></span>
                                    <div class="flex gap-2">
                                        <span class="text-xs px-2 py-1 rounded <?= $story['priority'] === 'high' ? 'bg-red-100 text-red-700' : ($story['priority'] === 'medium' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700') ?>">
                                            <?= $story['priority'] === 'high' ? 'Haute' : ($story['priority'] === 'medium' ? 'Moyenne' : 'Basse') ?>
                                        </span>
                                        <span class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700">
                                            <?= sanitize($story['points']) ?> pts
                                        </span>
                                    </div>
                                </div>
                                <p class="text-gray-700">
                                    <strong>En tant que</strong> <?= sanitize($story['role']) ?>,
                                    <strong>je veux</strong> <?= sanitize($story['action']) ?>
                                    <strong>afin de</strong> <?= sanitize($story['benefit']) ?>
                                </p>
                                <?php if (!empty($story['criteria'])): ?>
                                <div class="mt-2 text-sm text-gray-600">
                                    <strong>Criteres :</strong>
                                    <div class="whitespace-pre-line ml-2"><?= sanitize($story['criteria']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Retrospective -->
                    <?php if (!empty($retrospective['good']) || !empty($retrospective['improve']) || !empty($retrospective['actions'])): ?>
                    <div class="mb-6">
                        <h3 class="font-bold text-gray-800 mb-3">Retrospective</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-green-50 rounded-lg p-4">
                                <h4 class="font-semibold text-green-800 mb-2">Ce qui a bien fonctionne</h4>
                                <?php if (!empty($retrospective['good'])): ?>
                                <ul class="text-sm space-y-1">
                                    <?php foreach ($retrospective['good'] as $item): ?>
                                    <li class="bg-white rounded px-2 py-1">+ <?= sanitize($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                                <p class="text-sm text-gray-500 italic">Aucun element</p>
                                <?php endif; ?>
                            </div>
                            <div class="bg-amber-50 rounded-lg p-4">
                                <h4 class="font-semibold text-amber-800 mb-2">A ameliorer</h4>
                                <?php if (!empty($retrospective['improve'])): ?>
                                <ul class="text-sm space-y-1">
                                    <?php foreach ($retrospective['improve'] as $item): ?>
                                    <li class="bg-white rounded px-2 py-1">- <?= sanitize($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                                <p class="text-sm text-gray-500 italic">Aucun element</p>
                                <?php endif; ?>
                            </div>
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h4 class="font-semibold text-blue-800 mb-2">Actions</h4>
                                <?php if (!empty($retrospective['actions'])): ?>
                                <ul class="text-sm space-y-1">
                                    <?php foreach ($retrospective['actions'] as $item): ?>
                                    <li class="bg-white rounded px-2 py-1">* <?= sanitize($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                                <p class="text-sm text-gray-500 italic">Aucun element</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-16 text-gray-500">
                        <p class="text-lg">Selectionnez un projet pour voir les details</p>
                        <p class="text-sm mt-2">Les participants doivent activer le partage pour que leur projet apparaisse</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .bg-gradient-to-r, .grid-cols-1.sm\\:grid-cols-3, .lg\\:col-span-2 > div:first-child button { display: none !important; }
            .lg\\:col-span-2 { grid-column: span 3; }
            .max-h-\\[70vh\\] { max-height: none; overflow: visible; }
        }
    </style>

    <script>
    async function deleteAllProjects(includeUsers) {
        const msg = includeUsers
            ? 'ATTENTION : Supprimer TOUS les projets ET les comptes participants ?\n\nCette action est IRREVERSIBLE !'
            : 'ATTENTION : Supprimer TOUS les projets ?\n\nCette action est IRREVERSIBLE !';

        if (confirm(msg)) {
            try {
                const url = 'api.php?action=deleteAll' + (includeUsers ? '&users=1' : '');
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    alert('Suppression effectuee avec succes.');
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Echec de la suppression'));
                }
            } catch (error) {
                alert('Erreur de connexion: ' + error.message);
            }
        }
    }

    async function deleteProject(projectId, username) {
        if (confirm('Supprimer le projet de ' + username + ' ?')) {
            try {
                const response = await fetch('api.php?action=delete&id=' + projectId);
                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Echec de la suppression'));
                }
            } catch (error) {
                alert('Erreur de connexion: ' + error.message);
            }
        }
    }
    </script>
</body>
</html>
