<?php
// patient/medical_view.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$viewer_role = current_user_role(); // patient or doctor or admin
$patient_id = intval($_GET['patient_id'] ?? current_user_id());
if (!$patient_id) { echo "Invalid patient id"; exit; }

$stmt = $pdo->prepare("SELECT u.username,u.full_name,u.email,u.contact, p.* FROM users u LEFT JOIN patients_profiles p ON u.id=p.user_id WHERE u.id = ? LIMIT 1");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();
if (!$patient) { echo "Patient not found"; exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medical Profile - Healthcare System</title>
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
    
    .profile-header {
      background: linear-gradient(135deg, #0066cc 0%, #004999 100%);
      padding: 35px;
      border-radius: 12px;
      margin-bottom: 25px;
      color: white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .profile-header h1 {
      font-size: 28px;
      margin-bottom: 8px;
      font-weight: 700;
    }
    
    .profile-header .contact-info {
      font-size: 15px;
      opacity: 0.95;
      display: flex;
      gap: 25px;
      flex-wrap: wrap;
      margin-top: 15px;
    }
    
    .contact-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .info-card {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      margin-bottom: 25px;
    }
    
    .section-title {
      font-size: 18px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #e8f4f8;
    }
    
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }
    
    .info-item {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    
    .info-item.full-width {
      grid-column: 1 / -1;
    }
    
    .info-label {
      font-size: 13px;
      font-weight: 600;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .info-value {
      font-size: 15px;
      color: #1a1a1a;
      font-weight: 500;
      padding: 10px 14px;
      background: #f8f9fa;
      border-radius: 6px;
      border-left: 3px solid #0066cc;
    }
    
    .info-value.empty {
      color: #999;
      font-style: italic;
    }
    
    .info-value.important {
      background: #fff3cd;
      border-left-color: #ffc107;
    }
    
    .actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    
    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }
    
    .btn-primary {
      background: #0066cc;
      color: white;
    }
    
    .btn-primary:hover {
      background: #0052a3;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
    }
    
    .btn-secondary {
      background: #6c757d;
      color: white;
    }
    
    .btn-secondary:hover {
      background: #5a6268;
      transform: translateY(-1px);
    }
    
    @media (max-width: 768px) {
      .page-wrap {
        margin-left: 0;
        padding: 20px;
      }
      
      .profile-header {
        padding: 25px 20px;
      }
      
      .profile-header h1 {
        font-size: 24px;
      }
      
      .profile-header .contact-info {
        flex-direction: column;
        gap: 10px;
      }
      
      .info-card {
        padding: 20px;
      }
      
      .info-grid {
        grid-template-columns: 1fr;
      }
      
      .actions {
        flex-direction: column;
      }
      
      .btn {
        width: 100%;
      }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">
  <div class="profile-header">
    <h1><?php echo htmlspecialchars($patient['full_name'] ?: $patient['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="contact-info">
      <?php if($patient['email']): ?>
        <div class="contact-item">
          <strong>Email:</strong> <?php echo htmlspecialchars($patient['email'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>
      <?php if($patient['contact']): ?>
        <div class="contact-item">
          <strong>Contact:</strong> <?php echo htmlspecialchars($patient['contact'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Basic Information -->
  <div class="info-card">
    <div class="section-title">Basic Information</div>
    <div class="info-grid">
      <div class="info-item">
        <div class="info-label">Age</div>
        <div class="info-value <?php echo empty($patient['age']) ? 'empty' : ''; ?>">
          <?php echo $patient['age'] ? htmlspecialchars($patient['age'], ENT_QUOTES, 'UTF-8') . ' years' : 'Not specified'; ?>
        </div>
      </div>
      <div class="info-item">
        <div class="info-label">Blood Group</div>
        <div class="info-value <?php echo empty($patient['blood_group']) ? 'empty' : ''; ?>">
          <?php echo htmlspecialchars($patient['blood_group'] ?: 'Not specified', ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Medical Conditions -->
  <div class="info-card">
    <div class="section-title">Medical Conditions</div>
    <div class="info-grid">
      <div class="info-item">
        <div class="info-label">Diabetes</div>
        <div class="info-value <?php echo ($patient['diabetes'] && $patient['diabetes'] !== 'non') ? 'important' : ''; ?>">
          <?php 
          $diabetes_labels = [
            'non' => 'None',
            'type1' => 'Type 1 Diabetes',
            'type2' => 'Type 2 Diabetes',
            'gestational' => 'Gestational Diabetes',
            'prediabetic' => 'Prediabetic'
          ];
          echo htmlspecialchars($diabetes_labels[$patient['diabetes'] ?? 'non'] ?? 'None', ENT_QUOTES, 'UTF-8');
          ?>
        </div>
      </div>

      <div class="info-item">
        <div class="info-label">Thyroid</div>
        <div class="info-value <?php echo ($patient['thyroid'] && $patient['thyroid'] !== 'none') ? 'important' : ''; ?>">
          <?php 
          $thyroid_labels = [
            'none' => 'None',
            'hypo' => 'Hypothyroid',
            'hyper' => 'Hyperthyroid'
          ];
          echo htmlspecialchars($thyroid_labels[$patient['thyroid'] ?? 'none'] ?? 'None', ENT_QUOTES, 'UTF-8');
          ?>
        </div>
      </div>

      <div class="info-item">
        <div class="info-label">Blood Pressure</div>
        <div class="info-value <?php echo ($patient['blood_pressure'] && $patient['blood_pressure'] !== 'normal') ? 'important' : ''; ?>">
          <?php 
          $bp_labels = [
            'normal' => 'Normal',
            'hypertension' => 'High (Hypertension)',
            'hypotension' => 'Low (Hypotension)'
          ];
          echo htmlspecialchars($bp_labels[$patient['blood_pressure'] ?? 'normal'] ?? 'Normal', ENT_QUOTES, 'UTF-8');
          ?>
        </div>
      </div>

      <div class="info-item">
        <div class="info-label">Asthma</div>
        <div class="info-value <?php echo ($patient['asthma'] === 'yes') ? 'important' : ''; ?>">
          <?php echo ($patient['asthma'] === 'yes') ? 'Yes' : 'No'; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Allergies -->
  <div class="info-card">
    <div class="section-title">Allergies</div>
    <div class="info-grid">
      <div class="info-item full-width">
        <div class="info-label">Allergy Information</div>
        <div class="info-value <?php echo ($patient['allergies'] === 'yes' && !empty($patient['allergies_text'])) ? 'important' : (empty($patient['allergies_text']) ? 'empty' : ''); ?>">
          <?php 
          if ($patient['allergies'] === 'yes' && !empty($patient['allergies_text'])) {
            echo nl2br(htmlspecialchars($patient['allergies_text'], ENT_QUOTES, 'UTF-8'));
          } else {
            echo 'No known allergies';
          }
          ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Surgical History -->
  <div class="info-card">
    <div class="section-title">Surgical History</div>
    <div class="info-grid">
      <div class="info-item full-width">
        <div class="info-label">Past Surgeries</div>
        <div class="info-value <?php echo ($patient['past_surgeries'] === 'yes' && !empty($patient['surgeries_text'])) ? 'important' : (empty($patient['surgeries_text']) ? 'empty' : ''); ?>">
          <?php 
          if ($patient['past_surgeries'] === 'yes' && !empty($patient['surgeries_text'])) {
            echo nl2br(htmlspecialchars($patient['surgeries_text'], ENT_QUOTES, 'UTF-8'));
          } else {
            echo 'No past surgeries';
          }
          ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="info-card">
    <div class="actions">
      <?php if ($viewer_role === 'patient'): ?>
        <a href="/doctor-appointment/patient/profile_edit.php" class="btn btn-primary">Edit Medical Profile</a>
      <?php endif; ?>
      <a href="<?php echo ($viewer_role === 'patient') ? '/doctor-appointment/patient/dashboard.php' : 'javascript:history.back()'; ?>" class="btn btn-secondary">Back</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>