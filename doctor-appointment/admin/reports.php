<?php
// admin/reports.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Totals
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_patients = $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
$total_doctors = $pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor'")->fetchColumn();
$active_doctors = $pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor' AND status='active'")->fetchColumn();
$pending_doctors = $pdo->query("SELECT COUNT(*) FROM doctors_profiles WHERE status='pending'")->fetchColumn();

// Appointments stats
$total_appointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$upcoming_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE date >= CURDATE() AND status IN ('booked', 'confirmed')")->fetchColumn();
$completed_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='completed'")->fetchColumn();
$cancelled_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='cancelled'")->fetchColumn();

// Revenue calculation
$revenue = $pdo->query("SELECT IFNULL(SUM(dp.fee),0) FROM appointments a JOIN doctors_profiles dp ON dp.user_id=a.doctor_id WHERE a.status IN ('completed')")->fetchColumn();
$potential_revenue = $pdo->query("SELECT IFNULL(SUM(dp.fee),0) FROM appointments a JOIN doctors_profiles dp ON dp.user_id=a.doctor_id WHERE a.status IN ('booked', 'confirmed')")->fetchColumn();

// Today's appointments
$today_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE date = CURDATE()")->fetchColumn();

// This month stats
$this_month_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())")->fetchColumn();
$this_month_revenue = $pdo->query("SELECT IFNULL(SUM(dp.fee),0) FROM appointments a JOIN doctors_profiles dp ON dp.user_id=a.doctor_id WHERE a.status='completed' AND MONTH(a.date) = MONTH(CURDATE()) AND YEAR(a.date) = YEAR(CURDATE())")->fetchColumn();

// Top doctors by appointments
$top_doctors = $pdo->query("
    SELECT u.full_name, dp.specialty, COUNT(a.id) as appointment_count
    FROM users u
    JOIN doctors_profiles dp ON dp.user_id = u.id
    LEFT JOIN appointments a ON a.doctor_id = u.id
    WHERE u.role = 'doctor'
    GROUP BY u.id
    ORDER BY appointment_count DESC
    LIMIT 5
")->fetchAll();

// Recent logs
$logs = $pdo->query("
    SELECT l.*, u.full_name AS actor_name 
    FROM logs l 
    LEFT JOIN users u ON u.id=l.actor_id 
    ORDER BY l.id DESC 
    LIMIT 20
")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Reports & Analytics</title>
  <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
  <style>
    .page-wrap {
      max-width: 1200px;
      margin: 20px auto;
      padding: 12px;
    }
    
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .section-header h1 {
      margin: 0;
      color: #1f2937;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      margin-bottom: 32px;
    }
    
    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      border-left: 4px solid #4a90e2;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
    
    .stat-card.green { border-left-color: #10b981; }
    .stat-card.blue { border-left-color: #3b82f6; }
    .stat-card.purple { border-left-color: #8b5cf6; }
    .stat-card.orange { border-left-color: #f59e0b; }
    .stat-card.red { border-left-color: #ef4444; }
    
    .stat-card h3 {
      margin: 0 0 8px 0;
      font-size: 14px;
      color: #6b7280;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .stat-value {
      font-size: 32px;
      font-weight: 700;
      color: #1f2937;
      margin: 8px 0;
    }
    
    .stat-label {
      font-size: 13px;
      color: #9ca3af;
    }
    
    .content-section {
      background: white;
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .role-btn {
      background: #4a90e2;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 500;
      transition: background 0.2s;
    }
    
    .role-btn:hover {
      background: #357abd;
    }
    
    .role-btn.secondary {
      background: #6b7280;
    }
    
    .role-btn.secondary:hover {
      background: #4b5563;
    }
    
    .export-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }
    
    .export-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 24px;
      border-radius: 12px;
      text-decoration: none;
      transition: transform 0.2s, box-shadow 0.2s;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      cursor: pointer;
    }
    
    .export-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    }
    
    .export-card:nth-child(1) {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .export-card:nth-child(2) {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    
    .export-card:nth-child(3) {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .export-card:nth-child(4) {
      background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
    
    .export-icon {
      font-size: 48px;
      margin-bottom: 12px;
    }
    
    .export-title {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 8px;
    }
    
    .export-desc {
      font-size: 13px;
      opacity: 0.9;
    }
    
    table.data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 16px;
    }
    
    table.data-table th {
      background: #f9fafb;
      padding: 12px;
      text-align: left;
      font-weight: 600;
      color: #374151;
      border-bottom: 2px solid #e5e7eb;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    table.data-table td {
      padding: 12px;
      border-bottom: 1px solid #e5e7eb;
      color: #4b5563;
      font-size: 14px;
    }
    
    table.data-table tbody tr:hover {
      background: #f9fafb;
    }
    
    .badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .badge.success { background: #d1fae5; color: #065f46; }
    .badge.warning { background: #fef3c7; color: #92400e; }
    .badge.danger { background: #fee2e2; color: #991b1b; }
    .badge.info { background: #dbeafe; color: #1e40af; }
    
    .top-doctors-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    .top-doctors-list li {
      padding: 12px;
      border-bottom: 1px solid #e5e7eb;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .top-doctors-list li:last-child {
      border-bottom: none;
    }
    
    .doctor-info {
      display: flex;
      flex-direction: column;
    }
    
    .doctor-name {
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 4px;
    }
    
    .doctor-specialty {
      font-size: 13px;
      color: #6b7280;
    }
    
    .appointment-count {
      background: #4a90e2;
      color: white;
      padding: 6px 16px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 14px;
    }
    
    .empty-state {
      text-align: center;
      padding: 40px;
      color: #9ca3af;
    }
    
    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .export-grid {
        grid-template-columns: 1fr;
      }
      
      .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">
  <div class="section-header">
    <h1>Reports & Analytics</h1>
    <a class="role-btn secondary" href="dashboard.php">← Back to Dashboard</a>
  </div>
  
  <?php flash_render(); ?>

  <!-- Export Options Section -->
  <div class="content-section">
    <h3 style="margin-top: 0; margin-bottom: 16px; color: #374151;">📥 Export Reports</h3>
    <div class="export-grid">
      <a class="export-card" href="reports_export.php?type=appointments">
        <div class="export-icon">📊</div>
        <div class="export-title">All Appointments</div>
        <div class="export-desc">Export complete appointment history</div>
      </a>
      
      <a class="export-card" href="reports_export.php?type=doctors">
        <div class="export-icon">👨‍⚕️</div>
        <div class="export-title">Doctors</div>
        <div class="export-desc">Export all doctor profiles & stats</div>
      </a>
      
      <a class="export-card" href="reports_export.php?type=patients">
        <div class="export-icon">👥</div>
        <div class="export-title">Patients</div>
        <div class="export-desc">Export patient list & records</div>
      </a>
      
      <a class="export-card" href="reports_export.php?type=revenue">
        <div class="export-icon">💰</div>
        <div class="export-title">Revenue Report</div>
        <div class="export-desc">Export financial analytics</div>
      </a>
    </div>
  </div>

  <!-- Key Metrics -->
  <h2 style="margin-bottom: 16px; color: #374151;">Overview</h2>
  <div class="stats-grid">
    <div class="stat-card blue">
      <h3>Total Users</h3>
      <div class="stat-value"><?php echo number_format($total_users); ?></div>
      <div class="stat-label">All registered users</div>
    </div>
    
    <div class="stat-card green">
      <h3>Patients</h3>
      <div class="stat-value"><?php echo number_format($total_patients); ?></div>
      <div class="stat-label">Registered patients</div>
    </div>
    
    <div class="stat-card purple">
      <h3>Active Doctors</h3>
      <div class="stat-value"><?php echo number_format($active_doctors); ?></div>
      <div class="stat-label"><?php echo $pending_doctors; ?> pending approval</div>
    </div>
    
    <div class="stat-card orange">
      <h3>Today's Appointments</h3>
      <div class="stat-value"><?php echo number_format($today_appointments); ?></div>
      <div class="stat-label">Scheduled for today</div>
    </div>
  </div>

  <!-- Appointments Stats -->
  <h2 style="margin-bottom: 16px; color: #374151;">Appointments</h2>
  <div class="stats-grid">
    <div class="stat-card blue">
      <h3>Total Appointments</h3>
      <div class="stat-value"><?php echo number_format($total_appointments); ?></div>
      <div class="stat-label">All time</div>
    </div>
    
    <div class="stat-card green">
      <h3>Upcoming</h3>
      <div class="stat-value"><?php echo number_format($upcoming_appointments); ?></div>
      <div class="stat-label">Booked & confirmed</div>
    </div>
    
    <div class="stat-card purple">
      <h3>Completed</h3>
      <div class="stat-value"><?php echo number_format($completed_appointments); ?></div>
      <div class="stat-label">Successfully finished</div>
    </div>
    
    <div class="stat-card red">
      <h3>Cancelled</h3>
      <div class="stat-value"><?php echo number_format($cancelled_appointments); ?></div>
      <div class="stat-label">Cancellation rate: <?php echo $total_appointments > 0 ? number_format(($cancelled_appointments/$total_appointments)*100, 1) : 0; ?>%</div>
    </div>
  </div>

  <!-- Revenue Stats -->
  <h2 style="margin-bottom: 16px; color: #374151;">Revenue</h2>
  <div class="stats-grid">
    <div class="stat-card green">
      <h3>Total Revenue</h3>
      <div class="stat-value">₹<?php echo number_format($revenue, 2); ?></div>
      <div class="stat-label">From completed appointments</div>
    </div>
    
    <div class="stat-card blue">
      <h3>Potential Revenue</h3>
      <div class="stat-value">₹<?php echo number_format($potential_revenue, 2); ?></div>
      <div class="stat-label">From upcoming bookings</div>
    </div>
    
    <div class="stat-card purple">
      <h3>This Month</h3>
      <div class="stat-value">₹<?php echo number_format($this_month_revenue, 2); ?></div>
      <div class="stat-label"><?php echo number_format($this_month_appointments); ?> appointments</div>
    </div>
    
    <div class="stat-card orange">
      <h3>Average per Visit</h3>
      <div class="stat-value">₹<?php echo $completed_appointments > 0 ? number_format($revenue / $completed_appointments, 2) : '0.00'; ?></div>
      <div class="stat-label">Per completed appointment</div>
    </div>
  </div>

  <!-- Top Doctors -->
  <div class="content-section">
    <h2 style="margin-top: 0; color: #1f2937;">Top Doctors by Appointments</h2>
    <?php if (empty($top_doctors)): ?>
      <div class="empty-state">
        <p>No doctor data available yet.</p>
      </div>
    <?php else: ?>
      <ul class="top-doctors-list">
        <?php foreach ($top_doctors as $doctor): ?>
          <li>
            <div class="doctor-info">
              <span class="doctor-name"><?php echo e($doctor['full_name']); ?></span>
              <span class="doctor-specialty"><?php echo e($doctor['specialty']); ?></span>
            </div>
            <span class="appointment-count"><?php echo number_format($doctor['appointment_count']); ?> appointments</span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Recent Activity Logs -->
  <div class="content-section">
    <h2 style="margin-top: 0; color: #1f2937;">Recent Activity Logs</h2>
    <?php if (empty($logs)): ?>
      <div class="empty-state">
        <p>No activity logs available.</p>
      </div>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>User</th>
            <th>Role</th>
            <th>Event</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?php echo e(date('M d, Y H:i', strtotime($log['created_at']))); ?></td>
              <td><?php echo e($log['actor_name'] ?? 'System'); ?></td>
              <td>
                <?php
                $role = $log['actor_role'];
                $badge_class = 'info';
                if ($role === 'admin') $badge_class = 'danger';
                elseif ($role === 'doctor') $badge_class = 'success';
                elseif ($role === 'patient') $badge_class = 'warning';
                ?>
                <span class="badge <?php echo $badge_class; ?>"><?php echo e($role); ?></span>
              </td>
              <td><?php echo e(str_replace('_', ' ', ucfirst($log['event_type']))); ?></td>
              <td><?php echo e($log['detail']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>