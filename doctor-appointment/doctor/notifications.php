<?php
// doctor/notifications.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$doctor_id = current_user_id();

// Mark as read (single)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mark_id'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF token invalid.');
        header('Location: notifications.php');
        exit;
    }

    $mid = intval($_POST['mark_id']);

    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
        ->execute([$mid, $doctor_id]);

    header('Location: notifications.php');
    exit;
}

// Fetch notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 200");
$stmt->execute([$doctor_id]);
$notes = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Doctor Notifications</title>
    <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap" style="max-width:900px;margin:20px auto;padding:20px;">

    <h1 style="margin-bottom:20px;">🔔 Notifications</h1>

    <?php if(function_exists('flash_render')) flash_render(); ?>

    <?php if (empty($notes)): ?>
        <div class="muted" style="padding:10px;">No notifications found.</div>
    <?php else: ?>

        <ul class="notif-list">
            <?php foreach ($notes as $n): ?>
                <li class="<?php echo $n['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="notif-title">
                        <?php echo e($n['title']); ?>
                        <span class="muted" style="font-size:12px;"> — 
                            <?php echo e($n['created_at']); ?>
                        </span>
                    </div>

                    <div class="notif-msg"><?php echo e($n['message']); ?></div>

                    <?php if (!$n['is_read']): ?>
                        <form method="post" style="margin-top:8px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="mark_id" value="<?php echo (int)$n['id']; ?>">
                            <button class="role-btn small-btn" type="submit">Mark as Read</button>
                        </form>
                    <?php endif; ?>

                </li>
            <?php endforeach; ?>
        </ul>

    <?php endif; ?>

    <div style="margin-top:20px;">
        <a class="role-btn" href="/doctor-appointment/doctor/dashboard.php">← Back to Dashboard</a>
    </div>

</div>

</body>
</html>
