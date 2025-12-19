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

$stmt = $db->prepare("SELECT * FROM objectifs_smart WHERE participant_id = ?");
$stmt->execute([$participantId]);
$data = $stmt->fetch();

$etape1 = json_decode($data['etape1_analyses'] ?? '[]', true) ?: [];
$etape2 = json_decode($data['etape2_reformulations'] ?? '[]', true) ?: [];
$etape3 = json_decode($data['etape3_creations'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Objectifs SMART - <?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></title><script src="https://cdn.tailwindcss.com"></script><style>@media print { .no-print { display: none !important; }}</style></head><body class="bg-gray-100 min-h-screen"><div class="bg-gradient-to-r from-cyan-600 to-blue-600 text-white p-3 shadow-lg no-print sticky top-0 z-50"><div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3"><div><span class="font-medium"><?= sanitize($participant['prenom']) ?> <?= sanitize($participant['nom']) ?></span><span class="text-cyan-200 text-sm ml-2"><?= sanitize($participant['session_nom']) ?> (<?= sanitize($participant['session_code']) ?>)</span></div><div class="flex items-center gap-4"><span class="text-sm px-3 py-1 rounded-full <?= $data['is_submitted'] ? 'bg-green-500' : 'bg-yellow-500' ?>"><?= $data['is_submitted'] ? 'Soumis' : 'Brouillon' ?></span><button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button><a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a></div></div></div><div class="max-w-7xl mx-auto p-4"><div class="bg-white rounded-xl shadow-lg p-6 mb-6"><h1 class="text-2xl font-bold text-gray-800 mb-2">Objectifs SMART</h1><p class="text-sm text-gray-600">Etape courante: <?= $data['etape_courante'] ?? 1 ?>/3 - Completion: <?= $data['completion_percent'] ?? 0 ?>%</p></div><?php if (!empty($etape1)): ?><div class="bg-white rounded-xl shadow-lg p-6 mb-6"><h2 class="text-xl font-bold text-cyan-700 mb-4">Etape 1 : Analyse d'objectifs</h2><?php foreach ($etape1 as $idx => $analyse): ?><div class="mb-4 p-4 bg-gray-50 rounded-lg"><h3 class="font-bold text-sm mb-2">Objectif #<?= $idx + 1 ?></h3><div class="grid grid-cols-5 gap-2 text-xs"><?php $criteres = ['S', 'M', 'A', 'R', 'T']; foreach ($criteres as $c): $val = $analyse[$c] ?? ''; ?><div><span class="font-bold"><?= $c ?>:</span> <span class="<?= $val === 'oui' ? 'text-green-600' : ($val === 'non' ? 'text-red-600' : 'text-orange-600') ?>"><?= sanitize($val) ?></span></div><?php endforeach; ?></div></div><?php endforeach; ?></div><?php endif; if (!empty($etape2)): ?><div class="bg-white rounded-xl shadow-lg p-6 mb-6"><h2 class="text-xl font-bold text-blue-700 mb-4">Etape 2 : Reformulation d'objectifs</h2><?php foreach ($etape2 as $idx => $reform): ?><div class="mb-4 p-4 bg-blue-50 rounded-lg"><h3 class="font-bold text-sm mb-2">Reformulation #<?= $idx + 1 ?></h3><p class="text-sm"><?= sanitize($reform['reformulation'] ?? '') ?></p></div><?php endforeach; ?></div><?php endif; if (!empty($etape3)): ?><div class="bg-white rounded-xl shadow-lg p-6"><h2 class="text-xl font-bold text-green-700 mb-4">Etape 3 : Creation d'objectifs SMART</h2><?php foreach ($etape3 as $idx => $obj): ?><div class="mb-4 p-4 bg-green-50 rounded-lg"><h3 class="font-bold text-sm mb-2">Objectif #<?= $idx + 1 ?></h3><p class="text-sm mb-2"><strong>Objectif:</strong> <?= sanitize($obj['objectif'] ?? '') ?></p><div class="grid grid-cols-2 gap-2 text-xs"><div><strong>Specifique:</strong> <?= sanitize($obj['specifique'] ?? '') ?></div><div><strong>Mesurable:</strong> <?= sanitize($obj['mesurable'] ?? '') ?></div><div><strong>Atteignable:</strong> <?= sanitize($obj['atteignable'] ?? '') ?></div><div><strong>Realiste:</strong> <?= sanitize($obj['realiste'] ?? '') ?></div><div class="col-span-2"><strong>Temporel:</strong> <?= sanitize($obj['temporel'] ?? '') ?></div></div></div><?php endforeach; ?></div><?php endif; ?></div></body></html>
