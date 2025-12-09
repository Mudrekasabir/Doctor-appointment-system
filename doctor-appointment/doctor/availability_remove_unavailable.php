<?php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

header('Content-Type: application/json');

csrf_verify();

$doctor_id = current_user_id();
$unavailable_date = $_POST['unavailable_date'] ?? '';

if(empty($unavailable_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        DELETE FROM doctor_unavailable_dates 
        WHERE doctor_id=? AND unavailable_date=?
    ");
    $stmt->execute([$doctor_id, $unavailable_date]);
    
    echo json_encode(['success' => true]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}