<?php
// admin/add_patient.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Add Patient</title>
  <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
  <style>
    .page-wrap {
      max-width: 800px;
      margin: 18px auto;
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
    input[type="number"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
      box-sizing: border-box;
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
    
    h3 {
      margin-top: 24px;
      margin-bottom: 12px;
      color: #374151;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">
  <h1>Add Patient</h1>
  <?php flash_render(); ?>
  
  <form method="post" action="add_patient_save.php">
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

    <h3>Optional Medical Details</h3>
    
    <label>
      <span>Age</span>
      <input type="number" name="age" min="0" max="150">
    </label>
    
    <label>
      <span>Blood Group</span>
      <input type="text" name="blood_group" placeholder="e.g., A+, B-, O+, AB+">
    </label>

    <div style="margin-top: 20px;">
      <button class="role-btn" type="submit">Create Patient</button>
      <a class="role-btn" href="manage_patients.php" style="background:#6b7280;">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>