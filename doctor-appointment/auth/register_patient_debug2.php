<?php
// register_patient_debug2.php
// More aggressive CSRF debug: shows rendered HTML source and POST contents.

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) session_start();

// include csrf helper
$csrfPath = __DIR__ . '/../inc/csrf.php';
if (!file_exists($csrfPath)) {
    echo "<h2>Missing csrf helper at: {$csrfPath}</h2>";
    exit;
}
require_once $csrfPath;

function dbg_log($msg) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $file = $logDir . '/csrf_debug.log';
    $line = '[' . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// ensure token exists
$server_token = $_SESSION['csrf_token'] ?? '(none)';
$sess_id = session_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '(none)';
    $is_valid = validate_csrf($postedToken) ? 'VALID' : 'INVALID';

    // raw input
    $raw = file_get_contents('php://input');

    $dump = [
        'session_id' => $sess_id,
        'server_token' => $server_token,
        'posted_token' => $postedToken,
        'validation' => $is_valid,
        '_POST' => $_POST,
        '_REQUEST' => $_REQUEST,
        'raw_input' => $raw,
    ];
    dbg_log("POST DUMP: " . json_encode($dump));
}

// Capture the rendered HTML for the form so we can show exactly what the browser sees.
ob_start();
?>
<form id="debugForm" method="post" action="">
  <?php echo csrf_field(); ?>
  <label for="username">Username</label>
  <input id="username" name="username" type="text" value="debuguser">
  <div style="margin-top:10px"><button type="submit">Submit (POST)</button></div>
</form>
<?php
$renderedForm = ob_get_clean();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CSRF Debug 2</title>
<style>body{font-family:system-ui;padding:16px} pre{background:#f6f8fa;padding:12px;border-radius:6px}</style>
</head>
<body>
  <h1>CSRF Debug 2 — register_patient_debug2.php</h1>

  <h2>Session & token (server)</h2>
  <p>Session ID: <code><?php echo htmlspecialchars($sess_id); ?></code></p>
  <p>Server CSRF token: <code><?php echo htmlspecialchars($server_token); ?></code></p>

  <h2>Rendered form HTML (what we send to browser)</h2>
  <p>Copy this block and confirm the hidden input is present in the browser "View source":</p>
  <pre><?php echo htmlspecialchars($renderedForm); ?></pre>

  <h2>Browser form (live)</h2>
  <?php echo $renderedForm; ?>

  <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <h2>POST result (server)</h2>
    <pre><?php
      $postedToken = $_POST['csrf_token'] ?? '(none)';
      $is_valid = validate_csrf($postedToken) ? 'VALID' : 'INVALID';
      echo "posted_token: " . htmlspecialchars($postedToken) . PHP_EOL . PHP_EOL;
      echo "validation: " . $is_valid . PHP_EOL . PHP_EOL;
      echo "_POST:\n" . htmlspecialchars(var_export($_POST, true)) . PHP_EOL . PHP_EOL;
      echo "_REQUEST:\n" . htmlspecialchars(var_export($_REQUEST, true)) . PHP_EOL . PHP_EOL;
      echo "raw php://input:\n" . htmlspecialchars(file_get_contents('php://input')) . PHP_EOL;
    ?></pre>

    <h3>Last 20 log lines</h3>
    <pre><?php
      $logFile = __DIR__ . '/../logs/csrf_debug.log';
      if (file_exists($logFile)) {
          echo htmlspecialchars(implode("", array_slice(file($logFile), -20)));
      } else {
          echo "Log not found: " . htmlspecialchars($logFile);
      }
    ?></pre>
  <?php else: ?>
    <p>Open this page in a private window. Before submitting, right-click → View Page Source and confirm the hidden input exists inside the `<form>` block above. Then submit and paste the POST result here.</p>

    <h2>Quick checklist (do this before submitting)</h2>
    <ol>
      <li>Open DevTools → Application/Storage → Cookies → localhost. Confirm PHPSESSID exists and equals the Session ID printed above.</li>
      <li>View Page Source and find the `<form id="debugForm">` section. Confirm you see an `<input type="hidden" name="csrf_token" value="...">` line and copy it into your reply if present.</li>
      <li>Disable browser extensions that modify requests (adblockers, privacy extensions) or try a different browser.</li>
      <li>If you use any JS in the real app that manipulates forms, test with this debug page (no custom JS) to rule that out.</li>
    </ol>
  <?php endif; ?>

</body>
</html>
