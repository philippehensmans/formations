<?php
/**
 * Interface de travail - Carte d'identite du projet
 */
require_once 'config/database.php';
requireParticipant();

$db = getDB();
$participant = getCurrentParticipant();

// Verifier que le participant existe
if (!$participant) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Charger la carte projet
$stmt = $db->prepare("SELECT * FROM cartes_projet WHERE participant_id = ?");
$stmt->execute([$participant['id']]);
$carte = $stmt->fetch();

if (!$carte) {
    $stmt = $db->prepare("INSERT INTO cartes_projet (participant_id, session_id) VALUES (?, ?)");
    $stmt->execute([$participant['id'], $participant['session_id']]);
    $stmt = $db->prepare("SELECT * FROM cartes_projet WHERE participant_id = ?");
    $stmt->execute([$participant['id']]);
    $carte = $stmt->fetch();
}

$partenaires = json_decode($carte['partenaires'] ?: '[]', true);
$isSubmitted = $carte['is_submitted'] == 1;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('carte.title') ?> - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        @media print {
            .no-print { display: none; }
            header { display: none; }
        }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="p-4 md:p-8">
    <!-- Header -->
    <header class="no-print max-w-4xl mx-auto mb-4">
        <div class="bg-purple-900 text-white rounded-lg p-4 flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="font-bold"><?= t('carte.title') ?></h1>
                <p class="text-purple-200 text-sm">
                    <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?>
                    | Session: <?= sanitize($participant['session_code']) ?>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <?= renderLanguageSelector('lang-select') ?>
                <span id="saveStatus" class="text-sm text-purple-200"></span>
                <button onclick="manualSave()" class="bg-purple-700 hover:bg-purple-600 px-4 py-2 rounded text-sm">
                    <?= t('app.save') ?>
                </button>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">
                    <?= t('app.logout') ?>
                </a>
            </div>
        </div>
    </header>
    <?= renderLanguageScript() ?>

    <?php if ($isSubmitted): ?>
        <div class="max-w-4xl mx-auto mb-4">
            <div class="bg-green-100 border-l-4 border-green-500 p-4 rounded">
                <p class="text-green-700 font-medium"><?= t('carte.work_submitted') ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-2xl p-6 md:p-8">
        <div class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2"><?= t('carte.title') ?></h1>
            <p class="text-gray-600 italic"><?= t('carte.subtitle') ?></p>
        </div>

        <form id="projectForm" class="space-y-6">
            <!-- Titre du projet -->
            <div class="bg-purple-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    Titre du projet
                </label>
                <input type="text" id="titre" value="<?= sanitize($carte['titre']) ?>"
                       
                       class="w-full px-4 py-2 border-2 border-purple-200 rounded-md focus:ring-2 focus:ring-purple-500 "
                       placeholder="Indiquez le titre de votre projet...">
            </div>

            <!-- Objectifs -->
            <div class="bg-blue-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    Objectifs du projet
                </label>
                <p class="text-sm text-gray-600 mb-2 italic">Que cherche-t-on a atteindre ? Quelles transformations souhaitees ?</p>
                <textarea id="objectifs" rows="4" 
                          class="w-full px-4 py-2 border-2 border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 "
                          placeholder="Decrivez les objectifs principaux de votre projet..."><?= sanitize($carte['objectifs']) ?></textarea>
            </div>

            <!-- Public cible -->
            <div class="bg-green-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    Public(s) cible(s)
                </label>
                <p class="text-sm text-gray-600 mb-2 italic">A qui s'adresse le projet ? (age, situation, territoire, nombre estime, etc.)</p>
                <textarea id="publicCible" rows="3" 
                          class="w-full px-4 py-2 border-2 border-green-200 rounded-md focus:ring-2 focus:ring-green-500 "
                          placeholder="Decrivez le(s) public(s) vise(s)..."><?= sanitize($carte['public_cible']) ?></textarea>
            </div>

            <!-- Zone d'action -->
            <div class="bg-yellow-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    Zone d'action / Territoire concerne
                </label>
                <input type="text" id="territoire" value="<?= sanitize($carte['territoire']) ?>"
                       
                       class="w-full px-4 py-2 border-2 border-yellow-200 rounded-md focus:ring-2 focus:ring-yellow-500 "
                       placeholder="Indiquez la zone geographique...">
            </div>

            <!-- Partenaires -->
            <div class="bg-pink-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    Partenaires impliques ou pressentis
                </label>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border-2 border-pink-200 mb-3">
                        <thead class="bg-pink-100">
                            <tr>
                                <th class="border border-pink-200 px-3 py-2 text-left text-sm font-semibold">Structure / Organisme</th>
                                <th class="border border-pink-200 px-3 py-2 text-left text-sm font-semibold">Role ou contribution</th>
                                <th class="border border-pink-200 px-3 py-2 text-left text-sm font-semibold">Contact de reference</th>
                                <th class="border border-pink-200 px-3 py-2 text-center text-sm font-semibold no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody id="partenairesList">
                            <!-- Rempli par JS -->
                        </tbody>
                    </table>
                </div>
                <button type="button" onclick="addPartenaireRow()"
                        class="no-print bg-pink-500 text-white px-4 py-2 rounded-md hover:bg-pink-600 transition text-sm">
                    + Ajouter un partenaire
                </button>
            </div>

            <!-- Ressources -->
            <div class="bg-indigo-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-3">
                    Ressources mobilisables
                </label>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Humaines :</label>
                        <input type="text" id="ressourcesHumaines" value="<?= sanitize($carte['ressources_humaines']) ?>"
                               
                               class="w-full px-3 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500 "
                               placeholder="Equipe, benevoles, competences disponibles...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Materielles :</label>
                        <input type="text" id="ressourcesMaterielles" value="<?= sanitize($carte['ressources_materielles']) ?>"
                               
                               class="w-full px-3 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500 "
                               placeholder="Locaux, equipements, materiel...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Financieres :</label>
                        <input type="text" id="ressourcesFinancieres" value="<?= sanitize($carte['ressources_financieres']) ?>"
                               
                               class="w-full px-3 py-2 border-2 border-indigo-200 rounded-md focus:ring-2 focus:ring-indigo-500 "
                               placeholder="Budget, subventions, sources de financement...">
                    </div>
                </div>
            </div>

            <!-- Calendrier -->
            <div class="bg-orange-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    Calendrier previsionnel / Periode de mise en oeuvre
                </label>
                <input type="text" id="calendrier" value="<?= sanitize($carte['calendrier']) ?>"
                       
                       class="w-full px-4 py-2 border-2 border-orange-200 rounded-md focus:ring-2 focus:ring-orange-500 "
                       placeholder="Ex: Janvier 2025 - Decembre 2025, ou phases specifiques...">
            </div>

            <!-- Resultats attendus -->
            <div class="bg-teal-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    Resultats attendus (effets ou changements vises)
                </label>
                <p class="text-sm text-gray-600 mb-2 italic">Qu'esperez-vous observer concretement ? Qu'est-ce qui prouvera la reussite ?</p>
                <textarea id="resultats" rows="4" 
                          class="w-full px-4 py-2 border-2 border-teal-200 rounded-md focus:ring-2 focus:ring-teal-500 "
                          placeholder="Decrivez les resultats attendus et les indicateurs de reussite..."><?= sanitize($carte['resultats']) ?></textarea>
            </div>

            <!-- Notes complementaires -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <label class="block text-lg font-semibold text-gray-800 mb-2">
                    Notes complementaires / Observations
                </label>
                <textarea id="notes" rows="3" 
                          class="w-full px-4 py-2 border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500 "
                          placeholder="Ajoutez toute information complementaire pertinente..."><?= sanitize($carte['notes']) ?></textarea>
            </div>

            <!-- Boutons d'action -->
            <div class="no-print flex flex-wrap gap-3 pt-4 border-t-2 border-gray-200">
                <button type="button" onclick="exportToExcel()"
                        class="bg-emerald-600 text-white px-6 py-3 rounded-md hover:bg-emerald-700 transition font-semibold shadow-md">
                    Excel
                </button>
                <button type="button" onclick="exportToWord()"
                        class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition font-semibold shadow-md">
                    Word
                </button>
                <button type="button" onclick="exportJSON()"
                        class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition font-semibold shadow-md">
                    JSON
                </button>
                <button type="button" onclick="window.print()"
                        class="bg-purple-600 text-white px-6 py-3 rounded-md hover:bg-purple-700 transition font-semibold shadow-md">
                    Imprimer
                </button>
                <?php if (!$isSubmitted): ?>
                    <button type="button" onclick="submitWork()"
                            class="ml-auto bg-purple-900 text-white px-6 py-3 rounded-md hover:bg-purple-800 transition font-semibold shadow-md">
                        Marquer comme termine
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
        // Donnees
        let partenaires = <?= json_encode($partenaires) ?>;
        const isSubmitted = <?= $isSubmitted ? 'true' : 'false' ?>;
        let saveTimeout = null;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            renderPartenaires();

            // Auto-save sur tous les champs (toujours actif)
            const fields = ['titre', 'objectifs', 'publicCible', 'territoire', 'ressourcesHumaines',
                           'ressourcesMaterielles', 'ressourcesFinancieres', 'calendrier', 'resultats', 'notes'];
            fields.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('input', scheduleSave);
            });
        });

        function renderPartenaires() {
            const tbody = document.getElementById('partenairesList');

            if (partenaires.length === 0) {
                partenaires.push({ structure: '', role: '', contact: '' });
            }

            tbody.innerHTML = partenaires.map((p, index) => `
                <tr class="partenaire-row">
                    <td class="border border-pink-200 p-2">
                        <input type="text" value="${escapeHtml(p.structure)}"
                               onchange="updatePartenaire(${index}, 'structure', this.value)"
                               class="w-full px-2 py-1 border border-gray-300 rounded"
                               placeholder="Nom de la structure">
                    </td>
                    <td class="border border-pink-200 p-2">
                        <input type="text" value="${escapeHtml(p.role)}"
                               onchange="updatePartenaire(${index}, 'role', this.value)"
                               class="w-full px-2 py-1 border border-gray-300 rounded"
                               placeholder="Role">
                    </td>
                    <td class="border border-pink-200 p-2">
                        <input type="text" value="${escapeHtml(p.contact)}"
                               onchange="updatePartenaire(${index}, 'contact', this.value)"
                               class="w-full px-2 py-1 border border-gray-300 rounded"
                               placeholder="Contact">
                    </td>
                    <td class="border border-pink-200 p-2 text-center no-print">
                        <button type="button" onclick="removePartenaire(${index})" class="text-red-500 hover:text-red-700">X</button>
                    </td>
                </tr>
            `).join('');
        }

        function addPartenaireRow() {
            partenaires.push({ structure: '', role: '', contact: '' });
            renderPartenaires();
            scheduleSave();
        }

        function removePartenaire(index) {
            if (partenaires.length > 1) {
                partenaires.splice(index, 1);
                renderPartenaires();
                scheduleSave();
            }
        }

        function updatePartenaire(index, field, value) {
            partenaires[index][field] = value;
            scheduleSave();
        }

        function scheduleSave() {
            if (saveTimeout) clearTimeout(saveTimeout);
            document.getElementById('saveStatus').textContent = 'Modifications...';
            saveTimeout = setTimeout(doSave, 1000);
        }

        function manualSave() {
            if (saveTimeout) clearTimeout(saveTimeout);
            doSave();
        }

        function getFormData() {
            return {
                titre: document.getElementById('titre').value,
                objectifs: document.getElementById('objectifs').value,
                public_cible: document.getElementById('publicCible').value,
                territoire: document.getElementById('territoire').value,
                partenaires: partenaires.filter(p => p.structure || p.role || p.contact),
                ressources_humaines: document.getElementById('ressourcesHumaines').value,
                ressources_materielles: document.getElementById('ressourcesMaterielles').value,
                ressources_financieres: document.getElementById('ressourcesFinancieres').value,
                calendrier: document.getElementById('calendrier').value,
                resultats: document.getElementById('resultats').value,
                notes: document.getElementById('notes').value
            };
        }

        function doSave() {
            document.getElementById('saveStatus').textContent = 'Sauvegarde...';

            fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(getFormData())
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    document.getElementById('saveStatus').textContent = 'Sauvegarde OK';
                    setTimeout(() => {
                        document.getElementById('saveStatus').textContent = '';
                    }, 2000);
                } else {
                    document.getElementById('saveStatus').textContent = 'Erreur!';
                }
            })
            .catch(() => {
                document.getElementById('saveStatus').textContent = 'Erreur reseau';
            });
        }

        function submitWork() {
            if (!confirm('Soumettre votre travail ? Vous ne pourrez plus le modifier.')) return;

            fetch('api/submit.php', { method: 'POST' })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    alert('Travail soumis avec succes !');
                    location.reload();
                } else {
                    alert('Erreur: ' + (result.error || 'Erreur inconnue'));
                }
            });
        }

        function exportToExcel() {
            const data = getFormData();
            const wb = XLSX.utils.book_new();

            // Feuille principale
            const mainData = [
                ['CARTE D\'IDENTITE DU PROJET'],
                [''],
                ['Titre du projet', data.titre],
                [''],
                ['Objectifs du projet', data.objectifs],
                [''],
                ['Public(s) cible(s)', data.public_cible],
                [''],
                ['Zone d\'action / Territoire', data.territoire],
                [''],
                ['Calendrier previsionnel', data.calendrier],
                [''],
                ['Resultats attendus', data.resultats],
                [''],
                ['Notes complementaires', data.notes]
            ];

            const ws1 = XLSX.utils.aoa_to_sheet(mainData);
            ws1['!cols'] = [{ wch: 30 }, { wch: 80 }];
            XLSX.utils.book_append_sheet(wb, ws1, 'Informations');

            // Feuille Partenaires
            const partData = [
                ['PARTENAIRES'],
                [''],
                ['Structure', 'Role', 'Contact']
            ];
            data.partenaires.forEach(p => {
                partData.push([p.structure, p.role, p.contact]);
            });

            const ws2 = XLSX.utils.aoa_to_sheet(partData);
            ws2['!cols'] = [{ wch: 30 }, { wch: 40 }, { wch: 30 }];
            XLSX.utils.book_append_sheet(wb, ws2, 'Partenaires');

            // Feuille Ressources
            const resData = [
                ['RESSOURCES'],
                [''],
                ['Type', 'Description'],
                ['Humaines', data.ressources_humaines],
                ['Materielles', data.ressources_materielles],
                ['Financieres', data.ressources_financieres]
            ];

            const ws3 = XLSX.utils.aoa_to_sheet(resData);
            ws3['!cols'] = [{ wch: 20 }, { wch: 80 }];
            XLSX.utils.book_append_sheet(wb, ws3, 'Ressources');

            XLSX.writeFile(wb, 'carte_identite_projet.xlsx');
        }

        function exportToWord() {
            const data = getFormData();

            let html = `
                <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
                <head><meta charset="utf-8"><title>Carte d'identite du projet</title></head>
                <body style="font-family: Arial, sans-serif;">
                <h1 style="color: #7c3aed; text-align: center;">Carte d'identite du projet</h1>

                <h2 style="color: #7c3aed;">Titre du projet</h2>
                <p>${escapeHtml(data.titre)}</p>

                <h2 style="color: #3b82f6;">Objectifs du projet</h2>
                <p>${escapeHtml(data.objectifs).replace(/\n/g, '<br>')}</p>

                <h2 style="color: #22c55e;">Public(s) cible(s)</h2>
                <p>${escapeHtml(data.public_cible).replace(/\n/g, '<br>')}</p>

                <h2 style="color: #eab308;">Zone d'action / Territoire</h2>
                <p>${escapeHtml(data.territoire)}</p>

                <h2 style="color: #ec4899;">Partenaires</h2>
                <table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">
                    <tr style="background: #fce7f3;"><th>Structure</th><th>Role</th><th>Contact</th></tr>
                    ${data.partenaires.map(p => `<tr><td>${escapeHtml(p.structure)}</td><td>${escapeHtml(p.role)}</td><td>${escapeHtml(p.contact)}</td></tr>`).join('')}
                </table>

                <h2 style="color: #6366f1;">Ressources mobilisables</h2>
                <ul>
                    <li><strong>Humaines:</strong> ${escapeHtml(data.ressources_humaines)}</li>
                    <li><strong>Materielles:</strong> ${escapeHtml(data.ressources_materielles)}</li>
                    <li><strong>Financieres:</strong> ${escapeHtml(data.ressources_financieres)}</li>
                </ul>

                <h2 style="color: #f97316;">Calendrier previsionnel</h2>
                <p>${escapeHtml(data.calendrier)}</p>

                <h2 style="color: #14b8a6;">Resultats attendus</h2>
                <p>${escapeHtml(data.resultats).replace(/\n/g, '<br>')}</p>

                <h2 style="color: #6b7280;">Notes complementaires</h2>
                <p>${escapeHtml(data.notes).replace(/\n/g, '<br>')}</p>
                </body></html>
            `;

            const blob = new Blob([html], { type: 'application/msword' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'carte_identite_projet.doc';
            a.click();
            URL.revokeObjectURL(url);
        }

        function exportJSON() {
            const data = getFormData();
            data.exported_at = new Date().toISOString();

            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'carte_identite_projet.json';
            a.click();
            URL.revokeObjectURL(url);
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    </script>
</body>
</html>
