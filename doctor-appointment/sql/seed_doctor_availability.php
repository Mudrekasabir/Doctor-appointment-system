<?php
require 'C:/xampp/htdocs/doctor-appointment/inc/db.php';

$pdo->exec("DELETE FROM doctor_available_times");

$seed = [
    // dr_raj (id=3)
    [3, 1, '09:00', '13:00', 1], // Monday
    [3, 2, '10:00', '16:00', 1], // Tuesday
    [3, 3, '09:30', '14:00', 1], // Wednesday
    [3, 4, '11:00', '17:00', 1], // Thursday
    [3, 5, '09:00', '15:00', 1], // Friday

    // dr_neha (id=4)
    [4, 1, '10:00', '18:00', 1],
    [4, 3, '10:00', '18:00', 1],
    [4, 5, '10:00', '18:00', 1],
];

$stmt = $pdo->prepare("INSERT INTO doctor_available_times (doctor_id, day_of_week, start_time, end_time, repeat_weekly) VALUES (?, ?, ?, ?, ?)");

foreach ($seed as $row) {
    $stmt->execute($row);
}

echo "OK — doctor availability seeded";
