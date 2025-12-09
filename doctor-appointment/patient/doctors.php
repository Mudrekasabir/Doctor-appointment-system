<?php
// patient/doctors.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Get search parameters
$search = $_GET['search'] ?? '';
$specialty = $_GET['specialty'] ?? '';

// Build query
$query = "SELECT u.id, u.full_name, u.email, dp.specialty, dp.qualification, dp.experience, dp.fee, dp.image 
          FROM users u 
          JOIN doctors_profiles dp ON u.id = dp.user_id 
          WHERE u.role = 'doctor' AND u.status = 'active'";

$params = [];

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR dp.specialty LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($specialty)) {
    $query .= " AND dp.specialty = ?";
    $params[] = $specialty;
}

$query .= " ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all unique specialties for filter
$specialtyStmt = $pdo->query("SELECT DISTINCT specialty FROM doctors_profiles ORDER BY specialty ASC");
$specialties = $specialtyStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find Doctors - Patient Portal</title>
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
      max-width: 1200px;
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
    
    .search-filters {
      background: white;
      padding: 25px;
      border-radius: 12px;
      margin-bottom: 25px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .filter-form {
      display: grid;
      grid-template-columns: 1fr 1fr auto;
      gap: 15px;
      align-items: end;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
    }
    
    .form-group label {
      font-size: 14px;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    
    .form-group input,
    .form-group select {
      padding: 12px 16px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.2s;
    }
    
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #0066cc;
      box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
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
      text-decoration: none;
    }
    
    .btn-secondary:hover {
      background: #e9ecef;
    }
    
    .doctors-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 20px;
    }
    
    .doctor-card {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      transition: all 0.3s;
    }
    
    .doctor-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    
    .doctor-image-container {
      width: 100%;
      height: 200px;
      overflow: hidden;
      background: #f0f0f0;
    }
    
    .doctor-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .doctor-info {
      padding: 20px;
    }
    
    .doctor-name {
      font-size: 20px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 6px;
    }
    
    .doctor-specialty {
      color: #0066cc;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 12px;
    }
    
    .doctor-details {
      margin-bottom: 15px;
    }
    
    .detail-item {
      display: flex;
      align-items: center;
      gap: 8px;
      color: #666;
      font-size: 14px;
      margin-bottom: 6px;
    }
    
    .detail-icon {
      color: #0066cc;
      font-weight: 600;
    }
    
    .doctor-fee {
      font-size: 18px;
      font-weight: 700;
      color: #0066cc;
      margin-bottom: 15px;
    }
    
    .doctor-actions {
      display: flex;
      gap: 10px;
    }
    
    .btn-book {
      flex: 1;
      background: #0066cc;
      color: white;
      padding: 10px 20px;
      border-radius: 8px;
      text-decoration: none;
      text-align: center;
      font-weight: 600;
      transition: all 0.2s;
    }
    
    .btn-book:hover {
      background: #0052a3;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
    }
    
    .no-results {
      background: white;
      padding: 60px 30px;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .no-results h3 {
      font-size: 20px;
      color: #666;
      margin-bottom: 10px;
    }
    
    .no-results p {
      color: #999;
    }
    
    .results-count {
      color: #666;
      font-size: 14px;
      margin-bottom: 20px;
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
      
      .filter-form {
        grid-template-columns: 1fr;
      }
      
      .doctors-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">
  <div class="page-header">
    <h1>Find Doctors</h1>
    <p>Search and book appointments with qualified doctors</p>
  </div>

  <div class="search-filters">
    <form method="get" class="filter-form">
      <div class="form-group">
        <label for="search">Search by Name</label>
        <input type="text" 
               id="search" 
               name="search" 
               placeholder="Search doctor name..." 
               value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="form-group">
        <label for="specialty">Filter by Specialty</label>
        <select id="specialty" name="specialty">
          <option value="">All Specialties</option>
          <?php foreach ($specialties as $spec): ?>
            <option value="<?php echo htmlspecialchars($spec, ENT_QUOTES, 'UTF-8'); ?>" 
                    <?php echo ($specialty === $spec) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($spec, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <button type="submit" class="btn btn-primary">Search</button>
      </div>
    </form>
  </div>

  <?php if (!empty($search) || !empty($specialty)): ?>
    <div class="results-count">
      Found <?php echo count($doctors); ?> doctor(s)
      <?php if ($search): ?>
        matching "<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
      <?php endif; ?>
      <?php if ($specialty): ?>
        in <?php echo htmlspecialchars($specialty, ENT_QUOTES, 'UTF-8'); ?>
      <?php endif; ?>
      <a href="/doctor-appointment/patient/doctors.php" class="btn-secondary" style="margin-left: 10px; padding: 6px 15px; font-size: 13px;">Clear Filters</a>
    </div>
  <?php endif; ?>

  <?php if (empty($doctors)): ?>
    <div class="no-results">
      <h3>No doctors found</h3>
      <p>Try adjusting your search criteria</p>
    </div>
  <?php else: ?>
    <div class="doctors-grid">
      <?php foreach ($doctors as $doctor): ?>
        <div class="doctor-card">
          <div class="doctor-image-container">
            <img src="<?php echo htmlspecialchars($doctor['image'] ?: '/doctor-appointment/assets/images/placeholder.png', ENT_QUOTES, 'UTF-8'); ?>" 
                 alt="<?php echo htmlspecialchars($doctor['full_name'], ENT_QUOTES, 'UTF-8'); ?>" 
                 class="doctor-image">
          </div>
          
          <div class="doctor-info">
            <div class="doctor-name"><?php echo htmlspecialchars($doctor['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty'], ENT_QUOTES, 'UTF-8'); ?></div>
            
            <div class="doctor-details">
              <?php if (!empty($doctor['qualification'])): ?>
                <div class="detail-item">
                  <span class="detail-icon">🎓</span>
                  <span><?php echo htmlspecialchars($doctor['qualification'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($doctor['experience'])): ?>
                <div class="detail-item">
                  <span class="detail-icon">💼</span>
                  <span><?php echo htmlspecialchars($doctor['experience'], ENT_QUOTES, 'UTF-8'); ?> years experience</span>
                </div>
              <?php endif; ?>
            </div>
            
            <div class="doctor-fee">
              ₹<?php echo number_format($doctor['fee'], 2); ?> <span style="font-size: 14px; font-weight: 400; color: #666;">consultation fee</span>
            </div>
            
            <div class="doctor-actions">
              <a href="/doctor-appointment/patient/book.php?doctor_id=<?php echo $doctor['id']; ?>" 
                 class="btn-book">
                Book Appointment
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>