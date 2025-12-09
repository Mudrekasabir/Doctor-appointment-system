<?php
// patient/book_action.php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']); 
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Invalid CSRF token']); 
    exit;
}

$doctor_id = intval($_POST['doctor_id'] ?? 0);
$date = $_POST['date'] ?? '';
$start_time = $_POST['start_time'] ?? ''; 
$end_time = $_POST['end_time'] ?? '';

// Debug logging
error_log("Booking attempt - Doctor: $doctor_id, Date: $date, Start: $start_time, End: $end_time");

if (!$doctor_id || !$date || !$start_time || !$end_time) {
    echo json_encode(['error' => 'Missing required parameters']); 
    exit;
}

// Validate date format
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    echo json_encode(['error' => 'Invalid date format']); 
    exit;
}

// Check if date is in the past
if ($date < date('Y-m-d')) {
    echo json_encode(['error' => 'Cannot book appointments in the past']); 
    exit;
}

// Normalize time to HH:MM:SS format
// Try multiple formats for start_time
$start_time_formatted = null;
$formats = ['H:i:s', 'H:i', 'h:i A', 'h:i:s A', 'g:i A', 'g:i:s A'];

foreach ($formats as $format) {
    $start_time_obj = DateTime::createFromFormat($format, trim($start_time));
    if ($start_time_obj !== false) {
        $start_time_formatted = $start_time_obj->format('H:i:s');
        break;
    }
}

if (!$start_time_formatted) {
    error_log("Failed to parse start_time: '$start_time'");
    echo json_encode(['error' => 'Invalid start time format: ' . $start_time]); 
    exit;
}

// Try multiple formats for end_time
$end_time_formatted = null;
foreach ($formats as $format) {
    $end_time_obj = DateTime::createFromFormat($format, trim($end_time));
    if ($end_time_obj !== false) {
        $end_time_formatted = $end_time_obj->format('H:i:s');
        break;
    }
}

if (!$end_time_formatted) {
    error_log("Failed to parse end_time: '$end_time'");
    echo json_encode(['error' => 'Invalid end time format: ' . $end_time]); 
    exit;
}

$patient_id = $_SESSION['user_id'] ?? 0;
if (!$patient_id) { 
    echo json_encode(['error' => 'Not logged in']); 
    exit; 
}

try {
    $pdo->beginTransaction();

    // Check if doctor exists
    $checkDoctor = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'doctor'");
    $checkDoctor->execute([$doctor_id]);
    if (!$checkDoctor->fetch()) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Doctor not found']); 
        exit;
    }

    // Lock and check if slot is already booked
    $check = $pdo->prepare("
        SELECT id 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND start_time = ? 
        AND status NOT IN ('cancelled', 'rejected')
        LIMIT 1 
        FOR UPDATE
    ");
    $check->execute([$doctor_id, $date, $start_time_formatted]);
    
    if ($check->fetch()) {
        $pdo->rollBack();
        echo json_encode(['error' => 'This slot has already been booked. Please select another time.']); 
        exit;
    }

    // Check if doctor has a day off (if table exists)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM doctor_dayoffs WHERE doctor_id = ? AND date = ?");
        $stmt->execute([$doctor_id, $date]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Doctor is not available on this date']); 
            exit;
        }
    } catch (Exception $e) {
        // Table doesn't exist, skip this check
    }

    // Check if patient already has an appointment at this time
    $checkPatient = $pdo->prepare("
        SELECT id 
        FROM appointments 
        WHERE patient_id = ? 
        AND appointment_date = ? 
        AND (
            (start_time <= ? AND end_time > ?) OR
            (start_time < ? AND end_time >= ?) OR
            (start_time >= ? AND end_time <= ?)
        )
        AND status NOT IN ('cancelled', 'rejected')
    ");
    $checkPatient->execute([
        $patient_id, 
        $date, 
        $start_time_formatted, $start_time_formatted,
        $end_time_formatted, $end_time_formatted,
        $start_time_formatted, $end_time_formatted
    ]);
    
    if ($checkPatient->fetch()) {
        $pdo->rollBack();
        echo json_encode(['error' => 'You already have an appointment at this time']); 
        exit;
    }

    // Insert the appointment
    $ins = $pdo->prepare("
        INSERT INTO appointments 
        (patient_id, doctor_id, appointment_date, start_time, end_time, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $ins->execute([$patient_id, $doctor_id, $date, $start_time_formatted, $end_time_formatted]);
    $appoint_id = $pdo->lastInsertId();

    // Get patient and doctor names for notifications
    $stmtPatient = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmtPatient->execute([$patient_id]);
    $patient_name = $stmtPatient->fetchColumn() ?: 'Patient';
    
    $stmtDoctor = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmtDoctor->execute([$doctor_id]);
    $doctor_name = $stmtDoctor->fetchColumn() ?: 'Doctor';

    // Create notifications
    if (function_exists('create_notification')) {
        create_notification(
            $doctor_id, 
            'New Appointment Request', 
            "{$patient_name} has requested an appointment on {$date} at {$start_time}"
        );
        create_notification(
            $patient_id, 
            'Appointment Request Sent', 
            "Your appointment request with Dr. {$doctor_name} on {$date} at {$start_time} has been sent"
        );
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'appointment_id' => $appoint_id,
        'message' => 'Appointment booked successfully'
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Booking error: ' . $e->getMessage());
    echo json_encode(['error' => 'Booking failed. Please try again: ' . $e->getMessage()]);
    exit;
}