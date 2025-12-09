<?php
// auth/force_logout.php - temporary
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// destroy session server-side
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
// redirect to role select
header('Location: /doctor-appointment/role-select.php');
exit;
