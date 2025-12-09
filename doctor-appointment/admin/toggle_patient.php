<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

$id = intval($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['error'=>'Invalid id']); exit; }
if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['error'=>'Invalid CSRF']); exit; }

$stmt = $pdo->prepare("SELECT status FROM users WHERE id=? AND role='patient' LIMIT 1");
$stmt->execute([$id]); $s = $stmt->fetchColumn();
if (!$s) { echo json_encode(['error'=>'Not found']); exit; }
$new = $s === 'active' ? 'disabled' : 'active';
$pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$new,$id]);
echo json_encode(['success'=>true,'status'=>$new]);
