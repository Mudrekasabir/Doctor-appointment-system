<?php
// tools/check_env.php
// Run from CLI: C:\xampp\php\php.exe C:\xampp\htdocs\doctor-appointment\tools\check_env.php
// This script checks that inc/db.php defines $pdo and that helper functions exist.

$out = [];

// Try to load db and functions safely and capture errors
try {
    require __DIR__ . '/../inc/db.php';
    $out[] = 'db_loaded:' . (isset($pdo) && $pdo instanceof PDO ? '1' : '0');
} catch (Throwable $e) {
    $out[] = 'db_error:' . $e->getMessage();
}

try {
    require __DIR__ . '/../inc/functions.php';
    $out[] = 'create_notification:' . (function_exists('create_notification') ? '1' : '0');
    $out[] = 'flash_set:' . (function_exists('flash_set') ? '1' : '0');
    $out[] = 'flash_render:' . (function_exists('flash_render') ? '1' : '0');
} catch (Throwable $e) {
    $out[] = 'functions_error:' . $e->getMessage();
}

try {
    require __DIR__ . '/../inc/csrf.php';
    $out[] = 'csrf_token:' . (function_exists('csrf_token') ? '1' : '0');
    $out[] = 'validate_csrf:' . (function_exists('validate_csrf') ? '1' : '0');
    $out[] = 'csrf_field:' . (function_exists('csrf_field') ? '1' : '0');
} catch (Throwable $e) {
    $out[] = 'csrf_error:' . $e->getMessage();
}

// Print results, one per line
echo implode(PHP_EOL, $out) . PHP_EOL;
