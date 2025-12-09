<?php
require_once __DIR__ . '/../inc/auth_checks.php';
require_role('doctor');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';

csrf_verify();

$doctor_id = current_user_id();

try {
    // Delete existing date availability for this doctor
    $stmt = $pdo->prepare("DELETE FROM doctor_date_availability WHERE doctor_id=?");
    $stmt->execute([$doctor_id]);
    
    // Insert new availability
    foreach($_POST as $key => $value) {
        if(strpos($key, 'start_') === 0) {
            $date = substr($key, 6); // Remove 'start_' prefix
            
            if(isset($_POST['start_' . $date]) && isset($_POST['end_' . $date])) {
                $starts = $_POST['start_' . $date];
                $ends = $_POST['end_' . $date];
                
                for($i = 0; $i < count($starts); $i++) {
                    if(!empty($starts[$i]) && !empty($ends[$i])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO doctor_date_availability 
                            (doctor_id, availability_date, start_time, end_time) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$doctor_id, $date, $starts[$i], $ends[$i]]);
                    }
                }
            }
        }
    }
    
    flash_set('success', 'Availability updated successfully!');
} catch(Exception $e) {
    flash_set('error', 'Error updating availability: ' . $e->getMessage());
}

header('Location: availability.php');
exit;