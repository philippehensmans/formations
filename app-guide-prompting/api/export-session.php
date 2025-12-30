<?php
/**
 * Export all prompts from a session as HTML document
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../shared-auth/sessions.php';
require_once __DIR__ . '/../../shared-auth/lang.php';

// Verify trainer is logged in
if (!isLoggedIn() || !isFormateur()) {
    http_response_code(403);
    exit('Forbidden');
}

$sessionId = (int)($_GET['session_id'] ?? 0);
if (!$sessionId) {
    http_response_code(400);
    exit('Missing session_id');
}

// Verify access to session
$appKey = 'app-guide-prompting';
if (!canAccessSession($appKey, $sessionId)) {
    http_response_code(403);
    exit('Access denied');
}

$db = getDB();
$sharedDb = getSharedDB();

// Get session info
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    exit('Session not found');
}

// Get all participants data for this session
$stmt = $db->prepare("
    SELECT g.*, p.user_id
    FROM guides g
    JOIN participants p ON g.user_id = p.user_id AND g.session_id = p.session_id
    WHERE p.session_id = ?
    ORDER BY g.updated_at DESC
");
$stmt->execute([$sessionId]);
$guides = $stmt->fetchAll();

// Enrich with user data
$participantsData = [];
foreach ($guides as $guide) {
    $userStmt = $sharedDb->prepare("SELECT username, prenom, nom, organisation FROM users WHERE id = ?");
    $userStmt->execute([$guide['user_id']]);
    $userData = $userStmt->fetch();

    if ($userData) {
        $participantsData[] = [
            'user' => $userData,
            'guide' => $guide,
            'tasks' => json_decode($guide['tasks'] ?? '[]', true) ?: [],
            'experimentations' => json_decode($guide['experimentations'] ?? '[]', true) ?: [],
            'templates' => json_decode($guide['templates'] ?? '[]', true) ?: []
        ];
    }
}

// Generate HTML document
$html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . t('gp.title') . ' - Session ' . htmlspecialchars($session['code']) . '</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { color: #4f46e5; margin-bottom: 10px; }
        h2 { color: #1f2937; margin: 30px 0 15px; padding-bottom: 10px; border-bottom: 2px solid #e5e7eb; }
        h3 { color: #4b5563; margin: 20px 0 10px; }
        h4 { color: #6b7280; margin: 15px 0 8px; font-size: 0.95em; }
        .header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 3px solid #4f46e5; }
        .meta { color: #6b7280; font-size: 0.9em; }
        .participant { background: #f9fafb; border-radius: 12px; padding: 25px; margin-bottom: 30px; border: 1px solid #e5e7eb; page-break-inside: avoid; }
        .participant-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb; }
        .participant-name { font-size: 1.2em; font-weight: 600; color: #1f2937; }
        .org { color: #6b7280; font-size: 0.9em; }
        .task { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #4f46e5; }
        .task-name { font-weight: 600; color: #4f46e5; margin-bottom: 8px; }
        .task-detail { font-size: 0.9em; color: #4b5563; margin-bottom: 5px; }
        .task-detail strong { color: #1f2937; }
        .template { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #d1d5db; }
        .template-section { margin-bottom: 15px; }
        .template-section-title { font-weight: 600; color: #4f46e5; font-size: 0.85em; text-transform: uppercase; margin-bottom: 5px; }
        .template-section-content { background: #f3f4f6; padding: 12px; border-radius: 6px; font-size: 0.9em; white-space: pre-wrap; }
        .prompt-box { background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 15px; margin: 10px 0; }
        .prompt-label { font-weight: 600; color: #4338ca; font-size: 0.85em; margin-bottom: 8px; }
        .prompt-content { white-space: pre-wrap; font-family: monospace; font-size: 0.9em; }
        .no-data { color: #9ca3af; font-style: italic; }
        .tips { background: #fef3c7; border-radius: 6px; padding: 12px; margin-top: 10px; }
        .tips-title { font-weight: 600; color: #92400e; font-size: 0.85em; margin-bottom: 5px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat { background: #f3f4f6; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 2em; font-weight: 700; color: #4f46e5; }
        .stat-label { color: #6b7280; font-size: 0.85em; }
        @media print {
            body { max-width: 100%; }
            .participant { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . t('gp.title') . '</h1>
        <p class="meta">Session: <strong>' . htmlspecialchars($session['code']) . '</strong> - ' . htmlspecialchars($session['nom']) . '</p>
        <p class="meta">' . t('app.export_date') . ': ' . date('d/m/Y H:i') . '</p>
    </div>

    <div class="stats">
        <div class="stat">
            <div class="stat-value">' . count($participantsData) . '</div>
            <div class="stat-label">' . t('trainer.participants') . '</div>
        </div>
        <div class="stat">
            <div class="stat-value">' . array_sum(array_map(fn($p) => count($p['tasks']), $participantsData)) . '</div>
            <div class="stat-label">' . t('gp.step1') . '</div>
        </div>
        <div class="stat">
            <div class="stat-value">' . array_sum(array_map(fn($p) => count($p['templates']), $participantsData)) . '</div>
            <div class="stat-label">' . t('gp.step3') . '</div>
        </div>
    </div>';

if (empty($participantsData)) {
    $html .= '<p class="no-data">' . t('trainer.no_participant_in_session') . '</p>';
} else {
    foreach ($participantsData as $data) {
        $user = $data['user'];
        $guide = $data['guide'];
        $tasks = $data['tasks'];
        $templates = $data['templates'];

        $html .= '
    <div class="participant">
        <div class="participant-header">
            <div>
                <div class="participant-name">' . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . '</div>
                <div class="org">' . htmlspecialchars($user['organisation'] ?? '') . '</div>
            </div>
            <div class="org">' . htmlspecialchars($guide['organisation_nom'] ?? '') . '</div>
        </div>';

        if (!empty($guide['organisation_mission'])) {
            $html .= '<p><strong>' . t('gp.org_mission') . ':</strong> ' . htmlspecialchars($guide['organisation_mission']) . '</p>';
        }

        // Tasks
        if (!empty($tasks)) {
            $html .= '<h3>' . t('gp.tasks_title') . '</h3>';
            foreach ($tasks as $idx => $task) {
                $html .= '
        <div class="task">
            <div class="task-name">' . ($idx + 1) . '. ' . htmlspecialchars($task['name'] ?? 'Sans nom') . '</div>';
                if (!empty($task['objective'])) {
                    $html .= '<div class="task-detail"><strong>' . t('gp.task_objective') . ':</strong> ' . htmlspecialchars($task['objective']) . '</div>';
                }
                if (!empty($task['audience'])) {
                    $html .= '<div class="task-detail"><strong>' . t('gp.task_audience') . ':</strong> ' . htmlspecialchars($task['audience']) . '</div>';
                }
                if (!empty($task['style'])) {
                    $html .= '<div class="task-detail"><strong>' . t('gp.task_style') . ':</strong> ' . htmlspecialchars($task['style']) . '</div>';
                }
                if (!empty($task['elements'])) {
                    $html .= '<div class="task-detail"><strong>' . t('gp.task_elements') . ':</strong> ' . htmlspecialchars($task['elements']) . '</div>';
                }
                $html .= '</div>';
            }
        }

        // Templates (prompts)
        if (!empty($templates)) {
            $html .= '<h3>' . t('gp.step3') . '</h3>';
            foreach ($templates as $template) {
                $taskName = '';
                foreach ($tasks as $t) {
                    if (($t['id'] ?? '') === ($template['taskId'] ?? '')) {
                        $taskName = $t['name'] ?? '';
                        break;
                    }
                }

                $html .= '
        <div class="template">
            <h4>' . t('gp.template_task') . ': ' . htmlspecialchars($taskName ?: 'N/A') . '</h4>';

                // Build full prompt
                $promptParts = [];
                if (!empty($template['context'])) {
                    $html .= '<div class="template-section"><div class="template-section-title">' . t('gp.section_context') . '</div><div class="template-section-content">' . htmlspecialchars($template['context']) . '</div></div>';
                    $promptParts[] = $template['context'];
                }
                if (!empty($template['task'])) {
                    $html .= '<div class="template-section"><div class="template-section-title">' . t('gp.section_task') . '</div><div class="template-section-content">' . htmlspecialchars($template['task']) . '</div></div>';
                    $promptParts[] = $template['task'];
                }
                if (!empty($template['format'])) {
                    $html .= '<div class="template-section"><div class="template-section-title">' . t('gp.section_format') . '</div><div class="template-section-content">' . htmlspecialchars($template['format']) . '</div></div>';
                    $promptParts[] = $template['format'];
                }
                if (!empty($template['instructions'])) {
                    $html .= '<div class="template-section"><div class="template-section-title">' . t('gp.section_instructions') . '</div><div class="template-section-content">' . htmlspecialchars($template['instructions']) . '</div></div>';
                    $promptParts[] = $template['instructions'];
                }
                if (!empty($template['examples'])) {
                    $html .= '<div class="template-section"><div class="template-section-title">' . t('gp.section_examples') . '</div><div class="template-section-content">' . htmlspecialchars($template['examples']) . '</div></div>';
                    $promptParts[] = $template['examples'];
                }

                // Full prompt box
                if (!empty($promptParts)) {
                    $html .= '
            <div class="prompt-box">
                <div class="prompt-label">' . t('gp.full_prompt') . '</div>
                <div class="prompt-content">' . htmlspecialchars(implode("\n\n", $promptParts)) . '</div>
            </div>';
                }

                if (!empty($template['tips'])) {
                    $html .= '
            <div class="tips">
                <div class="tips-title">' . t('gp.tips_label') . '</div>
                <div>' . htmlspecialchars($template['tips']) . '</div>
            </div>';
                }

                $html .= '</div>';
            }
        }

        $html .= '</div>';
    }
}

$html .= '
</body>
</html>';

// Output as downloadable HTML file
$filename = 'prompts-session-' . $session['code'] . '-' . date('Y-m-d') . '.html';
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $html;
