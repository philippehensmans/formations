<?php
/**
 * Vue en lecture seule - Carte Mentale
 */
require_once __DIR__ . '/config.php';

if (!isFormateur()) {
    header('Location: login.php');
    exit;
}

$appKey = 'app-mindmap';

$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) {
    header('Location: formateur.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();

// Recuperer le participant
$stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id FROM participants p JOIN sessions s ON p.session_id = s.id WHERE p.id = ?");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    die("Participant non trouve");
}

// Verifier l'acces a cette session
if (!canAccessSession($appKey, $participant['session_id'])) {
    die("Acces refuse a cette session.");
}

$userStmt = $sharedDb->prepare("SELECT prenom, nom, organisation FROM users WHERE id = ?");
$userStmt->execute([$participant['user_id']]);
$userInfo = $userStmt->fetch();

// Recuperer la carte mentale de la session
$mindmap = getOrCreateMindmap($participant['session_id']);
$nodes = getNodes($mindmap['id']);
$icons = getIcons();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Mentale - <?= h($participant['session_nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
        .mindmap-container {
            position: relative;
            width: 100%;
            height: calc(100vh - 80px);
            overflow: auto;
            background: linear-gradient(#f1f5f9 1px, transparent 1px),
                        linear-gradient(90deg, #f1f5f9 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .mindmap-canvas {
            position: relative;
            min-width: 2000px;
            min-height: 1500px;
        }
        .node {
            position: absolute;
            min-width: 120px;
            max-width: 200px;
            padding: 8px 16px;
            border-radius: 20px;
            border: 3px solid;
            font-size: 14px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .node.root {
            min-width: 150px;
            font-weight: bold;
            font-size: 16px;
        }
        .node-icon { margin-right: 4px; }
        .connections {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
            transform-origin: 0 0;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gradient-to-r from-violet-600 to-violet-800 text-white p-3 shadow-lg no-print sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <span class="font-medium">Carte Mentale</span>
                <span class="text-violet-200 text-sm ml-2"><?= h($participant['session_nom']) ?> (<?= h($participant['session_code']) ?>)</span>
            </div>
            <div class="flex gap-3">
                <span class="text-sm"><?= count($nodes) ?> noeuds</span>
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Imprimer</button>
                <a href="formateur.php?session=<?= $participant['session_id'] ?>" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Retour</a>
            </div>
        </div>
    </div>

    <div class="mindmap-container">
        <svg id="connections" class="connections" width="2000" height="1500"></svg>
        <div id="mindmapCanvas" class="mindmap-canvas"></div>
    </div>

    <script>
        const nodes = <?= json_encode($nodes) ?>;
        const icons = <?= json_encode($icons) ?>;
        const canvas = document.getElementById('mindmapCanvas');
        const svg = document.getElementById('connections');

        function getColorClasses(color) {
            const map = {
                violet: {bg: '#8b5cf6', text: '#fff', border: '#7c3aed'},
                blue: {bg: '#3b82f6', text: '#fff', border: '#2563eb'},
                green: {bg: '#22c55e', text: '#fff', border: '#16a34a'},
                yellow: {bg: '#facc15', text: '#1f2937', border: '#eab308'},
                orange: {bg: '#f97316', text: '#fff', border: '#ea580c'},
                red: {bg: '#ef4444', text: '#fff', border: '#dc2626'},
                pink: {bg: '#ec4899', text: '#fff', border: '#db2777'},
                gray: {bg: '#6b7280', text: '#fff', border: '#4b5563'},
            };
            return map[color] || map.blue;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function drawConnection(parent, child) {
            const childColor = getColorClasses(child.color || 'blue');

            const x1 = parseFloat(parent.pos_x) + 60;
            const y1 = parseFloat(parent.pos_y) + 20;
            const x2 = parseFloat(child.pos_x) + 60;
            const y2 = parseFloat(child.pos_y) + 20;
            const midX = (x1 + x2) / 2;
            const d = `M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}`;

            // Ombre
            const shadow = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            shadow.setAttribute('d', d);
            shadow.setAttribute('stroke', 'rgba(0,0,0,0.1)');
            shadow.setAttribute('stroke-width', '8');
            shadow.setAttribute('fill', 'none');
            svg.appendChild(shadow);

            // Ligne coloree
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            line.setAttribute('d', d);
            line.setAttribute('stroke', childColor.border);
            line.setAttribute('stroke-width', '4');
            line.setAttribute('fill', 'none');
            line.setAttribute('stroke-linecap', 'round');
            svg.appendChild(line);

            // Fleche
            const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            const midY = (y1 + y2) / 2;
            const angle = Math.atan2(y2 - y1, x2 - x1);
            const arrowSize = 8;
            const points = [
                [midX + arrowSize * Math.cos(angle), midY + arrowSize * Math.sin(angle)],
                [midX + arrowSize * Math.cos(angle + 2.5), midY + arrowSize * Math.sin(angle + 2.5)],
                [midX + arrowSize * Math.cos(angle - 2.5), midY + arrowSize * Math.sin(angle - 2.5)]
            ];
            arrow.setAttribute('points', points.map(p => p.join(',')).join(' '));
            arrow.setAttribute('fill', childColor.border);
            svg.appendChild(arrow);
        }

        function render() {
            // Dessiner les liens
            nodes.forEach(node => {
                if (node.parent_id) {
                    const parent = nodes.find(n => n.id == node.parent_id);
                    if (parent) {
                        drawConnection(parent, node);
                    }
                }
            });

            // Dessiner les noeuds
            nodes.forEach(node => {
                const el = document.createElement('div');
                el.className = `node ${node.is_root ? 'root' : ''}`;
                el.style.left = node.pos_x + 'px';
                el.style.top = node.pos_y + 'px';
                const colorClass = getColorClasses(node.color || 'blue');
                el.style.backgroundColor = colorClass.bg;
                el.style.color = colorClass.text;
                el.style.borderColor = colorClass.border;

                // Indicateurs note et lien
                const hasNote = node.note && node.note.trim();
                const hasFile = node.file_url && node.file_url.trim();
                const indicators = [];
                if (hasNote) indicators.push('<span title="A une note">📝</span>');
                if (hasFile) indicators.push(`<a href="${escapeHtml(node.file_url)}" target="_blank" title="Ouvrir le lien">🔗</a>`);
                const indicatorHtml = indicators.length ? `<span style="margin-left:4px;font-size:12px">${indicators.join('')}</span>` : '';

                const iconHtml = node.icon && icons[node.icon] ? `<span class="node-icon">${icons[node.icon].emoji}</span>` : '';
                el.innerHTML = `${iconHtml}<span>${escapeHtml(node.text)}</span>${indicatorHtml}`;

                if (hasNote) el.title = node.note;

                canvas.appendChild(el);
            });
        }

        render();
    </script>
</body>
</html>
