<?php
// admin/appointments_cancel.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: appointments.php'); exit; }
if (!validate_csrf($_POST['csrf_token'] ?? '')) { flash_set('error','CSRF'); header('Location: appointments.php'); exit; }
$id = intval($_POST['id'] ?? 0);
$reason = trim($_POST['cancel_reason'] ?? 'Cancelled by admin');

$pdo->beginTransaction();
try {
    // get appointment info
    $sel = $pdo->prepare("SELECT * FROM appointments WHERE id=? FOR UPDATE");
    $sel->execute([$id]); $a = $sel->fetch();
    if (!$a) throw new Exception('Appointment not found');
    if ($a['status'] !== 'booked') throw new Exception('Cannot cancel non-booked');

    $upd = $pdo->prepare("UPDATE appointments SET status='cancelled', cancelled_by_role='admin', cancel_reason=?, cancelled_at=NOW() WHERE id=?");
    $upd->execute([$reason,$id]);

    // notify patient & doctor
    $note = $pdo->prepare("INSERT INTO notifications (user_id,title,message,created_at) VALUES (?,?,?,NOW())");
    $note->execute([$a['patient_id'],'Appointment cancelled','Admin cancelled your appointment on '.$a['date'].' at '.substr($a['start_time'],0,5).' Reason: '.$reason]);
    $note->execute([$a['doctor_id'],'Appointment cancelled','Admin cancelled appointment with patient ID '.$a['patient_id'].' on '.$a['date'].' at '.substr($a['start_time'],0,5).' Reason: '.$reason]);

    $pdo->prepare("INSERT INTO logs (actor_id,actor_role,event_type,detail,created_at) VALUES (?,?,?,?,NOW())")
        ->execute([current_user_id(),'admin','cancel_appointment',"appt_id={$id};reason={$reason}"]);

    $pdo->commit();
    flash_set('success','Appointment cancelled.');
    header('Location: appointments.php'); exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('error','Error: '.$e->getMessage());
    header('Location: appointments.php'); exit;
}
