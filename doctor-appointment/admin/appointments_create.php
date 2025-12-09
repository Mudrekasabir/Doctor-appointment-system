<?php
// admin/appointments_create.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token manually (since validate_csrf() might not exist)
    $csrf_token = $_POST['csrf_token'] ?? '';
    $is_valid_csrf = false;
    
    if (isset($_SESSION['csrf_token']) && !empty($csrf_token)) {
        if (function_exists('hash_equals')) {
            $is_valid_csrf = hash_equals($_SESSION['csrf_token'], $csrf_token);
        } else {
            $is_valid_csrf = ($_SESSION['csrf_token'] === $csrf_token);
        }
    }
    
    if (!$is_valid_csrf) {
        flash_set('error', 'Invalid CSRF token. Please try again.');
        header('Location: appointments_create.php');
        exit;
    }
    
    // Get form data
    $patient_id = intval($_POST['patient_id'] ?? 0);
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $notes = trim($_POST['notes'] ?? ''); // Optional notes
    
    // Validate inputs
    $errors = [];
    
    if ($patient_id <= 0) {
        $errors[] = 'Please select a patient';
    }
    
    if ($doctor_id <= 0) {
        $errors[] = 'Please select a doctor';
    }
    
    if (empty($date)) {
        $errors[] = 'Please select a date';
    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Appointment date cannot be in the past';
    }
    
    if (empty($start_time)) {
        $errors[] = 'Please select start time';
    }
    
    if (empty($end_time)) {
        $errors[] = 'Please select end time';
    }
    
    if (!empty($start_time) && !empty($end_time) && $start_time >= $end_time) {
        $errors[] = 'End time must be after start time';
    }
    
    // If no errors, proceed with creation
    if (empty($errors)) {
        try {
            // Check if doctor exists and is active
            $doctor_check = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'doctor' AND status = 'active'");
            $doctor_check->execute([$doctor_id]);
            $doctor = $doctor_check->fetch();
            
            if (!$doctor) {
                $errors[] = 'Selected doctor is not available';
            }
            
            // Check if patient exists
            $patient_check = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'patient'");
            $patient_check->execute([$patient_id]);
            $patient = $patient_check->fetch();
            
            if (!$patient) {
                $errors[] = 'Selected patient does not exist';
            }
            
            // Check for conflicting appointments
            if (empty($errors)) {
                $conflict_check = $pdo->prepare("
                    SELECT id FROM appointments 
                    WHERE doctor_id = ? 
                    AND date = ? 
                    AND status = 'booked'
                    AND (
                        (start_time < ? AND end_time > ?) OR
                        (start_time < ? AND end_time > ?) OR
                        (start_time >= ? AND end_time <= ?)
                    )
                ");
                $conflict_check->execute([
                    $doctor_id, $date,
                    $end_time, $start_time,
                    $end_time, $start_time,
                    $start_time, $end_time
                ]);
                
                if ($conflict_check->fetch()) {
                    $errors[] = 'Doctor already has an appointment during this time slot';
                }
            }
            
            // Insert appointment if no errors
            if (empty($errors)) {
                try {
                    // Get current user ID for created_by
                    $current_user_id = $_SESSION['user_id'] ?? null;
                    
                    // Simple insert query matching your actual table structure
                    $insert = $pdo->prepare("
                        INSERT INTO appointments 
                        (patient_id, doctor_id, date, start_time, end_time, status, created_at, created_by)
                        VALUES (?, ?, ?, ?, ?, 'booked', NOW(), ?)
                    ");
                    
                    $result = $insert->execute([
                        $patient_id,
                        $doctor_id,
                        $date,
                        $start_time,
                        $end_time,
                        $current_user_id
                    ]);
                    
                    if ($result) {
                        $appointment_id = $pdo->lastInsertId();
                        error_log("Appointment created successfully with ID: " . $appointment_id);
                        flash_set('success', 'Appointment created successfully! Appointment ID: #' . $appointment_id);
                        header('Location: appointments.php');
                        exit;
                    } else {
                        $errorInfo = $insert->errorInfo();
                        error_log("Insert failed - Error Info: " . print_r($errorInfo, true));
                        $errors[] = 'Failed to create appointment. Please try again.';
                    }
                    
                } catch (PDOException $e) {
                    error_log("Create Appointment PDO Error: " . $e->getMessage());
                    $errors[] = 'Database error: ' . $e->getMessage();
                } catch (Exception $e) {
                    error_log("Create Appointment General Error: " . $e->getMessage());
                    $errors[] = 'Error: ' . $e->getMessage();
                }
            }
            
        } catch (PDOException $e) {
            error_log("Outer Create Appointment Error: " . $e->getMessage());
            $errors[] = 'Database error: Unable to create appointment';
        }
    }
    
    // If there are errors, display them
    if (!empty($errors)) {
        foreach ($errors as $error) {
            flash_set('error', $error);
        }
    }
}

// Get doctors and patients for dropdowns
$doctors = $pdo->query("SELECT id, full_name FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY full_name")->fetchAll();
$patients = $pdo->query("SELECT id, full_name FROM users WHERE role = 'patient' ORDER BY full_name")->fetchAll();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Appointment - Admin Panel</title>
    <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .page-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .page-header h1 {
            font-size: 28px;
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 14px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #ef4444;
            margin-left: 4px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group small {
            display: block;
            margin-top: 6px;
            color: #6b7280;
            font-size: 13px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 12px;
            }

            .form-card {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-container">
    <div class="page-header">
        <h1>📅 Create New Appointment</h1>
        <p>Schedule a new appointment for a patient with a doctor</p>
    </div>

    <?php flash_render(); ?>

    <div class="form-card">
        <form method="post">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label>
                    Patient <span class="required">*</span>
                </label>
                <select name="patient_id" required>
                    <option value="">Select a patient</option>
                    <?php foreach($patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo (isset($_POST['patient_id']) && $_POST['patient_id'] == $p['id']) ? 'selected' : ''; ?>>
                            <?php echo e($p['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>
                    Doctor <span class="required">*</span>
                </label>
                <select name="doctor_id" required>
                    <option value="">Select a doctor</option>
                    <?php foreach($doctors as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $d['id']) ? 'selected' : ''; ?>>
                            <?php echo e($d['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>
                    Appointment Date <span class="required">*</span>
                </label>
                <input type="date" 
                       name="date" 
                       min="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo e($_POST['date'] ?? ''); ?>"
                       required>
                <small>Select a date for the appointment (cannot be in the past)</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        Start Time <span class="required">*</span>
                    </label>
                    <input type="time" 
                           name="start_time" 
                           value="<?php echo e($_POST['start_time'] ?? ''); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>
                        End Time <span class="required">*</span>
                    </label>
                    <input type="time" 
                           name="end_time" 
                           value="<?php echo e($_POST['end_time'] ?? ''); ?>"
                           required>
                </div>
            </div>

            <div class="form-group">
                <label>
                    Notes (Optional)
                </label>
                <textarea name="notes" 
                          placeholder="Enter any additional notes for this appointment..."><?php echo e($_POST['notes'] ?? ''); ?></textarea>
                <small>Any additional information about the appointment</small>
            </div>

            <div class="form-actions">
                <a href="appointments.php" class="btn btn-secondary">
                    ← Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    ✓ Create Appointment
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>