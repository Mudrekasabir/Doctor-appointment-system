<?php
// /doctor-appointment/doctor/profile_edit.php
// Robust doctor profile editor:
// - auto-detects an existing doctor table by searching for common columns
// - if none found, offers to create a safe `doctors` table (on explicit POST)
// - creates placeholder row for user_id if missing
// - updates only existing columns
// - debug-friendly (prints meaningful messages instead of silent redirects)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/auth_checks.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

// basic login+role guard — will show message if not satisfied
$user_id = (int)($_SESSION['user_id'] ?? 0);
$role    = $_SESSION['role'] ?? null;
if ($user_id <= 0) {
    echo "<h2>Not logged in</h2><p>Please login as a doctor.</p>";
    exit;
}
if ($role !== 'doctor') {
    echo "<h2>Access denied</h2><p>Your session role is not 'doctor'. Current role: " . htmlspecialchars($role) . "</p>";
    exit;
}

// helper: does column exist
function column_exists(PDO $pdo, string $table, string $column): bool {
    $sql = "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$table, $column]);
    return (bool)$st->fetchColumn();
}

// 1) Try to auto-detect table that likely contains doctor info.
$doctor_column_candidates = [
    'license_no','license','experience','specialty','speciality',
    'fee','bio','image','status','user_id'
];

$found_table = null;

// search information_schema for any table containing at least one of those columns
$placeholders = implode(',', array_fill(0, count($doctor_column_candidates), '?'));
$sql = "SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND COLUMN_NAME IN ({$placeholders})
        ORDER BY TABLE_NAME";

$st = $pdo->prepare($sql);
$st->execute($doctor_column_candidates);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// group by table name and count matching doctor-ish columns
$counts = [];
foreach ($rows as $r) {
    $counts[$r['TABLE_NAME']][] = $r['COLUMN_NAME'];
}

// Choose the table that matches the most candidate columns (heuristic)
$bestTable = null;
$bestCount = 0;
foreach ($counts as $tbl => $cols) {
    $c = count($cols);
    if ($c > $bestCount) {
        $bestCount = $c;
        $bestTable = $tbl;
    }
}

if ($bestTable) {
    $found_table = $bestTable;
}

// If nothing found, $found_table remains null — offer to create a new 'doctors' table.
if (!$found_table) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table']) && $_POST['create_table'] == '1') {
        try {
            $create_sql = <<<SQL
CREATE TABLE IF NOT EXISTS `doctors` (
  `user_id` INT(11) NOT NULL PRIMARY KEY,
  `license_no` VARCHAR(64) NOT NULL,
  `experience` INT(11) DEFAULT 0,
  `specialty` VARCHAR(128) DEFAULT NULL,
  `fee` DECIMAL(10,2) DEFAULT 0.00,
  `bio` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('approved','pending','disabled') DEFAULT 'pending',
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
            $pdo->exec($create_sql);
            $found_table = 'doctors';
        } catch (Exception $e) {
            echo "<h2>Failed to create table</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
            exit;
        }
    } else {
        // show friendly UI explaining table not found and offering to create one
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Doctor profile table not found</title>
            <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
        </head>
        <body>
        <?php include __DIR__ . '/../inc/header.php'; ?>
        <div class="page-wrap">
            <div style="max-width:800px;margin:30px auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 15px 40px rgba(15,23,42,0.08);">
                <h2 style="margin-bottom:8px;">Doctor profile storage not found</h2>
                <p style="color:#555;margin-bottom:12px;">
                    The system couldn't find a table in the
                    <strong><?= htmlspecialchars($pdo->query('SELECT DATABASE()')->fetchColumn()) ?></strong>
                    database that looks like a doctors table.
                </p>
                <p style="font-size:13px;color:#777;">
                    Detected columns matched in tables:
                    <code style="display:block;margin-top:4px;background:#f9fafb;padding:8px;border-radius:8px;">
                        <?= htmlspecialchars(json_encode($counts ?: [])) ?>
                    </code>
                </p>
                <p style="margin-top:12px;">You have two options:</p>
                <ol style="margin-left:20px;margin-bottom:14px;color:#444;">
                    <li>Tell me the correct table name that stores doctor data (so I can use it), or</li>
                    <li>Create a new <code>doctors</code> table now (safe default schema). The page will create it for you.</li>
                </ol>

                <form method="post" style="margin-top:1rem">
                    <input type="hidden" name="create_table" value="1">
                    <button type="submit"
                            style="background:#2563eb;color:#fff;border:none;padding:10px 16px;border-radius:8px;
                                   font-weight:600;cursor:pointer;">
                        Create `doctors` table (safe default)
                    </button>
                </form>

                <p style="margin-top:16px;font-size:13px;color:#6b7280;">
                    If you prefer not to create a new table, run this in phpMyAdmin or MySQL console and send me the result:<br>
                    <code>
                        SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND COLUMN_NAME IN ('user_id','license_no','license','experience','specialty',
                                            'speciality','fee','bio','image','status','created_by')
                        ORDER BY TABLE_NAME, COLUMN_NAME;
                    </code>
                </p>
            </div>
        </div>
        <?php include __DIR__ . '/../inc/footer.php'; ?>
        </body>
        </html>
        <?php
        exit;
    }
}

// by here, $found_table is set to a usable table name
$doctor_table = $found_table;

// Ensure the table has user_id column. If not, try to find suitable id column.
if (!column_exists($pdo, $doctor_table, 'user_id')) {
    $id_col = column_exists($pdo, $doctor_table, 'id') ? 'id' : null;
    if (!$id_col) {
        echo "<h2>Table found but no user identifier column.</h2>";
        echo "<p>Found table: " . htmlspecialchars($doctor_table) . " but it doesn't contain 'user_id' or 'id'.</p>";
        exit;
    } else {
        $user_key_col = 'id';
    }
} else {
    $user_key_col = 'user_id';
}

// fetch doctor row
try {
    $st = $pdo->prepare("SELECT * FROM `{$doctor_table}` WHERE {$user_key_col} = ? LIMIT 1");
    $st->execute([$user_id]);
    $doctor = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<h2>DB error fetching doctor row</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

// if no row, create placeholder row (so form loads)
if (!$doctor) {
    try {
        $cols = [];
        $vals = [];
        $ph   = [];

        if (column_exists($pdo, $doctor_table, 'user_id')) {
            $cols[] = 'user_id'; $ph[] = '?'; $vals[] = $user_id;
        }
        if (column_exists($pdo, $doctor_table, 'license_no')) {
            $cols[] = 'license_no'; $ph[] = '?'; $vals[] = '';
        }
        if (column_exists($pdo, $doctor_table, 'experience')) {
            $cols[] = 'experience'; $ph[] = '?'; $vals[] = 0;
        }
        if (column_exists($pdo, $doctor_table, 'specialty') || column_exists($pdo, $doctor_table, 'speciality')) {
            $colname = column_exists($pdo, $doctor_table, 'specialty') ? 'specialty' : 'speciality';
            $cols[] = "`{$colname}`"; $ph[] = '?'; $vals[] = null;
        }
        if (column_exists($pdo, $doctor_table, 'fee')) {
            $cols[] = 'fee'; $ph[] = '?'; $vals[] = 0.00;
        }
        if (column_exists($pdo, $doctor_table, 'status')) {
            $cols[] = 'status'; $ph[] = '?'; $vals[] = 'pending';
        }

        if (!empty($cols)) {
            $sql = "INSERT INTO `{$doctor_table}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")";
            $ins = $pdo->prepare($sql);
            $ins->execute($vals);
            // re-fetch
            $st->execute([$user_id]);
            $doctor = $st->fetch(PDO::FETCH_ASSOC);
        } else {
            echo "<h2>Cannot create placeholder row</h2><p>No writable columns detected in table " . htmlspecialchars($doctor_table) . ".</p>";
            exit;
        }
    } catch (Exception $e) {
        echo "<h2>Failed to create placeholder row</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        exit;
    }
}

// prepare form defaults using available columns
$form = [];
$doctor_cols = [];
$colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
$colStmt->execute([$doctor_table]);
foreach ($colStmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
    $doctor_cols[$c] = true;
    $form[$c] = $doctor[$c] ?? '';
}

// handle POST update (update only existing columns)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['create_table'])) {

    // CSRF protection (if helper exists)
    if (function_exists('validate_csrf') && !validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please reload the page and try again.';
    }

    $updates = [];
    $params  = [];
    $allowed_cols = ['license_no','experience','specialty','speciality','fee','bio','image','status'];

    if (empty($errors)) {
        foreach ($allowed_cols as $c) {
            if (isset($doctor_cols[$c]) && array_key_exists($c, $_POST)) {
                $val = trim((string)$_POST[$c]);

                if ($c === 'experience') {
                    if ($val !== '' && !ctype_digit($val)) {
                        $errors[] = 'Experience must be integer years.';
                    }
                    $val = $val === '' ? 0 : (int)$val;
                } elseif ($c === 'fee') {
                    if ($val !== '' && !is_numeric($val)) {
                        $errors[] = 'Fee must be numeric.';
                    }
                    $val = $val === '' ? 0.00 : $val;
                } else {
                    $val = $val === '' ? null : $val;
                }

                $updates[] = "`{$c}` = ?";
                $params[]  = $val;
                $form[$c]  = $val;
            }
        }
    }

    if (empty($errors)) {
        if (!empty($updates)) {
            $params[] = $user_id;
            $sql = "UPDATE `{$doctor_table}` SET " . implode(', ', $updates) . " WHERE {$user_key_col} = ?";

            try {
                $upd = $pdo->prepare($sql);
                $upd->execute($params);
                if (function_exists('flash_set')) {
                    flash_set('success', 'Profile updated successfully.');
                }
                header('Location: /doctor-appointment/doctor/profile_edit.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'DB error: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'No editable fields found.';
        }
    }
}

// Render the form using only columns present.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Doctor Profile</title>
  <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
  <style>
    .profile-card {
      background:#ffffff;
      border-radius:16px;
      padding:24px 28px;
      box-shadow:0 18px 45px rgba(15,23,42,0.08);
    }
    .profile-header {
      margin-bottom:18px;
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
    }
    .profile-header-title {
      font-size:22px;
      font-weight:700;
      color:#0f172a;
    }
    .profile-header-sub {
      font-size:13px;
      color:#6b7280;
      margin-top:4px;
    }
    .profile-grid {
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:18px 20px;
      margin-top:8px;
    }
    .form-group {
      display:flex;
      flex-direction:column;
    }
    .form-group label {
      font-size:13px;
      font-weight:600;
      color:#374151;
      margin-bottom:6px;
    }
    .form-group small {
      font-size:11px;
      color:#9ca3af;
      margin-top:3px;
    }
    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group textarea {
      padding:10px 12px;
      border-radius:8px;
      border:1px solid #e5e7eb;
      font-size:14px;
      font-family:inherit;
      transition:border-color 0.2s, box-shadow 0.2s;
      background:#f9fafb;
    }
    .form-group textarea {
      min-height:90px;
      resize:vertical;
    }
    .form-group input:focus,
    .form-group textarea:focus {
      outline:none;
      border-color:#2563eb;
      box-shadow:0 0 0 3px rgba(37,99,235,0.18);
      background:#ffffff;
    }
    .profile-actions {
      margin-top:22px;
      display:flex;
      gap:10px;
      flex-wrap:wrap;
    }
    .btn-primary {
      background:#2563eb;
      color:#fff;
      border:none;
      padding:10px 18px;
      border-radius:10px;
      font-size:14px;
      font-weight:600;
      cursor:pointer;
      box-shadow:0 10px 30px rgba(37,99,235,0.4);
      transition:transform 0.15s, box-shadow 0.15s;
    }
    .btn-primary:hover {
      transform:translateY(-1px);
      box-shadow:0 14px 36px rgba(37,99,235,0.5);
    }
    .btn-secondary {
      background:#f3f4f6;
      color:#374151;
      border:none;
      padding:10px 16px;
      border-radius:10px;
      font-size:14px;
      font-weight:500;
      cursor:pointer;
    }
    .errors {
      background:#fef2f2;
      border:1px solid #fecaca;
      color:#7f1d1d;
      padding:10px 12px;
      border-radius:10px;
      font-size:13px;
      margin-bottom:16px;
    }
    .errors ul {
      padding-left:18px;
      margin:0;
    }
    @media (max-width: 900px) {
      .profile-grid { grid-template-columns:1fr; }
      .page-wrap { padding:20px !important; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">
  <div style="max-width:900px;margin:0 auto;">
    <div class="profile-card">
      <div class="profile-header">
        <div>
          <div class="profile-header-title">Edit Profile</div>
          <div class="profile-header-sub">
            Update your professional details. Patients will see this information on your public profile.
          </div>
        </div>
      </div>

      <?php if (function_exists('flash_render')) flash_render(); ?>

      <?php if (!empty($errors)): ?>
        <div class="errors">
          <strong>There were some problems:</strong>
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post">
        <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

        <div class="profile-grid">

          <?php if (isset($doctor_cols['license_no'])): ?>
            <div class="form-group">
              <label for="license_no">License Number</label>
              <input id="license_no" name="license_no" type="text"
                     value="<?= htmlspecialchars($form['license_no'] ?? '') ?>">
              <small>Your registered medical license number.</small>
            </div>
          <?php endif; ?>

          <?php if (isset($doctor_cols['experience'])): ?>
            <div class="form-group">
              <label for="experience">Experience (years)</label>
              <input id="experience" name="experience" type="number" min="0"
                     value="<?= htmlspecialchars($form['experience'] ?? '') ?>">
              <small>Total years of clinical practice.</small>
            </div>
          <?php endif; ?>

          <?php if (isset($doctor_cols['specialty']) || isset($doctor_cols['speciality'])):
            $special_col = isset($doctor_cols['specialty']) ? 'specialty' : 'speciality';
          ?>
            <div class="form-group">
              <label for="<?= htmlspecialchars($special_col) ?>">Specialty</label>
              <input id="<?= htmlspecialchars($special_col) ?>"
                     name="<?= htmlspecialchars($special_col) ?>"
                     type="text"
                     placeholder="e.g., Cardiology, Dermatology"
                     value="<?= htmlspecialchars($form[$special_col] ?? '') ?>">
              <small>Main area of expertise.</small>
            </div>
          <?php endif; ?>

          <?php if (isset($doctor_cols['fee'])): ?>
            <div class="form-group">
              <label for="fee">Consultation Fee (₹)</label>
              <input id="fee" name="fee" type="number" min="0" step="0.01"
                     value="<?= htmlspecialchars($form['fee'] ?? '') ?>">
              <small>Fee per appointment.</small>
            </div>
          <?php endif; ?>

          <?php if (isset($doctor_cols['image'])): ?>
            <div class="form-group">
              <label for="image">Profile Image Path</label>
              <input id="image" name="image" type="text"
                     placeholder="/doctor-appointment/uploads/doctor123.jpg"
                     value="<?= htmlspecialchars($form['image'] ?? '') ?>">
              <small>Usually set automatically when you upload an image.</small>
            </div>
          <?php endif; ?>

          <?php if (isset($doctor_cols['status'])): ?>
            <div class="form-group">
              <label for="status">Status</label>
              <input id="status" name="status" type="text"
                     value="<?= htmlspecialchars($form['status'] ?? '') ?>"
                     readonly>
              <small>This is controlled by the admin.</small>
            </div>
          <?php endif; ?>

          <?php if (isset($doctor_cols['bio'])): ?>
            <div class="form-group" style="grid-column:1 / -1;">
              <label for="bio">Bio / About</label>
              <textarea id="bio" name="bio" rows="5"
                        placeholder="Describe your background, education, and approach to patient care."><?= htmlspecialchars($form['bio'] ?? '') ?></textarea>
            </div>
          <?php endif; ?>

        </div>

        <div class="profile-actions">
          <button type="submit" class="btn-primary">Save changes</button>
          <a href="/doctor-appointment/doctor/dashboard.php" class="btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>
