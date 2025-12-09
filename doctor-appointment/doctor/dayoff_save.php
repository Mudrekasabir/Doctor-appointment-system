<?php
// C:\xampp\htdocs\doctor-appointment\doctor\dayoff_save.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/db.php';

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function cslog($msg) {
    global $logDir;
    @file_put_contents($logDir . '/csrf_fail.log', '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Get posted token
$postedToken = $_POST['csrf_token'] ?? null;
$serverToken = $_SESSION['csrf_token'] ?? null;
$sid = session_id();

if (!csrf_validate($postedToken)) {
    cslog("CSRF FAIL on dayoff_save | SID: {$sid} | server_token: ".($serverToken??'(none)')." | posted: ".($postedToken??'(none)'));
    // Give a friendly error and a debug link back
    http_response_code(400);
    echo "CSRF validation failed. Session ID: " . htmlspecialchars($sid) . ". Check logs at logs/csrf_fail.log";
    exit;
}

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

$doctor_id = (int)$_SESSION['user_id'];
$date = trim($_POST['date'] ?? '');

if ($date === '') {
    http_response_code(400);
    echo "Date is required";
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO doctor_dayoffs (doctor_id, date) VALUES (:did, :dt)");
    $stmt->execute([':did' => $doctor_id, ':dt' => $date]);
    header("Location: dayoff.php?added=1");
    exit;
} catch (PDOException $e) {
    cslog("DB error dayoff_save: ".$e->getMessage());
    http_response_code(500);
    echo "Database error: " . htmlspecialchars($e->getMessage());
    exit;
}
