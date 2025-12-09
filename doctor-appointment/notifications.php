<?php
require_once __DIR__ . '/inc/auth_checks.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/csrf.php';

$user_id = current_user_id();

// fetch notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Notifications</title>
  <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
  <script defer src="/doctor-appointment/assets/js/app.js"></script>
</head>
<body>
<?php include __DIR__ . '/inc/header.php'; ?>

<div class="center-card" style="max-width:900px">
  <h2>Your Notifications</h2>

  <button id="mark-read-btn" class="role-btn small-btn">Mark All as Read</button>

  <div style="margin-top:20px">
    <?php if(empty($rows)): ?>
      <div class="muted">You have no notifications.</div>
    <?php else: ?>
      <?php foreach($rows as $n): ?>
        <div class="notif-card <?php echo $n['is_read'] ? '' : 'notif-unread'; ?>">
          <div class="notif-title"><?php echo e($n['title']); ?></div>
          <div class="notif-msg"><?php echo e($n['message']); ?></div>
          <div class="notif-time"><?php echo e($n['created_at']); ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
</body>
</html>
