<?php
require_once __DIR__ . '/config.php';

// Verification de l'authentification avec session
requireLoginWithSession();

$user = getLoggedUser();
$db = getDB();
$sessionId = $_SESSION['current_session_id'];

// Verifier que la session existe et est active
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND is_active = 1");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    unset($_SESSION['current_session_id']);
    header('Location: login.php');
    exit;
}

// Recuperer ou creer le tableau blanc
$whiteboard = getOrCreateWhiteboard($sessionId);
$elements = getElements($whiteboard['id']);
$paths = getPaths($whiteboard['id']);
$colors = getColors();
$elementTypes = getElementTypes();

// Participants connectes
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
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('wb.title') ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .canvas-container {
            cursor: crosshair;
        }
        .element {
            position: absolute;
            cursor: move;
            user-select: none;
        }
        .element.selected {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }
        .postit {
            padding: 12px;
            min-width: 120px;
            min-height: 80px;
            box-shadow: 2px 2px 8px rgba(0,0,0,0.2);
            border-radius: 2px;
            font-family: 'Comic Sans MS', cursive, sans-serif;
        }
        .postit::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            border-width: 0 16px 16px 0;
            border-style: solid;
            border-color: rgba(0,0,0,0.1) #f3f4f6 rgba(0,0,0,0.1);
        }
        .resize-handle {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #3b82f6;
            border-radius: 50%;
            cursor: se-resize;
            right: -5px;
            bottom: -5px;
        }
        .tool-btn.active {
            background-color: #4f46e5;
            color: white;
        }
        #drawing-canvas {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
        }
        #drawing-canvas.drawing-mode {
            pointer-events: auto;
            cursor: crosshair;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-indigo-600 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <span class="text-2xl font-bold">🎨 <?= APP_NAME ?></span>
                <span class="text-indigo-200">|</span>
                <span class="text-indigo-100"><?= htmlspecialchars($session['nom']) ?></span>
                <span class="bg-indigo-500 px-2 py-1 rounded text-sm"><?= $session['code'] ?></span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-indigo-200"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></span>
                <?php include __DIR__ . '/../shared-auth/lang-switcher.php'; ?>
                <a href="logout.php" class="bg-indigo-500 hover:bg-indigo-400 px-3 py-1 rounded text-sm">
                    <?= t('auth.logout') ?>
                </a>
            </div>
        </div>
    </header>
    <!-- Whiteboard Interface -->
    <div class="flex h-[calc(100vh-60px)]">
        <!-- Toolbar -->
        <div class="w-16 bg-gray-800 flex flex-col items-center py-4 gap-2">
            <button class="tool-btn active p-3 rounded-lg text-white hover:bg-gray-700" data-tool="select" title="<?= t('wb.tool_select') ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                </svg>
            </button>
            <button class="tool-btn p-3 rounded-lg text-white hover:bg-gray-700" data-tool="postit" title="<?= t('wb.tool_postit') ?>">
                📝
            </button>
            <button class="tool-btn p-3 rounded-lg text-white hover:bg-gray-700" data-tool="text" title="<?= t('wb.tool_text') ?>">
                <span class="text-xl font-bold">T</span>
            </button>
            <button class="tool-btn p-3 rounded-lg text-white hover:bg-gray-700" data-tool="rect" title="<?= t('wb.tool_rect') ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" stroke-width="2"/>
                </svg>
            </button>
            <button class="tool-btn p-3 rounded-lg text-white hover:bg-gray-700" data-tool="circle" title="<?= t('wb.tool_circle') ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="2"/>
                </svg>
            </button>
            <button class="tool-btn p-3 rounded-lg text-white hover:bg-gray-700" data-tool="draw" title="<?= t('wb.tool_draw') ?>">
                ✏️
            </button>
            <button class="tool-btn p-3 rounded-lg text-white hover:bg-gray-700" data-tool="eraser" title="<?= t('wb.tool_eraser') ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>

            <div class="flex-1"></div>

            <!-- Color Picker -->
            <div class="relative" id="color-picker">
                <button class="p-3 rounded-lg text-white hover:bg-gray-700" id="color-btn">
                    <div class="w-6 h-6 rounded border-2 border-white" id="current-color" style="background-color: #fef08a;"></div>
                </button>
                <div class="hidden absolute left-full ml-2 bottom-0 bg-white rounded-lg shadow-xl p-2 grid grid-cols-4 gap-1" id="color-menu">
                    <?php foreach ($colors as $name => $color): ?>
                        <button class="w-8 h-8 rounded border hover:scale-110 transition"
                                style="background-color: <?= $color['hex'] ?>;"
                                data-color="<?= $name ?>" data-hex="<?= $color['hex'] ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Stroke Width -->
            <div class="px-2 text-white text-xs text-center">
                <input type="range" min="1" max="10" value="2" class="w-full" id="stroke-width">
                <span id="stroke-label">2px</span>
            </div>
        </div>

        <!-- Canvas Area -->
        <div class="flex-1 relative overflow-hidden bg-white" id="canvas-container">
            <svg id="drawing-canvas" width="100%" height="100%">
                <!-- Paths will be drawn here -->
                <?php foreach ($paths as $path): ?>
                    <path d="<?= htmlspecialchars($path['points']) ?>"
                          stroke="<?= htmlspecialchars($path['color']) ?>"
                          stroke-width="<?= $path['stroke_width'] ?>"
                          fill="none"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          data-id="<?= $path['id'] ?>"/>
                <?php endforeach; ?>
            </svg>

            <!-- Elements Container -->
            <div id="elements-container">
                <?php foreach ($elements as $el): ?>
                    <div class="element <?= $el['type'] ?>"
                         data-id="<?= $el['id'] ?>"
                         data-type="<?= $el['type'] ?>"
                         style="left: <?= $el['x'] ?>px; top: <?= $el['y'] ?>px; width: <?= $el['width'] ?>px; height: <?= $el['height'] ?>px; background-color: <?= $colors[$el['color']]['hex'] ?? '#fef08a' ?>; transform: rotate(<?= $el['rotation'] ?>deg); z-index: <?= $el['z_index'] ?>;">
                        <?php if ($el['type'] === 'postit' || $el['type'] === 'text'): ?>
                            <div class="content" contenteditable="true"><?= htmlspecialchars($el['content'] ?? '') ?></div>
                        <?php endif; ?>
                        <div class="resize-handle hidden"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Participants Panel -->
        <div class="w-48 bg-gray-50 border-l p-4">
            <h3 class="font-medium text-gray-700 mb-3"><?= t('wb.participants') ?></h3>
            <div id="participants-list" class="space-y-2 text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                    <span><?= htmlspecialchars($user['prenom']) ?></span>
                    <span class="text-gray-400">(<?= t('wb.you') ?>)</span>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t">
                <button id="clear-btn" class="w-full bg-red-100 text-red-700 px-3 py-2 rounded hover:bg-red-200 text-sm">
                    <?= t('wb.clear_all') ?>
                </button>
            </div>
        </div>
    </div>

    <script>
        const whiteboardId = <?= $whiteboard['id'] ?>;
        const userId = <?= $user['id'] ?>;
        const colors = <?= json_encode($colors) ?>;

        let currentTool = 'select';
        let currentColor = 'yellow';
        let currentColorHex = '#fef08a';
        let strokeWidth = 2;
        let selectedElement = null;
        let isDrawing = false;
        let currentPath = null;
        let pathPoints = [];
        let lastUpdate = 0;

        // Tool selection
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentTool = btn.dataset.tool;

                const canvas = document.getElementById('drawing-canvas');
                if (currentTool === 'draw') {
                    canvas.classList.add('drawing-mode');
                } else {
                    canvas.classList.remove('drawing-mode');
                }
            });
        });

        // Color picker
        document.getElementById('color-btn').addEventListener('click', () => {
            document.getElementById('color-menu').classList.toggle('hidden');
        });

        document.querySelectorAll('#color-menu button').forEach(btn => {
            btn.addEventListener('click', () => {
                currentColor = btn.dataset.color;
                currentColorHex = btn.dataset.hex;
                document.getElementById('current-color').style.backgroundColor = currentColorHex;
                document.getElementById('color-menu').classList.add('hidden');
            });
        });

        // Stroke width
        document.getElementById('stroke-width').addEventListener('input', (e) => {
            strokeWidth = e.target.value;
            document.getElementById('stroke-label').textContent = strokeWidth + 'px';
        });

        // Canvas interaction
        const container = document.getElementById('canvas-container');
        const elementsContainer = document.getElementById('elements-container');
        const svg = document.getElementById('drawing-canvas');

        container.addEventListener('mousedown', (e) => {
            // Ne pas creer d'element si on clique sur un element existant
            if (e.target.closest('.element')) return;

            if (currentTool === 'draw') {
                startDrawing(e);
            } else if (currentTool === 'postit' || currentTool === 'text' || currentTool === 'rect' || currentTool === 'circle') {
                createElement(e);
            }
        });

        container.addEventListener('mousemove', (e) => {
            if (isDrawing) {
                draw(e);
            }
        });

        container.addEventListener('mouseup', () => {
            if (isDrawing) {
                stopDrawing();
            }
        });

        container.addEventListener('mouseleave', () => {
            if (isDrawing) {
                stopDrawing();
            }
        });

        function startDrawing(e) {
            isDrawing = true;
            pathPoints = [];
            const rect = svg.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            pathPoints.push(`M ${x} ${y}`);

            currentPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            currentPath.setAttribute('stroke', currentColorHex);
            currentPath.setAttribute('stroke-width', strokeWidth);
            currentPath.setAttribute('fill', 'none');
            currentPath.setAttribute('stroke-linecap', 'round');
            currentPath.setAttribute('stroke-linejoin', 'round');
            svg.appendChild(currentPath);
        }

        function draw(e) {
            if (!isDrawing || !currentPath) return;
            const rect = svg.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            pathPoints.push(`L ${x} ${y}`);
            currentPath.setAttribute('d', pathPoints.join(' '));
        }

        function stopDrawing() {
            if (!isDrawing) return;
            isDrawing = false;

            if (pathPoints.length > 1) {
                // Save path to server
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_path',
                        whiteboard_id: whiteboardId,
                        points: pathPoints.join(' '),
                        color: currentColorHex,
                        stroke_width: strokeWidth
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && currentPath) {
                        currentPath.dataset.id = data.id;
                    }
                });
            }
            currentPath = null;
        }

        function createElement(e) {
            const rect = container.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const width = currentTool === 'postit' ? 150 : (currentTool === 'text' ? 200 : 100);
            const height = currentTool === 'postit' ? 100 : (currentTool === 'text' ? 40 : 100);

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_element',
                    whiteboard_id: whiteboardId,
                    type: currentTool,
                    x: x,
                    y: y,
                    width: width,
                    height: height,
                    color: currentColor,
                    content: currentTool === 'postit' ? '<?= t('wb.new_postit') ?>' : ''
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    addElementToDOM(data.element);
                }
            });
        }

        function addElementToDOM(el) {
            const div = document.createElement('div');
            div.className = `element ${el.type}`;
            div.dataset.id = el.id;
            div.dataset.type = el.type;
            div.style.left = el.x + 'px';
            div.style.top = el.y + 'px';
            div.style.width = el.width + 'px';
            div.style.height = el.height + 'px';
            div.style.backgroundColor = colors[el.color]?.hex || '#fef08a';
            div.style.zIndex = el.z_index || 0;

            if (el.type === 'postit' || el.type === 'text') {
                const content = document.createElement('div');
                content.className = 'content';
                content.contentEditable = true;
                content.textContent = el.content || '';
                div.appendChild(content);
            }

            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'resize-handle hidden';
            div.appendChild(resizeHandle);

            initElementInteraction(div);
            elementsContainer.appendChild(div);
        }

        // Initialize existing elements
        document.querySelectorAll('.element').forEach(el => {
            initElementInteraction(el);
        });

        function initElementInteraction(el) {
            let isDragging = false;
            let startX, startY, origX, origY;

            el.addEventListener('mousedown', (e) => {
                // Toujours stopper la propagation pour eviter de creer un nouvel element
                e.stopPropagation();

                if (currentTool === 'eraser') {
                    deleteElement(el);
                    return;
                }

                // Si pas en mode selection, on ne fait rien d'autre
                if (currentTool !== 'select') return;

                // Select element
                document.querySelectorAll('.element').forEach(elem => {
                    elem.classList.remove('selected');
                    elem.querySelector('.resize-handle')?.classList.add('hidden');
                });
                el.classList.add('selected');
                el.querySelector('.resize-handle')?.classList.remove('hidden');
                selectedElement = el;

                // Start dragging
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                origX = parseInt(el.style.left);
                origY = parseInt(el.style.top);
            });

            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                el.style.left = (origX + dx) + 'px';
                el.style.top = (origY + dy) + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (isDragging) {
                    isDragging = false;
                    updateElement(el);
                }
            });

            // Content editing
            const content = el.querySelector('.content');
            if (content) {
                content.addEventListener('blur', () => {
                    updateElement(el);
                });
            }
        }

        function updateElement(el) {
            const content = el.querySelector('.content');
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_element',
                    id: el.dataset.id,
                    x: parseInt(el.style.left),
                    y: parseInt(el.style.top),
                    width: parseInt(el.style.width),
                    height: parseInt(el.style.height),
                    content: content ? content.textContent : null
                })
            });
        }

        function deleteElement(el) {
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_element',
                    id: el.dataset.id
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    el.remove();
                }
            });
        }

        // Clear all
        document.getElementById('clear-btn').addEventListener('click', () => {
            if (confirm('<?= t('wb.confirm_clear') ?>')) {
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'clear_all',
                        whiteboard_id: whiteboardId
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        elementsContainer.innerHTML = '';
                        svg.innerHTML = '';
                    }
                });
            }
        });

        // Polling for updates
        function pollUpdates() {
            fetch(`api.php?action=poll&whiteboard_id=${whiteboardId}&since=${lastUpdate}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        lastUpdate = data.timestamp;

                        // Update elements
                        data.elements.forEach(el => {
                            const existing = document.querySelector(`.element[data-id="${el.id}"]`);
                            if (existing) {
                                existing.style.left = el.x + 'px';
                                existing.style.top = el.y + 'px';
                                existing.style.width = el.width + 'px';
                                existing.style.height = el.height + 'px';
                                const content = existing.querySelector('.content');
                                if (content && el.content) {
                                    content.textContent = el.content;
                                }
                            } else {
                                addElementToDOM(el);
                            }
                        });

                        // Remove deleted elements
                        data.deleted_elements.forEach(id => {
                            document.querySelector(`.element[data-id="${id}"]`)?.remove();
                        });

                        // Update paths
                        data.paths.forEach(path => {
                            if (!svg.querySelector(`path[data-id="${path.id}"]`)) {
                                const pathEl = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                                pathEl.setAttribute('d', path.points);
                                pathEl.setAttribute('stroke', path.color);
                                pathEl.setAttribute('stroke-width', path.stroke_width);
                                pathEl.setAttribute('fill', 'none');
                                pathEl.setAttribute('stroke-linecap', 'round');
                                pathEl.dataset.id = path.id;
                                svg.appendChild(pathEl);
                            }
                        });
                    }
                })
                .catch(() => {})
                .finally(() => {
                    setTimeout(pollUpdates, 2000);
                });
        }

        pollUpdates();

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Delete' && selectedElement) {
                deleteElement(selectedElement);
                selectedElement = null;
            }
            if (e.key === 'Escape') {
                document.querySelectorAll('.element').forEach(el => {
                    el.classList.remove('selected');
                    el.querySelector('.resize-handle')?.classList.add('hidden');
                });
                selectedElement = null;
            }
        });
    </script>
</body>
</html>
