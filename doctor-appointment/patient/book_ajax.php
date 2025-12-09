<?php
// patient/book_ajax.php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']); exit;
}
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Invalid CSRF token']); exit;
}

$doctor_id = intval($_POST['doctor_id'] ?? 0);
$date = $_POST['date'] ?? '';
$start = $_POST['start'] ?? ''; // format HH:MM
$end   = $_POST['end'] ?? '';

if (!$doctor_id || !$date || !$start || !$end) {
    echo json_encode(['error' => 'Missing params']); exit;
}

// Normalize start_time to HH:MM:SS
$start_time = date('H:i:s', strtotime($start));
$end_time = date('H:i:s', strtotime($end));
$patient_id = $_SESSION['user_id'] ?? 0;
if (!$patient_id) { echo json_encode(['error'=>'Not logged in']); exit; }

// Concurrency-safe insertion: lock (using a lightweight advisory method)
// Approach: START TRANSACTION, SELECT ... FOR UPDATE on a small lock row (appointments table)
// We will check if an appointment exists for that doctor/date/start_time, and only insert if not.

try {
    $pdo->beginTransaction();

    // Option A: Lock the appointments table rows for this doctor+date (SELECT FOR UPDATE)
    $check = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND date = ? AND start_time = ? LIMIT 1 FOR UPDATE");
    $check->execute([$doctor_id, $date, $start_time]);
    if ($check->fetch()) {
        // already booked
        $pdo->rollBack();
        echo json_encode(['error' => 'Slot already booked']); exit;
    }

    // optional: re-check doctor_dayoffs (in case dayoff was added just now)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM doctor_dayoffs WHERE doctor_id = ? AND date = ?");
    $stmt->execute([$doctor_id, $date]);
    if ($stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Doctor has a day off']); exit;
    }

    // All good — insert appointment
    $ins = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, date, start_time, end_time, status, created_at, created_by) VALUES (?, ?, ?, ?, ?, 'booked', NOW(), ?)");
    $ins->execute([$patient_id, $doctor_id, $date, $start_time, $end_time, $patient_id]);
    $appoint_id = $pdo->lastInsertId();

    // Create notifications to doctor & patient
    if (function_exists('create_notification')) {
        create_notification($doctor_id, 'New appointment', "Patient {$_SESSION['username']} booked on {$date} {$start}");
        create_notification($patient_id, 'Appointment booked', "You booked with doctor ID {$doctor_id} on {$date} {$start}");
    }

    $pdo->commit();
    echo json_encode(['ok' => 1, 'appointment_id' => $appoint_id]);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Booking error: ' . $e->getMessage());
    echo json_encode(['error' => 'Booking failed: ' . $e->getMessage()]);
    exit;
}
