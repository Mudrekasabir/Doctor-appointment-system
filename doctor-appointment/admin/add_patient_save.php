<?php
// admin/add_patient_save.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error', 'Invalid request method.');
    header('Location: add_patient.php');
    exit;
}

// Validate CSRF token - accepts both field names
$csrf_token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? '';
if (!validate_csrf($csrf_token)) {
    flash_set('error', 'Invalid CSRF token. Please try again.');
    header('Location: add_patient.php');
    exit;
}

// Get form data
$username = trim($_POST['username'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$password = $_POST['password'] ?? '';
$age = !empty($_POST['age']) ? intval($_POST['age']) : null;
$blood_group = trim($_POST['blood_group'] ?? '');
$address = trim($_POST['address'] ?? '');
$emergency_contact = trim($_POST['emergency_contact'] ?? '');
$created_by = current_user_id();

// Validate required fields
if (empty($username) || empty($full_name) || empty($email) || empty($contact) || empty($password)) {
    flash_set('error', 'All required fields must be filled: username, full name, email, contact, and password.');
    header('Location: add_patient.php');
    exit;
}

// Validate username (alphanumeric and underscore only)
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    flash_set('error', 'Username must be 3-20 characters long and contain only letters, numbers, and underscores.');
    header('Location: add_patient.php');
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Invalid email format.');
    header('Location: add_patient.php');
    exit;
}

// Validate password length
if (strlen($password) < 6) {
    flash_set('error', 'Password must be at least 6 characters long.');
    header('Location: add_patient.php');
    exit;
}

// Validate contact number
if (!preg_match('/^[0-9+\-\s()]{10,15}$/', $contact)) {
    flash_set('error', 'Invalid contact number format. Must be 10-15 digits.');
    header('Location: add_patient.php');
    exit;
}

// Validate age if provided
if ($age !== null && ($age < 0 || $age > 150)) {
    flash_set('error', 'Age must be between 0 and 150.');
    header('Location: add_patient.php');
    exit;
}

// Validate blood group if provided
$valid_blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if (!empty($blood_group) && !in_array($blood_group, $valid_blood_groups)) {
    flash_set('error', 'Invalid blood group. Must be one of: ' . implode(', ', $valid_blood_groups));
    header('Location: add_patient.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if username already exists
    $check = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        throw new Exception('Username "' . htmlspecialchars($username) . '" already exists. Please choose a different username.');
    }

    // Check if email already exists
    $check = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        throw new Exception('Email "' . htmlspecialchars($email) . '" already exists. Please use a different email.');
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, full_name, email, contact, password_hash, role, status, created_at, created_by) 
        VALUES (?, ?, ?, ?, ?, 'patient', 'active', NOW(), ?)
    ");
    $stmt->execute([$username, $full_name, $email, $contact, $password_hash, $created_by]);
    $patient_id = $pdo->lastInsertId();

    // Insert patient profile (if patients_profiles table exists)
    try {
        $profile_stmt = $pdo->prepare("
            INSERT INTO patients_profiles (user_id, created_at) 
            VALUES (?, NOW())
        ");
        $profile_stmt->execute([$patient_id]);
    } catch (PDOException $e) {
        // Table might not exist, continue anyway
        error_log("Patient profile table error: " . $e->getMessage());
    }

    // Insert medical details if provided
    if ($age !== null || !empty($blood_group) || !empty($address) || !empty($emergency_contact)) {
        try {
            // Check if table exists first
            $table_check = $pdo->query("SHOW TABLES LIKE 'patient_medical_details'");
            
            if ($table_check->rowCount() > 0) {
                $med_stmt = $pdo->prepare("
                    INSERT INTO patient_medical_details (patient_id, age, blood_group, address, emergency_contact, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $med_stmt->execute([
                    $patient_id, 
                    $age, 
                    $blood_group ?: null,
                    $address ?: null,
                    $emergency_contact ?: null
                ]);
            } else {
                error_log("patient_medical_details table does not exist - skipping medical details");
            }
        } catch (PDOException $e) {
            // Log error but don't fail the entire operation
            error_log("Medical details insertion error: " . $e->getMessage());
        }
    }

    // Log action
    try {
        $detail = "patient_id={$patient_id};username={$username};full_name={$full_name}";
        $pdo->prepare("
            INSERT INTO logs (actor_id, actor_role, event_type, detail, created_at) 
            VALUES (?, 'admin', 'create_patient', ?, NOW())
        ")->execute([$created_by, $detail]);
    } catch (Exception $e) {
        error_log("Logging error: " . $e->getMessage());
    }

    // Create notification for the patient
    try {
        $message = "Your patient account has been created by the administrator. Username: {$username}. You can now log in and book appointments with our doctors.";
        create_notification($patient_id, 'Welcome to Our Healthcare System', $message);
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }

    $pdo->commit();
    
    flash_set('success', 'Patient account created successfully for "' . htmlspecialchars($full_name) . '".');
    header('Location: manage_patients.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Patient creation error: " . $e->getMessage());
    flash_set('error', 'Could not create patient: ' . $e->getMessage());
    header('Location: add_patient.php');
    exit;
}
?>