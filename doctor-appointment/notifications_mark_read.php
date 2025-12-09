<?php
header("Content-Type: application/json");

require_once __DIR__ . '/inc/auth_checks.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/csrf.php';

$user_id = current_user_id();

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'CSRF_FAIL']);
    exit;
}

$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->execute([$user_id]);

echo json_encode(['success' => true]);
