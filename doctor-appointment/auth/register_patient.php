<?php
// C:\xampp\htdocs\doctor-appointment\auth\register_patient.php
// Complete ready-to-paste register page with CSRF protection and safe DB insert.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include once to avoid redeclare errors
require_once __DIR__ . '/../inc/csrf.php';

// Try to include DB connection from your project if present.
// If you have an existing DB bootstrap file, adjust the path accordingly.
$incDbPath = __DIR__ . '/../inc/db.php';
if (file_exists($incDbPath)) {
    require_once $incDbPath;
}

// Fallback PDO if your project doesn't provide $pdo (XAMPP default)
if (!isset($pdo) || !$pdo instanceof PDO) {
    $dbHost = '127.0.0.1';
    $dbName = 'doctor_appointment';
    $dbUser = 'root';
    $dbPass = ''; // change if your MySQL root has a password
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo "<h2>Database connection failed</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        exit;
    }
}

// Error/message container
$errors = [];
$success = false;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    $postedToken = $_POST['csrf_token'] ?? null;
    if (!validate_csrf($postedToken)) {
        $errors[] = 'Invalid CSRF token.';
    }

    // Gather and sanitize inputs
    $username  = trim((string)($_POST['username'] ?? ''));
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $email     = trim((string)($_POST['email'] ?? ''));
    $contact   = trim((string)($_POST['contact'] ?? ''));
    $password  = $_POST['password'] ?? '';

    // Basic validation
    if ($username === '') { $errors[] = 'Username required.'; }
    if ($full_name === '') { $errors[] = 'Full name required.'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email required.'; }
    if ($password === '' || strlen($password) < 6) { $errors[] = 'Password required (min 6 chars).'; }

    // If no errors, try to insert user
    if (empty($errors)) {
        try {
            // Check username/email uniqueness
            $checkSql = "SELECT COUNT(*) AS cnt FROM users WHERE username = :username OR email = :email";
            $stmt = $pdo->prepare($checkSql);
            $stmt->execute([':username' => $username, ':email' => $email]);
            $row = $stmt->fetch();
            if ($row && (int)$row['cnt'] > 0) {
                $errors[] = 'Username or email already exists.';
            } else {
                // Insert into users
                $insertUser = "
                    INSERT INTO users (role, username, full_name, email, contact, password_hash, status, created_at)
                    VALUES ('patient', :username, :full_name, :email, :contact, :password_hash, 'active', NOW())
                ";
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare($insertUser);
                $stmt->execute([
                    ':username' => $username,
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':contact' => $contact,
                    ':password_hash' => $password_hash
                ]);

                $newUserId = (int)$pdo->lastInsertId();

                // Optionally create a patients_profiles row if your schema uses it
                // Check if table exists before inserting to avoid errors
                $checkTable = $pdo->query("SHOW TABLES LIKE 'patients_profiles'")->fetchColumn();
                if ($checkTable) {
                    $stmt = $pdo->prepare("INSERT INTO patients_profiles (user_id, created_by) VALUES (:uid, NULL)");
                    $stmt->execute([':uid' => $newUserId]);
                }

                $success = true;
                // Optionally remove the CSRF token after successful form to avoid reuse
                unset($_SESSION['csrf_token']);
                // Redirect to login or dashboard
                header('Location: ../auth/login.php?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Render page (simple layout; integrate into your site's header/footer if needed)
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Register as Patient</title>
<style>
  body { font-family: system-ui, sans-serif; padding: 20px; }
  .error { background: #fcebea; border: 1px solid #f5c6cb; padding: 10px; color: #721c24; margin-bottom: 12px; border-radius: 6px; }
  .success { background: #e6ffed; border: 1px solid #b7f5c9; padding: 10px; color: #0f5132; margin-bottom: 12px; border-radius: 6px; }
  label { display:block; margin: 12px 0 6px; }
  input[type="text"], input[type="email"], input[type="password"] { width: 320px; padding: 8px; }
  button { margin-top: 12px; padding: 10px 16px; border-radius: 8px; background: #1b6bd8; color: white; border: 0; cursor: pointer; }
</style>
</head>
<body>

  <h1>Register as Patient</h1>

  <?php if (!empty($errors)): ?>
    <div class="error">
      <?php foreach ($errors as $err): ?>
        <div><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success">Registration successful. Redirecting...</div>
  <?php endif; ?>

  <form method="post" action="">
    <?php echo csrf_field(); ?>

    <label for="username">Username</label>
    <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>">

    <label for="full_name">Full Name</label>
    <input id="full_name" name="full_name" type="text" value="<?php echo htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES); ?>">

    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>">

    <label for="contact">Contact</label>
    <input id="contact" name="contact" type="text" value="<?php echo htmlspecialchars($_POST['contact'] ?? '', ENT_QUOTES); ?>">

    <label for="password">Password</label>
    <input id="password" name="password" type="password">

    <div>
      <button type="submit">Register</button>
    </div>
  </form>

</body>
</html>
