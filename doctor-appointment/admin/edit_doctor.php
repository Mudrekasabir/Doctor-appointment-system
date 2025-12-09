<?php
// admin/edit_doctor.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid doctor ID');
    header('Location: manage_doctors.php');
    exit;
}

// Fetch doctor data
$stmt = $pdo->prepare("
    SELECT u.*, dp.* 
    FROM users u
    LEFT JOIN doctors_profiles dp ON dp.user_id = u.id
    WHERE u.id = ? AND u.role = 'doctor'
");
$stmt->execute([$id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    flash_set('error', 'Doctor not found');
    header('Location: manage_doctors.php');
    exit;
}

// Fetch availability
$avail_stmt = $pdo->prepare("SELECT * FROM doctor_availability WHERE doctor_id = ? ORDER BY day_of_week");
$avail_stmt->execute([$id]);
$availability = $avail_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize availability by day
$schedule = [];
foreach ($availability as $av) {
    $schedule[$av['day_of_week']] = [
        'start' => substr($av['start_time'], 0, 5),
        'end' => substr($av['end_time'], 0, 5)
    ];
}

$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor - <?php echo e($doctor['full_name']); ?></title>
    <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        .form-header h1 {
            color: #1f2937;
            margin: 0 0 10px 0;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .form-section h3 {
            color: #374151;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 600;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .schedule-grid {
            display: grid;
            gap: 15px;
        }
        .day-schedule {
            display: grid;
            grid-template-columns: 30px 150px 1fr 1fr;
            gap: 10px;
            align-items: center;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .day-schedule input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .day-name {
            font-weight: 600;
            color: #374151;
        }
        .time-input {
            width: 100%;
        }
        .current-image {
            max-width: 150px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .help-text {
            font-size: 13px;
            color: #6b7280;
            margin-top: 5px;
        }
        .required {
            color: #ef4444;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="form-container">
    <div class="form-header">
        <h1>✏️ Edit Doctor: <?php echo e($doctor['full_name']); ?></h1>
        <p style="color: #6b7280; margin: 5px 0 0 0;">Update doctor information and availability schedule</p>
    </div>

    <?php flash_render(); ?>

    <form method="POST" action="edit_doctor_save.php" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <!-- Basic Information -->
        <div class="form-section">
            <h3>👤 Basic Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="username" value="<?php echo e($doctor['username']); ?>" required>
                    <div class="help-text">3-20 characters, letters, numbers, and underscore only</div>
                </div>
                
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" value="<?php echo e($doctor['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" value="<?php echo e($doctor['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Contact <span class="required">*</span></label>
                    <input type="text" name="contact" value="<?php echo e($doctor['contact']); ?>" required>
                    <div class="help-text">10-15 digits</div>
                </div>
            </div>
        </div>

        <!-- Professional Information -->
        <div class="form-section">
            <h3>🩺 Professional Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>License Number <span class="required">*</span></label>
                    <input type="text" name="license_no" value="<?php echo e($doctor['license_no'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Specialty <span class="required">*</span></label>
                    <select name="specialty" required>
                        <option value="">Select Specialty</option>
                        <?php 
                        $specialties = ['Cardiology', 'Dermatology', 'Neurology', 'Orthopedics', 'Pediatrics', 'General Medicine', 'Dentistry', 'Ophthalmology', 'ENT', 'Psychiatry'];
                        foreach ($specialties as $spec): 
                        ?>
                            <option value="<?php echo $spec; ?>" <?php echo ($doctor['specialty'] ?? '') === $spec ? 'selected' : ''; ?>>
                                <?php echo $spec; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Experience (Years) <span class="required">*</span></label>
                    <input type="number" name="experience" value="<?php echo e($doctor['experience'] ?? 0); ?>" min="0" max="50" required>
                </div>
                
                <div class="form-group">
                    <label>Consultation Fee (₹) <span class="required">*</span></label>
                    <input type="number" name="fee" value="<?php echo e($doctor['fee'] ?? 0); ?>" min="0" step="0.01" required>
                </div>
            </div>

            <div class="form-group">
                <label>Bio / Description</label>
                <textarea name="bio"><?php echo e($doctor['bio'] ?? ''); ?></textarea>
                <div class="help-text">Brief description about the doctor's expertise and qualifications</div>
            </div>

            <div class="form-group">
                <label>Profile Image</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/jpg">
                <div class="help-text">JPG or PNG, max 2MB</div>
                <?php if (!empty($doctor['image'])): ?>
                    <div style="margin-top: 10px;">
                        <img src="/doctor-appointment/<?php echo e($doctor['image']); ?>" alt="Current Image" class="current-image">
                        <div class="help-text">Current image (will be replaced if new image is uploaded)</div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="profile_status">
                    <option value="approved" <?php echo ($doctor['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="pending" <?php echo ($doctor['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="rejected" <?php echo ($doctor['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
        </div>

        <!-- Weekly Schedule -->
        <div class="form-section">
            <h3>📅 Weekly Availability Schedule</h3>
            <div class="schedule-grid">
                <?php foreach ($days as $index => $day): 
                    $has_schedule = isset($schedule[$index]);
                ?>
                <div class="day-schedule">
                    <input type="checkbox" 
                           name="day_<?php echo $index; ?>_enabled" 
                           id="day_<?php echo $index; ?>"
                           <?php echo $has_schedule ? 'checked' : ''; ?>>
                    <label for="day_<?php echo $index; ?>" class="day-name"><?php echo $day; ?></label>
                    <input type="time" 
                           name="day_<?php echo $index; ?>_start" 
                           class="time-input"
                           value="<?php echo $schedule[$index]['start'] ?? '09:00'; ?>"
                           placeholder="Start Time">
                    <input type="time" 
                           name="day_<?php echo $index; ?>_end" 
                           class="time-input"
                           value="<?php echo $schedule[$index]['end'] ?? '17:00'; ?>"
                           placeholder="End Time">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="help-text" style="margin-top: 15px;">
                Check the days when the doctor is available and set their working hours
            </div>
        </div>

        <!-- Password Update (Optional) -->
        <div class="form-section">
            <h3>🔒 Change Password (Optional)</h3>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" minlength="6">
                <div class="help-text">Leave empty to keep current password. Minimum 6 characters if changing.</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">💾 Update Doctor</button>
            <a href="manage_doctors.php" class="btn btn-secondary">← Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>