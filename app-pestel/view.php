<?php
require_once __DIR__ . '/../shared-auth/config.php';
require_once 'config/database.php';

if (!isFormateur()) { header('Location: index.php'); exit; }

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) { die("Participant non trouve"); }

$stmt = $db->prepare("SELECT * FROM analyse_pestel WHERE participant_id = ?");
$stmt->execute([$participantId]);
$analyse = $stmt->fetch();

$pestelData = json_decode($analyse['pestel_data'] ?? '{}', true) ?: getEmptyPestel();
$categories = ['politique' => 'Politique', 'economique' => 'Economique', 'socioculturel' => 'Socioculturel', 'technologique' => 'Technologique', 'environnemental' => 'Environnemental', 'legal' => 'Legal'];
$colors = ['politique' => 'red', 'economique' => 'blue', 'socioculturel' => 'green', 'technologique' => 'purple', 'environnemental' => 'teal', 'legal' => 'orange'];
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Analyse PESTEL - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title><script src="https://cdn.tailwindcss.com"></script><style>@media print { .no-print { display: none !important; }}</style></head><body class="bg-gray-100 min-h-screen"><div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-3 shadow-lg no-print sticky top-0 z-50"><div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3"><div><span class="font-medium"><?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></span><span class="text-purple-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span></div><div class="flex items-center gap-4"><span class="text-sm px-3 py-1 rounded-full <?= $analyse['is_submitted'] ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $analyse['is_submitted'] ? 'Soumis' : 'Brouillon' ?></span><button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button><a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a></div></div></div><div class="max-w-7xl mx-auto p-4"><div class="bg-white rounded-xl shadow-lg p-6 mb-6"><h1 class="text-2xl font-bold text-gray-800 mb-4">Analyse PESTEL</h1><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><label class="block text-gray-500 text-sm mb-1">Nom du projet</label><div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($analyse['nom_projet']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></div></div><div><label class="block text-gray-500 text-sm mb-1">Zone d'intervention</label><div class="px-4 py-2 bg-gray-50 rounded-lg border"><?= sanitize($analyse['zone']) ?: '<em class="text-gray-400">Non renseigne</em>' ?></div></div></div></div><?php foreach ($categories as $key => $label): $color = $colors[$key]; ?><div class="bg-white rounded-xl shadow-lg p-6 mb-4"><h2 class="text-xl font-bold text-<?= $color ?>-700 mb-4"><?= $label ?></h2><ul class="space-y-2"><?php if (empty($pestelData[$key]) || (count($pestelData[$key]) == 1 && empty($pestelData[$key][0]))): ?><li class="text-gray-400 italic">Aucun element</li><?php else: foreach ($pestelData[$key] as $item): if (!empty($item)): ?><li class="flex items-start"><span class="text-<?= $color ?>-600 mr-2">•</span><span class="text-sm"><?= sanitize($item) ?></span></li><?php endif; endforeach; endif; ?></ul></div><?php endforeach; if (!empty($analyse['synthese'])): ?><div class="bg-white rounded-xl shadow-lg p-6"><h2 class="text-xl font-bold text-gray-800 mb-4">Synthese</h2><p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($analyse['synthese']) ?></p></div><?php endif; ?></div></body></html>
