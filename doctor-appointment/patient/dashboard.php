<?php
// patient/dashboard.php - Clean hospital-friendly UI
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$per_page = 8;
$page = max(1, intval($_GET['page'] ?? 1));
$q = trim($_GET['q'] ?? '');
$min_fee = $_GET['min_fee'] ?? '';
$max_fee = $_GET['max_fee'] ?? '';

// fetch unread notification count
$notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notif_count->execute([current_user_id()]);
$n_count = (int)$notif_count->fetchColumn();

$where = "WHERE u.role='doctor' AND dp.status='approved'";
$params = [];
if ($q !== '') {
  $where .= " AND (u.full_name LIKE ? OR dp.specialty LIKE ?)";
  $like = "%$q%";
  $params[] = $like;
  $params[] = $like;
}
if ($min_fee !== '') {
  $where .= " AND dp.fee >= ?";
  $params[] = floatval($min_fee);
}
if ($max_fee !== '') {
  $where .= " AND dp.fee <= ?";
  $params[] = floatval($max_fee);
}

$totalQ = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN doctors_profiles dp ON u.id=dp.user_id $where");
$totalQ->execute($params);
$total = (int)$totalQ->fetchColumn();
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT u.id AS doc_id, u.full_name, dp.specialty, dp.fee, dp.bio, dp.image FROM users u JOIN doctors_profiles dp ON u.id=dp.user_id $where ORDER BY u.id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $i => $param) {
  $stmt->bindValue($i + 1, $param);
}
$stmt->execute();
$doctors = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find Doctors — Patient Dashboard</title>
  <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
  <script defer src="/doctor-appointment/assets/js/patient_ui.js"></script>
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
      max-width: 1400px;
    }
    
    .welcome-section {
      background: linear-gradient(135deg, #0066cc 0%, #004999 100%);
      padding: 40px;
      border-radius: 12px;
      margin-bottom: 30px;
      color: white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .welcome-section h1 {
      font-size: 32px;
      margin-bottom: 8px;
      font-weight: 700;
    }
    
    .welcome-section p {
      font-size: 16px;
      opacity: 0.95;
    }
    
    .filter-card {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }
    
    .filter-title {
      font-size: 18px;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 20px;
    }
    
    .filter-bar {
      display: flex;
      gap: 15px;
      align-items: flex-end;
      flex-wrap: wrap;
    }
    
    .filter-group {
      flex: 1;
      min-width: 200px;
    }
    
    .filter-group label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #555;
      margin-bottom: 8px;
    }
    
    .filter-bar input {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.2s;
    }
    
    .filter-bar input:focus {
      outline: none;
      border-color: #0066cc;
      box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
    }
    
    .filter-group.small {
      flex: 0 0 140px;
    }
    
    .btn-primary {
      background: #0066cc;
      color: white;
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
    
    .btn-primary:hover {
      background: #0052a3;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
    }
    
    .doctors-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }
    
    .doctor-card {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      transition: all 0.3s;
      border: 1px solid #e8f4f8;
    }
    
    .doctor-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .doctor-image {
      height: 220px;
      overflow: hidden;
      background: #f0f0f0;
      position: relative;
    }
    
    .doctor-image img {
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
    
    .doctor-fee {
      font-size: 24px;
      font-weight: 700;
      color: #28a745;
      margin-bottom: 12px;
    }
    
    .doctor-bio {
      color: #666;
      font-size: 14px;
      line-height: 1.6;
      margin-bottom: 20px;
      height: 60px;
      overflow: hidden;
    }
    
    .doctor-actions {
      display: flex;
      gap: 10px;
    }
    
    .btn-secondary {
      flex: 1;
      background: #f8f9fa;
      color: #333;
      padding: 10px 16px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }
    
    .btn-secondary:hover {
      background: #e9ecef;
    }
    
    .btn-book {
      flex: 1;
      background: #0066cc;
      color: white;
      padding: 10px 16px;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }
    
    .btn-book:hover {
      background: #0052a3;
    }
    
    .empty-state {
      background: white;
      padding: 80px 40px;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .empty-icon {
      font-size: 64px;
      margin-bottom: 20px;
      opacity: 0.3;
    }
    
    .empty-text {
      color: #666;
      font-size: 18px;
      margin-bottom: 8px;
    }
    
    .empty-subtext {
      color: #999;
      font-size: 14px;
    }
    
    .pagination {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      display: flex;
      justify-content: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .page-link {
      padding: 8px 16px;
      border-radius: 6px;
      text-decoration: none;
      color: #333;
      font-weight: 500;
      transition: all 0.2s;
      border: 1px solid #ddd;
      background: white;
    }
    
    .page-link:hover {
      background: #f8f9fa;
      border-color: #0066cc;
      color: #0066cc;
    }
    
    .page-link.active {
      background: #0066cc;
      color: white;
      border-color: #0066cc;
    }
    
    @media (max-width: 768px) {
      .page-wrap {
        margin-left: 0;
        padding: 20px;
      }
      
      .welcome-section {
        padding: 25px;
      }
      
      .welcome-section h1 {
        font-size: 24px;
      }
      
      .doctors-grid {
        grid-template-columns: 1fr;
      }
      
      .filter-bar {
        flex-direction: column;
      }
      
      .filter-group,
      .filter-group.small {
        width: 100%;
      }
      
      .btn-primary {
        width: 100%;
      }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">
  <div class="welcome-section">
    <h1>Welcome Back!</h1>
    <p>Find and book appointments with qualified healthcare professionals</p>
  </div>

  <div class="filter-card">
    <div class="filter-title">Search Doctors</div>
    <form method="get" class="filter-bar">
      <div class="filter-group">
        <label>Search by Name or Specialty</label>
        <input name="q" type="text" placeholder="Enter doctor name or specialty..." value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="filter-group small">
        <label>Min Fee (₹)</label>
        <input name="min_fee" type="number" step="0.01" min="0" placeholder="0" value="<?php echo htmlspecialchars($min_fee, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="filter-group small">
        <label>Max Fee (₹)</label>
        <input name="max_fee" type="number" step="0.01" min="0" placeholder="10000" value="<?php echo htmlspecialchars($max_fee, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <button class="btn-primary" type="submit">Search</button>
    </form>
  </div>

  <?php if (empty($doctors)): ?>
    <div class="empty-state">
      <div class="empty-icon">🏥</div>
      <div class="empty-text">No Doctors Found</div>
      <div class="empty-subtext">Try adjusting your search criteria or filters</div>
    </div>
  <?php else: ?>
    <div class="doctors-grid">
      <?php foreach ($doctors as $d): ?>
        <div class="doctor-card">
          <div class="doctor-image">
            <img src="<?php echo htmlspecialchars($d['image'] ?: '/doctor-appointment/assets/images/placeholder.png', ENT_QUOTES, 'UTF-8'); ?>" 
                 alt="<?php echo htmlspecialchars($d['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div class="doctor-info">
            <div class="doctor-name"><?php echo htmlspecialchars($d['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="doctor-specialty"><?php echo htmlspecialchars($d['specialty'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="doctor-fee">₹<?php echo number_format($d['fee'], 2); ?></div>
            <div class="doctor-bio">
              <?php 
              $bio = $d['bio'] ?? 'No bio available';
              echo htmlspecialchars(substr($bio, 0, 100), ENT_QUOTES, 'UTF-8'); 
              echo strlen($bio) > 100 ? '...' : ''; 
              ?>
            </div>
            <div class="doctor-actions">
              <a class="btn-secondary" href="/doctor-appointment/doctor/profile.php?doctor_id=<?php echo (int)$d['doc_id']; ?>">
                View Profile
              </a>
              <a class="btn-book" href="/doctor-appointment/patient/book.php?doctor_id=<?php echo (int)$d['doc_id']; ?>">
                Book Now
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($total > $per_page): ?>
    <div class="pagination">
      <?php
      $total_pages = max(1, ceil($total / $per_page));
      for ($p = 1; $p <= $total_pages; $p++):
        $query_params = $_GET;
        $query_params['page'] = $p;
      ?>
        <a class="page-link<?php echo $p == $page ? ' active' : ''; ?>" 
           href="?<?php echo http_build_query($query_params); ?>">
          <?php echo $p; ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>