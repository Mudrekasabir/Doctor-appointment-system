<?php
// register_patient_debug.php
// Debug helper for CSRF token mismatch issues.
// Paste this file into auth/ and open in browser (use private window).

// Ensure session cookies are set predictably
// Set cookie params (adjust domain if not using localhost)
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',        // empty for localhost, change for real domain
    'secure' => false,     // true if using https
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include central csrf helper (use your file)
$csrfPath = __DIR__ . '/../inc/csrf.php';
if (!file_exists($csrfPath)) {
    echo "<h2>Missing csrf helper at: {$csrfPath}</h2>";
    exit;
}
require_once $csrfPath;

// Optional DB include for parity with real page (not required for CSRF test)
$incDb = __DIR__ . '/../inc/db.php';
if (file_exists($incDb)) {
    require_once $incDb;
}

// Log function
function dbg_log($msg) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $file = $logDir . '/csrf_debug.log';
    $line = '[' . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// Show GET info (server token)
$server_token = $_SESSION['csrf_token'] ?? '(none)';
$sess_id = session_id();

// On POST, capture posted token and validation result
$post_token = $_POST['csrf_token'] ?? '(none)';
$is_valid = validate_csrf($post_token) ? 'VALID' : 'INVALID';

// Log both states
dbg_log("SESSION ID: {$sess_id}");
dbg_log("SERVER TOKEN: {$server_token}");
dbg_log("POST TOKEN: {$post_token}");
dbg_log("VALIDATION: {$is_valid}");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CSRF Debug — Register Patient</title>
<style>
  body{font-family:system-ui,sans-serif;padding:18px}
  pre{background:#f6f8fa;padding:12px;border-radius:6px}
  .ok{color:green}.bad{color:crimson}
  label{display:block;margin-top:10px}
</style>
</head>
<body>
  <h1>CSRF Debug — Register Patient</h1>

  <h2>Browser cookie & session</h2>
  <p><strong>Session ID (server):</strong> <code><?php echo htmlspecialchars($sess_id); ?></code></p>
  <p><strong>Server CSRF token:</strong> <code><?php echo htmlspecialchars($server_token); ?></code></p>
  <p>Open devtools → Application (Chrome) / Storage (Firefox) → Cookies → <code>localhost</code> → check <code>PHPSESSID</code> value. It should match the Session ID shown above.</p>

  <h2>Form (will POST to this page)</h2>
  <form method="post" action="">
    <?php echo csrf_field(); ?>
    <label for="username">(test) Username</label>
    <input id="username" name="username" type="text" value="debuguser">
    <div style="margin-top:10px"><button type="submit">Submit (POST)</button></div>
  </form>

  <h2>POST result</h2>
  <pre>
Posted csrf_token: <?php echo htmlspecialchars($post_token); ?>

Validation result: <?php echo ($is_valid === 'VALID') ? '<span class="ok">VALID</span>' : '<span class="bad">INVALID</span>'; ?>
  </pre>

  <h2>Server log (last 40 lines)</h2>
  <pre><?php
    $logFile = __DIR__ . '/../logs/csrf_debug.log';
    if (file_exists($logFile)) {
        echo htmlspecialchars(implode("", array_slice(file($logFile), -40)));
    } else {
        echo "Log file not found: " . htmlspecialchars($logFile);
    }
  ?></pre>

  <h2>Troubleshooting checklist</h2>
  <ol>
    <li>Do you see a <code>PHPSESSID</code> cookie in devtools? If not, your browser blocked cookies. Allow cookies for localhost.</li>
    <li>Does the Session ID in devtools match the Session ID printed above? If not, session cookie is not persisted.</li>
    <li>On GET: do you see a server CSRF token (not '(none)')? If no token, csrf_token() failed to create one.</li>
    <li>On POST: is the posted token identical to the server token? If not identical, your form is not including the right token or another request regenerated/cleared the token between GET and POST.</li>
    <li>If validation shows INVALID but the tokens printed look identical, copy both strings and paste them here — we will compare character-by-character (sometimes whitespace or hidden characters cause mismatch).</li>
  </ol>
</body>
</html>
