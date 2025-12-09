<?php
// admin/appointments_create_ajax_slots.php
// Purpose: AJAX endpoint to fetch available slots for a doctor on a specific date

header('Content-Type: application/json');

// Start output buffering to prevent any stray output
ob_start();

require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';

// Clear buffer
ob_end_clean();

$doctor_id = intval($_GET['doctor_id'] ?? 0);
$date = trim($_GET['date'] ?? '');

// Validate inputs
if ($doctor_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid doctor or date.']);
    exit;
}

// Check if date is in the past
if ($date < date('Y-m-d')) {
    echo json_encode(['error' => 'Cannot book appointments in the past.']);
    exit;
}

try {
    // Check if doctor exists and is active
    $doctor_check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'doctor' AND status = 'active'");
    $doctor_check->execute([$doctor_id]);
    if (!$doctor_check->fetch()) {
        echo json_encode(['error' => 'Doctor not found or inactive.']);
        exit;
    }

    // Check if doctor has a day-off on this date
    $dayoff = $pdo->prepare("SELECT id FROM doctor_dayoffs WHERE doctor_id = ? AND date = ?");
    $dayoff->execute([$doctor_id, $date]);
    if ($dayoff->fetch()) {
        echo json_encode(['error' => 'Doctor is not available on this date (day off).']);
        exit;
    }

    // Get the day of week (0=Sunday, 6=Saturday)
    $day_of_week = date('w', strtotime($date));

    // Fetch doctor's availability for this day of week
    $avail = $pdo->prepare("
        SELECT start_time, end_time 
        FROM doctor_availability 
        WHERE doctor_id = ? AND day_of_week = ?
        ORDER BY start_time
    ");
    $avail->execute([$doctor_id, $day_of_week]);
    $availability = $avail->fetchAll(PDO::FETCH_ASSOC);

    if (empty($availability)) {
        echo json_encode(['error' => 'Doctor has no availability set for ' . date('l', strtotime($date)) . 's.']);
        exit;
    }

    // Get already booked appointments for this doctor on this date
    $booked = $pdo->prepare("
        SELECT start_time, end_time 
        FROM appointments 
        WHERE doctor_id = ? AND date = ? AND status IN ('booked', 'confirmed', 'completed')
    ");
    $booked->execute([$doctor_id, $date]);
    $bookedSlots = $booked->fetchAll(PDO::FETCH_ASSOC);

    // Generate available slots
    $slots = [];
    $slot_duration = 30; // 30 minutes per slot

    foreach ($availability as $avail_range) {
        $start = strtotime($avail_range['start_time']);
        $end = strtotime($avail_range['end_time']);

        // Generate slots within this availability range
        $current = $start;
        while ($current < $end) {
            $slot_start = date('H:i:s', $current);
            $slot_end_time = $current + ($slot_duration * 60);
            
            // Don't create slot if it would end after availability end time
            if ($slot_end_time > $end) {
                break;
            }
            
            $slot_end = date('H:i:s', $slot_end_time);

            // Check if this slot is already booked
            $is_booked = false;
            foreach ($bookedSlots as $booked_slot) {
                $booked_start = $booked_slot['start_time'];
                $booked_end = $booked_slot['end_time'];

                // Check for overlap
                if ($slot_start < $booked_end && $slot_end > $booked_start) {
                    $is_booked = true;
                    break;
                }
            }

            // If slot is not booked, add it to available slots
            if (!$is_booked) {
                $slots[] = [
                    'start' => substr($slot_start, 0, 5), // HH:MM format
                    'end' => substr($slot_end, 0, 5)
                ];
            }

            $current += ($slot_duration * 60);
        }
    }

    // If today, filter out past slots
    if ($date === date('Y-m-d')) {
        $current_time = date('H:i:s');
        $slots = array_filter($slots, function($slot) use ($current_time) {
            return $slot['start'] . ':00' > $current_time;
        });
        $slots = array_values($slots); // Re-index array
    }

    if (empty($slots)) {
        echo json_encode(['error' => 'No available slots for this date. All slots are booked.']);
        exit;
    }

    echo json_encode(['slots' => $slots, 'count' => count($slots)]);

} catch (Exception $e) {
    error_log("Slots fetch error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
exit;
?>