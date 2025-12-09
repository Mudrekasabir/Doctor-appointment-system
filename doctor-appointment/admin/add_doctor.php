<?php
// admin/add_doctor.php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/functions.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Add Doctor</title>
  <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
  <style>
    .page-wrap {
      max-width: 900px;
      margin: 22px auto;
      padding: 12px;
    }
    
    label {
      display: block;
      margin-bottom: 16px;
    }
    
    label span {
      display: block;
      margin-bottom: 6px;
      font-weight: 500;
    }
    
    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="number"],
    input[type="file"],
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
      box-sizing: border-box;
    }
    
    textarea {
      min-height: 80px;
      resize: vertical;
    }
    
    .day-row { 
      display: flex; 
      gap: 8px; 
      align-items: center; 
      margin-bottom: 8px; 
    }
    
    .small {
      width: 90px;
    }
    
    .day-checkbox { 
      width: 18px; 
      height: 18px; 
    }
    
    .time-input { 
      padding: 6px; 
      border-radius: 6px; 
      border: 1px solid #ddd;
      width: 100%;
    }
    
    .avail-box { 
      background: rgba(30,60,90,0.03); 
      padding: 16px; 
      border-radius: 8px; 
      margin-top: 16px; 
      margin-bottom: 16px;
    }
    
    .role-btn {
      background: #4a90e2;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      margin-right: 8px;
    }
    
    .role-btn:hover {
      background: #357abd;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>
<div class="page-wrap">
  <h1>Add Doctor (Admin)</h1>
  <?php flash_render(); ?>
  <form method="post" action="add_doctor_save.php" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    
    <label>
      <span>Username</span>
      <input type="text" name="username" required>
    </label>
    
    <label>
      <span>Full Name</span>
      <input type="text" name="full_name" required>
    </label>
    
    <label>
      <span>Email</span>
      <input type="email" name="email" required>
    </label>
    
    <label>
      <span>Contact</span>
      <input type="text" name="contact" required>
    </label>
    
    <label>
      <span>Password</span>
      <input type="password" name="password" required minlength="6">
    </label>
    
    <label>
      <span>Medical License Number</span>
      <input type="text" name="license_no" required>
    </label>
    
    <label>
      <span>Years of Experience</span>
      <input type="number" name="experience" min="0" value="0">
    </label>
    
    <label>
      <span>Specialty</span>
      <input type="text" name="specialty" required placeholder="e.g., Cardiology, Dermatology">
    </label>
    
    <label>
      <span>Consultation Fee (₹)</span>
      <input type="number" name="fee" min="0" step="0.01" required>
    </label>
    
    <label>
      <span>Short Bio</span>
      <textarea name="bio" placeholder="Brief description about the doctor..."></textarea>
    </label>
    
    <label>
      <span>Profile Image (JPG/PNG, max 2MB)</span>
      <input type="file" name="image" accept="image/jpeg,image/png">
    </label>

    <div class="avail-box">
      <h3>Weekly Availability (check day, then set start/end)</h3>
      <?php
        $days = ['0'=>'Sunday','1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday'];
        foreach($days as $k=>$d):
      ?>
        <div class="day-row">
          <label style="width:120px;display:flex;align-items:center;gap:6px;">
            <input type="checkbox" class="day-toggle" data-day="<?php echo $k?>" name="day_<?php echo $k?>_enabled"> 
            <?php echo $d?>
          </label>
          <label class="small">
            Start 
            <input class="time-input" type="time" name="day_<?php echo $k?>_start" disabled>
          </label>
          <label class="small">
            End 
            <input class="time-input" type="time" name="day_<?php echo $k?>_end" disabled>
          </label>
        </div>
      <?php endforeach; ?>
      <p style="font-size:13px;color:#555;margin-top:12px;">You may add multiple ranges later via doctor profile (this is a single default range per selected day).</p>
    </div>

    <div style="margin-top:20px">
      <button class="role-btn" type="submit">Create Doctor</button>
      <a class="role-btn" href="manage_doctors.php" style="background:#6b7280">Cancel</a>
    </div>
  </form>
</div>

<script>
document.querySelectorAll('.day-toggle').forEach(function(ch){
  ch.addEventListener('change', function(){
    var day = ch.getAttribute('data-day');
    var start = document.querySelector('input[name="day_'+day+'_start"]');
    var end = document.querySelector('input[name="day_'+day+'_end"]');
    if (ch.checked) { 
      start.disabled = false; 
      end.disabled = false; 
    } else { 
      start.disabled = true; 
      end.disabled = true; 
      start.value=''; 
      end.value=''; 
    }
  });
});
</script>
</body>
</html>