<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('patient');
require_once __DIR__ . '/../inc/db.php';

try {
    $doctor_id = intval($_GET['doctor_id'] ?? 0);
    $date = $_GET['date'] ?? '';

    if ($doctor_id <= 0) {
        echo json_encode(['error' => 'Invalid doctor ID']);
        exit;
    }

    if (empty($date)) {
        echo json_encode(['error' => 'Date is required']);
        exit;
    }

    // Validate date
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        echo json_encode(['error' => 'Invalid date format']);
        exit;
    }

    if ($date < date('Y-m-d')) {
        echo json_encode(['error' => 'Cannot book in past']);
        exit;
    }

    // Check if doctor marked this date as unavailable
    $q = $pdo->prepare("
        SELECT 1 FROM doctor_unavailable_dates 
        WHERE doctor_id = ? AND unavailable_date = ?
    ");
    $q->execute([$doctor_id, $date]);
    if ($q->fetch()) {
        echo json_encode(['slots' => [], 'message' => 'Doctor is unavailable on this date']);
        exit;
    }

    // Get availability for this specific date from the new table
    $q = $pdo->prepare("
        SELECT start_time, end_time 
        FROM doctor_date_availability
        WHERE doctor_id = ? 
        AND availability_date = ?
        AND is_available = 1
        ORDER BY start_time
    ");
    $q->execute([$doctor_id, $date]);
    $availability = $q->fetchAll(PDO::FETCH_ASSOC);

    if (!$availability || empty($availability)) {
        echo json_encode(['slots' => [], 'message' => 'No availability set for this date']);
        exit;
    }

    // Get booked appointments for this date
    $q = $pdo->prepare("
        SELECT start_time, end_time
        FROM appointments
        WHERE doctor_id = ?
        AND appointment_date = ?
        AND status IN ('pending', 'confirmed', 'booked')
    ");
    $q->execute([$doctor_id, $date]);
    $booked = $q->fetchAll(PDO::FETCH_ASSOC);

    // Create a set of booked time slots for quick lookup
    $bookedSlots = [];
    foreach ($booked as $b) {
        // Normalize to H:i:s format for comparison
        $start = date('H:i:s', strtotime($b['start_time']));
        $end = date('H:i:s', strtotime($b['end_time']));
        $bookedSlots[] = $start . "-" . $end;
    }

    // Build 30-minute slots from available time ranges
    $slots = [];
    $slotLength = 30 * 60; // 30 minutes in seconds

    foreach ($availability as $a) {
        $start = strtotime($a['start_time']);
        $end   = strtotime($a['end_time']);

        // Generate 30-minute slots within this time range
        for ($t = $start; $t + $slotLength <= $end; $t += $slotLength) {
            $s = date('H:i:s', $t);                    // 24-hour format with seconds
            $e = date('H:i:s', $t + $slotLength);     // 24-hour format with seconds

            // Skip if this slot is already booked
            if (in_array("$s-$e", $bookedSlots)) {
                continue;
            }

            // Add available slot
            $slots[] = [
                'start'      => date('h:i A', $t),              // 12-hour format for display
                'end'        => date('h:i A', $t + $slotLength), // 12-hour format for display
                'start_time' => $s,                             // 24-hour H:i:s format for booking
                'end_time'   => $e                              // 24-hour H:i:s format for booking
            ];
        }
    }

    if (empty($slots)) {
        echo json_encode(['slots' => [], 'message' => 'All slots are booked for this date']);
        exit;
    }

    echo json_encode(['slots' => $slots]);

} catch (Exception $e) {
    error_log('Slots AJAX Error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while fetching slots. Please try again.']);
}