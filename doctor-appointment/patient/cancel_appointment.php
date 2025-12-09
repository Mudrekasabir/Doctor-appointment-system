<?php
// patient/cancel_appointment.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /doctor-appointment/patient/appointments.php'); exit; }
if (!validate_csrf($_POST['csrf_token'] ?? '')) { flash_set('error','CSRF error'); header('Location: /doctor-appointment/patient/appointments.php'); exit; }

$appointment_id = intval($_POST['appointment_id'] ?? 0);
$reason = trim($_POST['cancel_reason'] ?? '');
$uid = current_user_id();

// verify appointment belongs to patient and can be cancelled
$stmt = $pdo->prepare("SELECT * FROM appointments WHERE id=? AND patient_id=? LIMIT 1");
$stmt->execute([$appointment_id,$uid]);
$a = $stmt->fetch();
if (!$a) { flash_set('error','Appointment not found'); header('Location:/doctor-appointment/patient/appointments.php'); exit; }
if ($a['status'] !== 'booked') { flash_set('error','Cannot cancel this appointment'); header('Location:/doctor-appointment/patient/appointments.php'); exit; }

// check whether it's in future
if (strtotime($a['date'].' '.$a['start_time']) <= time()) { flash_set('error','Cannot cancel past or ongoing appointment'); header('Location:/doctor-appointment/patient/appointments.php'); exit; }

// perform cancellation
$pdo->beginTransaction();
try {
  $upd = $pdo->prepare("UPDATE appointments SET status='cancelled', cancelled_by_role='patient', cancel_reason=?, cancelled_at=NOW() WHERE id=?");
  $upd->execute([$reason,$appointment_id]);

  // notify doctor
  $note = $pdo->prepare("INSERT INTO notifications (user_id,title,message,created_at) VALUES (?,?,?,NOW())");
  $note->execute([$a['doctor_id'],'Appointment cancelled','Patient cancelled appointment on '.$a['date'].' at '.substr($a['start_time'],0,5).'. Reason: '.$reason]);

  // log
  $lg = $pdo->prepare("INSERT INTO logs (actor_id,actor_role,event_type,detail,created_at) VALUES (?,?,?,?,NOW())");
  $lg->execute([$uid,'patient','cancel','Cancelled appt id '.$appointment_id.' reason: '.$reason]);

  $pdo->commit();
  flash_set('success','Appointment cancelled');
} catch (Exception $e) {
  $pdo->rollBack();
  flash_set('error','Server error: '.$e->getMessage());
}

header('Location: /doctor-appointment/patient/appointments.php');
exit;
