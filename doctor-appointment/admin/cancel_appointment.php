<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$id = intval($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
if (!$id) { echo json_encode(['error'=>'Invalid id']); exit; }
if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['error'=>'Invalid CSRF']); exit; }

$pdo->beginTransaction();
try {
    // set cancelled
    $stmt = $pdo->prepare("SELECT patient_id, doctor_id, date, start_time FROM appointments WHERE id=? LIMIT 1");
    $stmt->execute([$id]); $a = $stmt->fetch();
    if (!$a) { $pdo->rollBack(); echo json_encode(['error'=>'Not found']); exit; }
    $pdo->prepare("UPDATE appointments SET status='cancelled', cancelled_by_role='admin', cancel_reason=?, cancelled_at=NOW() WHERE id=?")->execute([$reason ?: 'Cancelled by admin', $id]);

    // notify patient and doctor
    create_notification($a['patient_id'], 'Appointment cancelled', "Your appointment on {$a['date']} at {$a['start_time']} was cancelled by admin. Reason: ".($reason?:'N/A'));
    create_notification($a['doctor_id'], 'Appointment cancelled', "Appointment on {$a['date']} at {$a['start_time']} was cancelled by admin.");

    // log
    $lg = $pdo->prepare("INSERT INTO logs (actor_id, actor_role, event_type, detail, created_at) VALUES (?,?,?,?,NOW())");
    $lg->execute([$_SESSION['user_id'],'admin','admin_cancel',"Cancelled appointment {$id}: ".$reason]);

    $pdo->commit();
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    $pdo->rollBack(); echo json_encode(['error'=>$e->getMessage()]);
}
