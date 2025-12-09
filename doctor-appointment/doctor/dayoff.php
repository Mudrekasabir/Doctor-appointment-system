<?php
// C:\xampp\htdocs\doctor-appointment\doctor\dayoff.php
// Page to view/add/delete doctor day-offs. Includes CSRF token and simple debug info.

if (session_status() === PHP_SESSION_NONE) {
    // Explicit cookie params for localhost
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/db.php';

// Check user logged in and role doctor
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') {
    header('HTTP/1.1 403 Forbidden');
    echo "Unauthorized. Please log in as doctor.";
    exit;
}

$doctor_id = (int)$_SESSION['user_id'];

// Fetch dayoffs
$dbErr = null;
$dayoffs = [];
try {
    $stmt = $pdo->prepare("SELECT id, date FROM doctor_dayoffs WHERE doctor_id = :did ORDER BY date DESC");
    $stmt->execute([':did' => $doctor_id]);
    $dayoffs = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbErr = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Day Offs</title>
<style>
  body{font-family:system-ui;padding:18px}
  table{border-collapse:collapse;width:100%;max-width:720px}
  td,th{border:1px solid #ddd;padding:8px}
  th{background:#f6f6f6}
  form.inline{display:inline;margin:0}
  .debug{background:#f3f3f3;padding:8px;border:1px solid #e0e0e0;margin-bottom:12px}
</style>
</head>
<body>

  <h1>My Day Offs</h1>

  <div class="debug">
    <strong>Session ID:</strong> <code id="sessId"><?php echo htmlspecialchars(session_id(), ENT_QUOTES); ?></code><br>
    <strong>Server CSRF token:</strong> <code id="serverCsrf"><?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?></code>
    <p style="margin:8px 0 0;font-size:0.9em;color:#333">
      Open DevTools → Application → Cookies → <code>localhost</code> and confirm <code>PHPSESSID</code> equals the Session ID above.
    </p>
  </div>

  <?php if (!empty($dbErr)): ?>
    <div style="color:red">DB error: <?php echo htmlspecialchars($dbErr, ENT_QUOTES); ?></div>
  <?php endif; ?>

  <h2>Add Day Off</h2>
  <form id="addDayForm" method="post" action="dayoff_save.php">
    <?php echo csrf_field(); ?>
    <label for="d_date">Date</label>
    <input id="d_date" name="date" type="date" required>
    <button type="submit">Add</button>
  </form>

  <h2>Existing Day Offs</h2>
  <?php if (empty($dayoffs)): ?>
    <p>No day offs found.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($dayoffs as $i => $r): ?>
          <tr>
            <td><?php echo $i+1; ?></td>
            <td><?php echo htmlspecialchars($r['date'], ENT_QUOTES); ?></td>
            <td>
              <form class="inline" method="post" action="dayoff_delete.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <button type="submit" onclick="return confirm('Delete this day off?')">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  <?php endif; ?>

<script>
// Debug: print csrf token values in console and confirm hidden input is present
(function(){
  try {
    const serverToken = document.getElementById('serverCsrf')?.textContent || '(none)';
    console.log('Session ID (page):', document.getElementById('sessId')?.textContent || '(none)');
    console.log('Server CSRF token (page):', serverToken);

    // show values of all hidden csrf inputs
    document.querySelectorAll('input[name="csrf_token"]').forEach((el, idx) => {
      console.log('csrf input['+idx+'] value=', el.value);
    });

    // Hook submit to show what is being sent
    document.querySelectorAll('form').forEach(f => {
      f.addEventListener('submit', function(e){
        const fd = new FormData(f);
        const entries = {};
        for (const [k,v] of fd.entries()) entries[k] = v;
        console.log('Submitting form', f.action, entries);
      });
    });
  } catch (err) {
    console.warn('Debug script error', err);
  }
})();
</script>

</body>
</html>
