<?php
require_once __DIR__ . '/config.php';

$user = requireAuth();
$db = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));

    if (empty($code)) {
        $error = t('wb.error_code_required');
    } else {
        // Check if session exists
        $stmt = $db->prepare("SELECT * FROM sessions WHERE code = ? AND is_active = 1");
        $stmt->execute([$code]);
        $session = $stmt->fetch();

        if (!$session) {
            $error = t('wb.error_session_not_found');
        } else {
            // Add participant if not already joined
            $stmt = $db->prepare("INSERT OR IGNORE INTO participants (session_id, user_id) VALUES (?, ?)");
            $stmt->execute([$session['id'], $user['id']]);

            // Redirect to whiteboard
            header('Location: app.php?session=' . $code);
            exit;
        }
    }
}

// If we reach here with an error, redirect back to app with error
if ($error) {
    header('Location: app.php?error=' . urlencode($error));
    exit;
}

header('Location: app.php');
exit;
