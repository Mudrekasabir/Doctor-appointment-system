<?php
// admin/requests.php — Doctor pending approval list
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
$csrf_token = csrf_token();

$stmt = $pdo->prepare("
    SELECT 
        u.id, u.username, u.full_name, u.email, u.contact,
        dp.license_no, dp.specialty, dp.bio
    FROM users u 
    JOIN doctors_profiles dp ON u.id = dp.user_id
    WHERE u.role='doctor' AND dp.status='pending'
");
$stmt->execute();
$requests = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Doctor Requests - Admin Panel</title>
  <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
  <style>
    .admin-wrap {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 1rem;
    }
    
    .center-card {
      background: white;
      border-radius: 8px;
      padding: 2rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .center-card h2 {
      margin-top: 0;
      color: #1f2937;
    }
    
    .requests-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1.5rem;
    }
    
    .requests-table th,
    .requests-table td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .requests-table th {
      background: #f9fafb;
      font-weight: 600;
      color: #374151;
    }
    
    .requests-table tbody tr:hover {
      background: #f9fafb;
    }
    
    .btn-approve,
    .btn-reject {
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      margin-right: 8px;
    }
    
    .btn-approve {
      background: #10b981;
      color: white;
    }
    
    .btn-approve:hover {
      background: #059669;
    }
    
    .btn-reject {
      background: #ef4444;
      color: white;
    }
    
    .btn-reject:hover {
      background: #dc2626;
    }
    
    .no-data {
      text-align: center;
      padding: 2rem;
      color: #6b7280;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="admin-wrap">
  <main>
    <div class="center-card">
      <h2>Doctor Registration Requests</h2>
      <p style="color: #6b7280;">Review pending doctor accounts.</p>

      <?php if (empty($requests)): ?>
        <p class="no-data">No pending doctor requests.</p>
      <?php else: ?>

      <table class="requests-table">
        <thead>
          <tr>
            <th>Doctor</th>
            <th>License</th>
            <th>Specialty</th>
            <th>Contact</th>
            <th>Email</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($requests as $r): ?>
            <tr data-id="<?= $r['id']; ?>">
              <td>
                <strong><?= htmlspecialchars($r['full_name']); ?></strong><br>
                @<?= htmlspecialchars($r['username']); ?>
              </td>
              <td><?= htmlspecialchars($r['license_no']); ?></td>
              <td><?= htmlspecialchars($r['specialty']); ?></td>
              <td><?= htmlspecialchars($r['contact']); ?></td>
              <td><?= htmlspecialchars($r['email']); ?></td>
              <td>
                <button class="btn-approve" data-id="<?= $r['id']; ?>">Approve</button>
                <button class="btn-reject" data-id="<?= $r['id']; ?>">Reject</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>

      </table>
      <?php endif; ?>

    </div>
  </main>
</div>

<script>
const CSRF = <?= json_encode($csrf_token); ?>;

async function process(id, action, reason = "") {
    try {
        const form = new URLSearchParams();
        form.append('id', id);
        form.append('action', action);
        form.append('csrf_token', CSRF);
        if (reason) form.append('reason', reason);

        const res = await fetch('/doctor-appointment/admin/approve_doctor.php', {
            method: 'POST',
            body: form,
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        // Check if response is ok
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        // Get response text first
        const text = await res.text();
        
        // Try to parse as JSON
        try {
            const data = JSON.parse(text);
            return data;
        } catch (parseError) {
            console.error('Response was not valid JSON:', text);
            throw new Error('Server returned invalid JSON. Check console for details.');
        }

    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}

document.addEventListener('click', e => {
    if (e.target.classList.contains('btn-approve')) {
        const id = e.target.dataset.id;
        const btn = e.target;
        
        btn.disabled = true;
        btn.textContent = 'Processing...';
        
        process(id, 'approve')
            .then(resp => {
                if (resp.success) {
                    alert('Doctor approved successfully!');
                    location.reload();
                } else {
                    alert(resp.error || 'An error occurred');
                    btn.disabled = false;
                    btn.textContent = 'Approve';
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Network error: ' + err.message);
                btn.disabled = false;
                btn.textContent = 'Approve';
            });
    }

    if (e.target.classList.contains('btn-reject')) {
        const id = e.target.dataset.id;
        const reason = prompt("Reason for rejection:");
        
        if (reason !== null && reason.trim() !== '') {
            const btn = e.target;
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            process(id, 'reject', reason)
                .then(resp => {
                    if (resp.success) {
                        alert('Doctor rejected successfully!');
                        location.reload();
                    } else {
                        alert(resp.error || 'An error occurred');
                        btn.disabled = false;
                        btn.textContent = 'Reject';
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Network error: ' + err.message);
                    btn.disabled = false;
                    btn.textContent = 'Reject';
                });
        }
    }
});
</script>

</body>
</html>