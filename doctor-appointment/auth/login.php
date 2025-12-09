<?php
// auth/login.php - Enhanced login with improved security
session_start();

// Prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

require_once __DIR__ . '/../inc/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = match($_SESSION['role']) {
        'admin' => '../admin/dashboard.php',
        'doctor' => '../doctor/dashboard.php',
        default => '../patient/dashboard.php'
    };
    header("Location: $redirect");
    exit;
}

$role = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : '';
$allowed_roles = ['admin', 'doctor', 'patient'];

// Validate role parameter
if ($role && !in_array($role, $allowed_roles)) {
    header('Location: ../role-select.php');
    exit;
}

$error = '';
$username_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection check
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_post = strtolower(trim($_POST['role'] ?? ''));
        
        $username_value = htmlspecialchars($username);

        // Input validation
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } elseif (!in_array($role_post, $allowed_roles)) {
            $error = 'Invalid role selected.';
        } else {
            try {
                // For doctors, also check their profile status
                if ($role_post === 'doctor') {
                    $stmt = $pdo->prepare("
                        SELECT u.id, u.username, u.role, u.password_hash, u.status, 
                               dp.status as profile_status
                        FROM users u
                        LEFT JOIN doctors_profiles dp ON u.id = dp.user_id
                        WHERE u.username = ? AND u.role = 'doctor'
                        LIMIT 1
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        SELECT id, username, role, password_hash, status 
                        FROM users 
                        WHERE username = ? 
                        LIMIT 1
                    ");
                }
                
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($role_post !== strtolower($user['role'])) {
                        $error = 'Role mismatch. Please select the correct login portal.';
                    }
                    elseif ($role_post === 'doctor' && isset($user['profile_status'])) {
                        if ($user['profile_status'] === 'pending') {
                            $error = 'Your doctor account is pending approval by an administrator. Please wait for approval.';
                        } elseif ($user['profile_status'] === 'rejected') {
                            $error = 'Your doctor registration has been rejected. Please contact support for more information.';
                        } elseif ($user['profile_status'] === 'approved' && $user['status'] === 'active') {
                            session_regenerate_id(true);
                            
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['last_activity'] = time();

                            header("Location: ../doctor/dashboard.php");
                            exit;
                        } else {
                            $error = 'Your account status is invalid. Please contact support.';
                        }
                    }
                    elseif ($user['status'] !== 'active') {
                        $error = 'Your account is currently ' . htmlspecialchars($user['status']) . '. Please contact support.';
                    }
                    else {
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();

                        $redirect = match($user['role']) {
                            'admin' => '../admin/dashboard.php',
                            'doctor' => '../doctor/dashboard.php',
                            default => '../patient/dashboard.php'
                        };
                        
                        header("Location: $redirect");
                        exit;
                    }
                } else {
                    $error = 'Invalid username or password.';
                    sleep(1);
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'A system error occurred. Please try again later.';
            }
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Role-specific colors and icons
$role_config = [
    'admin' => ['color' => '#8b5cf6', 'icon' => '👨‍💼', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
    'doctor' => ['color' => '#3b82f6', 'icon' => '👨‍⚕️', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
    'patient' => ['color' => '#10b981', 'icon' => '🧑‍🤝‍🧑', 'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'],
];
$current_config = $role_config[$role] ?? ['color' => '#4a90e2', 'icon' => '🏥', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login<?php echo $role ? ' - ' . ucfirst(htmlspecialchars($role)) : ''; ?> | Healthcare System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 440px;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .login-header {
            background: <?php echo $current_config['gradient']; ?>;
            padding: 40px 32px;
            text-align: center;
            color: white;
        }
        
        .role-icon {
            font-size: 48px;
            margin-bottom: 12px;
            display: inline-block;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .login-header h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .login-header p {
            font-size: 15px;
            opacity: 0.95;
            font-weight: 400;
        }
        
        .login-body {
            padding: 40px 32px;
        }
        
        .alert {
            padding: 14px 16px;
            margin-bottom: 24px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            display: flex;
            align-items: start;
            gap: 10px;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        
        .alert-error::before {
            content: '⚠️';
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #374151;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 20px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: <?php echo $current_config['color']; ?>;
            box-shadow: 0 0 0 4px <?php echo $current_config['color']; ?>20;
        }
        
        .form-group input::placeholder {
            color: #9ca3af;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: <?php echo $current_config['gradient']; ?>;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px <?php echo $current_config['color']; ?>40;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px <?php echo $current_config['color']; ?>50;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        
        .back-link {
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: <?php echo $current_config['color']; ?>;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            font-size: 13px;
            color: #9ca3af;
        }
        
        .security-badge::before {
            content: '🔒';
        }
        
        @media (max-width: 480px) {
            .login-header {
                padding: 32px 24px;
            }
            
            .login-body {
                padding: 32px 24px;
            }
            
            .login-header h2 {
                font-size: 24px;
            }
        }
        
        /* Password visibility toggle */
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            padding: 4px;
            color: #9ca3af;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="role-icon"><?php echo $current_config['icon']; ?></div>
                <h2>Welcome Back!</h2>
                <p>Sign in as <?php echo $role ? ucfirst(htmlspecialchars($role)) : 'User'; ?></p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert">
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <span class="input-icon">👤</span>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                value="<?php echo $username_value; ?>"
                                placeholder="Enter your username"
                                autocomplete="username"
                                required 
                                autofocus
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔑</span>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword()">👁️</button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">Sign In</button>
                </form>
                
                <div class="login-footer">
                    <a href="../role-select.php" class="back-link">
                        <span>←</span>
                        <span>Back to role selection</span>
                    </a>
                </div>
                
                <div class="security-badge">
                    Secure connection enabled
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '👁️‍🗨️';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }
        
        // Add loading state on form submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = document.querySelector('.btn-login');
            btn.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite;">⏳</span> Signing in...';
            btn.disabled = true;
        });
    </script>
    
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</body>
</html>