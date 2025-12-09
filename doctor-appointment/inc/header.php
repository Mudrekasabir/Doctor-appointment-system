<?php
// inc/header.php — common header + sidebars for patient/admin/doctor
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';

// unread notifications
$unread = 0;
if (!empty($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$uid]);
    $unread = (int)$stmt->fetchColumn();
}

$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? 'User';
?>

<!-- Header -->
<header style="background:#ffffff;border-bottom:2px solid #e8f4f8;padding:15px 30px;
                display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:50;">
  <div style="display:flex;align-items:center;gap:12px;">
    <div style="width:40px;height:40px;background:#0066cc;border-radius:8px;display:flex;
                align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:20px;">
      DAS
    </div>
    <div>
      <div style="font-weight:700;font-size:18px;color:#1a1a1a;line-height:1.2;">
        Doctor Appointment System
      </div>
      <div style="font-size:12px;color:#666;margin-top:2px;">
        <?php 
        if($role === 'patient') echo 'Patient Portal';
        else if($role === 'doctor') echo 'Doctor Portal';
        else if($role === 'admin') echo 'Admin Portal';
        ?>
      </div>
    </div>
  </div>

  <?php if($role): ?>
  <div style="display:flex;align-items:center;gap:20px;">
    
    <!-- User Info -->
    <div style="text-align:right;display:none;" class="user-info-desktop">
      <div style="font-size:14px;font-weight:600;color:#1a1a1a;">
        <?php echo htmlspecialchars($username); ?>
      </div>
      <div style="font-size:12px;color:#666;text-transform:capitalize;">
        <?php echo htmlspecialchars($role); ?>
      </div>
    </div>

    <!-- Notification Bell -->
    <div style="position:relative;cursor:pointer;padding:8px;border-radius:8px;
                background:#f8f9fa;transition:background 0.2s;" 
         id="notif-bell"
         onmouseover="this.style.background='#e9ecef'"
         onmouseout="this.style.background='#f8f9fa'">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
      </svg>
      <?php if($unread > 0): ?>
        <span id="notif-count"
              style="position:absolute;top:2px;right:2px;background:#dc3545;color:#fff;
                     padding:2px 6px;font-size:10px;border-radius:10px;font-weight:700;
                     min-width:18px;text-align:center;">
          <?php echo $unread; ?>
        </span>
      <?php endif; ?>
    </div>

    <!-- Logout Button -->
    <a href="/doctor-appointment/auth/logout_confirm.php"
       style="background:#dc3545;color:#fff;padding:8px 20px;border-radius:6px;
              text-decoration:none;font-size:14px;font-weight:500;
              transition:background 0.2s;"
       onmouseover="this.style.background='#c82333'"
       onmouseout="this.style.background='#dc3545'">
       Logout
    </a>
  </div>
  <?php endif; ?>
</header>


<!-- SIDEBAR MENUS -->
<?php if($role === 'patient'): ?>
    <aside class="sidebar-menu">
      <div class="sidebar-header">Patient Menu</div>
      <a href="/doctor-appointment/patient/dashboard.php">
        <span class="menu-icon">🏠</span> Dashboard
      </a>
      <a href="/doctor-appointment/patient/appointments.php">
        <span class="menu-icon">📅</span> My Appointments
      </a>
      <a href="/doctor-appointment/patient/profile_edit.php">
        <span class="menu-icon">👤</span> Medical Details
      </a>
    </aside>
<?php endif; ?>

<?php if($role === 'admin'): ?>
    <aside class="sidebar-menu">
      <div class="sidebar-header">Admin Menu</div>
      <a href="/doctor-appointment/admin/dashboard.php">
        <span class="menu-icon">🏠</span> Dashboard
      </a>
      <a href="/doctor-appointment/admin/requests.php">
        <span class="menu-icon">📋</span> Doctor Requests
      </a>
      <a href="/doctor-appointment/admin/manage_doctors.php">
        <span class="menu-icon">👨‍⚕️</span> Manage Doctors
      </a>
      <a href="/doctor-appointment/admin/manage_patients.php">
        <span class="menu-icon">🧑‍🤝‍🧑</span> Manage Patients
      </a>
      <a href="/doctor-appointment/admin/appointments.php">
        <span class="menu-icon">📅</span> Appointments
      </a>
      <a href="/doctor-appointment/admin/reports.php">
        <span class="menu-icon">📊</span> Reports
      </a>
    </aside>
<?php endif; ?>

<?php if($role === 'doctor'): ?>
    <aside class="sidebar-menu">
      <div class="sidebar-header">Doctor Menu</div>
      <a href="/doctor-appointment/doctor/dashboard.php">
        <span class="menu-icon">🏠</span> Dashboard
      </a>
      <a href="/doctor-appointment/doctor/appointments.php">
        <span class="menu-icon">📅</span> My Appointments
      </a>
      <a href="/doctor-appointment/doctor/profile_edit.php">
        <span class="menu-icon">👤</span> Edit Profile
      </a>
      <a href="/doctor-appointment/doctor/availability.php">
        <span class="menu-icon">🕐</span> Availability
      </a>
    </aside>
<?php endif; ?>


<style>
/* Sidebar styling */
.sidebar-menu {
    width: 220px;
    position: fixed;
    top: 80px;
    left: 0;
    background:#ffffff;
    border-right:1px solid #e8f4f8;
    height: calc(100vh - 80px);
    display:flex;
    flex-direction:column;
    padding:20px 0;
    z-index:30;
    overflow-y: auto;
}

.sidebar-header {
    padding:0 20px 15px 20px;
    font-size:12px;
    font-weight:700;
    color:#666;
    text-transform:uppercase;
    letter-spacing:0.5px;
    border-bottom:1px solid #e8f4f8;
    margin-bottom:10px;
}

.sidebar-menu a {
    padding:12px 20px;
    text-decoration:none;
    color:#333;
    font-size:15px;
    display:flex;
    align-items:center;
    gap:10px;
    transition: all 0.2s;
    border-left:3px solid transparent;
}

.sidebar-menu a:hover {
    background:#f8f9fa;
    border-left-color:#0066cc;
}

.sidebar-menu a.active {
    background:#e8f4f8;
    color:#0066cc;
    font-weight:600;
    border-left-color:#0066cc;
}

.menu-icon {
    font-size:18px;
    width:24px;
    text-align:center;
}

/* Page content wrapper */
.page-wrap {
    margin-left:240px !important;
    padding: 30px;
    background:#f8f9fa;
    min-height:calc(100vh - 80px);
}

/* Mobile responsive */
@media (max-width: 768px) {
    .sidebar-menu {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar-menu.mobile-open {
        transform: translateX(0);
    }
    
    .page-wrap {
        margin-left: 0 !important;
    }
    
    .user-info-desktop {
        display:none !important;
    }
}
</style>

<script>
document.addEventListener("DOMContentLoaded",function(){
  // Notification bell click
  var bell = document.getElementById("notif-bell");
  if(bell){
    bell.addEventListener("click",function(){
      window.location.href="/doctor-appointment/notifications.php";
    });
  }
  
  // Highlight active menu item
  var currentPath = window.location.pathname;
  var sidebarLinks = document.querySelectorAll('.sidebar-menu a');
  sidebarLinks.forEach(function(link) {
    var href = link.getAttribute('href');
    if (href && currentPath.includes(href)) {
      link.classList.add('active');
    }
  });
});
</script>