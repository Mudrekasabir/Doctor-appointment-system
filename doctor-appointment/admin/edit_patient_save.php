<?php
// admin/edit_doctor_save.php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error', 'Invalid request');
    header('Location: manage_doctors.php');
    exit;
}

// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? '';
if (!validate_csrf($csrf_token)) {
    flash_set('error', 'Invalid CSRF token. Please try again.');
    header('Location: manage_doctors.php');
    exit;
}

// Get doctor ID
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid doctor ID');
    header('Location: manage_doctors.php');
    exit;
}

// Check if doctor exists
$check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'doctor'");
$check->execute([$id]);
if (!$check->fetch()) {
    flash_set('error', 'Doctor not found');
    header('Location: manage_doctors.php');
    exit;
}

// Get form data
$username = trim($_POST['username'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$license_no = trim($_POST['license_no'] ?? '');
$experience = intval($_POST['experience'] ?? 0);
$specialty = trim($_POST['specialty'] ?? '');
$fee = floatval($_POST['fee'] ?? 0);
$bio = trim($_POST['bio'] ?? '');
$profile_status = $_POST['profile_status'] ?? 'approved';
$updated_by = current_user_id();

// Validate required fields
if ($username === '' || $full_name === '' || $email === '' || $license_no === '' || $specialty === '') {
    flash_set('error', 'Missing required fields');
    header('Location: edit_doctor.php?id=' . $id);
    exit;
}

// Validate username
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    flash_set('error', 'Username must be 3-20 characters long and contain only letters, numbers, and underscores.');
    header('Location: edit_doctor.php?id=' . $id);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Invalid email format.');
    header('Location: edit_doctor.php?id=' . $id);
    exit;
}

// Validate contact
if (!empty($contact) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $contact)) {
    flash_set('error', 'Invalid contact number format.');
    header('Location: edit_doctor.php?id=' . $id);
    exit;
}

// Validate fee
if ($fee < 0) {
    flash_set('error', 'Consultation fee cannot be negative.');
    header('Location: edit_doctor.php?id=' . $id);
    exit;
}

// Check for duplicate username/email (excluding current doctor)
$dup_check = $pdo->prepare("SELECT id, username, email FROM users WHERE (username = ? OR email = ?) AND id != ?");
$dup_check->execute([$username, $email, $id]);
$duplicate = $dup_check->fetch();

if ($duplicate) {
    if ($duplicate['username'] === $username) {
        flash_set('error', 'Username "' . htmlspecialchars($username) . '" is already taken by another user.');
    } else {
        flash_set('error', 'Email "' . htmlspecialchars($email) . '" is already registered to another user.');
    }
    header('Location: edit_doctor.php?id=' . $id);
    exit;
}

// Handle image upload
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
$image_path = null;

if (!empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
    
    if ($f['error'] === UPLOAD_ERR_OK) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        
        if (in_array($mime, $allowed) && $f['size'] <= 2 * 1024 * 1024) {
            $ext = ($mime === 'image/png') ? '.png' : '.jpg';
            $fname = uniqid('drimg_') . $ext;
            
            if (move_uploaded_file($f['tmp_name'], $upload_dir . $fname)) {
                $image_path = 'uploads/' . $fname;
                
                // Delete old image
                $old_img = $pdo->prepare("SELECT image FROM doctors_profiles WHERE user_id = ?");
                $old_img->execute([$id]);
                $old = $old_img->fetchColumn();
                if ($old && file_exists(__DIR__ . '/../' . $old)) {
                    @unlink(__DIR__ . '/../' . $old);
                }
            }
        } else {
            flash_set('error', 'Invalid image file. Must be JPG/PNG and under 2MB.');
            header('Location: edit_doctor.php?id=' . $id);
            exit;
        }
    }
}

try {
    $pdo->beginTransaction();

    // Update user table
    $user_update = "UPDATE users SET username = ?, full_name = ?, email = ?, contact = ?";
    $user_params = [$username, $full_name, $email, $contact];
    
    // Update password if provided
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            throw new Exception('Password must be at least 6 characters long.');
        }
        $user_update .= ", password_hash = ?";
        $user_params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }
    
    $user_update .= " WHERE id = ?";
    $user_params[] = $id;
    
    $stmt = $pdo->prepare($user_update);
    $stmt->execute($user_params);

    // Update doctor profile
    $profile_update = "UPDATE doctors_profiles SET license_no = ?, experience = ?, specialty = ?, fee = ?, bio = ?, status = ?";
    $profile_params = [$license_no, $experience, $specialty, $fee, $bio, $profile_status];
    
    if ($image_path !== null) {
        $profile_update .= ", image = ?";
        $profile_params[] = $image_path;
    }
    
    $profile_update .= " WHERE user_id = ?";
    $profile_params[] = $id;
    
    $stmt = $pdo->prepare($profile_update);
    $stmt->execute($profile_params);

    // Update availability schedule
    // First, delete existing availability
    $pdo->prepare("DELETE FROM doctor_availability WHERE doctor_id = ?")->execute([$id]);
    
    // Insert new availability
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $availability_added = 0;
    
    for ($d = 0; $d <= 6; $d++) {
        $enabled = isset($_POST["day_{$d}_enabled"]);
        $start = trim($_POST["day_{$d}_start"] ?? '');
        $end = trim($_POST["day_{$d}_end"] ?? '');
        
        if ($enabled && $start !== '' && $end !== '') {
            // Add seconds if not present
            if (strlen($start) === 5) $start .= ':00';
            if (strlen($end) === 5) $end .= ':00';
            
            // Validate time logic
            if (strtotime($start) >= strtotime($end)) {
                throw new Exception("End time must be after start time for {$days[$d]}.");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$id, $d, $start, $end]);
            $availability_added++;
        }
    }

    // Log the update
    try {
        $detail = "doctor_id={$id};username={$username};specialty={$specialty};availability_days={$availability_added}";
        $pdo->prepare("
            INSERT INTO logs (actor_id, actor_role, event_type, detail, created_at) 
            VALUES (?, 'admin', 'update_doctor', ?, NOW())
        ")->execute([$updated_by, $detail]);
    } catch (Exception $e) {
        error_log("Logging error: " . $e->getMessage());
    }

    // Notify doctor of profile update
    try {
        $message = "Your profile has been updated by the administrator.";
        if (!empty($new_password)) {
            $message .= " Your password has been changed.";
        }
        create_notification($id, 'Profile Updated', $message);
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }

    $pdo->commit();

    flash_set('success', 'Doctor "' . htmlspecialchars($full_name) . '" updated successfully.');
    header('Location: manage_doctors.php');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    // Clean up uploaded image on failure
    if ($image_path && file_exists($upload_dir . basename($image_path))) {
        @unlink($upload_dir . basename($image_path));
    }
    
    error_log("edit_doctor_save error: " . $e->getMessage());
    flash_set('error', 'Failed to update doctor: ' . $e->getMessage());
    header('Location: edit_doctor.php?id=' . $id);
    exit;
}
?>