<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// auth/register_doctor.php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['role'])) {
    header("Location: /doctor-appointment/role-select.php");
    exit;
}

$errors = [];
$success = "";

$upload_dir = __DIR__ . '/../uploads';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!validate_csrf($_POST["csrf_token"] ?? "")) {
        $errors[] = "Invalid CSRF token.";
    }

    $username   = trim($_POST["username"]   ?? '');
    $full_name  = trim($_POST["full_name"]  ?? '');
    $email      = trim($_POST["email"]      ?? '');
    $contact    = trim($_POST["contact"]    ?? '');
    $password   = $_POST["password"]        ?? '';
    $license_no = trim($_POST["license_no"] ?? '');
    $specialty  = trim($_POST["specialty"]  ?? '');
    $experience = intval($_POST["experience"] ?? 0);
    $fee        = floatval($_POST["fee"]      ?? 0);
    $bio        = trim($_POST["bio"]        ?? '');

    if ($username === "" || $full_name === "" || $email === "" || 
        $contact === "" || $password === "" || $license_no === "" ||
        $specialty === "" ) {
        $errors[] = "All fields except image are required.";
    }

    // Validate email format
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Validate password strength
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if (empty($errors)) {
        // UNIQUE username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) $errors[] = "Username already exists.";

        // UNIQUE email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = "Email already registered.";
    }

    // Upload image
    $image_path = "/doctor-appointment/assets/images/placeholder.png";
    if (empty($errors) && !empty($_FILES["image"]["name"])) {
        $img = $_FILES["image"];

        if ($img["error"] === UPLOAD_ERR_OK) {
            $allowed = ["image/jpeg", "image/png", "image/jpg"];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $img["tmp_name"]);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                $errors[] = "Image must be JPG or PNG format.";
            } elseif ($img["size"] > 2 * 1024 * 1024) {
                $errors[] = "Image must be less than 2MB.";
            } else {
                $ext      = pathinfo($img["name"], PATHINFO_EXTENSION);
                $filename = "doc-" . uniqid() . "." . $ext;
                $target   = $upload_dir . "/" . $filename;
                if (move_uploaded_file($img["tmp_name"], $target)) {
                    $image_path = "/doctor-appointment/uploads/" . $filename;
                } else {
                    $errors[] = "Failed to upload image.";
                }
            }
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO users (role, username, full_name, email, contact, password_hash, status, created_at)
                VALUES ('doctor', ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$username, $full_name, $email, $contact, $hash]);

            $doctor_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO doctors_profiles
                (user_id, license_no, experience, specialty, fee, bio, image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$doctor_id, $license_no, $experience, $specialty, $fee, $bio, $image_path]);

            $pdo->commit();

            $_SESSION['flash_success'] = "Registration submitted successfully! Please wait for admin approval.";
            header("Location: /doctor-appointment/auth/login.php?role=doctor");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed. Please try again.";
            error_log("Doctor registration error: " . $e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Registration - Doctor Appointment System</title>
<link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
<style>
/* same CSS as before */
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.register-container {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    max-width: 700px;
    width: 100%;
    padding: 40px;
    animation: slideUp 0.4s ease;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0);   }
}
.register-header {
    text-align: center;
    margin-bottom: 30px;
}
.register-header h2 {
    margin: 0 0 8px 0;
    color: #1f2937;
    font-size: 28px;
    font-weight: 700;
}
.register-header p {
    color: #6b7280;
    font-size: 14px;
    margin: 0;
}
.alert {
    padding: 14px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.5;
}
.alert-error {
    background: #fee;
    border: 1px solid #fcc;
    color: #c00;
}
.alert-success {
    background: #d1fae5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.form-group { display:flex; flex-direction:column; }
.form-group.full-width { grid-column:1 / -1; }
.form-group label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    display:flex;
    align-items:center;
    gap:4px;
}
.form-group label .required { color:#ef4444; }
.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"],
.form-group input[type="number"],
.form-group textarea {
    padding: 12px 14px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
    font-family: inherit;
}
.form-group input:focus,
.form-group textarea:focus {
    outline:none;
    border-color:#667eea;
    box-shadow:0 0 0 3px rgba(102,126,234,0.1);
}
.form-group textarea {
    min-height:80px;
    resize:vertical;
}
.file-upload-wrapper {
    position:relative;
    overflow:hidden;
    display:inline-block;
    width:100%;
}
.file-upload-wrapper input[type="file"] {
    position:absolute;
    left:-9999px;
}
.file-upload-label {
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:12px 14px;
    border:2px dashed #d1d5db;
    border-radius:8px;
    cursor:pointer;
    transition:all 0.2s;
    font-size:14px;
    color:#6b7280;
    background:#f9fafb;
}
.file-upload-label:hover {
    border-color:#667eea;
    background:#f3f4f6;
}
.file-name {
    margin-top:6px;
    font-size:13px;
    color:#059669;
    font-weight:500;
}
.submit-btn {
    width:100%;
    padding:14px;
    background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color:#fff;
    border:none;
    border-radius:8px;
    font-size:16px;
    font-weight:600;
    cursor:pointer;
    transition:transform 0.2s, box-shadow 0.2s;
    margin-top:10px;
}
.submit-btn:hover {
    transform:translateY(-2px);
    box-shadow:0 10px 25px rgba(102,126,234,0.4);
}
.submit-btn:active { transform:translateY(0); }
.footer-links {
    text-align:center;
    margin-top:24px;
    padding-top:24px;
    border-top:1px solid #e5e7eb;
}
.footer-links a {
    color:#667eea;
    text-decoration:none;
    font-size:14px;
    font-weight:500;
}
.footer-links a:hover { text-decoration:underline; }
.help-text {
    font-size:12px;
    color:#6b7280;
    margin-top:4px;
}
@media (max-width:640px){
    .form-grid { grid-template-columns:1fr; }
    .register-container { padding:30px 20px; }
}
</style>
</head>
<body>

<div class="register-container">
    <div class="register-header">
        <h2>🩺 Doctor Registration</h2>
        <p>Join our platform and start helping patients</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>⚠️ Please fix the following errors:</strong><br>
        <?php echo implode("<br>", array_map('htmlspecialchars', $errors)); ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <strong>✓ <?php echo htmlspecialchars($success); ?></strong>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="doctorForm">
        <?php echo csrf_field(); ?>

        <div class="form-grid">
            <div class="form-group">
                <label>Username <span class="required">*</span></label>
                <input type="text" name="username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>" required>
                <div class="help-text">Choose a unique username</div>
            </div>

            <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="full_name"
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES); ?>" required>
            </div>

            <div class="form-group">
                <label>Email <span class="required">*</span></label>
                <input type="email" name="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>" required>
            </div>

            <div class="form-group">
                <label>Contact Number <span class="required">*</span></label>
                <input type="text" name="contact"
                       value="<?php echo htmlspecialchars($_POST['contact'] ?? '', ENT_QUOTES); ?>" required>
            </div>

            <div class="form-group">
                <label>Password <span class="required">*</span></label>
                <input type="password" name="password" required minlength="6">
                <div class="help-text">At least 6 characters</div>
            </div>

            <div class="form-group">
                <label>Medical License No. <span class="required">*</span></label>
                <input type="text" name="license_no"
                       value="<?php echo htmlspecialchars($_POST['license_no'] ?? '', ENT_QUOTES); ?>" required>
            </div>

            <div class="form-group">
                <label>Specialty <span class="required">*</span></label>
                <input type="text" name="specialty"
                       value="<?php echo htmlspecialchars($_POST['specialty'] ?? '', ENT_QUOTES); ?>" required
                       placeholder="e.g., Cardiology, Dermatology">
            </div>

            <div class="form-group">
                <label>Experience (Years)</label>
                <input type="number" name="experience"
                       value="<?php echo htmlspecialchars($_POST['experience'] ?? '0', ENT_QUOTES); ?>"
                       min="0" max="50">
            </div>

            <div class="form-group">
                <label>Consultation Fee (₹)</label>
                <input type="number" name="fee"
                       value="<?php echo htmlspecialchars($_POST['fee'] ?? '0', ENT_QUOTES); ?>"
                       min="0" step="0.01">
                <div class="help-text">Amount in Indian Rupees</div>
            </div>

            <div class="form-group full-width">
                <label>Short Bio</label>
                <textarea name="bio"
                          placeholder="Tell patients about yourself, your qualifications, and experience..."><?php
                    echo htmlspecialchars($_POST['bio'] ?? '', ENT_QUOTES);
                ?></textarea>
            </div>

            <div class="form-group full-width">
                <label>Profile Image (Optional)</label>
                <div class="file-upload-wrapper">
                    <input type="file" name="image" id="imageUpload" accept="image/jpeg,image/png,image/jpg">
                    <label for="imageUpload" class="file-upload-label">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Choose Profile Image
                    </label>
                </div>
                <div class="file-name" id="fileName"></div>
                <div class="help-text">JPG or PNG, max 2MB</div>
            </div>
        </div>

        <button type="submit" class="submit-btn">Register as Doctor</button>
    </form>

    <div class="footer-links">
        <a href="/doctor-appointment/auth/login.php">← Already have an account? Login</a>
    </div>
</div>

<script>
document.getElementById('imageUpload').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileNameDiv = document.getElementById('fileName');
    if (fileName) {
        fileNameDiv.textContent = '✓ Selected: ' + fileName;
    } else {
        fileNameDiv.textContent = '';
    }
});

// Simple client-side validation
document.getElementById('doctorForm').addEventListener('submit', function(e) {
    const password = this.querySelector('[name="password"]').value;
    const email    = this.querySelector('[name="email"]').value;

    if (password.length < 6) {
        alert('Password must be at least 6 characters long');
        e.preventDefault();
        return false;
    }

    if (!email.includes('@')) {
        alert('Please enter a valid email address');
        e.preventDefault();
        return false;
    }
});
</script>

</body>
</html>
