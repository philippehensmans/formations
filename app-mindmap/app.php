<?php
/**
 * Application Carte Mentale Collaborative
 */
require_once __DIR__ . '/config.php';

// Verifier authentification
if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php');
    exit;
}

$user = getLoggedUser();
$sessionId = $_SESSION['current_session_id'];
$db = getDB();

// Verifier que la session existe et est active
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND is_active = 1");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    unset($_SESSION['current_session_id']);
    header('Location: login.php');
    exit;
}

// Recuperer ou creer la carte mentale
$mindmap = getOrCreateMindmap($sessionId);
$nodes = getNodes($mindmap['id']);
$colors = getColors();
$icons = getIcons();

// Participants connectes (pour affichage)
$stmt = $db->prepare("SELECT p.user_id FROM participants p WHERE p.session_id = ?");
$stmt->execute([$sessionId]);
$participantIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$sharedDb = getSharedDB();
$participants = [];
foreach ($participantIds as $pid) {
    $pStmt = $sharedDb->prepare("SELECT prenom, nom FROM users WHERE id = ?");
    $pStmt->execute([$pid]);
    $p = $pStmt->fetch();
    if ($p) $participants[] = $p['prenom'] . ' ' . substr($p['nom'], 0, 1) . '.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Mentale - <?= h($session['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .mindmap-container {
            position: relative;
            width: 100%;
            height: calc(100vh - 120px);
            overflow: hidden;
            background: linear-gradient(#f1f5f9 1px, transparent 1px),
                        linear-gradient(90deg, #f1f5f9 1px, transparent 1px);
            background-size: 20px 20px;
            cursor: grab;
        }
        .mindmap-container.grabbing { cursor: grabbing; }
        .mindmap-canvas {
            position: absolute;
            transform-origin: 0 0;
        }
        .node {
            position: absolute;
            min-width: 120px;
            max-width: 200px;
            padding: 8px 16px;
            border-radius: 20px;
            border: 3px solid;
            cursor: move;
            user-select: none;
            font-size: 14px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transition: box-shadow 0.2s, transform 0.1s;
        }
        .node:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.25); }
        .node.dragging { opacity: 0.8; z-index: 1000; }
        .node.root {
            min-width: 150px;
            font-weight: bold;
            font-size: 16px;
        }
        .node-icon { margin-right: 4px; }
        .node-actions {
            position: absolute;
            top: -10px;
            right: -10px;
            display: none;
            gap: 2px;
        }
        .node:hover .node-actions { display: flex; }
        .node-btn {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
            border: none;
        }
        .connections {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
        }
        .sync-indicator {
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .toolbar-btn {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .toolbar-btn:hover { transform: scale(1.05); }
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .modal.hidden { display: none; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-sm h-14 flex items-center px-4 justify-between">
        <div class="flex items-center gap-4">
            <h1 class="font-bold text-violet-600"><?= h($session['nom']) ?></h1>
            <span class="text-sm text-gray-500">Code: <span class="font-mono"><?= h($session['code']) ?></span></span>
            <span id="syncStatus" class="text-xs px-2 py-1 rounded bg-green-100 text-green-700">Connecte</span>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-sm text-gray-500">
                <?= count($participants) ?> participant<?= count($participants) > 1 ? 's' : '' ?>
            </div>
            <span class="text-sm font-medium text-gray-700"><?= h($user['prenom']) ?></span>
            <a href="logout.php" class="text-sm text-gray-500 hover:text-gray-700">Deconnexion</a>
        </div>
    </header>

    <!-- Toolbar -->
    <div class="bg-white border-b px-4 py-2 flex items-center gap-2 flex-wrap">
        <button onclick="zoomIn()" class="toolbar-btn bg-gray-100 hover:bg-gray-200">➕ Zoom</button>
        <button onclick="zoomOut()" class="toolbar-btn bg-gray-100 hover:bg-gray-200">➖ Zoom</button>
        <button onclick="resetView()" class="toolbar-btn bg-gray-100 hover:bg-gray-200">🎯 Centrer</button>
        <div class="w-px h-6 bg-gray-300 mx-2"></div>
        <span class="text-sm text-gray-500">Couleurs:</span>
        <?php foreach ($colors as $colorKey => $colorClass): ?>
            <button onclick="setCurrentColor('<?= $colorKey ?>')"
                    class="w-6 h-6 rounded-full <?= $colorClass['bg'] ?> border-2 border-white shadow color-btn"
                    data-color="<?= $colorKey ?>" title="<?= ucfirst($colorKey) ?>"></button>
        <?php endforeach; ?>
        <div class="w-px h-6 bg-gray-300 mx-2"></div>
        <span class="text-sm text-gray-500">Icones:</span>
        <?php foreach ($icons as $iconKey => $icon): ?>
            <button onclick="setCurrentIcon('<?= $iconKey ?>')"
                    class="toolbar-btn bg-gray-100 hover:bg-gray-200 icon-btn"
                    data-icon="<?= $iconKey ?>" title="<?= $icon['label'] ?>"><?= $icon['emoji'] ?></button>
        <?php endforeach; ?>
        <button onclick="setCurrentIcon(null)" class="toolbar-btn bg-gray-100 hover:bg-gray-200" title="Sans icone">🚫</button>
        <div class="flex-1"></div>
        <button onclick="exportImage()" class="toolbar-btn bg-violet-100 text-violet-700 hover:bg-violet-200">📷 Exporter</button>
    </div>

    <!-- Mindmap Container -->
    <div id="mindmapContainer" class="mindmap-container">
        <svg id="connections" class="connections" width="4000" height="3000"></svg>
        <div id="mindmapCanvas" class="mindmap-canvas"></div>
    </div>

    <!-- Modal Edition -->
    <div id="editModal" class="modal hidden">
        <div class="bg-white rounded-xl shadow-xl p-6 w-96">
            <h3 class="text-lg font-bold mb-4">Modifier le noeud</h3>
            <input type="hidden" id="editNodeId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Texte</label>
                <input type="text" id="editNodeText" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-violet-500" maxlength="100">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                <div class="flex gap-2">
                    <?php foreach ($colors as $colorKey => $colorClass): ?>
                        <button type="button" onclick="selectEditColor('<?= $colorKey ?>')"
                                class="w-8 h-8 rounded-full <?= $colorClass['bg'] ?> edit-color-btn"
                                data-color="<?= $colorKey ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Icone</label>
                <div class="flex gap-2 flex-wrap">
                    <button type="button" onclick="selectEditIcon(null)" class="px-2 py-1 border rounded edit-icon-btn" data-icon="">Aucune</button>
                    <?php foreach ($icons as $iconKey => $icon): ?>
                        <button type="button" onclick="selectEditIcon('<?= $iconKey ?>')"
                                class="px-2 py-1 border rounded edit-icon-btn"
                                data-icon="<?= $iconKey ?>"><?= $icon['emoji'] ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button onclick="closeEditModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Annuler</button>
                <button onclick="saveNodeEdit()" class="px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700">Enregistrer</button>
            </div>
        </div>
    </div>

    <script>
        // Configuration
        const mindmapId = <?= $mindmap['id'] ?>;
        const userId = <?= $user['id'] ?>;
        const icons = <?= json_encode($icons) ?>;
        const colors = <?= json_encode(array_map(fn($c) => $c['bg'], $colors)) ?>;

        // Etat
        let nodes = <?= json_encode($nodes) ?>;
        let scale = 1;
        let panX = 0, panY = 0;
        let isPanning = false;
        let startPan = {x: 0, y: 0};
        let currentColor = 'blue';
        let currentIcon = null;
        let editColor = 'blue';
        let editIcon = null;
        let lastUpdate = '<?= $mindmap['updated_at'] ?>';
        let isSyncing = false;

        // Elements DOM
        const container = document.getElementById('mindmapContainer');
        const canvas = document.getElementById('mindmapCanvas');
        const svg = document.getElementById('connections');

        // Initialisation
        function init() {
            render();
            startPolling();
            setupPanning();
        }

        // Rendu de la carte
        function render() {
            // Effacer
            canvas.innerHTML = '';
            svg.innerHTML = '';

            // Dessiner les liens d'abord
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
                const el = createNodeElement(node);
                canvas.appendChild(el);
            });

            updateTransform();
        }

        // Creer un element noeud
        function createNodeElement(node) {
            const el = document.createElement('div');
            el.className = `node ${node.is_root ? 'root' : ''}`;
            el.dataset.id = node.id;
            el.style.left = node.pos_x + 'px';
            el.style.top = node.pos_y + 'px';

            // Couleur
            const colorClass = getColorClasses(node.color || 'blue');
            el.style.backgroundColor = colorClass.bg;
            el.style.color = colorClass.text;
            el.style.borderColor = colorClass.border;

            // Contenu
            const iconHtml = node.icon && icons[node.icon] ? `<span class="node-icon">${icons[node.icon].emoji}</span>` : '';
            el.innerHTML = `
                ${iconHtml}<span class="node-text">${escapeHtml(node.text)}</span>
                <div class="node-actions">
                    <button class="node-btn bg-green-500 text-white" onclick="addChild(${node.id})" title="Ajouter enfant">+</button>
                    <button class="node-btn bg-blue-500 text-white" onclick="editNode(${node.id})" title="Modifier">✏️</button>
                    ${!node.is_root ? `<button class="node-btn bg-red-500 text-white" onclick="deleteNode(${node.id})" title="Supprimer">×</button>` : ''}
                </div>
            `;

            // Drag & drop
            setupDrag(el, node);

            return el;
        }

        // Couleurs CSS
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

        // Dessiner une connexion
        function drawConnection(parent, child) {
            const childColor = getColorClasses(child.color || 'blue');

            // Ligne d'ombre pour effet de profondeur
            const shadow = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');

            const x1 = parseFloat(parent.pos_x) + 60;
            const y1 = parseFloat(parent.pos_y) + 20;
            const x2 = parseFloat(child.pos_x) + 60;
            const y2 = parseFloat(child.pos_y) + 20;

            // Courbe de Bezier
            const midX = (x1 + x2) / 2;
            const d = `M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}`;

            // Ombre
            shadow.setAttribute('d', d);
            shadow.setAttribute('stroke', 'rgba(0,0,0,0.1)');
            shadow.setAttribute('stroke-width', '8');
            shadow.setAttribute('fill', 'none');
            svg.appendChild(shadow);

            // Ligne principale coloree
            line.setAttribute('d', d);
            line.setAttribute('stroke', childColor.border);
            line.setAttribute('stroke-width', '4');
            line.setAttribute('fill', 'none');
            line.setAttribute('stroke-linecap', 'round');
            svg.appendChild(line);

            // Fleche au milieu du chemin
            const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            const midY = (y1 + y2) / 2;
            const angle = Math.atan2(y2 - y1, x2 - x1);
            const arrowSize = 8;
            const ax = midX;
            const ay = midY;
            const points = [
                [ax + arrowSize * Math.cos(angle), ay + arrowSize * Math.sin(angle)],
                [ax + arrowSize * Math.cos(angle + 2.5), ay + arrowSize * Math.sin(angle + 2.5)],
                [ax + arrowSize * Math.cos(angle - 2.5), ay + arrowSize * Math.sin(angle - 2.5)]
            ];
            arrow.setAttribute('points', points.map(p => p.join(',')).join(' '));
            arrow.setAttribute('fill', childColor.border);
            svg.appendChild(arrow);
        }

        // Setup drag & drop
        function setupDrag(el, node) {
            let startX, startY, origX, origY;
            let isDragging = false;

            el.addEventListener('mousedown', (e) => {
                if (e.target.classList.contains('node-btn')) return;
                e.stopPropagation();
                isDragging = true;
                el.classList.add('dragging');
                startX = e.clientX;
                startY = e.clientY;
                origX = parseFloat(node.pos_x);
                origY = parseFloat(node.pos_y);
            });

            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                const dx = (e.clientX - startX) / scale;
                const dy = (e.clientY - startY) / scale;
                node.pos_x = origX + dx;
                node.pos_y = origY + dy;
                el.style.left = node.pos_x + 'px';
                el.style.top = node.pos_y + 'px';
                render(); // Redessiner les liens
            });

            document.addEventListener('mouseup', () => {
                if (isDragging) {
                    isDragging = false;
                    el.classList.remove('dragging');
                    // Sauvegarder position
                    apiCall('move', {id: node.id, x: node.pos_x, y: node.pos_y});
                }
            });
        }

        // Panning
        function setupPanning() {
            container.addEventListener('mousedown', (e) => {
                if (e.target === container || e.target === svg) {
                    isPanning = true;
                    container.classList.add('grabbing');
                    startPan = {x: e.clientX - panX, y: e.clientY - panY};
                }
            });

            container.addEventListener('mousemove', (e) => {
                if (!isPanning) return;
                panX = e.clientX - startPan.x;
                panY = e.clientY - startPan.y;
                updateTransform();
            });

            container.addEventListener('mouseup', () => {
                isPanning = false;
                container.classList.remove('grabbing');
            });

            container.addEventListener('wheel', (e) => {
                e.preventDefault();
                const delta = e.deltaY > 0 ? 0.9 : 1.1;
                scale = Math.min(Math.max(scale * delta, 0.3), 2);
                updateTransform();
            });
        }

        function updateTransform() {
            canvas.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
            svg.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
        }

        // Zoom controls
        function zoomIn() { scale = Math.min(scale * 1.2, 2); updateTransform(); }
        function zoomOut() { scale = Math.max(scale * 0.8, 0.3); updateTransform(); }
        function resetView() { scale = 1; panX = 0; panY = 0; updateTransform(); }

        // Couleur et icone courantes
        function setCurrentColor(color) {
            currentColor = color;
            document.querySelectorAll('.color-btn').forEach(b => {
                b.style.outline = b.dataset.color === color ? '3px solid #8b5cf6' : 'none';
            });
        }
        function setCurrentIcon(icon) {
            currentIcon = icon;
            document.querySelectorAll('.icon-btn').forEach(b => {
                b.style.background = b.dataset.icon === icon ? '#ddd6fe' : '';
            });
        }

        // Ajouter un enfant
        function addChild(parentId) {
            const parent = nodes.find(n => n.id == parentId);
            const text = prompt('Texte du nouveau noeud:');
            if (!text) return;

            // Position: decaler par rapport au parent
            const angle = Math.random() * Math.PI * 2;
            const distance = 150;
            const x = parseFloat(parent.pos_x) + Math.cos(angle) * distance;
            const y = parseFloat(parent.pos_y) + Math.sin(angle) * distance;

            apiCall('add', {
                parent_id: parentId,
                text: text,
                color: currentColor,
                icon: currentIcon,
                x: x,
                y: y
            });
        }

        // Modal edition
        function editNode(nodeId) {
            const node = nodes.find(n => n.id == nodeId);
            document.getElementById('editNodeId').value = nodeId;
            document.getElementById('editNodeText').value = node.text;
            editColor = node.color || 'blue';
            editIcon = node.icon || null;

            document.querySelectorAll('.edit-color-btn').forEach(b => {
                b.style.outline = b.dataset.color === editColor ? '3px solid #000' : 'none';
            });
            document.querySelectorAll('.edit-icon-btn').forEach(b => {
                b.style.background = b.dataset.icon === (editIcon || '') ? '#ddd6fe' : '';
            });

            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editNodeText').focus();
        }

        function selectEditColor(color) {
            editColor = color;
            document.querySelectorAll('.edit-color-btn').forEach(b => {
                b.style.outline = b.dataset.color === color ? '3px solid #000' : 'none';
            });
        }

        function selectEditIcon(icon) {
            editIcon = icon;
            document.querySelectorAll('.edit-icon-btn').forEach(b => {
                b.style.background = b.dataset.icon === (icon || '') ? '#ddd6fe' : '';
            });
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function saveNodeEdit() {
            const id = document.getElementById('editNodeId').value;
            const text = document.getElementById('editNodeText').value.trim();
            if (!text) return;

            apiCall('update', {id: id, text: text, color: editColor, icon: editIcon});
            closeEditModal();
        }

        // Supprimer
        function deleteNode(nodeId) {
            if (!confirm('Supprimer ce noeud et tous ses enfants?')) return;
            apiCall('delete', {id: nodeId});
        }

        // API
        async function apiCall(action, data = {}) {
            const status = document.getElementById('syncStatus');
            status.textContent = 'Synchronisation...';
            status.className = 'text-xs px-2 py-1 rounded bg-yellow-100 text-yellow-700 sync-indicator';

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action, mindmap_id: mindmapId, ...data})
                });
                const result = await response.json();

                if (result.success) {
                    if (result.nodes) {
                        nodes = result.nodes;
                        render();
                    }
                    lastUpdate = result.updated_at || lastUpdate;
                    status.textContent = 'Connecte';
                    status.className = 'text-xs px-2 py-1 rounded bg-green-100 text-green-700';
                } else {
                    throw new Error(result.error || 'Erreur');
                }
            } catch (e) {
                status.textContent = 'Erreur';
                status.className = 'text-xs px-2 py-1 rounded bg-red-100 text-red-700';
                console.error(e);
            }
        }

        // Polling pour synchro
        function startPolling() {
            setInterval(async () => {
                if (isSyncing) return;
                isSyncing = true;
                try {
                    const response = await fetch(`api.php?action=poll&mindmap_id=${mindmapId}&since=${encodeURIComponent(lastUpdate)}`);
                    const result = await response.json();
                    if (result.updated && result.nodes) {
                        nodes = result.nodes;
                        lastUpdate = result.updated_at;
                        render();
                    }
                } catch (e) {
                    console.error('Poll error:', e);
                }
                isSyncing = false;
            }, 2000);
        }

        // Export image
        function exportImage() {
            alert('Pour exporter: utilisez la fonction capture d\'ecran de votre navigateur (Ctrl+Shift+S sur Firefox, ou extension)');
        }

        // Utils
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Init
        init();
        setCurrentColor('blue');
    </script>
</body>
</html>
