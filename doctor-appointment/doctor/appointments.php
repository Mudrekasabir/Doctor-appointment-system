<?php
// doctor/appointments.php (UI improved)
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$doctor_id = current_user_id();

$filter_date = trim($_GET['date'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$q_patient = trim($_GET['patient_q'] ?? '');

$sql = "SELECT a.*, u.full_name AS patient_name, u.contact AS patient_contact, dp.blood_group
        FROM appointments a
        JOIN users u ON u.id = a.patient_id
        LEFT JOIN patients_profiles dp ON dp.user_id = u.id
        WHERE a.doctor_id = :doc";
$params = [':doc' => $doctor_id];

if ($filter_date !== '') {
    $sql .= " AND a.date = :date";
    $params[':date'] = $filter_date;
}
if ($filter_status !== '' && in_array($filter_status, ['booked','cancelled','completed'])) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $filter_status;
}
if ($q_patient !== '') {
    $sql .= " AND (u.full_name LIKE :pq OR u.username LIKE :pq)";
    $params[':pq'] = "%$q_patient%";
}

$sql .= " ORDER BY a.date DESC, a.start_time DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Doctor Appointments</title>
  <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">

  <style>
    /* Page container */
    .dash-container {
        max-width: 1150px;
        margin: 20px auto;
        padding: 25px;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        animation: fadeIn .4s ease;
    }

    @keyframes fadeIn {
        from { opacity:0; transform:translateY(15px); }
        to   { opacity:1; transform:translateY(0);  }
    }

    h1 {
        font-size: 26px;
        font-weight: 700;
        margin-bottom: 20px;
    }

    /* Filter card */
    .filter-card {
        background: #f9fafb;
        padding: 16px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 20px;
    }

    .filter-card label {
        font-size: 14px;
        color: #374151;
        font-weight: 600;
    }

    .filter-card input,
    .filter-card select {
        padding: 8px 10px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        margin-left: 6px;
    }

    /* Table */
    .wide-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 12px;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
    }

    .wide-table th {
        background: #f1f5f9;
        padding: 12px;
        text-align: left;
        font-size: 14px;
        color: #374151;
    }

    .wide-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }

    .wide-table tr:hover {
        background: #f8fafc;
    }

    /* Status badges */
    .status {
        padding: 6px 10px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        text-transform: capitalize;
    }

    .status.booked { background:#e6ffef; color:#065f46; }
    .status.completed { background:#dbeafe; color:#1e40af; }
    .status.cancelled { background:#fee2e2; color:#991b1b; }

    /* Buttons */
    .role-btn.small-btn {
        padding: 6px 10px;
        font-size: 13px;
        border-radius: 8px;
    }

    .btn-reset {
        background:#6b7280 !important;
    }

    /* Empty state */
    .empty-box {
        padding: 20px;
        text-align: center;
        background:#f9fafb;
        border:1px solid #e5e7eb;
        border-radius:10px;
        margin-top:15px;
        font-size:15px;
        color:#6b7280;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">
<div class="dash-container">

    <h1>📅 Appointments</h1>

    <?php if(function_exists('flash_render')) flash_render(); ?>

    <!-- Filters -->
    <form method="get" class="filter-card">
        <div>
            <label>Date:</label>
            <input type="date" name="date" value="<?php echo e($filter_date); ?>">
        </div>

        <div>
            <label>Status:</label>
            <select name="status">
                <option value="">All</option>
                <option value="booked" <?php echo $filter_status==='booked'?'selected':''; ?>>Booked</option>
                <option value="cancelled" <?php echo $filter_status==='cancelled'?'selected':''; ?>>Cancelled</option>
                <option value="completed" <?php echo $filter_status==='completed'?'selected':''; ?>>Completed</option>
            </select>
        </div>

        <div>
            <label>Patient:</label>
            <input name="patient_q" placeholder="name or username" value="<?php echo e($q_patient); ?>">
        </div>

        <button class="role-btn" type="submit">Apply</button>
        <a class="role-btn btn-reset" href="/doctor-appointment/doctor/appointments.php">Reset</a>
    </form>

    <!-- Table -->
    <?php if(empty($rows)): ?>
        <div class="empty-box">No appointments found for the selected filters.</div>
    <?php else: ?>
        <table class="wide-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Contact</th>
                    <th>Blood Group</th>
                    <th>Status</th>
                    <th>View</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rows as $r): ?>
                <tr>
                    <td><?php echo e($r['date']); ?></td>
                    <td><?php echo e(substr($r['start_time'],0,5).' - '.substr($r['end_time'],0,5)); ?></td>
                    <td><?php echo e($r['patient_name']); ?></td>
                    <td><?php echo e($r['patient_contact']); ?></td>
                    <td><?php echo e($r['blood_group'] ?? '-'); ?></td>
                    <td><span class="status <?php echo e($r['status']); ?>"><?php echo e(ucfirst($r['status'])); ?></span></td>
                    <td>
                        <a class="role-btn small-btn"
                           href="/doctor-appointment/doctor/patient_view.php?patient_id=<?php echo (int)$r['patient_id']; ?>&appointment_id=<?php echo (int)$r['id']; ?>">
                           Open
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top:18px;">
        <a class="role-btn" href="/doctor-appointment/doctor/dashboard.php">← Back</a>
    </div>

</div>
</div>

</body>
</html>
