<?php
// patient/appointments.php - Modern UI
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$uid = current_user_id();

// Fetch appointments
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.appointment_date,
        a.start_time,
        a.end_time,
        a.status,
        a.cancel_reason,
        ud.full_name AS doctor_name,
        dp.specialty
    FROM appointments a
    JOIN users ud ON a.doctor_id = ud.id
    JOIN doctors_profiles dp ON dp.user_id = ud.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.start_time DESC
");
$stmt->execute([$uid]);
$appts = $stmt->fetchAll();

// Split upcoming & past
$upcoming = [];
$past = [];
foreach ($appts as $a) {
    $time = strtotime($a['appointment_date'] . ' ' . $a['start_time']);
    ($time >= time()) ? $upcoming[] = $a : $past[] = $a;
}

function h($v){ return htmlspecialchars($v,ENT_QUOTES,'UTF-8'); }
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>My Appointments</title>
<link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">

<style>
body {
    font-family: 'Inter', sans-serif;
    background: #f0f2f5;
}

.page-wrap {
    margin-left: 240px;
    padding: 30px;
}

.card {
    background: white;
    padding: 28px;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 6px;
}

.page-sub {
    color: #6b7280;
}

.tabs {
    display: flex;
    background: #e6e9ef;
    border-radius: 12px;
    padding: 6px;
    margin-bottom: 25px;
}

.tab-btn {
    flex: 1;
    padding: 14px;
    font-weight: 600;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: .2s;
    background: transparent;
    color: #4b5563;
}

.tab-btn.active {
    background: white;
    color: #2563eb;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.tab-panel { display: none; }
.tab-panel.active { display: block; }

.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 14px;
}

.table-row {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
}

.table td {
    padding: 18px;
    font-size: 14px;
    color: #374151;
}

.status {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status.pending { background: #fff7d6; color: #8a6d00; }
.status.booked { background: #e1ffed; color: #0f8a41; }
.status.completed { background: #e5f6ff; color: #005d8a; }
.status.cancelled { background: #ffe4e4; color: #b21f27; }

.btn-cancel {
    padding: 8px 16px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    transition: .2s;
}
.btn-cancel:hover {
    background: #b71d28;
}

input[type="text"] {
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    width: 200px;
}
</style>

</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">

    <div class="card">
        <div class="page-title">My Appointments</div>
        <div class="page-sub">Review and manage your appointments</div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab(event,'upcoming')">
            Upcoming (<?=count($upcoming)?>)
        </button>
        <button class="tab-btn" onclick="switchTab(event,'past')">
            Past (<?=count($past)?>)
        </button>
    </div>

    <!-- Upcoming -->
    <div id="upcoming" class="tab-panel active">
        <?php if (!$upcoming): ?>
            <div class="card">No upcoming appointments.</div>
        <?php else: ?>
            <table class="table">
                <?php foreach($upcoming as $a): ?>
                <tr class="table-row">
                    <td><?=date('M d, Y', strtotime($a['appointment_date']))?></td>
                    <td><?=date('g:i A', strtotime($a['start_time']))?> - <?=date('g:i A', strtotime($a['end_time']))?></td>
                    <td><strong><?=h($a['doctor_name'])?></strong></td>
                    <td><?=h($a['specialty'])?></td>
                    <td><span class="status <?=h($a['status'])?>"><?=ucfirst($a['status'])?></span></td>
                    <td>
                        <?php if($a['status']==='pending' || $a['status']==='booked'): ?>
                        <form method="post" action="/doctor-appointment/patient/cancel_appointment.php">
                            <?=csrf_field()?>
                            <input type="hidden" name="appointment_id" value="<?=$a['id']?>">
                            <input type="text" name="cancel_reason" placeholder="Reason (optional)">
                            <button class="btn-cancel">Cancel</button>
                        </form>
                        <?php else: ?> - <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- Past -->
    <div id="past" class="tab-panel">
        <?php if (!$past): ?>
            <div class="card">No past appointments.</div>
        <?php else: ?>
            <table class="table">
                <?php foreach($past as $a): ?>
                <tr class="table-row">
                    <td><?=date('M d, Y', strtotime($a['appointment_date']))?></td>
                    <td><?=date('g:i A', strtotime($a['start_time']))?> - <?=date('g:i A', strtotime($a['end_time']))?></td>
                    <td><strong><?=h($a['doctor_name'])?></strong></td>
                    <td><?=h($a['specialty'])?></td>
                    <td><span class="status <?=h($a['status'])?>"><?=ucfirst($a['status'])?></span></td>
                    <td><?=h($a['cancel_reason'] ?: '-')?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

</div>

<script>
function switchTab(event, id) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

    event.currentTarget.classList.add('active');
    document.getElementById(id).classList.add('active');
}
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>
