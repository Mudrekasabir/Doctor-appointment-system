<?php
// admin/manage_patients.php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$q = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'all';

$where = "WHERE u.role='patient'";
$params = [];

if ($q !== '') {
    $where .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.contact LIKE ?)";
    $like = "%$q%"; 
    $params = [$like, $like, $like, $like];
}

if ($status_filter !== 'all') {
    $where .= " AND u.status = ?";
    $params[] = $status_filter;
}

$stmt = $pdo->prepare("
    SELECT 
        u.id, u.username, u.full_name, u.email, u.contact, u.status, u.created_at,
        COUNT(DISTINCT a.id) as appointment_count,
        MAX(a.date) as last_appointment_date
    FROM users u 
    LEFT JOIN appointments a ON a.patient_id = u.id
    $where 
    GROUP BY u.id
    ORDER BY u.id DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Get stats
$total_patients = count($rows);
$active_patients = count(array_filter($rows, fn($r) => $r['status'] === 'active'));
$patients_with_appointments = count(array_filter($rows, fn($r) => $r['appointment_count'] > 0));
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - Admin Panel</title>
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
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
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
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
            border-color: #3b82f6;
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
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
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
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
            animation: fadeIn 0.2s;
            justify-content: center;
            align-items: center;
            overflow: auto;
        }

        .modal.show {
            display: flex !important;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0;
                backdrop-filter: blur(0px);
            }
            to { 
                opacity: 1;
                backdrop-filter: blur(3px);
            }
        }

        .modal-content {
            background-color: white;
            margin: 20px;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideIn 0.3s;
            position: relative;
            z-index: 10000;
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

        .patient-name-highlight {
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

        .patient-info-detail {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
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
            <h1>👥 Manage Patients</h1>
            <a class="btn btn-primary" href="/doctor-appointment/admin/add_patient.php">
                ➕ Add New Patient
            </a>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Patients</h3>
                <div class="stat-value"><?php echo number_format($total_patients); ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Patients</h3>
                <div class="stat-value"><?php echo number_format($active_patients); ?></div>
            </div>
            <div class="stat-card">
                <h3>With Appointments</h3>
                <div class="stat-value"><?php echo number_format($patients_with_appointments); ?></div>
            </div>
        </div>
    </div>

    <?php flash_render(); ?>

    <!-- Toolbar with Search and Filters -->
    <div class="toolbar">
        <form method="get" class="toolbar-row">
            <div class="search-box">
                <input type="text" name="q" placeholder="🔍 Search by name, email, username, or contact..." value="<?php echo e($q); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>

            <div class="filter-group">
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <?php if ($q !== '' || $status_filter !== 'all'): ?>
                <a href="manage_patients.php" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Patients Table -->
    <div class="content-card">
        <?php if(empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">👤</div>
                <h3>No patients found</h3>
                <p>Try adjusting your search filters or add a new patient.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Appointments</th>
                            <th>Last Visit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $r): 
                        $status = $r['status'];
                        $badge_class = $status === 'active' ? 'badge-success' : 'badge-warning';
                    ?>
                        <tr>
                            <td><strong>#<?php echo e($r['id']); ?></strong></td>
                            <td>
                                <strong><?php echo e($r['full_name']); ?></strong><br>
                                <small style="color: #9ca3af;">@<?php echo e($r['username']); ?></small>
                            </td>
                            <td><?php echo e($r['email']); ?></td>
                            <td><?php echo e($r['contact']); ?></td>
                            <td>
                                <span class="badge badge-info"><?php echo number_format($r['appointment_count']); ?></span>
                            </td>
                            <td>
                                <?php if ($r['last_appointment_date']): ?>
                                    <?php echo date('M d, Y', strtotime($r['last_appointment_date'])); ?>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo e($status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-primary btn-sm" href="/doctor-appointment/admin/edit_patient.php?id=<?php echo (int)$r['id']; ?>">
                                        ✏️ Edit
                                    </a>
                                    <button type="button" 
                                            class="btn btn-danger btn-sm delete-patient-btn" 
                                            data-patient-id="<?php echo (int)$r['id']; ?>"
                                            data-patient-name="<?php echo htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-patient-appointments="<?php echo (int)$r['appointment_count']; ?>">
                                        🗑️ Delete
                                    </button>
                                    <a class="btn btn-secondary btn-sm" href="/doctor-appointment/admin/appointments.php?patient_id=<?php echo (int)$r['id']; ?>">
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
            <!-- Dynamic content will be inserted here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btnCancelDelete">Cancel</button>
            <button type="button" class="btn btn-danger" id="btnConfirmDelete">Delete Permanently</button>
        </div>
    </div>
</div>

<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('========================================');
    console.log('🚀 DELETE MODAL INITIALIZATION START');
    console.log('========================================');
    
    // Store the patient data to delete
    var patientToDelete = {
        id: 0,
        name: '',
        appointments: 0
    };

    // Get modal elements
    var modal = document.getElementById('deleteModal');
    var modalBody = document.getElementById('deleteModalBody');
    var btnCancel = document.getElementById('btnCancelDelete');
    var btnConfirm = document.getElementById('btnConfirmDelete');

    if (!modal || !modalBody || !btnCancel || !btnConfirm) {
        console.error('❌ FATAL: Modal elements not found!');
        return;
    }

    console.log('✅ All modal elements found');

    // Function to show the modal
    function showDeleteModal(patientId, patientName, appointmentCount) {
        console.log('========================================');
        console.log('📋 SHOWING DELETE MODAL');
        console.log('Patient ID:', patientId);
        console.log('Patient Name:', patientName);
        console.log('Appointments:', appointmentCount);
        console.log('========================================');
        
        // Store patient data
        patientToDelete.id = patientId;
        patientToDelete.name = patientName;
        patientToDelete.appointments = appointmentCount;

        // Create safe HTML content
        var tempDiv = document.createElement('div');
        tempDiv.textContent = patientName;
        var safeNameHTML = tempDiv.innerHTML;

        // Build modal content
        modalBody.innerHTML = 
            '<p><strong>You are about to permanently delete this patient:</strong></p>' +
            '<div class="patient-name-highlight">' + safeNameHTML + '</div>' +
            '<p class="patient-info-detail">This patient has <strong>' + appointmentCount + '</strong> appointment(s) on record.</p>' +
            '<p style="color: #ef4444; font-weight: 600; margin-top: 16px;"><strong>⚠️ Warning:</strong> This action will:</p>' +
            '<ul style="color: #4b5563;">' +
            '<li>Permanently delete this patient\'s account</li>' +
            '<li>Remove all ' + appointmentCount + ' appointment(s)</li>' +
            '<li>Delete all medical history and records</li>' +
            '<li>Remove all activity logs</li>' +
            '</ul>' +
            '<p style="margin-top: 16px; font-weight: 600; color: #991b1b;">⚠️ This action cannot be undone!</p>';

        // FORCE modal to display using every method possible
        modal.style.cssText = 'display: flex !important; visibility: visible !important; opacity: 1 !important; pointer-events: auto !important; position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; justify-content: center !important; align-items: center !important; z-index: 9999 !important;';
        modal.classList.add('show');
        modal.removeAttribute('hidden');
        
        // Prevent any automatic closing
        modal.setAttribute('data-modal-open', 'true');
        
        console.log('✅ Modal should now be visible');
        console.log('Modal display style:', modal.style.display);
        console.log('Modal computed display:', window.getComputedStyle(modal).display);
        console.log('Modal has show class:', modal.classList.contains('show'));
        
        // Extra safety: check if modal is still visible after 100ms
        setTimeout(function() {
            if (modal.style.display !== 'flex' || !modal.classList.contains('show')) {
                console.error('⚠️ Modal was closed automatically! Re-opening...');
                modal.style.cssText = 'display: flex !important; visibility: visible !important; opacity: 1 !important; position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; justify-content: center !important; align-items: center !important; z-index: 9999 !important;';
                modal.classList.add('show');
            } else {
                console.log('✅ Modal still visible after 100ms');
            }
        }, 100);
    }

    // Function to hide the modal
    function hideDeleteModal() {
        console.log('🚪 Hiding modal');
        
        // Only hide if it's safe to do so
        if (modal.getAttribute('data-modal-open') === 'true') {
            modal.removeAttribute('data-modal-open');
        }
        
        modal.style.display = 'none';
        modal.style.visibility = 'hidden';
        modal.style.opacity = '0';
        modal.classList.remove('show');
        patientToDelete = { id: 0, name: '', appointments: 0 };
    }

    // Function to confirm deletion
    function confirmDelete() {
        console.log('🗑️ Confirming deletion for patient ID:', patientToDelete.id);
        
        if (!patientToDelete.id || patientToDelete.id <= 0) {
            alert('Error: Invalid patient ID');
            return;
        }

        // Create and submit form
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_patient.php';

        // Add CSRF token
        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?php echo csrf_token(); ?>';
        form.appendChild(csrfInput);

        // Add patient ID
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = patientToDelete.id;
        form.appendChild(idInput);

        // Submit form
        document.body.appendChild(form);
        console.log('📤 Submitting deletion form...');
        form.submit();
    }

    // Attach event listeners to delete buttons
    var deleteButtons = document.querySelectorAll('.delete-patient-btn');
    console.log('🔍 Found ' + deleteButtons.length + ' delete buttons');

    for (var i = 0; i < deleteButtons.length; i++) {
        (function(button) {
            button.addEventListener('click', function(e) {
                // Stop ALL event handling IMMEDIATELY
                if (e) {
                    if (e.preventDefault) e.preventDefault();
                    if (e.stopPropagation) e.stopPropagation();
                    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                }
                
                console.log('========================================');
                console.log('🖱️ DELETE BUTTON CLICKED');
                console.log('========================================');
                
                var patientId = parseInt(button.getAttribute('data-patient-id'), 10);
                var patientName = button.getAttribute('data-patient-name');
                var appointmentCount = parseInt(button.getAttribute('data-patient-appointments'), 10) || 0;
                
                console.log('Retrieved data:', { patientId: patientId, patientName: patientName, appointmentCount: appointmentCount });
                
                if (patientId && patientName) {
                    // Use requestAnimationFrame to ensure modal opens on next frame
                    requestAnimationFrame(function() {
                        showDeleteModal(patientId, patientName, appointmentCount);
                    });
                } else {
                    console.error('❌ Missing patient data');
                    alert('Error: Could not retrieve patient information');
                }
                
                return false;
            }, true); // Capture phase
        })(deleteButtons[i]);
    }

    // Cancel button
    btnCancel.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        hideDeleteModal();
        return false;
    });

    // Confirm button
    btnConfirm.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        confirmDelete();
        return false;
    });

    // Close on outside click
    modal.addEventListener('click', function(e) {
        if (e.target === modal && modal.getAttribute('data-modal-open') === 'true') {
            e.preventDefault();
            e.stopPropagation();
            hideDeleteModal();
        }
    });
    
    // Prevent clicks inside modal content from closing
    var modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // CRITICAL: Block all document clicks when modal is open
    document.addEventListener('click', function(e) {
        if (modal.getAttribute('data-modal-open') === 'true') {
            // Check if click is outside modal
            if (!modal.contains(e.target)) {
                console.log('⚠️ Click detected outside modal - blocking');
                e.stopPropagation();
                e.preventDefault();
            }
        }
    }, true); // Capture phase to catch first

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if (modal.classList.contains('show') || modal.style.display === 'block') {
                hideDeleteModal();
            }
        }
    });

    console.log('========================================');
    console.log('✅ DELETE MODAL INITIALIZATION COMPLETE');
    console.log('========================================');
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>