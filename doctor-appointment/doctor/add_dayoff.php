<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$doctor_id = current_user_id();
$date = $_POST['date'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['error'=>'Invalid CSRF']); exit; }
if (!$date) { echo json_encode(['error'=>'Date required']); exit; }

// transaction: insert dayoff, find overlapping appointments, cancel them, notify users, log
try {
    $pdo->beginTransaction();

    // insert dayoff (ignore duplicate)
    $ins = $pdo->prepare("INSERT IGNORE INTO doctor_dayoffs (doctor_id, date, reason, created_at) VALUES (?,?,?,NOW())");
    $ins->execute([$doctor_id, $date, $reason]);

    // find appointments on that date that are booked
    $find = $pdo->prepare("SELECT id, patient_id FROM appointments WHERE doctor_id=? AND date=? AND status='booked' FOR UPDATE");
    $find->execute([$doctor_id, $date]);
    $rows = $find->fetchAll();

    $cancelStmt = $pdo->prepare("UPDATE appointments SET status='cancelled', cancelled_by_role='doctor', cancel_reason=?, cancelled_at=NOW() WHERE id=?");
    foreach ($rows as $r) {
        $cancelStmt->execute(["Doctor day off: " . ($reason ?: 'No reason provided'), $r['id']]);
        // create notification for patient
        $title = "Appointment cancelled";
        $msg = "Your appointment on {$date} was cancelled due to doctor's day off. Reason: " . ($reason ?: 'N/A');
        create_notification($r['patient_id'], $title, $msg);
        // log
        $lg = $pdo->prepare("INSERT INTO logs (actor_id, actor_role, event_type, detail, created_at) VALUES (?,?,?,?,NOW())");
        $lg->execute([$doctor_id, 'doctor', 'dayoff_cancel', "Cancelled appointment id {$r['id']} due to dayoff {$date}"]);
    }

    $pdo->commit();
    echo json_encode(['success'=>true, 'cancelled'=>count($rows)]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error'=>'Server error: '.$e->getMessage()]);
}
