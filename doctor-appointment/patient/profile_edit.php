<?php
// patient/profile_edit.php - medical details editor
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$uid = current_user_id();
$stmt = $pdo->prepare("SELECT * FROM patients_profiles WHERE user_id = ?");
$stmt->execute([$uid]);
$profile = $stmt->fetch();

// Fix: Initialize profile as empty array if no record found
if (!$profile) {
    $profile = [];
}

// Set default values to prevent array offset warnings
$profile = array_merge([
    'diabetes' => 'non',
    'thyroid' => 'none',
    'blood_pressure' => 'normal',
    'asthma' => 'no',
    'age' => null,
    'blood_group' => null,
    'allergies' => 'no',
    'allergies_text' => null,
    'past_surgeries' => 'no',
    'surgeries_text' => null
], $profile);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF'; }
    $diabetes = $_POST['diabetes'] ?? 'non';
    $thyroid = $_POST['thyroid'] ?? 'none';
    $blood_pressure = $_POST['blood_pressure'] ?? 'normal';
    $asthma = $_POST['asthma'] ?? 'no';
    $age = intval($_POST['age'] ?? 0) ?: null;
    $blood_group = trim($_POST['blood_group'] ?? '') ?: null;
    $allergies = $_POST['allergies'] ?? 'no';
    $allergies_text = trim($_POST['allergies_text'] ?? '') ?: null;
    $past_surgeries = $_POST['past_surgeries'] ?? 'no';
    $surgeries_text = trim($_POST['surgeries_text'] ?? '') ?: null;

    try {
        $up = $pdo->prepare("UPDATE patients_profiles SET diabetes=?, thyroid=?, blood_pressure=?, asthma=?, age=?, blood_group=?, allergies=?, allergies_text=?, past_surgeries=?, surgeries_text=? WHERE user_id=?");
        $up->execute([$diabetes,$thyroid,$blood_pressure,$asthma,$age,$blood_group,$allergies,$allergies_text,$past_surgeries,$surgeries_text,$uid]);
        flash_set('success','Medical profile updated successfully.');
        header('Location: /doctor-appointment/patient/dashboard.php');
        exit;
    } catch (Exception $e) {
        $errors[] = 'Save failed: '.$e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Medical Details - Patient Portal</title>
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
    
    .page-header p {
      color: #666;
      font-size: 15px;
    }
    
    .alert {
      padding: 16px 20px;
      border-radius: 8px;
      margin-bottom: 25px;
      font-size: 14px;
    }
    
    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .form-card {
      background: white;
      padding: 35px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .form-section {
      margin-bottom: 35px;
    }
    
    .form-section:last-child {
      margin-bottom: 0;
    }
    
    .section-title {
      font-size: 18px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #e8f4f8;
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
    }
    
    .form-group.full-width {
      grid-column: 1 / -1;
    }
    
    .form-group label {
      font-size: 14px;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    
    .form-group select,
    .form-group input,
    .form-group textarea {
      padding: 12px 16px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.2s;
      font-family: inherit;
    }
    
    .form-group select:focus,
    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #0066cc;
      box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
    }
    
    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }
    
    .form-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      margin-top: 30px;
      padding-top: 25px;
      border-top: 2px solid #e8f4f8;
    }
    
    .btn {
      padding: 12px 30px;
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
      background: #f8f9fa;
      color: #333;
      border: 1px solid #ddd;
    }
    
    .btn-secondary:hover {
      background: #e9ecef;
    }
    
    .help-text {
      font-size: 13px;
      color: #666;
      margin-top: 6px;
      font-style: italic;
    }
    
    @media (max-width: 768px) {
      .page-wrap {
        margin-left: 0;
        padding: 20px;
      }
      
      .page-header {
        padding: 20px;
      }
      
      .page-header h1 {
        font-size: 24px;
      }
      
      .form-card {
        padding: 25px 20px;
      }
      
      .form-grid {
        grid-template-columns: 1fr;
      }
      
      .form-actions {
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
  <div class="page-header">
    <h1>Medical Details</h1>
    <p>Keep your medical information up to date for better healthcare</p>
  </div>

  <?php if($errors): ?>
    <div class="alert alert-error">
      <?php echo htmlspecialchars(implode("<br>", $errors), ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <?php if(function_exists('flash_render')) flash_render(); ?>

  <div class="form-card">
    <form method="post">
      <?php echo csrf_field(); ?>
      
      <!-- Basic Health Information -->
      <div class="form-section">
        <div class="section-title">Basic Health Information</div>
        <div class="form-grid">
          <div class="form-group">
            <label for="age">Age</label>
            <input type="number" id="age" name="age" min="0" max="150" 
                   value="<?php echo htmlspecialchars($profile['age'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div class="form-group">
            <label for="blood_group">Blood Group</label>
            <input type="text" id="blood_group" name="blood_group" placeholder="e.g., A+, B-, O+" 
                   value="<?php echo htmlspecialchars($profile['blood_group'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        </div>
      </div>

      <!-- Medical Conditions -->
      <div class="form-section">
        <div class="section-title">Medical Conditions</div>
        <div class="form-grid">
          <div class="form-group">
            <label for="diabetes">Diabetes Status</label>
            <select id="diabetes" name="diabetes">
              <?php 
              $diabetes_options = [
                'non' => 'None',
                'type1' => 'Type 1',
                'type2' => 'Type 2',
                'gestational' => 'Gestational',
                'prediabetic' => 'Prediabetic'
              ];
              foreach($diabetes_options as $value => $label): 
              ?>
                <option value="<?php echo $value; ?>" 
                        <?php echo ($profile['diabetes'] == $value) ? 'selected' : ''; ?>>
                  <?php echo $label; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="thyroid">Thyroid Condition</label>
            <select id="thyroid" name="thyroid">
              <?php 
              $thyroid_options = [
                'none' => 'None',
                'hypo' => 'Hypothyroid',
                'hyper' => 'Hyperthyroid'
              ];
              foreach($thyroid_options as $value => $label): 
              ?>
                <option value="<?php echo $value; ?>" 
                        <?php echo ($profile['thyroid'] == $value) ? 'selected' : ''; ?>>
                  <?php echo $label; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="blood_pressure">Blood Pressure</label>
            <select id="blood_pressure" name="blood_pressure">
              <?php 
              $bp_options = [
                'normal' => 'Normal',
                'hypertension' => 'High (Hypertension)',
                'hypotension' => 'Low (Hypotension)'
              ];
              foreach($bp_options as $value => $label): 
              ?>
                <option value="<?php echo $value; ?>" 
                        <?php echo ($profile['blood_pressure'] == $value) ? 'selected' : ''; ?>>
                  <?php echo $label; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="asthma">Asthma</label>
            <select id="asthma" name="asthma">
              <option value="no" <?php echo ($profile['asthma'] == 'no') ? 'selected' : ''; ?>>No</option>
              <option value="yes" <?php echo ($profile['asthma'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Allergies -->
      <div class="form-section">
        <div class="section-title">Allergies</div>
        <div class="form-grid">
          <div class="form-group">
            <label for="allergies">Do you have allergies?</label>
            <select id="allergies" name="allergies">
              <option value="no" <?php echo ($profile['allergies'] == 'no') ? 'selected' : ''; ?>>No</option>
              <option value="yes" <?php echo ($profile['allergies'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
            </select>
          </div>

          <div class="form-group full-width">
            <label for="allergies_text">Allergy Details</label>
            <textarea id="allergies_text" name="allergies_text" placeholder="Please list any allergies (medications, food, environmental, etc.)"><?php echo htmlspecialchars($profile['allergies_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="help-text">Provide details if you have any allergies</div>
          </div>
        </div>
      </div>

      <!-- Past Surgeries -->
      <div class="form-section">
        <div class="section-title">Surgical History</div>
        <div class="form-grid">
          <div class="form-group">
            <label for="past_surgeries">Have you had any surgeries?</label>
            <select id="past_surgeries" name="past_surgeries">
              <option value="no" <?php echo ($profile['past_surgeries'] == 'no') ? 'selected' : ''; ?>>No</option>
              <option value="yes" <?php echo ($profile['past_surgeries'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
            </select>
          </div>

          <div class="form-group full-width">
            <label for="surgeries_text">Surgery Details</label>
            <textarea id="surgeries_text" name="surgeries_text" placeholder="Please list any past surgeries with approximate dates"><?php echo htmlspecialchars($profile['surgeries_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="help-text">Provide details if you have had any surgeries</div>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <a href="/doctor-appointment/patient/dashboard.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Medical Profile</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>