<?php
// admin/export_csv.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf($_POST['csrf_token'] ?? '')) { die('Invalid'); }

$from = $_POST['from'] ?? date('Y-m-01');
$to = $_POST['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare("SELECT a.id, a.date, a.start_time, a.end_time, a.status, up.full_name AS patient, ud.full_name AS doctor, dp.fee FROM appointments a JOIN users up ON a.patient_id=up.id JOIN users ud ON a.doctor_id=ud.id JOIN doctors_profiles dp ON dp.user_id=ud.id WHERE a.date BETWEEN ? AND ? ORDER BY a.date");
$stmt->execute([$from,$to]);
$rows = $stmt->fetchAll();

$filename = "appointments_{$from}_to_{$to}.csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$filename.'"');
$out = fopen('php://output','w');
fputcsv($out, ['id','date','start_time','end_time','status','patient','doctor','fee']);
foreach($rows as $r) fputcsv($out, [$r['id'],$r['date'],$r['start_time'],$r['end_time'],$r['status'],$r['patient'],$r['doctor'],$r['fee']]);
fclose($out);
exit;
