<?php
// admin/dashboard.php (Modern User-Friendly Version)
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Metrics
$month_start = date('Y-m-01');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='patient' AND created_at >= ?");
$stmt->execute([$month_start]); 
$users_this_month = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'"); 
$total_patients = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor' AND status='active'"); 
$total_doctors = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='booked'"); 
$upcoming_appointments = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='completed'"); 
$completed_appointments = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor' AND status='pending'"); 
$pending_doctors = (int)$stmt->fetchColumn();

// Recent activity - appointments
$stmt = $pdo->query("
    SELECT a.id, a.date, a.start_time, a.end_time, a.status, 
           p.full_name AS patient_name, d.full_name AS doctor_name, a.created_at
    FROM appointments a 
    JOIN users p ON a.patient_id=p.id 
    JOIN users d ON a.doctor_id=d.id 
    ORDER BY a.created_at DESC 
    LIMIT 8
");
$recent_appointments = $stmt->fetchAll();

// Recent cancelled appointments
$stmt = $pdo->query("
    SELECT a.id, a.date, a.start_time, a.end_time, a.cancel_reason, a.cancelled_at, 
           p.full_name AS patient_name, d.full_name AS doctor_name 
    FROM appointments a 
    JOIN users p ON a.patient_id=p.id 
    JOIN users d ON a.doctor_id=d.id 
    WHERE a.status='cancelled' 
    ORDER BY a.cancelled_at DESC 
    LIMIT 5
");
$recent_cancelled = $stmt->fetchAll();

// Appointments per day (last 7 days)
$days = [];
$counts = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $days[] = date('M d', strtotime($d));
    $q = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE date = ?");
    $q->execute([$d]);
    $counts[] = (int)$q->fetchColumn();
}

// Status breakdown
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY status
");
$status_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$booked = $status_data['booked'] ?? 0;
$completed = $status_data['completed'] ?? 0;
$cancelled = $status_data['cancelled'] ?? 0;

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Doctor Appointment System</title>
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
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
}

.welcome-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px;
    padding: 40px;
    margin-bottom: 32px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}

.welcome-section h1 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
}

.welcome-section p {
    font-size: 16px;
    opacity: 0.95;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--accent-color);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.stat-card.blue { --accent-color: #3b82f6; }
.stat-card.green { --accent-color: #10b981; }
.stat-card.purple { --accent-color: #8b5cf6; }
.stat-card.orange { --accent-color: #f59e0b; }
.stat-card.red { --accent-color: #ef4444; }
.stat-card.indigo { --accent-color: #6366f1; }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-color) 100%);
    opacity: 0.15;
}

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.stat-change {
    font-size: 13px;
    color: #10b981;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 8px;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f3f4f6;
}

.card-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

.view-all {
    font-size: 14px;
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
}

.view-all:hover {
    color: #2563eb;
}

.chart-container {
    position: relative;
    height: 300px;
    margin-top: 20px;
}

.activity-list {
    list-style: none;
}

.activity-item {
    padding: 16px;
    border-bottom: 1px solid #f3f4f6;
    transition: background 0.2s;
}

.activity-item:hover {
    background: #f9fafb;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.activity-title {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
}

.activity-time {
    font-size: 12px;
    color: #9ca3af;
}

.activity-details {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.6;
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-booked {
    background: #dbeafe;
    color: #1e40af;
}

.badge-completed {
    background: #d1fae5;
    color: #065f46;
}

.badge-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.quick-actions {
    display: grid;
    gap: 12px;
}

.quick-action {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 10px;
    text-decoration: none;
    color: #374151;
    transition: all 0.2s;
    border: 2px solid transparent;
}

.quick-action:hover {
    background: white;
    border-color: #3b82f6;
    transform: translateX(4px);
}

.quick-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: white;
}

.quick-action-content {
    flex: 1;
}

.quick-action-title {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
}

.quick-action-desc {
    font-size: 12px;
    color: #6b7280;
}

.alert-badge {
    background: #ef4444;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #9ca3af;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .page-container {
        padding: 16px;
    }

    .welcome-section {
        padding: 24px;
    }

    .welcome-section h1 {
        font-size: 24px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-value {
        font-size: 28px;
    }
}

/* Simple Chart Styles */
.simple-chart {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    height: 200px;
    gap: 8px;
    padding: 20px 0;
}

.chart-bar {
    flex: 1;
    background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 6px 6px 0 0;
    position: relative;
    min-height: 20px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.chart-bar:hover {
    background: linear-gradient(180deg, #2563eb 0%, #1e40af 100%);
    transform: scaleY(1.05);
}

.chart-bar-label {
    position: absolute;
    bottom: -25px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 11px;
    color: #6b7280;
    font-weight: 500;
}

.chart-bar-value {
    position: absolute;
    top: -25px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 12px;
    color: #1f2937;
    font-weight: 600;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-container">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1>👋 Welcome back, Admin!</h1>
        <p>Here's what's happening with your doctor appointment system today</p>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($total_patients); ?></div>
                    <div class="stat-label">Total Patients</div>
                    <div class="stat-change">↑ <?php echo $users_this_month; ?> this month</div>
                </div>
                <div class="stat-icon">👥</div>
            </div>
        </div>

        <div class="stat-card green">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($total_doctors); ?></div>
                    <div class="stat-label">Active Doctors</div>
                </div>
                <div class="stat-icon">👨‍⚕️</div>
            </div>
        </div>

        <div class="stat-card purple">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($upcoming_appointments); ?></div>
                    <div class="stat-label">Upcoming Appointments</div>
                </div>
                <div class="stat-icon">📅</div>
            </div>
        </div>

        <div class="stat-card orange">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($completed_appointments); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-icon">✅</div>
            </div>
        </div>

        <div class="stat-card red">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($pending_doctors); ?></div>
                    <div class="stat-label">Pending Requests</div>
                    <?php if($pending_doctors > 0): ?>
                        <a href="/doctor-appointment/admin/requests.php" style="text-decoration:none;">
                            <div class="stat-change" style="color: #ef4444;">⚠️ Requires attention</div>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="stat-icon">⏳</div>
            </div>
        </div>

        <div class="stat-card indigo">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($booked + $completed + $cancelled); ?></div>
                    <div class="stat-label">Total This Month</div>
                </div>
                <div class="stat-icon">📊</div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Chart Section -->
        <div class="card">
            <div class="card-header">
                <h2>📈 Appointments - Last 7 Days</h2>
                <a href="/doctor-appointment/admin/appointments.php" class="view-all">View All →</a>
            </div>
            <div class="simple-chart">
                <?php 
                $max = max($counts) ?: 1;
                foreach($counts as $idx => $count): 
                    $height = ($count / $max) * 100;
                ?>
                    <div class="chart-bar" style="height: <?php echo $height; ?>%;" title="<?php echo $days[$idx]; ?>: <?php echo $count; ?> appointments">
                        <div class="chart-bar-value"><?php echo $count; ?></div>
                        <div class="chart-bar-label"><?php echo $days[$idx]; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2>⚡ Quick Actions</h2>
            </div>
            <div class="quick-actions">
                <a href="/doctor-appointment/admin/requests.php" class="quick-action">
                    <div class="quick-action-icon">📋</div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Doctor Requests</div>
                        <div class="quick-action-desc">Review pending applications</div>
                    </div>
                    <?php if($pending_doctors > 0): ?>
                        <span class="alert-badge"><?php echo $pending_doctors; ?></span>
                    <?php endif; ?>
                </a>

                <a href="/doctor-appointment/admin/appointments_create.php" class="quick-action">
                    <div class="quick-action-icon">➕</div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Create Appointment</div>
                        <div class="quick-action-desc">Schedule new appointment</div>
                    </div>
                </a>

                <a href="/doctor-appointment/admin/manage_doctors.php" class="quick-action">
                    <div class="quick-action-icon">👨‍⚕️</div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Manage Doctors</div>
                        <div class="quick-action-desc">View all doctors</div>
                    </div>
                </a>

                <a href="/doctor-appointment/admin/manage_patients.php" class="quick-action">
                    <div class="quick-action-icon">👥</div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Manage Patients</div>
                        <div class="quick-action-desc">View all patients</div>
                    </div>
                </a>

                <a href="/doctor-appointment/admin/reports.php" class="quick-action">
                    <div class="quick-action-icon">📊</div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Reports</div>
                        <div class="quick-action-desc">View analytics</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="content-grid">
        <div class="card">
            <div class="card-header">
                <h2>🕐 Recent Appointments</h2>
                <a href="/doctor-appointment/admin/appointments.php" class="view-all">View All →</a>
            </div>
            <?php if(empty($recent_appointments)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <p>No recent appointments</p>
                </div>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach($recent_appointments as $appt): ?>
                        <li class="activity-item">
                            <div class="activity-header">
                                <div class="activity-title">
                                    <?php echo e($appt['patient_name']); ?> → Dr. <?php echo e($appt['doctor_name']); ?>
                                    <span class="badge badge-<?php echo $appt['status']; ?>"><?php echo ucfirst($appt['status']); ?></span>
                                </div>
                                <div class="activity-time"><?php echo date('M d, g:i A', strtotime($appt['created_at'])); ?></div>
                            </div>
                            <div class="activity-details">
                                📅 <?php echo date('M d, Y', strtotime($appt['date'])); ?> at <?php echo date('g:i A', strtotime($appt['start_time'])); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>❌ Recent Cancellations</h2>
            </div>
            <?php if(empty($recent_cancelled)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <p>No recent cancellations</p>
                </div>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach($recent_cancelled as $cancel): ?>
                        <li class="activity-item">
                            <div class="activity-header">
                                <div class="activity-title"><?php echo e($cancel['patient_name']); ?></div>
                                <div class="activity-time"><?php echo date('M d', strtotime($cancel['cancelled_at'])); ?></div>
                            </div>
                            <div class="activity-details">
                                Dr. <?php echo e($cancel['doctor_name']); ?><br>
                                <?php if($cancel['cancel_reason']): ?>
                                    <span style="color: #ef4444; font-size: 12px;">
                                        "<?php echo e($cancel['cancel_reason']); ?>"
                                    </span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>