<?php
// Disable display errors in production - only enable for development
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

// Start session to check if user is already logged in
session_start();

// Redirect logged-in users to their dashboard
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
    <meta name="description" content="Book doctor appointments instantly. Manage your healthcare with our easy-to-use appointment system.">
    <title>Doctor Appointment System - Home</title>
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
            background: #f9fafb;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        h1 {
            font-size: 24px;
            color: #1f2937;
        }
        
        /* Hero Section */
        .hero {
            max-width: 1100px;
            margin: 60px auto;
            padding: 48px;
            border-radius: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
            display: flex;
            gap: 40px;
            align-items: center;
        }
        
        .hero-left {
            flex: 1;
        }
        
        .hero-left h2 {
            font-size: 42px;
            line-height: 1.2;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .hero-left p {
            font-size: 18px;
            opacity: 0.95;
            margin-bottom: 30px;
        }
        
        .hero-right {
            width: 380px;
            text-align: center;
        }
        
        .hero-right img {
            max-width: 100%;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-primary {
            background: white;
            color: #667eea;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255,255,255,0.3);
        }
        
        .btn-secondary {
            background: #0ea5e9;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #0284c7;
        }
        
        /* Features Section */
        .features {
            max-width: 1100px;
            margin: 80px auto;
            padding: 0 20px;
        }
        
        .features h3 {
            text-align: center;
            font-size: 32px;
            margin-bottom: 50px;
            color: #1f2937;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }
        
        .feature-icon {
            font-size: 42px;
            margin-bottom: 16px;
        }
        
        .feature-card h4 {
            font-size: 20px;
            margin-bottom: 12px;
            color: #1f2937;
        }
        
        .feature-card p {
            color: #6b7280;
            line-height: 1.6;
        }
        
        /* Quick Links Section */
        .quick-links {
            max-width: 1100px;
            margin: 60px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .quick-links h3 {
            font-size: 24px;
            margin-bottom: 24px;
            color: #1f2937;
        }
        
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .link-card {
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .link-card h4 {
            font-size: 16px;
            margin-bottom: 12px;
            color: #1f2937;
        }
        
        .link-card a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .link-card a:hover {
            text-decoration: underline;
        }
        
        /* Footer */
        footer {
            background: #1f2937;
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 80px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                padding: 32px 24px;
                margin: 30px 20px;
            }
            
            .hero-left h2 {
                font-size: 32px;
            }
            
            .hero-right {
                width: 100%;
            }
            
            .header-content h1 {
                font-size: 18px;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <h1>🏥 Doctor Appointment System</h1>
            <nav>
                <a href="/doctor-appointment/role-select.php" class="btn btn-secondary">Get Started</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-left">
                <h2>Healthcare Made Simple</h2>
                <p>Book appointments instantly with top doctors. Manage your schedule efficiently with our intuitive platform.</p>
                <a class="btn btn-primary" href="/doctor-appointment/role-select.php">Register / Login →</a>
            </div>
            <div class="hero-right">
                <img src="/doctor-appointment/assets/images/placeholder.png" alt="Doctor Appointment Interface" onerror="this.style.display='none'">
            </div>
        </section>

        <section class="features">
            <h3>Why Choose Our System?</h3>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h4>For Patients</h4>
                    <p>Browse available doctors, view their schedules, and book appointments instantly. Manage all your appointments in one place.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👨‍⚕️</div>
                    <h4>For Doctors</h4>
                    <p>Set your availability, manage patient appointments, and maintain your professional schedule with ease.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚙️</div>
                    <h4>Admin Control</h4>
                    <p>Comprehensive dashboard to manage users, approve doctor registrations, and oversee the entire system.</p>
                </div>
            </div>
        </section>

        <section class="quick-links container">
            <h3>Quick Access Links</h3>
            <div class="links-grid">
                <div class="link-card">
                    <h4>👤 Patient Portal</h4>
                    <p>
                        <a href="/doctor-appointment/auth/register_patient.php">Register as Patient</a><br>
                        <a href="/doctor-appointment/auth/login.php?role=patient">Patient Login</a>
                    </p>
                </div>
                <div class="link-card">
                    <h4>👨‍⚕️ Doctor Portal</h4>
                    <p>
                        <a href="/doctor-appointment/auth/register_doctor.php">Register as Doctor</a><br>
                        <a href="/doctor-appointment/auth/login.php?role=doctor">Doctor Login</a>
                    </p>
                </div>
                <div class="link-card">
                    <h4>⚙️ Admin Portal</h4>
                    <p>
                        <a href="/doctor-appointment/auth/login.php?role=admin">Admin Login</a><br>
                        <a href="/doctor-appointment/admin/dashboard.php">Admin Dashboard</a>
                    </p>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Doctor Appointment System. All rights reserved.</p>
            <p style="margin-top: 8px; opacity: 0.8; font-size: 14px;">Built with care for better healthcare management</p>
        </div>
    </footer>
</body>
</html>