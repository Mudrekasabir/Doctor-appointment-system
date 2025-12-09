<?php
// admin/approve_doctor.php

// Start output buffering FIRST - before any includes
ob_start();

// Suppress error display (log them instead)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

// Clear any output from included files
ob_end_clean();

// Set JSON header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Check CSRF token using the correct function from csrf.php
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf($csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Start transaction to update both tables
    $pdo->beginTransaction();
    
    if ($action === 'approve') {
        // Update doctors_profiles status
        $stmt = $pdo->prepare("UPDATE doctors_profiles SET status='approved' WHERE user_id=?");
        $stmt->execute([$id]);
        
        // Update users status to active
        $stmt = $pdo->prepare("UPDATE users SET status='active' WHERE id=? AND role='doctor'");
        $stmt->execute([$id]);
        
        // Optional: Create notification for the doctor
        if (function_exists('create_notification')) {
            create_notification($id, 'Account Approved', 'Your doctor account has been approved. You can now log in and manage appointments.');
        }
        
    } else {
        $reason = $_POST['reason'] ?? 'No reason provided';
        
        // Update doctors_profiles status
        $stmt = $pdo->prepare("UPDATE doctors_profiles SET status='rejected' WHERE user_id=?");
        $stmt->execute([$id]);
        
        // Update users status to rejected
        $stmt = $pdo->prepare("UPDATE users SET status='rejected' WHERE id=? AND role='doctor'");
        $stmt->execute([$id]);
        
        // Optional: Create notification for the doctor
        if (function_exists('create_notification')) {
            create_notification($id, 'Account Rejected', 'Your doctor registration has been rejected. Reason: ' . $reason);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Doctor approval error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

exit;