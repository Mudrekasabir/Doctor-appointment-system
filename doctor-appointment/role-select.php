<?php
// role-select.php - Enhanced role selection page
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $redirect = match($_SESSION['role']) {
        'admin' => '/doctor-appointment/admin/dashboard.php',
        'doctor' => '/doctor-appointment/doctor/dashboard.php',
        default => '/doctor-appointment/patient/dashboard.php'
    };
    header("Location: $redirect");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Role - Doctor Appointment System</title>
    <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 60px;
            color: white;
        }

        .page-header h1 {
            font-size: 42px;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 18px;
            opacity: 0.95;
        }

        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-bottom: 30px;
            font-weight: 500;
            transition: opacity 0.3s ease;
        }

        .back-link:hover {
            opacity: 0.8;
        }

        .roles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .role-card {
            background: white;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .role-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .role-card:hover::before {
            transform: scaleX(1);
        }

        .role-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }

        .role-card h3 {
            font-size: 24px;
            margin-bottom: 12px;
            color: #1f2937;
        }

        .role-card p {
            color: #6b7280;
            margin-bottom: 24px;
            line-height: 1.6;
            min-height: 48px;
        }

        .role-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn {
            display: block;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: 2px solid transparent;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-ghost {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-ghost:hover {
            background: #f3f4f6;
        }

        .admin-note {
            background: #fef3c7;
            color: #92400e;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-top: 12px;
            border-left: 3px solid #f59e0b;
        }

        .features-list {
            text-align: left;
            margin: 16px 0 24px;
            padding-left: 0;
            list-style: none;
        }

        .features-list li {
            padding: 6px 0;
            color: #6b7280;
            font-size: 14px;
        }

        .features-list li::before {
            content: "✓";
            color: #10b981;
            font-weight: bold;
            margin-right: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 32px;
            }

            .page-header p {
                font-size: 16px;
            }

            .roles {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .role-card {
                padding: 30px 20px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .roles {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php 
    // Include header if it exists, otherwise skip
    $header_path = __DIR__ . '/inc/header.php';
    if (file_exists($header_path)) {
        include $header_path;
    }
    ?>

    <div class="container">
        <a href="/doctor-appointment/" class="back-link">← Back to Home</a>
        
        <div class="page-header">
            <h1>Choose Your Role</h1>
            <p>Select how you'd like to access the system</p>
        </div>

        <div class="roles">
            <!-- Patient Card -->
            <div class="role-card">
                <div class="role-icon">👤</div>
                <h3>Patient</h3>
                <p>Book appointments with qualified doctors and manage your healthcare.</p>
                
                <ul class="features-list">
                    <li>Browse doctor profiles</li>
                    <li>Book appointments instantly</li>
                    <li>View appointment history</li>
                </ul>

                <div class="role-actions">
                    <a class="btn btn-primary" href="/doctor-appointment/auth/register_patient.php">
                        Register as Patient
                    </a>
                    <a class="btn btn-ghost" href="/doctor-appointment/auth/login.php?role=patient">
                        Patient Login
                    </a>
                </div>
            </div>

            <!-- Doctor Card -->
            <div class="role-card">
                <div class="role-icon">👨‍⚕️</div>
                <h3>Doctor</h3>
                <p>Manage your practice and connect with patients seeking care.</p>
                
                <ul class="features-list">
                    <li>Set your availability</li>
                    <li>Manage appointments</li>
                    <li>Update your profile</li>
                </ul>

                <div class="role-actions">
                    <a class="btn btn-primary" href="/doctor-appointment/auth/register_doctor.php">
                        Register as Doctor
                    </a>
                    <a class="btn btn-ghost" href="/doctor-appointment/auth/login.php?role=doctor">
                        Doctor Login
                    </a>
                </div>
                
                <div class="admin-note">
                    ⚠️ Admin approval required after registration
                </div>
            </div>

            <!-- Admin Card -->
            <div class="role-card">
                <div class="role-icon">⚙️</div>
                <h3>Administrator</h3>
                <p>Manage the entire platform, users, and system settings.</p>
                
                <ul class="features-list">
                    <li>Approve doctor accounts</li>
                    <li>Manage all users</li>
                    <li>View system reports</li>
                </ul>

                <div class="role-actions">
                    <a class="btn btn-primary" href="/doctor-appointment/auth/login.php?role=admin">
                        Admin Login
                    </a>
                </div>
                
                <div class="admin-note">
                    🔒 Admin accounts are created by system
                </div>
            </div>
        </div>
    </div>

    <?php 
    // Include footer if it exists, otherwise skip
    $footer_path = __DIR__ . '/inc/footer.php';
    if (file_exists($footer_path)) {
        include $footer_path;
    }
    ?>
</body>
</html>