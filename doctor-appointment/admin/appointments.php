<?php
// admin/appointments.php - view all appointments and cancel
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$filter_doc = intval($_GET['doctor_id'] ?? 0);
$filter_patient = intval($_GET['patient_id'] ?? 0);
$filter_date = trim($_GET['date'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$sql = "SELECT a.*, d.full_name AS doctor_name, p.full_name AS patient_name, p.contact AS patient_contact 
        FROM appointments a 
        JOIN users d ON d.id=a.doctor_id 
        JOIN users p ON p.id=a.patient_id 
        WHERE 1=1";
$params = [];
if ($filter_doc) { $sql .= " AND a.doctor_id=?"; $params[] = $filter_doc; }
if ($filter_patient) { $sql .= " AND a.patient_id=?"; $params[] = $filter_patient; }
if ($filter_date) { $sql .= " AND a.date=?"; $params[] = $filter_date; }
if (in_array($filter_status,['booked','cancelled','completed'])) { $sql .= " AND a.status=?"; $params[] = $filter_status; }
$sql .= " ORDER BY a.date DESC, a.start_time DESC LIMIT 1000";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Get stats
$total_appointments = count($rows);
$booked_count = count(array_filter($rows, fn($r) => $r['status'] === 'booked'));
$completed_count = count(array_filter($rows, fn($r) => $r['status'] === 'completed'));
$cancelled_count = count(array_filter($rows, fn($r) => $r['status'] === 'cancelled'));

// get doctors & patients for filters
$docs = $pdo->query("SELECT id,full_name FROM users WHERE role='doctor' ORDER BY full_name")->fetchAll();
$patients = $pdo->query("SELECT id,full_name FROM users WHERE role='patient' ORDER BY full_name")->fetchAll();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Admin Panel</title>
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

        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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

        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filter-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
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

        .badge-booked {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .cancel-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .cancel-form input {
            padding: 6px 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 13px;
            min-width: 150px;
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

        .appointment-time {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #6b7280;
            font-size: 13px;
        }

        .appointment-date {
            font-weight: 600;
            color: #1f2937;
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

            .filter-row {
                grid-template-columns: 1fr;
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

            .cancel-form {
                flex-direction: column;
                width: 100%;
            }

            .cancel-form input {
                width: 100%;
            }
        }

        /* Modal Styles */
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

        .modal-body textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            margin-top: 12px;
        }

        .modal-body textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .appointment-details {
            background: #f3f4f6;
            padding: 16px;
            border-radius: 8px;
            margin: 16px 0;
        }

        .appointment-details p {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }

        .appointment-details strong {
            color: #1f2937;
        }

        .modal-footer {
            padding: 20px 24px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-container">
    <!-- Page Header with Stats -->
    <div class="page-header">
        <div class="page-title">
            <h1>📅 Appointments Management</h1>
            <a class="btn btn-success" href="appointments_create.php">
                ➕ Create New Appointment
            </a>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Appointments</h3>
                <div class="stat-value"><?php echo number_format($total_appointments); ?></div>
            </div>
            <div class="stat-card">
                <h3>Booked</h3>
                <div class="stat-value"><?php echo number_format($booked_count); ?></div>
            </div>
            <div class="stat-card">
                <h3>Completed</h3>
                <div class="stat-value"><?php echo number_format($completed_count); ?></div>
            </div>
            <div class="stat-card">
                <h3>Cancelled</h3>
                <div class="stat-value"><?php echo number_format($cancelled_count); ?></div>
            </div>
        </div>
    </div>

    <?php flash_render(); ?>

    <!-- Filters -->
    <div class="filter-card">
        <div class="filter-title">🔍 Filter Appointments</div>
        <form method="get">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Doctor</label>
                    <select name="doctor_id">
                        <option value="">All Doctors</option>
                        <?php foreach($docs as $d): ?>
                            <option value="<?php echo $d['id'];?>" <?php echo $filter_doc==$d['id']?'selected':'';?>>
                                <?php echo e($d['full_name']);?>
                            </option>
                        <?php endforeach;?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Patient</label>
                    <select name="patient_id">
                        <option value="">All Patients</option>
                        <?php foreach($patients as $p): ?>
                            <option value="<?php echo $p['id'];?>" <?php echo $filter_patient==$p['id']?'selected':'';?>>
                                <?php echo e($p['full_name']);?>
                            </option>
                        <?php endforeach;?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?php echo e($filter_date);?>">
                </div>

                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="booked" <?php echo $filter_status=='booked'?'selected':'';?>>Booked</option>
                        <option value="cancelled" <?php echo $filter_status=='cancelled'?'selected':'';?>>Cancelled</option>
                        <option value="completed" <?php echo $filter_status=='completed'?'selected':'';?>>Completed</option>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary" type="submit">🔍 Apply Filters</button>
                <?php if($filter_doc || $filter_patient || $filter_date || $filter_status): ?>
                    <a class="btn btn-secondary" href="appointments.php">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Appointments Table -->
    <div class="content-card">
        <?php if(empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📅</div>
                <h3>No appointments found</h3>
                <p>Try adjusting your filters or create a new appointment.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Patient</th>
                            <th>Status</th>
                            <th>Cancel Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $r): 
                        $badge_class = 'badge-' . $r['status'];
                        $formatted_date = date('M d, Y', strtotime($r['date']));
                        $formatted_time = date('g:i A', strtotime($r['start_time'])) . ' - ' . date('g:i A', strtotime($r['end_time']));
                    ?>
                        <tr>
                            <td><strong>#<?php echo e($r['id']); ?></strong></td>
                            <td>
                                <div class="appointment-date"><?php echo $formatted_date; ?></div>
                                <div class="appointment-time">🕐 <?php echo $formatted_time; ?></div>
                            </td>
                            <td>
                                <strong><?php echo e($r['doctor_name']); ?></strong>
                            </td>
                            <td>
                                <strong><?php echo e($r['patient_name']); ?></strong><br>
                                <small style="color: #9ca3af;">📞 <?php echo e($r['patient_contact']); ?></small>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo e(ucfirst($r['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($r['cancel_reason']): ?>
                                    <span style="color: #ef4444;"><?php echo e($r['cancel_reason']); ?></span>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if($r['status']=='booked'): ?>
                                        <button type="button" 
                                                class="btn btn-danger btn-sm cancel-appointment-btn" 
                                                data-id="<?php echo (int)$r['id']; ?>"
                                                data-doctor="<?php echo htmlspecialchars($r['doctor_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-patient="<?php echo htmlspecialchars($r['patient_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-date="<?php echo $formatted_date; ?>"
                                                data-time="<?php echo $formatted_time; ?>">
                                            ❌ Cancel
                                        </button>
                                    <?php endif; ?>
                                    <a class="btn btn-secondary btn-sm" href="/doctor-appointment/admin/appointments.php?patient_id=<?php echo (int)$r['patient_id']; ?>">
                                        👤 Patient History
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Appointment Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>⚠️ Cancel Appointment</h2>
        </div>
        <div class="modal-body" id="cancelModalBody">
            <!-- Dynamic content will be inserted here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btnCancelModal">Close</button>
            <button type="button" class="btn btn-danger" id="btnConfirmCancel">Cancel Appointment</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('🚀 Initializing cancel appointment functionality...');
    
    var appointmentToCancel = {
        id: 0,
        doctor: '',
        patient: '',
        date: '',
        time: ''
    };

    var modal = document.getElementById('cancelModal');
    var modalBody = document.getElementById('cancelModalBody');
    var btnCancel = document.getElementById('btnCancelModal');
    var btnConfirm = document.getElementById('btnConfirmCancel');

    if (!modal || !modalBody || !btnCancel || !btnConfirm) {
        console.error('❌ Modal elements not found!');
        return;
    }

    function showCancelModal(id, doctor, patient, date, time) {
        console.log('📋 Opening cancel modal for appointment:', id);
        
        appointmentToCancel = { id: id, doctor: doctor, patient: patient, date: date, time: time };

        modalBody.innerHTML = 
            '<p><strong>You are about to cancel this appointment:</strong></p>' +
            '<div class="appointment-details">' +
            '<p><strong>Patient:</strong> <span>' + escapeHtml(patient) + '</span></p>' +
            '<p><strong>Doctor:</strong> <span>' + escapeHtml(doctor) + '</span></p>' +
            '<p><strong>Date:</strong> <span>' + escapeHtml(date) + '</span></p>' +
            '<p><strong>Time:</strong> <span>' + escapeHtml(time) + '</span></p>' +
            '</div>' +
            '<p><strong>Please provide a reason for cancellation:</strong></p>' +
            '<textarea id="cancelReason" placeholder="Enter cancellation reason (required)" required></textarea>';

        modal.style.display = 'flex';
        modal.classList.add('show');
        modal.setAttribute('data-modal-open', 'true');
        
        setTimeout(function() {
            var textarea = document.getElementById('cancelReason');
            if (textarea) textarea.focus();
        }, 100);
    }

    function hideCancelModal() {
        console.log('🚪 Closing cancel modal');
        modal.style.display = 'none';
        modal.classList.remove('show');
        modal.removeAttribute('data-modal-open');
        appointmentToCancel = { id: 0, doctor: '', patient: '', date: '', time: '' };
    }

    function confirmCancel() {
        var reason = document.getElementById('cancelReason');
        if (!reason || !reason.value.trim()) {
            alert('Please provide a cancellation reason');
            if (reason) reason.focus();
            return;
        }

        if (appointmentToCancel.id <= 0) {
            alert('Error: Invalid appointment ID');
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'appointments_cancel.php';

        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?php echo csrf_token(); ?>';
        form.appendChild(csrfInput);

        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = appointmentToCancel.id;
        form.appendChild(idInput);

        var reasonInput = document.createElement('input');
        reasonInput.type = 'hidden';
        reasonInput.name = 'cancel_reason';
        reasonInput.value = reason.value.trim();
        form.appendChild(reasonInput);

        document.body.appendChild(form);
        console.log('📤 Submitting cancellation form...');
        form.submit();
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    var cancelButtons = document.querySelectorAll('.cancel-appointment-btn');
    console.log('🔍 Found ' + cancelButtons.length + ' cancel buttons');

    for (var i = 0; i < cancelButtons.length; i++) {
        (function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var id = parseInt(button.getAttribute('data-id'), 10);
                var doctor = button.getAttribute('data-doctor');
                var patient = button.getAttribute('data-patient');
                var date = button.getAttribute('data-date');
                var time = button.getAttribute('data-time');
                
                if (id && doctor && patient) {
                    requestAnimationFrame(function() {
                        showCancelModal(id, doctor, patient, date, time);
                    });
                }
                
                return false;
            }, true);
        })(cancelButtons[i]);
    }

    btnCancel.addEventListener('click', function(e) {
        e.preventDefault();
        hideCancelModal();
    });

    btnConfirm.addEventListener('click', function(e) {
        e.preventDefault();
        confirmCancel();
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            hideCancelModal();
        }
    });

    var modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            hideCancelModal();
        }
    });

    console.log('✅ Cancel appointment functionality initialized');
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>