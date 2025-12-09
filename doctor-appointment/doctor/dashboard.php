<?php
// doctor/dashboard.php — Doctor Home Dashboard (date-picker + appointments by date + hamburger quick actions)
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$doctor_id = current_user_id();

// fetch doctor info
$infoQ = $pdo->prepare("SELECT u.full_name, COALESCE(dp.specialty,'') AS specialty FROM users u LEFT JOIN doctors_profiles dp ON dp.user_id=u.id WHERE u.id=?");
$infoQ->execute([$doctor_id]);
$doc = $infoQ->fetch();

// notification count
$ncQ = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$ncQ->execute([$doctor_id]);
$notif_count = (int)$ncQ->fetchColumn();

// which date? default today or GET param
$sel_date = $_GET['date'] ?? date('Y-m-d');

// fetch appointments for selected date
$apptQ = $pdo->prepare("
    SELECT a.id, a.patient_id, a.start_time, a.end_time, a.status, a.cancel_reason,
           u.full_name AS patient_name
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    WHERE a.doctor_id = ? AND a.date = ?
    ORDER BY a.start_time
");
$apptQ->execute([$doctor_id, $sel_date]);
$appts = $apptQ->fetchAll();

// Get statistics
$statsQ = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM appointments 
    WHERE doctor_id = ? AND date = ?
");
$statsQ->execute([$doctor_id, $sel_date]);
$stats = $statsQ->fetch();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Dashboard</title>
<link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
  
  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
  }
  
  .dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
  }
  
  /* Header Section */
  .dashboard-header {
    background: white;
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    animation: slideDown 0.5s ease-out;
  }
  
  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  .doctor-info h1 {
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 8px;
  }
  
  .specialty-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
  }
  
  .header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
  }
  
  .icon-btn {
    position: relative;
    background: #f3f4f6;
    border: none;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    text-decoration: none;
  }
  
  .icon-btn:hover {
    background: #e5e7eb;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }
  
  .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    min-width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
    animation: pulse 2s infinite;
  }
  
  @keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
  }
  
  .hamburger-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 22px;
    transition: all 0.3s ease;
  }
  
  .hamburger-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
  }
  
  .hamburger-container {
    position: relative;
  }
  
  .hamburger-menu {
    position: absolute;
    right: 0;
    top: 60px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    padding: 8px;
    min-width: 220px;
    z-index: 1000;
    animation: menuSlide 0.3s ease-out;
  }
  
  @keyframes menuSlide {
    from {
      opacity: 0;
      transform: translateY(-10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  .hamburger-menu[aria-hidden="true"] {
    display: none;
  }
  
  .hamburger-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: #374151;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s;
  }
  
  .hamburger-menu a:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
  }
  
  /* Stats Cards */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
    animation: fadeIn 0.6s ease-out 0.2s both;
  }
  
  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  .stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
  }
  
  .stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  }
  
  .stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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
  }
  
  .stat-card.total .stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  }
  
  .stat-card.pending .stat-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  }
  
  .stat-card.confirmed .stat-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  }
  
  .stat-card.completed .stat-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  }
  
  .stat-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
    margin-bottom: 4px;
  }
  
  .stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
  }
  
  /* Date Picker Section */
  .date-picker-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    animation: fadeIn 0.6s ease-out 0.3s both;
  }
  
  .date-picker-content {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
  }
  
  .date-input-group {
    display: flex;
    gap: 12px;
    align-items: center;
  }
  
  .date-input-group label {
    font-weight: 600;
    color: #374151;
  }
  
  input[type="date"] {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    font-family: inherit;
    transition: all 0.3s ease;
  }
  
  input[type="date"]:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
  }
  
  .load-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
  }
  
  .load-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
  }
  
  .date-label {
    color: #6b7280;
    font-size: 15px;
  }
  
  .date-label strong {
    color: #1f2937;
  }
  
  /* Appointments Section */
  .appointments-card {
    background: white;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    animation: fadeIn 0.6s ease-out 0.4s both;
  }
  
  .appointments-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
  }
  
  .appointments-header h2 {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
  }
  
  .appointments-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
  }
  
  .appointments-table thead th {
    text-align: left;
    padding: 12px 16px;
    font-weight: 600;
    color: #6b7280;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  
  .appointments-table tbody tr {
    background: #f9fafb;
    transition: all 0.3s ease;
  }
  
  .appointments-table tbody tr:hover {
    background: #f3f4f6;
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }
  
  .appointments-table tbody td {
    padding: 16px;
    border-top: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
  }
  
  .appointments-table tbody td:first-child {
    border-left: 1px solid #e5e7eb;
    border-top-left-radius: 10px;
    border-bottom-left-radius: 10px;
  }
  
  .appointments-table tbody td:last-child {
    border-right: 1px solid #e5e7eb;
    border-top-right-radius: 10px;
    border-bottom-right-radius: 10px;
  }
  
  .time-slot {
    font-weight: 600;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .time-slot::before {
    content: '🕐';
  }
  
  .patient-name {
    font-weight: 500;
    color: #374151;
  }
  
  .status {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
  }
  
  .status.pending {
    background: #fef3c7;
    color: #92400e;
  }
  
  .status.confirmed {
    background: #d1fae5;
    color: #065f46;
  }
  
  .status.completed {
    background: #dbeafe;
    color: #1e40af;
  }
  
  .status.cancelled {
    background: #fee2e2;
    color: #991b1b;
  }
  
  .view-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    display: inline-block;
    transition: all 0.3s ease;
  }
  
  .view-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
  }
  
  .no-appointments {
    text-align: center;
    padding: 64px 32px;
    color: #9ca3af;
  }
  
  .no-appointments-icon {
    font-size: 64px;
    margin-bottom: 16px;
  }
  
  .no-appointments p {
    font-size: 18px;
    font-weight: 500;
  }
  
  @media (max-width: 768px) {
    .dashboard-header {
      flex-direction: column;
      gap: 20px;
      align-items: flex-start;
    }
    
    .header-actions {
      width: 100%;
      justify-content: flex-end;
    }
    
    .stats-grid {
      grid-template-columns: 1fr;
    }
    
    .date-picker-content {
      flex-direction: column;
      align-items: flex-start;
    }
    
    .appointments-table {
      font-size: 14px;
    }
    
    .appointments-table tbody td {
      padding: 12px;
    }
  }
</style>
<script defer src="/doctor-appointment/assets/js/doctor_ui.js"></script>
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="dashboard-container">
  
  <!-- Header Section -->
  <div class="dashboard-header">
    <div class="doctor-info">
      <h1>👨‍⚕️ Dr. <?php echo e($doc['full_name'] ?: 'Doctor'); ?></h1>
      <span class="specialty-badge">
        <span>🩺</span>
        <span><?php echo e($doc['specialty'] ?: 'General Practice'); ?></span>
      </span>
    </div>

    <div class="header-actions">
      <a id="notif-link" href="/doctor-appointment/doctor/notifications.php" class="icon-btn" title="Notifications">
        🔔
        <?php if($notif_count > 0): ?>
          <span class='badge'><?php echo $notif_count; ?></span>
        <?php endif; ?>
      </a>

      <!-- Hamburger Menu -->
      <div class="hamburger-container">
        <button id="hamburger-btn" class="hamburger-btn" aria-label="Menu" title="Quick Actions">
          ☰
        </button>
        <div id="hamburger-menu" class="hamburger-menu" aria-hidden="true">
          <a href="/doctor-appointment/doctor/availability.php">
            <span>📅</span>
            <span>Manage Availability</span>
          </a>
          <a href="/doctor-appointment/doctor/dayoff.php">
            <span>🏖️</span>
            <span>Mark Day Off</span>
          </a>
          <a href="/doctor-appointment/doctor/profile_edit.php">
            <span>✏️</span>
            <span>Edit Profile</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="stats-grid">
    <div class="stat-card total">
      <div class="stat-header">
        <div>
          <div class="stat-label">Total Appointments</div>
          <div class="stat-value"><?php echo $stats['total']; ?></div>
        </div>
        <div class="stat-icon">📊</div>
      </div>
    </div>

    <div class="stat-card pending">
      <div class="stat-header">
        <div>
          <div class="stat-label">Pending</div>
          <div class="stat-value"><?php echo $stats['pending']; ?></div>
        </div>
        <div class="stat-icon">⏳</div>
      </div>
    </div>

    <div class="stat-card confirmed">
      <div class="stat-header">
        <div>
          <div class="stat-label">Confirmed</div>
          <div class="stat-value"><?php echo $stats['confirmed']; ?></div>
        </div>
        <div class="stat-icon">✅</div>
      </div>
    </div>

    <div class="stat-card completed">
      <div class="stat-header">
        <div>
          <div class="stat-label">Completed</div>
          <div class="stat-value"><?php echo $stats['completed']; ?></div>
        </div>
        <div class="stat-icon">✔️</div>
      </div>
    </div>
  </div>

  <!-- Date Picker -->
  <div class="date-picker-card">
    <div class="date-picker-content">
      <div class="date-input-group">
        <label for="date-picker">📅 Select Date:</label>
        <input id="date-picker" type="date" value="<?php echo e($sel_date); ?>">
        <button id="load-btn" class="load-btn" type="button">Load Appointments</button>
      </div>
      <div class="date-label">
        Viewing appointments for <strong id="date-label"><?php echo date('F j, Y', strtotime($sel_date)); ?></strong>
      </div>
    </div>
  </div>

  <!-- Appointments Table -->
  <div class="appointments-card">
    <div class="appointments-header">
      <h2>Today's Appointments</h2>
    </div>

    <div id="appointments-area">
      <?php if(empty($appts)): ?>
        <div class="no-appointments">
          <div class="no-appointments-icon">📭</div>
          <p>No appointments scheduled for <?php echo date('F j, Y', strtotime($sel_date)); ?></p>
        </div>
      <?php else: ?>
        <table class="appointments-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Patient</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($appts as $a): ?>
            <tr>
              <td>
                <div class="time-slot">
                  <?php echo e(date('g:i A', strtotime($a['start_time']))); ?> - <?php echo e(date('g:i A', strtotime($a['end_time']))); ?>
                </div>
              </td>
              <td>
                <div class="patient-name"><?php echo e($a['patient_name']); ?></div>
              </td>
              <td>
                <span class="status <?php echo e($a['status']); ?>">
                  <?php echo e(ucfirst($a['status'])); ?>
                </span>
              </td>
              <td>
                <a class="view-btn" href="/doctor-appointment/doctor/patient_view.php?patient_id=<?php echo (int)$a['patient_id']; ?>&appointment_id=<?php echo (int)$a['id']; ?>">
                  View Details
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
// Hamburger menu toggle
document.getElementById('hamburger-btn').addEventListener('click', function(e) {
  e.stopPropagation();
  const menu = document.getElementById('hamburger-menu');
  const isHidden = menu.getAttribute('aria-hidden') === 'true';
  menu.setAttribute('aria-hidden', isHidden ? 'false' : 'true');
});

// Close menu when clicking outside
document.addEventListener('click', function(e) {
  const menu = document.getElementById('hamburger-menu');
  const btn = document.getElementById('hamburger-btn');
  
  if (!menu.contains(e.target) && e.target !== btn) {
    menu.setAttribute('aria-hidden', 'true');
  }
});

// Date picker load functionality
document.getElementById('load-btn').addEventListener('click', function() {
  const selectedDate = document.getElementById('date-picker').value;
  if (selectedDate) {
    this.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite;">⏳</span> Loading...';
    this.disabled = true;
    window.location.href = '?date=' + selectedDate;
  }
});

// Allow Enter key to load date
document.getElementById('date-picker').addEventListener('keypress', function(e) {
  if (e.key === 'Enter') {
    document.getElementById('load-btn').click();
  }
});
</script>

<style>
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
</style>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>