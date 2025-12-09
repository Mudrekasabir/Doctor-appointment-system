<?php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

$doctor_id = current_user_id();

// Check if tables exist, if not create them
$tables_exist = true;

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'doctor_date_availability'");
    $result = $stmt->fetch();
    if (!$result) {
        $tables_exist = false;
        $pdo->exec("
            CREATE TABLE doctor_date_availability (
                id INT PRIMARY KEY AUTO_INCREMENT,
                doctor_id INT NOT NULL,
                availability_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                is_available TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_doctor_date (doctor_id, availability_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
} catch (PDOException $e) {
    // Handle error silently or log it
}

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'doctor_unavailable_dates'");
    $result = $stmt->fetch();
    if (!$result) {
        $tables_exist = false;
        $pdo->exec("
            CREATE TABLE doctor_unavailable_dates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                doctor_id INT NOT NULL,
                unavailable_date DATE NOT NULL,
                reason VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_doctor_date (doctor_id, unavailable_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
} catch (PDOException $e) {
    // Handle error silently or log it
}

// Fetch date-specific availability (next 90 days)
$stmt = $pdo->prepare("
    SELECT id, availability_date, start_time, end_time, is_available
    FROM doctor_date_availability
    WHERE doctor_id=? AND availability_date >= CURDATE() AND availability_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ORDER BY availability_date, start_time
");
$stmt->execute([$doctor_id]);
$date_rows = $stmt->fetchAll();

// Group by date
$dates = [];
foreach($date_rows as $r){ 
    $dates[$r['availability_date']][] = $r; 
}

// Fetch unavailable dates
$stmt = $pdo->prepare("
    SELECT unavailable_date, reason
    FROM doctor_unavailable_dates
    WHERE doctor_id=? AND unavailable_date >= CURDATE()
    ORDER BY unavailable_date
");
$stmt->execute([$doctor_id]);
$unavailable = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Availability</title>
<link rel="stylesheet" href="/doctor-appointment/assets/css/style.css">

<style>
/* Card wrapper */
.avail-card {
    background:#fff;
    padding:24px;
    border-radius:16px;
    box-shadow:0 15px 40px rgba(15,23,42,0.08);
    margin-bottom:25px;
    max-width:900px;
}

.avail-header {
    font-size:20px;
    font-weight:700;
    margin-bottom:10px;
    color:#0f172a;
}

.small-muted {
    font-size:13px;
    color:#6b7280;
    margin-bottom:14px;
}

/* Tabs */
.tabs {
    display:flex;
    gap:8px;
    margin-bottom:24px;
    border-bottom:2px solid #e5e7eb;
}

.tab-btn {
    padding:12px 24px;
    background:none;
    border:none;
    border-bottom:3px solid transparent;
    cursor:pointer;
    font-size:15px;
    font-weight:600;
    color:#6b7280;
    transition:0.2s;
    margin-bottom:-2px;
}

.tab-btn.active {
    color:#2563eb;
    border-bottom-color:#2563eb;
}

.tab-btn:hover {
    color:#1e40af;
}

.tab-content {
    display:none;
}

.tab-content.active {
    display:block;
}

/* Date availability section */
.date-selector {
    margin-bottom:20px;
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
}

.date-selector input[type="date"] {
    padding:10px 12px;
    border-radius:8px;
    border:1px solid #d1d5db;
    font-size:14px;
    background:#f9fafb;
}

.date-selector button {
    background:#2563eb;
    color:#fff;
    padding:10px 16px;
    border:none;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition:0.2s;
}

.date-selector button:hover {
    background:#1e40af;
}

.date-block {
    margin-bottom:30px;
    padding:16px;
    background:#f9fafb;
    border-radius:12px;
    border:1px solid #e5e7eb;
}

.date-title {
    font-size:16px;
    font-weight:600;
    color:#1f2937;
    margin-bottom:12px;
}

/* time range input row */
.time-row {
    display:flex;
    gap:12px;
    margin-bottom:10px;
    align-items:center;
}

.time-row input[type="time"] {
    padding:10px 12px;
    border-radius:8px;
    border:1px solid #d1d5db;
    font-size:14px;
    background:#fff;
    transition:.2s;
    flex:1;
    min-width:120px;
}

.time-row input:focus {
    outline:none;
    background:#fff;
    border-color:#2563eb;
    box-shadow:0 0 0 3px rgba(37,99,235,0.2);
}

/* Remove button */
.remove-btn {
    background:#fee;
    color:#dc2626;
    padding:8px 12px;
    font-size:13px;
    border-radius:8px;
    border:1px solid #fecaca;
    cursor:pointer;
    transition:0.2s;
    white-space:nowrap;
}

.remove-btn:hover {
    background:#fecaca;
}

.remove-date-btn {
    background:#fee;
    color:#dc2626;
    padding:6px 12px;
    font-size:12px;
    border-radius:6px;
    border:1px solid #fecaca;
    cursor:pointer;
    transition:0.2s;
}

.remove-date-btn:hover {
    background:#fecaca;
}

/* Add button */
.add-btn {
    background:#eef2ff;
    color:#374151;
    padding:8px 12px;
    font-size:13px;
    border-radius:8px;
    border:1px solid #c7d2fe;
    cursor:pointer;
    transition:0.2s;
}

.add-btn:hover {
    background:#e0e7ff;
}

/* Save button */
.save-btn {
    background:#2563eb;
    color:#fff;
    padding:12px 20px;
    border:none;
    border-radius:10px;
    font-size:15px;
    font-weight:600;
    cursor:pointer;
    box-shadow:0 10px 25px rgba(37,99,235,0.3);
    transition:0.2s;
    margin-top:10px;
}

.save-btn:hover {
    transform:translateY(-2px);
    box-shadow:0 12px 32px rgba(37,99,235,0.35);
}

.save-btn:active {
    transform:translateY(0);
}

.time-separator {
    color:#6b7280;
    font-weight:500;
}

/* Unavailable dates section */
.unavailable-list {
    margin-bottom:20px;
}

.unavailable-item {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 16px;
    background:#fef2f2;
    border:1px solid #fecaca;
    border-radius:8px;
    margin-bottom:10px;
}

.unavailable-info {
    flex:1;
}

.unavailable-date {
    font-weight:600;
    color:#991b1b;
    margin-bottom:4px;
}

.unavailable-reason {
    font-size:13px;
    color:#7f1d1d;
}

.add-unavailable {
    display:flex;
    gap:12px;
    margin-top:16px;
    flex-wrap:wrap;
}

.add-unavailable input[type="date"],
.add-unavailable input[type="text"] {
    padding:10px 12px;
    border-radius:8px;
    border:1px solid #d1d5db;
    font-size:14px;
    background:#f9fafb;
    flex:1;
    min-width:150px;
}

.add-unavailable input:focus {
    outline:none;
    border-color:#2563eb;
    box-shadow:0 0 0 3px rgba(37,99,235,0.2);
}

.add-unavailable button {
    background:#dc2626;
    color:#fff;
    padding:10px 16px;
    border:none;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition:0.2s;
}

.add-unavailable button:hover {
    background:#b91c1c;
}

#dates-container {
    margin-bottom:20px;
}

@media(max-width:700px){
    .time-row { 
        flex-wrap:wrap;
    }
    .time-row input[type="time"] {
        min-width:100px;
    }
    .tabs {
        overflow-x:auto;
    }
}
</style>

</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="page-wrap">

    <div class="avail-card">
        <div class="avail-header">Manage Availability</div>
        <div class="small-muted">Set your available time slots and mark dates when you're unavailable.</div>

        <?php flash_render(); ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('date-availability')">Date-wise Availability</button>
            <button class="tab-btn" onclick="switchTab('unavailable-dates')">Unavailable Dates</button>
        </div>

        <!-- Date-wise Availability Tab -->
        <div id="date-availability" class="tab-content active">
            <form method="post" action="availability_save_dates.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="date-selector">
                    <label for="new-date">Add availability for date:</label>
                    <input type="date" id="new-date" min="<?= date('Y-m-d') ?>">
                    <button type="button" onclick="addDateBlock()">Add Date</button>
                </div>

                <div id="dates-container">
                    <?php if(empty($dates)): ?>
                        <div class="small-muted" id="empty-dates">No date-specific availability set yet.</div>
                    <?php else: ?>
                        <?php foreach($dates as $date => $slots): ?>
                            <div class="date-block" data-date="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                    <div class="date-title"><?= date('l, F j, Y', strtotime($date)) ?></div>
                                    <button type="button" class="remove-date-btn" onclick="removeDateBlock(this)">Remove Date</button>
                                </div>

                                <div class="time-slots" id="slots-<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach($slots as $slot): ?>
                                        <div class="time-row">
                                            <input type="time"
                                                   name="start_<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>[]"
                                                   value="<?= htmlspecialchars($slot['start_time'], ENT_QUOTES, 'UTF-8') ?>"
                                                   required>
                                            <span class="time-separator">to</span>
                                            <input type="time"
                                                   name="end_<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>[]"
                                                   value="<?= htmlspecialchars($slot['end_time'], ENT_QUOTES, 'UTF-8') ?>"
                                                   required>
                                            <button type="button" class="remove-btn" onclick="removeTimeSlot(this)">Remove</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="button" class="add-btn" onclick="addTimeSlot('<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>')">
                                    + Add Time Slot
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button class="save-btn" type="submit">Save Availability</button>
            </form>
        </div>

        <!-- Unavailable Dates Tab -->
        <div id="unavailable-dates" class="tab-content">
            <div class="unavailable-list" id="unavailable-list">
                <?php if(empty($unavailable)): ?>
                    <div class="small-muted">No unavailable dates marked.</div>
                <?php else: ?>
                    <?php foreach($unavailable as $u): ?>
                        <div class="unavailable-item" data-date="<?= htmlspecialchars($u['unavailable_date'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="unavailable-info">
                                <div class="unavailable-date"><?= date('l, F j, Y', strtotime($u['unavailable_date'])) ?></div>
                                <?php if(!empty($u['reason'])): ?>
                                    <div class="unavailable-reason"><?= htmlspecialchars($u['reason'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="remove-btn" onclick="removeUnavailableDate('<?= htmlspecialchars($u['unavailable_date'], ENT_QUOTES, 'UTF-8') ?>')">
                                Remove
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <form method="post" action="availability_add_unavailable.php" class="add-unavailable">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="date" name="unavailable_date" min="<?= date('Y-m-d') ?>" required>
                <input type="text" name="reason" placeholder="Reason (optional)">
                <button type="submit">Mark as Unavailable</button>
            </form>
        </div>

    </div>

</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}

function addDateBlock() {
    const dateInput = document.getElementById('new-date');
    const selectedDate = dateInput.value;
    
    if(!selectedDate) {
        alert('Please select a date');
        return;
    }
    
    // Check if date already exists
    if(document.querySelector(`.date-block[data-date="${selectedDate}"]`)) {
        alert('This date already exists');
        return;
    }
    
    const container = document.getElementById('dates-container');
    const emptyMsg = document.getElementById('empty-dates');
    if(emptyMsg) emptyMsg.remove();
    
    const dateObj = new Date(selectedDate + 'T00:00:00');
    const dateFormatted = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    const block = document.createElement('div');
    block.className = 'date-block';
    block.setAttribute('data-date', selectedDate);
    
    block.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div class="date-title">${dateFormatted}</div>
            <button type="button" class="remove-date-btn" onclick="removeDateBlock(this)">Remove Date</button>
        </div>
        <div class="time-slots" id="slots-${selectedDate}">
            <div class="time-row">
                <input type="time" name="start_${selectedDate}[]" required>
                <span class="time-separator">to</span>
                <input type="time" name="end_${selectedDate}[]" required>
                <button type="button" class="remove-btn" onclick="removeTimeSlot(this)">Remove</button>
            </div>
        </div>
        <button type="button" class="add-btn" onclick="addTimeSlot('${selectedDate}')">+ Add Time Slot</button>
    `;
    
    container.appendChild(block);
    dateInput.value = '';
}

function addTimeSlot(date) {
    const container = document.getElementById('slots-' + date);
    const row = document.createElement('div');
    row.className = 'time-row';
    
    row.innerHTML = `
        <input type="time" name="start_${date}[]" required>
        <span class="time-separator">to</span>
        <input type="time" name="end_${date}[]" required>
        <button type="button" class="remove-btn" onclick="removeTimeSlot(this)">Remove</button>
    `;
    
    container.appendChild(row);
}

function removeTimeSlot(btn) {
    btn.closest('.time-row').remove();
}

function removeDateBlock(btn) {
    const block = btn.closest('.date-block');
    const container = document.getElementById('dates-container');
    
    block.remove();
    
    if(container.querySelectorAll('.date-block').length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'small-muted';
        emptyDiv.id = 'empty-dates';
        emptyDiv.textContent = 'No date-specific availability set yet.';
        container.appendChild(emptyDiv);
    }
}

function removeUnavailableDate(date) {
    if(!confirm('Remove this unavailable date?')) return;
    
    // Send AJAX request to remove
    fetch('availability_remove_unavailable.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `csrf_token=<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>&unavailable_date=${date}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.querySelector(`.unavailable-item[data-date="${date}"]`).remove();
            
            if(document.querySelectorAll('.unavailable-item').length === 0) {
                document.getElementById('unavailable-list').innerHTML = '<div class="small-muted">No unavailable dates marked.</div>';
            }
        } else {
            alert('Error removing date');
        }
    });
}
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
</body>
</html>