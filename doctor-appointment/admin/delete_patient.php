<?php
// admin/delete_patient.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    flash_set('error', 'Invalid request method');
    header('Location: manage_patients.php'); 
    exit; 
}

// Get CSRF token from POST
$csrf_token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? '';

// Validate CSRF token
$is_valid_csrf = false;

// Method 1: Use validate_csrf if available
if (function_exists('validate_csrf')) {
    try {
        $is_valid_csrf = validate_csrf($csrf_token);
    } catch (Throwable $e) {
        error_log("CSRF Error (validate_csrf): " . $e->getMessage());
    }
}

// Method 2: Manual session validation as fallback
if (!$is_valid_csrf && isset($_SESSION['csrf_token']) && !empty($csrf_token)) {
    // Use hash_equals if available (PHP 5.6+), otherwise use timing-safe comparison
    if (function_exists('hash_equals')) {
        try {
            $session_token = (string)$_SESSION['csrf_token'];
            $user_token = (string)$csrf_token;
            $is_valid_csrf = hash_equals($session_token, $user_token);
        } catch (Throwable $e) {
            error_log("CSRF Error (hash_equals): " . $e->getMessage());
        }
    } else {
        // Fallback for PHP < 5.6
        $is_valid_csrf = ($_SESSION['csrf_token'] === $csrf_token);
    }
}

// Reject if CSRF validation failed
if (!$is_valid_csrf) { 
    flash_set('error', 'Invalid CSRF token. Please try again.');
    header('Location: manage_patients.php'); 
    exit; 
}

// Validate patient ID
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) { 
    flash_set('error', 'Invalid patient ID');
    header('Location: manage_patients.php'); 
    exit; 
}

try {
    // Check if patient exists
    $check = $pdo->prepare("SELECT id, full_name, username, email FROM users WHERE id = ? AND role = 'patient'");
    $check->execute([$id]);
    $patient = $check->fetch();

    if (!$patient) {
        flash_set('error', 'Patient not found');
        header('Location: manage_patients.php'); 
        exit;
    }

    // Prevent self-deletion
    $current_user = 0;
    if (function_exists('current_user_id')) {
        $current_user = current_user_id();
    } elseif (isset($_SESSION['user_id'])) {
        $current_user = $_SESSION['user_id'];
    }
    
    if ($current_user == $id) {
        flash_set('error', 'You cannot delete your own account');
        header('Location: manage_patients.php'); 
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();
    
    // Get appointment count
    $apt_stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
    $apt_stmt->execute([$id]);
    $total_appointments = (int)$apt_stmt->fetchColumn();
    
    // Delete related records in proper order
    
    // 1. Delete appointments
    $pdo->prepare("DELETE FROM appointments WHERE patient_id = ?")->execute([$id]);
    
    // 2. Delete patient profile (if table/records exist)
    try {
        $pdo->prepare("DELETE FROM patients_profiles WHERE user_id = ?")->execute([$id]);
    } catch (PDOException $e) {
        error_log("Note: patients_profiles deletion: " . $e->getMessage());
    }
    
    // 3. Delete medical records (if table/records exist)
    try {
        $pdo->prepare("DELETE FROM medical_records WHERE patient_id = ?")->execute([$id]);
    } catch (PDOException $e) {
        error_log("Note: medical_records deletion: " . $e->getMessage());
    }
    
    // 4. Delete prescriptions (if table/records exist)
    try {
        $pdo->prepare("DELETE FROM prescriptions WHERE patient_id = ?")->execute([$id]);
    } catch (PDOException $e) {
        error_log("Note: prescriptions deletion: " . $e->getMessage());
    }
    
    // 5. Delete the user account
    $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'");
    $delete_stmt->execute([$id]);
    
    // Verify deletion succeeded
    if ($delete_stmt->rowCount() === 0) {
        throw new Exception('Failed to delete patient account');
    }
    
    // Log the deletion (if logs table exists)
    try {
        $log_stmt = $pdo->prepare("INSERT INTO logs (actor_id, actor_role, event_type, detail, created_at) VALUES (?, ?, ?, ?, NOW())");
        $actor_id = $current_user > 0 ? $current_user : null;
        $log_detail = "Deleted patient '{$patient['full_name']}' (Username: {$patient['username']}, ID: {$id}, Appointments: {$total_appointments})";
        
        $log_stmt->execute([
            $actor_id,
            'admin',
            'delete_patient',
            $log_detail
        ]);
    } catch (PDOException $e) {
        error_log("Note: logs insertion failed: " . $e->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Success message
    $message = "Patient '{$patient['full_name']}' deleted successfully";
    if ($total_appointments > 0) {
        $message .= " ({$total_appointments} appointment(s) removed)";
    }
    flash_set('success', $message);
    
} catch (PDOException $e) {
    // Rollback on database error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Delete Patient DB Error [ID: {$id}]: " . $e->getMessage());
    
    // User-friendly error messages
    $error_msg = $e->getMessage();
    if (stripos($error_msg, 'foreign key') !== false || stripos($error_msg, 'constraint') !== false) {
        flash_set('error', 'Cannot delete patient: Database constraint error.');
    } else {
        flash_set('error', 'Database error: Unable to delete patient. Please try again.');
    }
    
} catch (Exception $e) {
    // Rollback on general error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Delete Patient Error [ID: {$id}]: " . $e->getMessage());
    flash_set('error', 'Error: ' . $e->getMessage());
}

// Redirect back to manage patients
header('Location: manage_patients.php'); 
exit;
?>