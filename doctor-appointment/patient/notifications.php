<?php
// patient/notifications.php

require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

// Current logged-in user
$uid = current_user_id();

// Handle marking as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_id'])) {

    // CSRF validate
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        if (function_exists('flash_set')) flash_set('error', 'Invalid CSRF token.');
        header('Location: notifications.php');
        exit;
    }

    $id = intval($_POST['mark_id']);

    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $uid]);

    header('Location: notifications.php');
    exit;
}

// Fetch notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 200");
$stmt->execute([$uid]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper safe output
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Notifications</title>
    <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
    <style>
        .notif-list { list-style: none; padding: 0; margin-top: 20px; }
        .notif-list li { 
            background: #fff;
            padding: 16px; 
            margin-bottom: 12px; 
            border-radius: 8px;
            border-left: 6px solid #4F46E5; 
            box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
        }
        .notif-list li.read { 
            opacity: 0.65; 
            border-left-color: #9CA3AF;
        }
        .notif-title { font-size: 16px; font-weight: 600; color: #111827; }
        .notif-msg { margin-top: 6px; font-size: 14px; color: #374151; }
        .muted { color: #6B7280; }
        .role-btn.small-btn {
            background: #4F46E5;
            color: white;
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            margin-top: 5px;
        }
        .role-btn.small-btn:hover { background: #4338CA; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap" style="max-width:850px; margin:22px auto; padding:20px;">

    <h1 style="margin-bottom: 10px;">Notifications</h1>

    <?php if (function_exists('flash_render')) flash_render(); ?>

    <?php if (empty($notes)): ?>
        <p class="muted">You have no notifications.</p>
    <?php endif; ?>

    <ul class="notif-list">
        <?php foreach ($notes as $n): ?>
            <li class="<?php echo $n['is_read'] ? 'read' : 'unread'; ?>">

                <div class="notif-title">
                    <?php echo h($n['title']); ?>
                    <span class="muted" style="font-size:12px;">
                        • <?php echo h($n['created_at']); ?>
                    </span>
                </div>

                <div class="notif-msg"><?php echo h($n['message']); ?></div>

                <?php if (!$n['is_read']): ?>
                    <form method="post" style="margin-top:10px;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="mark_id" value="<?php echo (int)$n['id']; ?>">
                        <button class="role-btn small-btn" type="submit">Mark as read</button>
                    </form>
                <?php endif; ?>

            </li>
        <?php endforeach; ?>
    </ul>

</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>

</body>
</html>
