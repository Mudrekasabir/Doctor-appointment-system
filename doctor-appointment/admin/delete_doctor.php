<?php
// admin/delete_doctor.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    flash_set('error', 'Invalid request method');
    header('Location: manage_doctors.php'); 
    exit; 
}

// Validate CSRF token - accepts both field names
$csrf_token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? '';
if (!validate_csrf($csrf_token)) { 
    flash_set('error', 'Invalid CSRF token. Please try again.');
    header('Location: manage_doctors.php'); 
    exit; 
}

// Validate doctor ID
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) { 
    flash_set('error', 'Invalid doctor ID');
    header('Location: manage_doctors.php'); 
    exit; 
}

// Check if doctor exists
$check = $pdo->prepare("SELECT id, full_name FROM users WHERE id=? AND role='doctor'");
$check->execute([$id]);
$doctor = $check->fetch();

if (!$doctor) {
    flash_set('error', 'Doctor not found');
    header('Location: manage_doctors.php'); 
    exit;
}

// Begin transaction
$pdo->beginTransaction();

try {
    // Get appointment count before deletion
    $apt_count = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=?");
    $apt_count->execute([$id]);
    $total_appointments = $apt_count->fetchColumn();
    
    // Delete related records first (foreign key constraints)
    $pdo->prepare("DELETE FROM appointments WHERE doctor_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM doctors_profiles WHERE user_id=?")->execute([$id]);
    
    // Delete user account
    $pdo->prepare("DELETE FROM users WHERE id=? AND role='doctor'")->execute([$id]);
    
    // Log the deletion
    $pdo->prepare("INSERT INTO logs (actor_id, actor_role, event_type, detail, created_at) 
                   VALUES (?, ?, ?, ?, NOW())")
        ->execute([
            current_user_id(),
            'admin',
            'delete_doctor',
            "Deleted doctor '{$doctor['full_name']}' (ID: {$id}, Appointments: {$total_appointments})"
        ]);
    
    // Commit transaction
    $pdo->commit();
    
    flash_set('success', "Doctor '{$doctor['full_name']}' and {$total_appointments} appointment(s) deleted successfully");
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Delete Doctor Error: " . $e->getMessage());
    flash_set('error', 'Error deleting doctor: ' . $e->getMessage());
}

header('Location: manage_doctors.php'); 
exit;
?>