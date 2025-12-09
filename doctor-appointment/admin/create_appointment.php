<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

$data = $_POST;
if (!validate_csrf($data['csrf_token'] ?? '')) { echo json_encode(['error'=>'Invalid CSRF']); exit; }
$patient_id = intval($data['patient_id'] ?? 0);
$doctor_id = intval($data['doctor_id'] ?? 0);
$date = $data['date'] ?? '';
$start = $data['start_time'] ?? '';
$end = $data['end_time'] ?? '';

if (!$patient_id || !$doctor_id || !$date || !$start || !$end) { echo json_encode(['error'=>'Missing fields']); exit; }

try {
    $pdo->beginTransaction();
    // lock potential overlapping appointments
    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id=? AND date=? AND (start_time < ? AND end_time > ?) FOR UPDATE");
    $stmt->execute([$doctor_id,$date,$end,$start]);
    if ($stmt->fetch()) { $pdo->rollBack(); echo json_encode(['error'=>'Slot already taken']); exit; }

    $ins = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, date, start_time, end_time, status, created_at, created_by) VALUES (?,?,?,?,?,'booked',NOW(),'admin')");
    $ins->execute([$patient_id,$doctor_id,$date,$start,$end]);
    $newId = $pdo->lastInsertId();

    create_notification($patient_id, 'Appointment created', "An appointment was created for you on {$date} at {$start} by admin.");
    create_notification($doctor_id, 'New appointment', "An appointment was created on {$date} at {$start} by admin.");

    $pdo->commit();
    echo json_encode(['success'=>true,'id'=>$newId]);
} catch (Exception $e) {
    $pdo->rollBack(); echo json_encode(['error'=>$e->getMessage()]);
}
