<?php
// inc/auth_checks.php
// Purpose: helper functions to enforce role-based access and session user info.

if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /doctor-appointment/role-select.php');
        exit;
    }
}

function require_role($role) {
    require_login();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        // unauthorized -> back to role select
        header('Location: /doctor-appointment/role-select.php');
        exit;
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user_role() {
    return $_SESSION['role'] ?? null;
}
