<?php
// C:\xampp\htdocs\doctor-appointment\doctor\profile.php
// Fixed to match actual database structure

session_start();

// Bootstrap DB connection
$incDbPath = __DIR__ . '/../inc/db.php';
if (file_exists($incDbPath)) {
    require_once $incDbPath;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    $dbHost = '127.0.0.1';
    $dbName = 'doctor_appointment';
    $dbUser = 'root';
    $dbPass = '';
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo "<h2>Database connection failed</h2>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        exit;
    }
}

// Validate and sanitize doctor_id
$doctor_id = null;
if (isset($_GET['doctor_id']) && is_numeric($_GET['doctor_id'])) {
    $doctor_id = (int) $_GET['doctor_id'];
} else {
    http_response_code(400);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
    <meta charset="utf-8">
    <title>Error - Invalid Doctor ID</title>
    <style>
      body {
        font-family: system-ui, -apple-system, sans-serif;
        padding: 20px;
        max-width: 600px;
        margin: 50px auto;
        background: #f5f5f5;
      }
      .error-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 40px;
        text-align: center;
      }
      .error-icon {
        font-size: 64px;
        color: #dc3545;
        margin-bottom: 20px;
      }
      h2 {
        color: #333;
        margin-bottom: 10px;
      }
      p {
        color: #666;
        line-height: 1.6;
      }
      .btn {
        display: inline-block;
        padding: 12px 24px;
        margin-top: 20px;
        background: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        transition: all 0.3s;
      }
      .btn:hover {
        background: #0056b3;
      }
      .debug-info {
        margin-top: 30px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 6px;
        text-align: left;
        font-size: 14px;
      }
    </style>
    </head>
    <body>
    <div class="error-container">
      <div class="error-icon">⚠️</div>
      <h2>Invalid Doctor Specified</h2>
      <p>Missing or invalid <code>doctor_id</code> in the request.</p>
      <p>Please access this page with a valid doctor ID.<br>
      Example: <code>profile.php?doctor_id=1</code></p>
      
      <a href="/doctor-appointment/" class="btn">← Go to Home</a>
      
      <?php if (isset($_GET) && !empty($_GET)): ?>
      <div class="debug-info">
        <strong>Debug Information:</strong><br>
        Received GET parameters:
        <pre><?php print_r($_GET); ?></pre>
      </div>
      <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Fetch doctor profile (join users + doctors_profiles)
try {
    $sql = "
        SELECT
            u.id AS user_id,
            u.full_name,
            u.email,
            u.contact,
            u.status AS user_status,
            dp.license_no,
            dp.experience,
            dp.specialty,
            dp.fee,
            dp.bio,
            dp.image,
            dp.status AS doctor_status
        FROM users u
        LEFT JOIN doctors_profiles dp ON dp.user_id = u.id
        WHERE u.id = :id AND u.role = 'doctor'
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $doctor_id]);
    $doctor = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    echo "<h2>Query error</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

if (!$doctor) {
    http_response_code(404);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
    <meta charset="utf-8">
    <title>Doctor Not Found</title>
    <style>
      body {
        font-family: system-ui, -apple-system, sans-serif;
        padding: 20px;
        max-width: 600px;
        margin: 50px auto;
        background: #f5f5f5;
      }
      .error-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 40px;
        text-align: center;
      }
      .error-icon {
        font-size: 64px;
        margin-bottom: 20px;
      }
      h2 {
        color: #333;
        margin-bottom: 10px;
      }
      p {
        color: #666;
        line-height: 1.6;
      }
      .btn {
        display: inline-block;
        padding: 12px 24px;
        margin-top: 20px;
        background: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        transition: all 0.3s;
      }
      .btn:hover {
        background: #0056b3;
      }
    </style>
    </head>
    <body>
    <div class="error-container">
      <div class="error-icon">🔍</div>
      <h2>Doctor Not Found</h2>
      <p>The requested doctor (ID: <?php echo htmlspecialchars($doctor_id); ?>) does not exist or is not available.</p>
      <a href="/doctor-appointment/" class="btn">← Back to Home</a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Fetch availability from doctor_available_times
try {
    $sqlTimes = "
        SELECT id, doctor_id, day_of_week, date_for, start_time, end_time, repeat_weekly
        FROM doctor_available_times
        WHERE doctor_id = :id
        ORDER BY
            (date_for IS NOT NULL) DESC,
            day_of_week ASC,
            start_time ASC
    ";
    $stmt2 = $pdo->prepare($sqlTimes);
    $stmt2->execute([':id' => $doctor_id]);
    $availability = $stmt2->fetchAll();
} catch (PDOException $e) {
    // If doctor_available_times fails, try doctor_date_availability
    try {
        $sqlTimes = "
            SELECT id, doctor_id, availability_date, start_time, end_time, is_available
            FROM doctor_date_availability
            WHERE doctor_id = :id AND is_available = 1
            ORDER BY availability_date ASC, start_time ASC
        ";
        $stmt2 = $pdo->prepare($sqlTimes);
        $stmt2->execute([':id' => $doctor_id]);
        $availability = $stmt2->fetchAll();
    } catch (PDOException $e2) {
        error_log("Availability query error: " . $e->getMessage());
        $availability = [];
    }
}

// Helper: readable day_of_week
$dayMap = [
    '0' => 'Sunday',
    '1' => 'Monday',
    '2' => 'Tuesday',
    '3' => 'Wednesday',
    '4' => 'Thursday',
    '5' => 'Friday',
    '6' => 'Saturday',
];

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Doctor Profile — <?php echo htmlspecialchars($doctor['full_name']); ?></title>
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
  
  body {
    font-family: system-ui, -apple-system, sans-serif;
    padding: 20px;
    background: #f5f5f5;
    line-height: 1.6;
  }
  
  .container {
    max-width: 1000px;
    margin: 0 auto;
  }
  
  .profile-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 20px;
  }
  
  .profile-header {
    display: flex;
    gap: 25px;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
  }
  
  .profile-image {
    width: 150px;
    height: 150px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
  }
  
  .profile-image-placeholder {
    width: 150px;
    height: 150px;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
    font-weight: bold;
    flex-shrink: 0;
  }
  
  .profile-info {
    flex: 1;
  }
  
  h1 {
    margin: 0 0 8px 0;
    color: #333;
    font-size: 28px;
  }
  
  .specialty {
    color: #666;
    font-size: 18px;
    margin: 5px 0 15px 0;
  }
  
  .badges {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
  }
  
  .badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
  }
  
  .badge.active {
    background: #d4edda;
    color: #155724;
  }
  
  .badge.approved {
    background: #cce5ff;
    color: #004085;
  }
  
  .badge.pending {
    background: #fff3cd;
    color: #856404;
  }
  
  .meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    background: #f9f9f9;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 30px;
  }
  
  .meta-item {
    display: flex;
    align-items: center;
  }
  
  .meta-item strong {
    min-width: 130px;
    color: #555;
  }
  
  .meta-item span {
    color: #333;
  }
  
  h2 {
    color: #333;
    margin-top: 30px;
    margin-bottom: 15px;
    font-size: 22px;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 8px;
  }
  
  table {
    border-collapse: collapse;
    width: 100%;
    background: white;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  }
  
  th, td {
    padding: 12px;
    text-align: left;
  }
  
  th {
    background: #f4f4f4;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #e0e0e0;
  }
  
  td {
    border-bottom: 1px solid #e0e0e0;
  }
  
  tr:last-child td {
    border-bottom: none;
  }
  
  tbody tr:hover {
    background: #f9f9f9;
  }
  
  .no-availability {
    background: #fff3cd;
    padding: 20px;
    border-radius: 6px;
    color: #856404;
    text-align: center;
  }
  
  .bio-section {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 6px;
    line-height: 1.6;
    color: #444;
  }
  
  .actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }
  
  .btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    font-size: 16px;
  }
  
  .btn-primary {
    background: #007bff;
    color: white;
  }
  
  .btn-primary:hover {
    background: #0056b3;
  }
  
  .btn-secondary {
    background: #6c757d;
    color: white;
  }
  
  .btn-secondary:hover {
    background: #545b62;
  }
  
  /* Responsive */
  @media (max-width: 768px) {
    .profile-header {
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    
    .meta-grid {
      grid-template-columns: 1fr;
    }
    
    table {
      font-size: 14px;
    }
    
    th, td {
      padding: 8px;
    }
    
    .actions {
      flex-direction: column;
    }
    
    .btn {
      width: 100%;
      text-align: center;
    }
  }
</style>
</head>
<body>

<div class="container">
  <div class="profile-container">
    <div class="profile-header">
      <?php if (!empty($doctor['image'])): ?>
        <img class="profile-image" src="/doctor-appointment/<?php echo htmlspecialchars($doctor['image']); ?>" alt="<?php echo htmlspecialchars($doctor['full_name']); ?>">
      <?php else: ?>
        <div class="profile-image-placeholder">
          <?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?>
        </div>
      <?php endif; ?>
      
      <div class="profile-info">
        <h1><?php echo htmlspecialchars($doctor['full_name']); ?></h1>
        <div class="specialty"><?php echo htmlspecialchars($doctor['specialty'] ?? 'General Practitioner'); ?></div>
        
        <div class="badges">
          <span class="badge active"><?php echo htmlspecialchars($doctor['user_status'] ?? 'active'); ?></span>
          <?php if (!empty($doctor['doctor_status'])): ?>
            <span class="badge <?php echo ($doctor['doctor_status'] === 'approved') ? 'approved' : 'pending'; ?>">
              <?php echo htmlspecialchars($doctor['doctor_status']); ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="meta-grid">
      <div class="meta-item">
        <strong>Experience:</strong>
        <span><?php echo isset($doctor['experience']) ? ((int)$doctor['experience'] . ' years') : 'N/A'; ?></span>
      </div>
      <div class="meta-item">
        <strong>Consultation Fee:</strong>
        <span>₹<?php echo isset($doctor['fee']) ? number_format($doctor['fee'], 2) : '0.00'; ?></span>
      </div>
      <div class="meta-item">
        <strong>License No:</strong>
        <span><?php echo htmlspecialchars($doctor['license_no'] ?? 'N/A'); ?></span>
      </div>
      <div class="meta-item">
        <strong>Contact:</strong>
        <span><?php echo htmlspecialchars($doctor['contact'] ?? 'N/A'); ?></span>
      </div>
      <div class="meta-item">
        <strong>Email:</strong>
        <span><?php echo htmlspecialchars($doctor['email'] ?? 'N/A'); ?></span>
      </div>
    </div>

    <?php if (!empty($doctor['bio'])): ?>
      <h2>About</h2>
      <div class="bio-section">
        <?php echo nl2br(htmlspecialchars($doctor['bio'])); ?>
      </div>
    <?php endif; ?>

    <h2>Availability Schedule</h2>

    <?php if (empty($availability)): ?>
      <div class="no-availability">
        No availability schedule set for this doctor yet.
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:50px;">#</th>
            <th>Day / Date</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th style="width:120px;">Repeats Weekly</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($availability as $i => $slot): ?>
            <?php
              $displayDay = 'Not specified';
              
              // Check for date_for (specific date)
              if (!empty($slot['date_for'])) {
                  $displayDay = date('D, M j, Y', strtotime($slot['date_for']));
              }
              // Check for availability_date (from doctor_date_availability)
              elseif (!empty($slot['availability_date'])) {
                  $displayDay = date('D, M j, Y', strtotime($slot['availability_date']));
              }
              // Check for day_of_week (recurring)
              elseif (isset($slot['day_of_week']) && $slot['day_of_week'] !== null) {
                  $key = (string)$slot['day_of_week'];
                  $displayDay = $dayMap[$key] ?? ("Day " . htmlspecialchars($slot['day_of_week']));
              }

              $start = $slot['start_time'] ?? '';
              $end = $slot['end_time'] ?? '';
              
              // Format times
              if ($start) $start = date('g:i A', strtotime($start));
              if ($end) $end = date('g:i A', strtotime($end));
              
              // Check repeat_weekly
              $repeats = (isset($slot['repeat_weekly']) && (int)$slot['repeat_weekly']) ? 'Yes' : 'No';
            ?>
            <tr>
              <td><?php echo $i + 1; ?></td>
              <td><strong><?php echo htmlspecialchars($displayDay); ?></strong></td>
              <td><?php echo htmlspecialchars($start ?: 'N/A'); ?></td>
              <td><?php echo htmlspecialchars($end ?: 'N/A'); ?></td>
              <td><?php echo htmlspecialchars($repeats); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div class="actions">
      <a href="javascript:history.back()" class="btn btn-secondary">← Back</a>
      <a href="/doctor-appointment/patient/book_appointment.php?doctor_id=<?php echo $doctor_id; ?>" class="btn btn-primary">Book Appointment</a>
    </div>
  </div>
</div>

</body>
</html>