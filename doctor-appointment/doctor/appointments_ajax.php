<?php
// doctor/appointments_ajax.php — returns JSON of appointments for logged-in doctor
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$doctor_id = current_user_id();
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) {
    echo json_encode(['error'=>'Invalid date']); exit;
}

$stmt = $pdo->prepare("SELECT a.id, a.patient_id, a.start_time, a.end_time, a.status, u.full_name AS patient_name
                       FROM appointments a
                       JOIN users u ON u.id = a.patient_id
                       WHERE a.doctor_id = ? AND a.date = ?
                       ORDER BY a.start_time");
$stmt->execute([$doctor_id, $date]);
$rows = $stmt->fetchAll();

$appts = [];
foreach($rows as $r){
    $appts[] = [
        'id' => (int)$r['id'],
        'patient_id' => (int)$r['patient_id'],
        'patient_name' => $r['patient_name'],
        'start' => substr($r['start_time'],0,5),
        'end' => substr($r['end_time'],0,5),
        'status' => $r['status']
    ];
}

echo json_encode(['appointments'=>$appts]);
