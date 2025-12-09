<?php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

csrf_verify();

$doctor_id = current_user_id();
$unavailable_date = $_POST['unavailable_date'] ?? '';
$reason = $_POST['reason'] ?? '';

if(empty($unavailable_date)) {
    flash_set('error', 'Please select a date');
    header('Location: availability.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO doctor_unavailable_dates (doctor_id, unavailable_date, reason) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE reason = VALUES(reason)
    ");
    $stmt->execute([$doctor_id, $unavailable_date, $reason]);
    
    flash_set('success', 'Unavailable date added successfully!');
} catch(Exception $e) {
    flash_set('error', 'Error adding unavailable date: ' . $e->getMessage());
}

header('Location: availability.php');
exit;