<?php
// inc/csrf.php - CSRF Token Functions

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a CSRF token and store it in session
 * @return string The generated CSRF token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate a CSRF token hidden input field
 * @return string HTML hidden input field
 */
function csrf_field() {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from request
 * @param string|null $token The token to validate (optional, will read from POST if not provided)
 * @return bool True if valid, false otherwise
 */
function validate_csrf($token = null) {
    // If no token provided, try to get it from POST
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? '';
    }
    
    // Check if session token exists
    if (empty($_SESSION['csrf_token'])) {
        error_log("CSRF validation failed: No session token");
        return false;
    }
    
    // Check if submitted token exists
    if (empty($token)) {
        error_log("CSRF validation failed: No submitted token");
        return false;
    }
    
    // Use hash_equals for timing-safe comparison (PHP 5.6+)
    if (function_exists('hash_equals')) {
        $isValid = hash_equals((string)$_SESSION['csrf_token'], (string)$token);
    } else {
        // Fallback for older PHP versions
        $isValid = ((string)$_SESSION['csrf_token'] === (string)$token);
    }
    
    if (!$isValid) {
        error_log("CSRF validation failed: Token mismatch");
    }
    
    return $isValid;
}

/**
 * Validate CSRF token and exit with error if invalid
 * @param string|null $token The token to validate (optional)
 * @return void
 */
function csrf_verify($token = null) {
    if (!validate_csrf($token)) {
        http_response_code(403);
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}

/**
 * Check if request method is POST and validate CSRF token
 * @return bool True if POST request with valid CSRF token
 */
function is_post_with_valid_csrf() {
    return $_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf();
}

/**
 * Regenerate CSRF token (call after successful form submission)
 * @return string The new CSRF token
 */
function csrf_regenerate() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token meta tag for AJAX requests
 * @return string HTML meta tag
 */
function csrf_meta() {
    $token = csrf_token();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF for AJAX requests (checks X-CSRF-TOKEN header)
 * @return bool True if valid, false otherwise
 */
function validate_csrf_ajax() {
    $token = null;
    
    // Check for token in header
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    // Fallback to POST data
    elseif (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    
    return validate_csrf($token);
}