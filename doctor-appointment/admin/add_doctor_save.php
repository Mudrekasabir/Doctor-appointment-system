<?php
// admin/add_doctor_save.php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error','Invalid request');
    header('Location: add_doctor.php'); 
    exit;
}

// Validate CSRF token - accepts both field names
$csrf_token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? '';
if (!validate_csrf($csrf_token)) {
    flash_set('error','Invalid CSRF token. Please try again.'); 
    header('Location: add_doctor.php'); 
    exit;
}

// Basic fields
$username = trim($_POST['username'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$password = $_POST['password'] ?? '';
$license_no = trim($_POST['license_no'] ?? '');
$experience = intval($_POST['experience'] ?? 0);
$specialty = trim($_POST['specialty'] ?? '');
$fee = floatval($_POST['fee'] ?? 0);
$bio = trim($_POST['bio'] ?? '');
$created_by = current_user_id();

// Validate required fields
if ($username === '' || $full_name === '' || $email === '' || $password === '' || $license_no === '' || $specialty === '') {
    flash_set('error','Missing required fields: username, full name, email, password, license number, and specialty are required.'); 
    header('Location: add_doctor.php'); 
    exit;
}

// Validate username (alphanumeric and underscore only)
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    flash_set('error','Username must be 3-20 characters long and contain only letters, numbers, and underscores.'); 
    header('Location: add_doctor.php'); 
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error','Invalid email format.'); 
    header('Location: add_doctor.php'); 
    exit;
}

// Validate password length
if (strlen($password) < 6) {
    flash_set('error','Password must be at least 6 characters long.'); 
    header('Location: add_doctor.php'); 
    exit;
}

// Validate contact number
if (!empty($contact) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $contact)) {
    flash_set('error','Invalid contact number format.'); 
    header('Location: add_doctor.php'); 
    exit;
}

// Validate fee
if ($fee < 0) {
    flash_set('error','Consultation fee cannot be negative.'); 
    header('Location: add_doctor.php'); 
    exit;
}

// Image upload (optional)
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
$image_path = null;

if (!empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
    
    if ($f['error'] === UPLOAD_ERR_OK) {
        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        
        if (in_array($mime, $allowed) && $f['size'] <= 2 * 1024 * 1024) {
            $ext = ($mime === 'image/png') ? '.png' : '.jpg';
            $fname = uniqid('drimg_') . $ext;
            
            if (move_uploaded_file($f['tmp_name'], $upload_dir . $fname)) {
                $image_path = 'uploads/' . $fname;
            } else {
                flash_set('error', 'Failed to upload image file.'); 
                header('Location: add_doctor.php'); 
                exit;
            }
        } else {
            flash_set('error', 'Invalid image file. Must be JPG/PNG and under 2MB.'); 
            header('Location: add_doctor.php'); 
            exit;
        }
    } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
        flash_set('error', 'Image upload error: ' . $f['error']); 
        header('Location: add_doctor.php'); 
        exit;
    }
}

// Check duplicates
$chk = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
$chk->execute([$username, $email]);
$existing = $chk->fetch();

if ($existing) {
    if ($existing['username'] === $username) {
        flash_set('error', 'Username "' . htmlspecialchars($username) . '" already exists. Please choose a different username.'); 
    } else {
        flash_set('error', 'Email "' . htmlspecialchars($email) . '" already exists. Please use a different email.'); 
    }
    header('Location: add_doctor.php'); 
    exit; 
}

try {
    $pdo->beginTransaction();

    // Insert user - admin-created doctors are approved immediately
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("
        INSERT INTO users (role, username, full_name, email, contact, password_hash, status, created_at, created_by) 
        VALUES ('doctor', ?, ?, ?, ?, ?, 'active', NOW(), ?)
    ");
    $ins->execute([$username, $full_name, $email, $contact, $hash, $created_by]);
    $uid = $pdo->lastInsertId();

    // Insert doctor profile
    $dp = $pdo->prepare("
        INSERT INTO doctors_profiles (user_id, license_no, experience, specialty, fee, bio, image, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?)
    ");
    $dp->execute([$uid, $license_no, $experience, $specialty, $fee, $bio, $image_path, $created_by]);

    // Insert weekly availability into doctor_available_times table
    // This table should store recurring weekly schedules (day_of_week)
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $availability_added = 0;
    
    // Check which table to use and what columns it has
    $use_available_times = $pdo->query("SHOW TABLES LIKE 'doctor_available_times'")->fetch();
    
    if ($use_available_times) {
        // Check columns in doctor_available_times
        $columns = $pdo->query("DESCRIBE doctor_available_times")->fetchAll(PDO::FETCH_COLUMN);
        
        for ($d = 0; $d <= 6; $d++) {
            $enabled = isset($_POST["day_{$d}_enabled"]);
            $start = trim($_POST["day_{$d}_start"] ?? '');
            $end = trim($_POST["day_{$d}_end"] ?? '');
            
            if ($enabled && $start !== '' && $end !== '') {
                // Add seconds if not present
                if (strlen($start) === 5) $start .= ':00';
                if (strlen($end) === 5) $end .= ':00';
                
                // Validate time format
                if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end)) {
                    throw new Exception("Invalid time format for {$days[$d]}. Use HH:MM format.");
                }
                
                // Validate time logic
                if (strtotime($start) >= strtotime($end)) {
                    throw new Exception("End time must be after start time for {$days[$d]}.");
                }
                
                // Build INSERT based on available columns
                if (in_array('day_of_week', $columns)) {
                    // doctor_available_times has day_of_week column
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_available_times (doctor_id, day_of_week, start_time, end_time) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$uid, $d, $start, $end]);
                } elseif (in_array('day', $columns)) {
                    // Alternative column name
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_available_times (doctor_id, day, start_time, end_time) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$uid, $d, $start, $end]);
                } else {
                    // Fallback: create date-based availability for next 7 days
                    $availability_date = date('Y-m-d', strtotime("next {$days[$d]}"));
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_available_times (doctor_id, availability_date, start_time, end_time) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$uid, $availability_date, $start, $end]);
                }
                
                $availability_added++;
            }
        }
    } else {
        // Fallback: use doctor_date_availability for specific dates
        // Create availability for the next 30 days based on selected days
        for ($d = 0; $d <= 6; $d++) {
            $enabled = isset($_POST["day_{$d}_enabled"]);
            $start = trim($_POST["day_{$d}_start"] ?? '');
            $end = trim($_POST["day_{$d}_end"] ?? '');
            
            if ($enabled && $start !== '' && $end !== '') {
                // Add seconds if not present
                if (strlen($start) === 5) $start .= ':00';
                if (strlen($end) === 5) $end .= ':00';
                
                // Validate time format
                if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end)) {
                    throw new Exception("Invalid time format for {$days[$d]}. Use HH:MM format.");
                }
                
                // Validate time logic
                if (strtotime($start) >= strtotime($end)) {
                    throw new Exception("End time must be after start time for {$days[$d]}.");
                }
                
                // Create specific date entries for the next 4 weeks
                for ($week = 0; $week < 4; $week++) {
                    $date = date('Y-m-d', strtotime("+" . ($week * 7 + $d) . " days"));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_date_availability (doctor_id, availability_date, start_time, end_time, is_available) 
                        VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$uid, $date, $start, $end]);
                }
                
                $availability_added++;
            }
        }
    }

    // Log action
    try {
        $detail = "doctor_id={$uid};username={$username};specialty={$specialty};availability_days={$availability_added}";
        $pdo->prepare("
            INSERT INTO logs (actor_id, actor_role, event_type, detail, created_at) 
            VALUES (?, 'admin', 'create_doctor', ?, NOW())
        ")->execute([$created_by, $detail]);
    } catch (Exception $e) {
        error_log("Logging error: " . $e->getMessage());
    }

    // Create notification for the doctor
    try {
        $message = "Your doctor account has been created by the administrator. Username: {$username}. You can now log in and manage your appointments.";
        create_notification($uid, 'Account Created', $message);
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }

    $pdo->commit();

    $success_msg = 'Doctor "' . htmlspecialchars($full_name) . '" created successfully';
    if ($availability_added > 0) {
        $success_msg .= " with availability for {$availability_added} day(s)";
    }
    $success_msg .= '.';
    
    flash_set('success', $success_msg);
    header('Location: manage_doctors.php'); 
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    // Clean up uploaded image if transaction failed
    if ($image_path && file_exists($upload_dir . basename($image_path))) {
        @unlink($upload_dir . basename($image_path));
    }
    
    error_log("add_doctor_save error: " . $e->getMessage());
    flash_set('error', 'Failed to create doctor: ' . $e->getMessage());
    header('Location: add_doctor.php'); 
    exit;
}
?>