<?php
// admin/edit_patient.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid patient ID');
    header('Location: manage_patients.php');
    exit;
}

// Fetch patient data
$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u
    WHERE u.id = ? AND u.role = 'patient'
");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    flash_set('error', 'Patient not found');
    header('Location: manage_patients.php');
    exit;
}

// Fetch medical details if table exists
$medical = null;
try {
    $med_stmt = $pdo->prepare("SELECT * FROM patient_medical_details WHERE patient_id = ?");
    $med_stmt->execute([$id]);
    $medical = $med_stmt->fetch();
} catch (PDOException $e) {
    // Table might not exist
    error_log("Medical details table error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient - <?php echo e($patient['full_name']); ?></title>
    <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
    <style>
        .form-container {
            max-width: 800px;
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
            border-color: #3b82f6;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
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
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
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
        .info-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="form-container">
    <div class="form-header">
        <h1>✏️ Edit Patient: <?php echo e($patient['full_name']); ?></h1>
        <p style="color: #6b7280; margin: 5px 0 0 0;">
            Update patient information and medical details
            <span class="info-badge">ID: <?php echo $id; ?></span>
        </p>
    </div>

    <?php flash_render(); ?>

    <form method="POST" action="edit_patient_save.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <!-- Basic Information -->
        <div class="form-section">
            <h3>👤 Basic Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="username" value="<?php echo e($patient['username']); ?>" required>
                    <div class="help-text">3-20 characters, letters, numbers, and underscore only</div>
                </div>
                
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" value="<?php echo e($patient['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" value="<?php echo e($patient['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Contact <span class="required">*</span></label>
                    <input type="text" name="contact" value="<?php echo e($patient['contact']); ?>" required>
                    <div class="help-text">10-15 digits</div>
                </div>
            </div>

            <div class="form-group">
                <label>Account Status</label>
                <select name="status">
                    <option value="active" <?php echo $patient['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $patient['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>

        <!-- Medical Information -->
        <?php if ($medical !== false): ?>
        <div class="form-section">
            <h3>🏥 Medical Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="age" value="<?php echo e($medical['age'] ?? ''); ?>" min="0" max="150">
                    <div class="help-text">Optional</div>
                </div>
                
                <div class="form-group">
                    <label>Blood Group</label>
                    <select name="blood_group">
                        <option value="">Select Blood Group</option>
                        <?php 
                        $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        foreach ($blood_groups as $bg): 
                        ?>
                            <option value="<?php echo $bg; ?>" <?php echo ($medical['blood_group'] ?? '') === $bg ? 'selected' : ''; ?>>
                                <?php echo $bg; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address"><?php echo e($medical['address'] ?? ''); ?></textarea>
                <div class="help-text">Full residential address</div>
            </div>

            <div class="form-group">
                <label>Emergency Contact</label>
                <input type="text" name="emergency_contact" value="<?php echo e($medical['emergency_contact'] ?? ''); ?>">
                <div class="help-text">Name and phone number of emergency contact person</div>
            </div>
        </div>
        <?php endif; ?>

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
            <button type="submit" class="btn btn-primary">💾 Update Patient</button>
            <a href="manage_patients.php" class="btn btn-secondary">← Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>