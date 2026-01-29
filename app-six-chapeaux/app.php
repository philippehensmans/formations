<?php
/**
 * Interface principale - Six Chapeaux de Bono
 * Permet aux participants de soumettre des avis categorises par chapeau
 */
require_once __DIR__ . '/config.php';
requireLoginWithSession();

$user = getLoggedUser();
$db = getDB();
$chapeaux = getChapeaux();

// Recuperer les informations de la session
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$_SESSION['current_session_id']]);
$session = $stmt->fetch();

// Recuperer les avis existants du participant
$avis = getAvisParticipant($user['id'], $_SESSION['current_session_id']);

// Compter les avis par chapeau
$avisCounts = [];
foreach ($chapeaux as $key => $chapeau) {
    $avisCounts[$key] = 0;
}
foreach ($avis as $a) {
    if (isset($avisCounts[$a['chapeau']])) {
        $avisCounts[$a['chapeau']]++;
    }
}

$totalAvis = count($avis);
$avisPartages = count(array_filter($avis, fn($a) => $a['is_shared'] == 1));
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Six Chapeaux de Bono</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .chapeau-card { transition: all 0.3s ease; }
        .chapeau-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.15); }
        .avis-item { transition: all 0.2s ease; }
        .avis-item:hover { background-color: rgba(0,0,0,0.02); }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-100 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white p-4 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-xl font-bold">Six Chapeaux de Bono</h1>
                    <p class="text-indigo-200 text-sm"><?= h($user['prenom']) ?> <?= h($user['nom']) ?> - <?= h($session['nom'] ?? 'Session') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('indigo') ?>
                <span class="bg-white/20 px-3 py-1 rounded-full text-sm">
                    <?= $totalAvis ?> avis (<?= $avisPartages ?> partages)
                </span>
                <?php if (isFormateur()): ?>
                <a href="formateur.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg">Formateur</a>
                <?php endif; ?>
                <a href="logout.php" class="bg-red-500/80 hover:bg-red-600 px-4 py-2 rounded-lg">Deconnexion</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Sujet de la session -->
        <?php if (!empty($session['sujet'])): ?>
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-2">Sujet de reflexion</h2>
            <p class="text-gray-700"><?= nl2br(h($session['sujet'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Introduction aux Six Chapeaux -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Les Six Chapeaux de la Reflexion</h2>
            <p class="text-gray-600 mb-4">
                La methode des Six Chapeaux d'Edward de Bono permet d'organiser la reflexion en separant differents modes de pensee.
                Chaque chapeau represente une facon de penser. Cliquez sur un chapeau pour ajouter votre avis selon cette perspective.
            </p>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php foreach ($chapeaux as $key => $chapeau): ?>
                <div class="chapeau-card <?= $chapeau['bg'] ?> <?= $chapeau['text'] ?> p-4 rounded-xl border-2 <?= $chapeau['border'] ?> cursor-pointer"
                     onclick="openModal('<?= $key ?>')">
                    <div class="text-3xl text-center mb-2"><?= $chapeau['icon'] ?></div>
                    <div class="font-bold text-center text-sm"><?= $chapeau['nom'] ?></div>
                    <div class="text-xs text-center mt-1 opacity-80"><?= $chapeau['description'] ?></div>
                    <div class="text-center mt-2">
                        <span class="inline-block px-2 py-1 bg-white/50 rounded-full text-xs font-bold">
                            <?= $avisCounts[$key] ?> avis
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Liste des avis du participant -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">Mes Avis</h2>
                <button onclick="submitAll()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Partager tous mes avis
                </button>
            </div>

            <?php if (empty($avis)): ?>
            <div class="text-center py-12 text-gray-400">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-lg">Vous n'avez pas encore ajoute d'avis</p>
                <p class="text-sm">Cliquez sur un chapeau ci-dessus pour commencer</p>
            </div>
            <?php else: ?>
            <div class="space-y-4" id="avisList">
                <?php foreach ($avis as $a):
                    $ch = $chapeaux[$a['chapeau']] ?? $chapeaux['blanc'];
                ?>
                <div class="avis-item p-4 rounded-xl border-2 <?= $ch['border'] ?> <?= $ch['bg'] ?>" data-id="<?= $a['id'] ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex items-start gap-3">
                            <span class="text-2xl"><?= $ch['icon'] ?></span>
                            <div>
                                <div class="font-bold <?= $ch['text'] ?>"><?= $ch['nom'] ?></div>
                                <p class="<?= $a['chapeau'] === 'noir' ? 'text-gray-300' : 'text-gray-700' ?> mt-1"><?= nl2br(h($a['contenu'])) ?></p>
                                <div class="flex items-center gap-2 mt-2 text-xs <?= $a['chapeau'] === 'noir' ? 'text-gray-400' : 'text-gray-500' ?>">
                                    <span><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></span>
                                    <?php if ($a['is_shared']): ?>
                                    <span class="bg-green-200 text-green-800 px-2 py-0.5 rounded">Partage</span>
                                    <?php else: ?>
                                    <span class="bg-gray-200 text-gray-600 px-2 py-0.5 rounded">Non partage</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="editAvis(<?= $a['id'] ?>, '<?= $a['chapeau'] ?>', <?= htmlspecialchars(json_encode($a['contenu']), ENT_QUOTES) ?>)"
                                    class="p-2 hover:bg-white/50 rounded-lg" title="Modifier">
                                <svg class="w-5 h-5 <?= $ch['text'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                            <button onclick="deleteAvis(<?= $a['id'] ?>)"
                                    class="p-2 hover:bg-red-100 rounded-lg" title="Supprimer">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pour ajouter/modifier un avis -->
    <div id="avisModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800" id="modalTitle">Ajouter un avis</h3>
                <button onclick="closeModal()" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div id="chapeauInfo" class="p-4 rounded-xl mb-4">
                <!-- Info du chapeau selectionne -->
            </div>

            <form id="avisForm" onsubmit="saveAvis(event)">
                <input type="hidden" id="avisId" value="">
                <input type="hidden" id="chapeauType" value="">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Votre avis</label>
                    <textarea id="avisContenu" rows="5"
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                              placeholder="Exprimez votre point de vue selon la perspective de ce chapeau..."
                              required></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()"
                            class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const chapeaux = <?= json_encode($chapeaux) ?>;

        function openModal(chapeau) {
            document.getElementById('avisId').value = '';
            document.getElementById('chapeauType').value = chapeau;
            document.getElementById('avisContenu').value = '';
            document.getElementById('modalTitle').textContent = 'Ajouter un avis - ' + chapeaux[chapeau].nom;

            const info = document.getElementById('chapeauInfo');
            info.className = 'p-4 rounded-xl mb-4 ' + chapeaux[chapeau].bg + ' ' + chapeaux[chapeau].border + ' border-2';
            info.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="text-3xl">${chapeaux[chapeau].icon}</span>
                    <div>
                        <div class="font-bold ${chapeaux[chapeau].text}">${chapeaux[chapeau].nom}</div>
                        <div class="text-sm ${chapeau === 'noir' ? 'text-gray-300' : 'text-gray-600'}">${chapeaux[chapeau].description}</div>
                    </div>
                </div>
            `;

            document.getElementById('avisModal').classList.remove('hidden');
            document.getElementById('avisContenu').focus();
        }

        function editAvis(id, chapeau, contenu) {
            document.getElementById('avisId').value = id;
            document.getElementById('chapeauType').value = chapeau;
            document.getElementById('avisContenu').value = contenu;
            document.getElementById('modalTitle').textContent = 'Modifier un avis - ' + chapeaux[chapeau].nom;

            const info = document.getElementById('chapeauInfo');
            info.className = 'p-4 rounded-xl mb-4 ' + chapeaux[chapeau].bg + ' ' + chapeaux[chapeau].border + ' border-2';
            info.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="text-3xl">${chapeaux[chapeau].icon}</span>
                    <div>
                        <div class="font-bold ${chapeaux[chapeau].text}">${chapeaux[chapeau].nom}</div>
                        <div class="text-sm ${chapeau === 'noir' ? 'text-gray-300' : 'text-gray-600'}">${chapeaux[chapeau].description}</div>
                    </div>
                </div>
            `;

            document.getElementById('avisModal').classList.remove('hidden');
            document.getElementById('avisContenu').focus();
        }

        function closeModal() {
            document.getElementById('avisModal').classList.add('hidden');
        }

        async function saveAvis(event) {
            event.preventDefault();

            const data = {
                id: document.getElementById('avisId').value || null,
                chapeau: document.getElementById('chapeauType').value,
                contenu: document.getElementById('avisContenu').value.trim()
            };

            if (!data.contenu) {
                alert('Veuillez entrer votre avis');
                return;
            }

            try {
                const response = await fetch('api/save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Erreur inconnue'));
                }
            } catch (e) {
                alert('Erreur de connexion');
                console.error(e);
            }
        }

        async function deleteAvis(id) {
            if (!confirm('Voulez-vous vraiment supprimer cet avis ?')) return;

            try {
                const response = await fetch('api/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Erreur inconnue'));
                }
            } catch (e) {
                alert('Erreur de connexion');
                console.error(e);
            }
        }

        async function submitAll() {
            if (!confirm('Voulez-vous partager tous vos avis avec le formateur ?')) return;

            try {
                const response = await fetch('api/submit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const result = await response.json();

                if (result.success) {
                    alert('Vos avis ont ete partages avec succes !');
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Erreur inconnue'));
                }
            } catch (e) {
                alert('Erreur de connexion');
                console.error(e);
            }
        }

        // Fermer le modal avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // Fermer le modal en cliquant en dehors
        document.getElementById('avisModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
    <?= renderLanguageScript() ?>
</body>
</html>
