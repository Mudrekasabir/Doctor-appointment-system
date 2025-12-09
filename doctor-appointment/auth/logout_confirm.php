<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Confirm Logout</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f8fafc;
      display:flex;
      justify-content:center;
      align-items:center;
      height:100vh;
    }
    .card {
      background:#ffffff;
      padding:24px;
      border-radius:12px;
      box-shadow:0 8px 30px rgba(0,0,0,0.07);
      text-align:center;
      max-width:320px;
      width:100%;
    }
    .btn {
      padding:10px 16px;
      border-radius:8px;
      font-weight:bold;
      text-decoration:none;
      display:inline-block;
      margin:8px;
    }
    .btn-danger {
      background:#ef4444;
      color:#fff;
    }
    .btn-secondary {
      background:#e5e7eb;
      color:#111;
    }
  </style>
</head>
<body>
  <div class="card">
    <h2>Logout?</h2>
    <p>Are you sure you want to logout?</p>

    <a href="/doctor-appointment/auth/logout.php" class="btn btn-danger">Yes, Logout</a>
    <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
  </div>
</body>
</html>
