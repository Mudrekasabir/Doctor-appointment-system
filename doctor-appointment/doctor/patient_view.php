<?php
// doctor/patient_view.php — read-only patient medical details for doctors
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$doctor_id = current_user_id();
$patient_id = intval($_GET['patient_id'] ?? 0);
$appointment_id = intval($_GET['appointment_id'] ?? 0);

if ($patient_id <= 0) { http_response_code(400); echo "Invalid patient id"; exit; }

// security: allow only if doctor has at least one appointment with this patient (any status)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND patient_id=? LIMIT 1");
$stmt->execute([$doctor_id, $patient_id]);
if ((int)$stmt->fetchColumn() === 0) {
    http_response_code(403);
    echo "Access denied: No relationship with this patient.";
    exit;
}

// fetch patient basic
$u = $pdo->prepare("SELECT id, username, full_name, email, contact FROM users WHERE id=? LIMIT 1");
$u->execute([$patient_id]);
$user = $u->fetch();
if (!$user) { http_response_code(404); echo "Patient not found"; exit; }

// fetch medical details
$mp = $pdo->prepare("SELECT * FROM patients_profiles WHERE user_id=? LIMIT 1");
$mp->execute([$patient_id]);
$profile = $mp->fetch();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Patient medical details — <?php echo e($user['full_name']); ?></title>
<link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>
<div class="page-wrap" style="max-width:800px;margin:18px auto;padding:12px">
  <h1>Patient — <?php echo e($user['full_name']); ?></h1>
  <div class="muted">Contact: <?php echo e($user['contact']); ?> · Email: <?php echo e($user['email']); ?></div>

  <h3 style="margin-top:18px">Medical Details</h3>
  <table class="wide-table">
    <tr><th>Diabetes</th><td><?php echo e($profile['diabetes'] ?? '-'); ?></td></tr>
    <tr><th>Thyroid</th><td><?php echo e($profile['thyroid'] ?? '-'); ?></td></tr>
    <tr><th>Blood Pressure</th><td><?php echo e($profile['blood_pressure'] ?? '-'); ?></td></tr>
    <tr><th>Asthma</th><td><?php echo e($profile['asthma'] ?? '-'); ?></td></tr>
    <tr><th>Age</th><td><?php echo e($profile['age'] ?? '-'); ?></td></tr>
    <tr><th>Blood Group</th><td><?php echo e($profile['blood_group'] ?? '-'); ?></td></tr>
    <tr><th>Allergies</th><td><?php echo e($profile['allergies'] ?? '-') ; if(!empty($profile['allergies_text'])) echo ' — '.e($profile['allergies_text']); ?></td></tr>
    <tr><th>Past Surgeries</th><td><?php echo e($profile['past_surgeries'] ?? '-'); if(!empty($profile['surgeries_text'])) echo ' — '.e($profile['surgeries_text']); ?></td></tr>
  </table>

  <div style="margin-top:14px">
    <a class="role-btn" href="/doctor-appointment/doctor/dashboard.php">Back to dashboard</a>
  </div>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>
