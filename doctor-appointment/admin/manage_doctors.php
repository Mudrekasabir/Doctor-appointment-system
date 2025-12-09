<?php
// admin/manage_doctors.php - list and manage doctors
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$q = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$specialty_filter = $_GET['specialty'] ?? 'all';

$where = "WHERE u.role='doctor'";
$params = [];

if ($q !== '') {
    $where .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR dp.specialty LIKE ?)";
    $like = "%$q%"; 
    $params = [$like, $like, $like, $like];
}

if ($status_filter !== 'all') {
    $where .= " AND dp.status = ?";
    $params[] = $status_filter;
}

if ($specialty_filter !== 'all') {
    $where .= " AND dp.specialty = ?";
    $params[] = $specialty_filter;
}

// Get all specialties for filter
$specialties = $pdo->query("SELECT DISTINCT specialty FROM doctors_profiles WHERE specialty IS NOT NULL ORDER BY specialty")->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("
    SELECT 
        u.id, u.username, u.full_name, u.email, u.contact, u.status as user_status,
        dp.specialty, dp.fee, dp.experience, dp.status as profile_status,
        COUNT(DISTINCT a.id) as appointment_count
    FROM users u 
    LEFT JOIN doctors_profiles dp ON dp.user_id = u.id 
    LEFT JOIN appointments a ON a.doctor_id = u.id
    $where 
    GROUP BY u.id
    ORDER BY u.id DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Get stats
$total_doctors = count($rows);
$active_doctors = count(array_filter($rows, fn($r) => ($r['profile_status'] ?? 'pending') === 'approved'));
$pending_doctors = count(array_filter($rows, fn($r) => ($r['profile_status'] ?? 'pending') === 'pending'));
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Admin Panel</title>
    <link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .page-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .page-title h1 {
            font-size: 28px;
            color: #1f2937;
            font-weight: 700;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
        }

        .toolbar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .toolbar-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            display: flex;
            gap: 8px;
        }

        .search-box input {
            flex: 1;
            padding: 10px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .filter-group select {
            padding: 10px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            background: white;
            transition: all 0.2s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
            font-size: 14px;
        }

        tbody tr {
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .delete-form {
            display: inline;
            margin: 0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.2s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-header h2 {
            margin: 0;
            color: #ef4444;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-body {
            padding: 24px;
            line-height: 1.6;
            color: #4b5563;
        }

        .modal-body p {
            margin-bottom: 12px;
        }

        .modal-body ul {
            margin: 12px 0;
            padding-left: 24px;
        }

        .modal-body li {
            margin-bottom: 8px;
        }

        .doctor-name-highlight {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            padding: 12px;
            background: #f3f4f6;
            border-radius: 8px;
            margin: 16px 0;
        }

        .modal-footer {
            padding: 20px 24px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 12px;
            }

            .page-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .toolbar-row {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 10px 8px;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-sm {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-container">
    <!-- Page Header with Stats -->
    <div class="page-header">
        <div class="page-title">
            <h1>👨‍⚕️ Manage Doctors</h1>
            <a class="btn btn-primary" href="/doctor-appointment/admin/add_doctor.php">
                ➕ Add New Doctor
            </a>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Doctors</h3>
                <div class="stat-value"><?php echo number_format($total_doctors); ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Doctors</h3>
                <div class="stat-value"><?php echo number_format($active_doctors); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Approval</h3>
                <div class="stat-value"><?php echo number_format($pending_doctors); ?></div>
            </div>
        </div>
    </div>

    <?php flash_render(); ?>

    <!-- Toolbar with Search and Filters -->
    <div class="toolbar">
        <form method="get" class="toolbar-row">
            <div class="search-box">
                <input type="text" name="q" placeholder="🔍 Search by name, email, or specialty..." value="<?php echo e($q); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>

            <div class="filter-group">
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>

                <select name="specialty" onchange="this.form.submit()">
                    <option value="all" <?php echo $specialty_filter === 'all' ? 'selected' : ''; ?>>All Specialties</option>
                    <?php foreach ($specialties as $spec): ?>
                        <option value="<?php echo e($spec); ?>" <?php echo $specialty_filter === $spec ? 'selected' : ''; ?>>
                            <?php echo e($spec); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($q !== '' || $status_filter !== 'all' || $specialty_filter !== 'all'): ?>
                <a href="manage_doctors.php" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Doctors Table -->
    <div class="content-card">
        <?php if(empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🩺</div>
                <h3>No doctors found</h3>
                <p>Try adjusting your search filters or add a new doctor.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Doctor Name</th>
                            <th>Email</th>
                            <th>Specialty</th>
                            <th>Experience</th>
                            <th>Fee</th>
                            <th>Appointments</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $r): 
                        $profile_status = $r['profile_status'] ?? 'pending';
                        $badge_class = 'badge-warning';
                        if ($profile_status === 'approved') $badge_class = 'badge-success';
                        elseif ($profile_status === 'rejected') $badge_class = 'badge-danger';
                    ?>
                        <tr>
                            <td><strong>#<?php echo e($r['id']); ?></strong></td>
                            <td>
                                <strong><?php echo e($r['full_name']); ?></strong><br>
                                <small style="color: #9ca3af;">@<?php echo e($r['username']); ?></small>
                            </td>
                            <td><?php echo e($r['email']); ?></td>
                            <td><?php echo e($r['specialty'] ?? '-'); ?></td>
                            <td><?php echo $r['experience'] ? e($r['experience']) . ' years' : '-'; ?></td>
                            <td><?php echo $r['fee'] ? '₹' . number_format($r['fee'], 2) : '-'; ?></td>
                            <td>
                                <span class="badge badge-info"><?php echo number_format($r['appointment_count']); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo e($profile_status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-primary btn-sm" href="/doctor-appointment/admin/edit_doctor.php?id=<?php echo (int)$r['id']; ?>">
                                        ✏️ Edit
                                    </a>
                                    <button type="button" 
                                            class="btn btn-danger btn-sm" 
                                            onclick="showDeleteModal('doctor', <?php echo $r['id']; ?>, '<?php echo addslashes($r['full_name']); ?>', '<?php echo csrf_token()(); ?>')">
                                        🗑️ Delete
                                    </button>
                                    <a class="btn btn-secondary btn-sm" href="/doctor-appointment/admin/appointments.php?doctor_id=<?php echo (int)$r['id']; ?>">
                                        📅 Appts
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>⚠️ Confirm Deletion</h2>
        </div>
        <div class="modal-body" id="deleteModalBody">
            <!-- Dynamic content -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete Permanently</button>
        </div>
    </div>
</div>

<script>
let deleteData = {
    type: '',
    id: 0,
    name: '',
    csrfToken: ''
};

function showDeleteModal(type, id, name, csrfToken) {
    deleteData = { type, id, name, csrfToken };
    
    const modal = document.getElementById('deleteModal');
    const modalBody = document.getElementById('deleteModalBody');
    
    modalBody.innerHTML = `
        <p><strong>You are about to permanently delete this ${type}:</strong></p>
        <div class="doctor-name-highlight">${name}</div>
        <p style="color: #ef4444; font-weight: 600;"><strong>⚠️ Warning:</strong> This action will:</p>
        <ul style="color: #4b5563;">
            <li>Permanently delete this ${type}'s account</li>
            <li>Remove all related appointments</li>
            <li>Delete all associated profile data</li>
            <li>Remove all activity logs</li>
        </ul>
        <p style="margin-top: 16px; font-weight: 600; color: #991b1b;">
            ⚠️ This action cannot be undone!
        </p>
    `;
    
    modal.style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function confirmDelete() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = deleteData.type === 'patient' ? 'delete_patient.php' : 'delete_doctor.php';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = deleteData.csrfToken;
    form.appendChild(csrfInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = deleteData.id;
    form.appendChild(idInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        closeDeleteModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>