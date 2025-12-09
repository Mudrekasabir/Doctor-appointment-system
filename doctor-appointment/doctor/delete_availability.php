<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['error'=>'Invalid id']); exit; }
if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['error'=>'Invalid CSRF']); exit; }

$doctor_id = current_user_id();

$stmt = $pdo->prepare("DELETE FROM doctor_available_times WHERE id = ? AND doctor_id = ?");
$stmt->execute([$id, $doctor_id]);
echo json_encode(['success'=>true]);
