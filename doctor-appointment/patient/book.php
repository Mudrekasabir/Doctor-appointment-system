<?php
// patient/book.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/functions.php';

$doctor_id = intval($_GET['doctor_id'] ?? 0);
if ($doctor_id <= 0) { 
    echo "Invalid doctor id"; 
    exit; 
}

$stmt = $pdo->prepare("SELECT u.id,u.full_name,dp.specialty,dp.fee,dp.image FROM users u JOIN doctors_profiles dp ON u.id=dp.user_id WHERE u.id=? LIMIT 1");
$stmt->execute([$doctor_id]);
$doc = $stmt->fetch();
if (!$doc) { 
    echo "Doctor not found"; 
    exit; 
}

// Set minimum date to today
$today = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book Appointment with <?php echo htmlspecialchars($doc['full_name'], ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      background: #f8f9fa;
      min-height: 100vh;
    }
    
    .page-wrap {
      margin-left: 240px;
      padding: 30px;
      max-width: 1000px;
    }
    
    .page-header {
      background: white;
      padding: 30px;
      border-radius: 12px;
      margin-bottom: 25px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .page-header h1 {
      font-size: 28px;
      color: #1a1a1a;
      margin-bottom: 8px;
      font-weight: 700;
    }
    
    .doctor-card {
      background: white;
      padding: 35px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      margin-bottom: 25px;
    }
    
    .doctor-info {
      display: flex;
      gap: 25px;
      margin-bottom: 35px;
      padding-bottom: 25px;
      border-bottom: 2px solid #e8f4f8;
    }
    
    .doctor-image {
      width: 180px;
      height: 180px;
      object-fit: cover;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      flex-shrink: 0;
    }
    
    .doctor-details {
      flex: 1;
    }
    
    .doctor-name {
      font-size: 24px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 8px;
    }
    
    .doctor-specialty {
      color: #0066cc;
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 15px;
    }
    
    .doctor-fee {
      font-size: 18px;
      color: #333;
      margin-top: 12px;
    }
    
    .fee-amount {
      font-weight: 700;
      color: #0066cc;
    }
    
    .booking-section {
      margin-top: 30px;
    }
    
    .section-title {
      font-size: 18px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 20px;
    }
    
    .date-selector {
      margin-bottom: 25px;
    }
    
    .date-selector label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    
    .date-selector input[type="date"] {
      width: 100%;
      max-width: 300px;
      padding: 12px 16px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.2s;
    }
    
    .date-selector input[type="date"]:focus {
      outline: none;
      border-color: #0066cc;
      box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
    }
    
    .slots-container {
      margin-top: 25px;
    }
    
    .slots-title {
      font-size: 16px;
      font-weight: 600;
      color: #333;
      margin-bottom: 15px;
    }
    
    #slots {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 12px;
      min-height: 50px;
    }
    
    #slots.empty {
      display: flex;
      align-items: center;
      justify-content: center;
      color: #999;
      font-style: italic;
    }
    
    .slot-btn {
      padding: 12px 16px;
      background: #f8f9fa;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      color: #333;
      cursor: pointer;
      transition: all 0.2s;
      text-align: center;
    }
    
    .slot-btn:hover {
      background: #e8f4f8;
      border-color: #0066cc;
      color: #0066cc;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 102, 204, 0.15);
    }
    
    .slot-btn:active {
      transform: translateY(0);
    }
    
    .loading {
      text-align: center;
      color: #666;
      padding: 20px;
      font-style: italic;
    }
    
    .error-message {
      background: #f8d7da;
      color: #721c24;
      padding: 16px 20px;
      border-radius: 8px;
      border: 1px solid #f5c6cb;
      margin-top: 15px;
    }
    
    .info-message {
      background: #fff3cd;
      color: #856404;
      padding: 16px 20px;
      border-radius: 8px;
      border: 1px solid #ffeeba;
      margin-top: 15px;
    }
    
    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: #0066cc;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
    }
    
    .back-link:hover {
      text-decoration: underline;
    }
    
    @media (max-width: 768px) {
      .page-wrap {
        margin-left: 0;
        padding: 20px;
      }
      
      .doctor-card {
        padding: 25px 20px;
      }
      
      .doctor-info {
        flex-direction: column;
        align-items: center;
        text-align: center;
      }
      
      .doctor-image {
        width: 150px;
        height: 150px;
      }
      
      .date-selector input[type="date"] {
        max-width: 100%;
      }
      
      #slots {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      }
    }
  </style>
<script>
  function fetchSlots() {
    var date = document.getElementById('date').value;
    if (!date) return;
    
    var wrap = document.getElementById('slots');
    wrap.innerHTML = '<div class="loading">Loading available slots...</div>';
    
    fetch('/doctor-appointment/patient/slots_ajax.php?doctor_id=<?php echo $doctor_id; ?>&date=' + encodeURIComponent(date))
      .then(r => r.json())
      .then(d => {
        console.log('Slots response:', d); // Debug log
        wrap.innerHTML = '';
        
        if (d.error) {
          wrap.innerHTML = '<div class="error-message">' + escapeHtml(d.error) + '</div>';
          return;
        }
        
        if (d.message && (!d.slots || d.slots.length === 0)) {
          wrap.innerHTML = '<div class="info-message">' + escapeHtml(d.message) + '</div>';
          return;
        }
        
        if (!d.slots || d.slots.length === 0) {
          wrap.innerHTML = '<div class="info-message">No available slots for this date.</div>';
          return;
        }
        
        d.slots.forEach(function(s) {
          console.log('Slot data:', s); // Debug log
          var b = document.createElement('button');
          b.className = 'slot-btn';
          b.type = 'button';
          b.innerText = s.start + ' - ' + s.end;
          b.onclick = function() { 
            bookSlot(date, s.start_time, s.end_time, s.start + ' - ' + s.end); 
          };
          wrap.appendChild(b);
        });
      })
      .catch(err => {
        wrap.innerHTML = '<div class="error-message">Failed to load slots. Please try again.</div>';
        console.error('Error fetching slots:', err);
      });
  }

  function bookSlot(date, start, end, displayTime) {
    console.log('Booking with:', { date, start, end, displayTime }); // Debug log
    
    if (!confirm('Confirm booking for ' + date + ' at ' + displayTime + '?')) return;
    
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name=csrf_token]').value);
    fd.append('doctor_id', '<?php echo $doctor_id; ?>');
    fd.append('date', date);
    fd.append('start_time', start);
    fd.append('end_time', end);
    
    console.log('FormData being sent:');
    for (var pair of fd.entries()) {
      console.log(pair[0] + ': ' + pair[1]);
    }

    fetch('/doctor-appointment/patient/book_action.php', { 
      method: 'POST', 
      body: fd 
    })
      .then(r => r.json())
      .then(d => {
        console.log('Booking response:', d); // Debug log
        if (d.error) {
          alert('Error: ' + d.error);
        } else if (d.success) {
          alert(d.message || 'Appointment booked successfully!');
          window.location = '/doctor-appointment/patient/appointments.php';
        }
      })
      .catch(err => {
        alert('Booking failed. Please try again.');
        console.error('Booking error:', err);
      });
  }
  
  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
</script>
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">
  <a href="/doctor-appointment/patient/doctors.php" class="back-link">← Back to Doctors</a>
  
  <div class="page-header">
    <h1>Book Appointment</h1>
  </div>

  <div class="doctor-card">
    <div class="doctor-info">
      <img src="<?php echo htmlspecialchars($doc['image'] ?: '/doctor-appointment/assets/images/placeholder.png', ENT_QUOTES, 'UTF-8'); ?>" 
           alt="<?php echo htmlspecialchars($doc['full_name'], ENT_QUOTES, 'UTF-8'); ?>" 
           class="doctor-image">
      <div class="doctor-details">
        <div class="doctor-name"><?php echo htmlspecialchars($doc['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="doctor-specialty"><?php echo htmlspecialchars($doc['specialty'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="doctor-fee">
          Consultation Fee: <span class="fee-amount">₹<?php echo number_format($doc['fee'], 2); ?></span>
        </div>
      </div>
    </div>

    <div class="booking-section">
      <div class="section-title">Select Appointment Date & Time</div>
      
      <div class="date-selector">
        <label for="date">Choose Date</label>
        <input type="date" 
               id="date" 
               name="date"
               min="<?php echo $today; ?>" 
               onchange="fetchSlots()">
      </div>

      <div class="slots-container">
        <div class="slots-title">Available Time Slots</div>
        <div id="slots" class="empty">Please select a date to view available slots</div>
      </div>
    </div>
  </div>

  <form style="display:none">
    <?php echo csrf_field(); ?>
  </form>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>