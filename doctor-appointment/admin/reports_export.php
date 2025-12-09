<?php
// admin/reports_export.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in CSV output

// Start output buffering to prevent any accidental output
ob_start();

require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Clear any output that might have been generated
ob_end_clean();

$export_type = $_GET['type'] ?? 'appointments';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$export_type.'_export_'.date('Y-m-d_His').'.csv"');
header('Pragma: no-cache');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

try {
    switch ($export_type) {
        case 'appointments':
            // Export appointments
            fputcsv($output, [
                'ID', 
                'Date', 
                'Start Time',
                'End Time',
                'Status', 
                'Doctor', 
                'Specialty', 
                'Patient', 
                'Patient Contact',
                'Fee', 
                'Cancel Reason',
                'Created At'
            ]);
            
            $stmt = $pdo->prepare("
                SELECT 
                    a.id, 
                    a.date, 
                    a.start_time, 
                    a.end_time, 
                    a.status, 
                    a.cancel_reason,
                    a.created_at,
                    d.full_name AS doctor_name, 
                    COALESCE(dp.specialty, 'N/A') AS specialty, 
                    COALESCE(dp.fee, 0) AS fee,
                    p.full_name AS patient_name,
                    p.contact AS patient_contact
                FROM appointments a
                JOIN users d ON d.id = a.doctor_id
                JOIN users p ON p.id = a.patient_id
                LEFT JOIN doctors_profiles dp ON dp.user_id = d.id
                ORDER BY a.date DESC, a.start_time ASC
            ");
            $stmt->execute();
            
            $count = 0;
            $total_revenue = 0;
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fee = floatval($row['fee']);
                
                fputcsv($output, [
                    $row['id'],
                    $row['date'],
                    substr($row['start_time'], 0, 5),
                    substr($row['end_time'], 0, 5),
                    ucfirst($row['status']),
                    $row['doctor_name'],
                    $row['specialty'],
                    $row['patient_name'],
                    $row['patient_contact'],
                    number_format($fee, 2),
                    $row['cancel_reason'] ?? '',
                    date('Y-m-d H:i', strtotime($row['created_at']))
                ]);
                
                $count++;
                if ($row['status'] === 'completed') {
                    $total_revenue += $fee;
                }
            }
            
            // Add summary
            fputcsv($output, []);
            fputcsv($output, ['=== SUMMARY ===']);
            fputcsv($output, ['Total Appointments:', $count]);
            fputcsv($output, ['Total Revenue (Completed):', 'Rs ' . number_format($total_revenue, 2)]);
            break;
            
        case 'doctors':
            // Export doctors
            fputcsv($output, [
                'ID', 
                'Name', 
                'Email', 
                'Contact', 
                'Specialty', 
                'Experience', 
                'Fee', 
                'Status', 
                'Total Appointments'
            ]);
            
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, 
                    u.full_name, 
                    u.email, 
                    u.contact, 
                    u.status,
                    COALESCE(dp.specialty, 'N/A') AS specialty, 
                    COALESCE(dp.experience, 0) AS experience, 
                    COALESCE(dp.fee, 0) AS fee,
                    COUNT(a.id) as appointment_count
                FROM users u
                LEFT JOIN doctors_profiles dp ON dp.user_id = u.id
                LEFT JOIN appointments a ON a.doctor_id = u.id
                WHERE u.role = 'doctor'
                GROUP BY u.id
                ORDER BY u.full_name
            ");
            $stmt->execute();
            
            $count = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['id'],
                    $row['full_name'],
                    $row['email'],
                    $row['contact'],
                    $row['specialty'],
                    $row['experience'] . ' years',
                    'Rs ' . number_format($row['fee'], 2),
                    ucfirst($row['status']),
                    $row['appointment_count']
                ]);
                $count++;
            }
            
            fputcsv($output, []);
            fputcsv($output, ['Total Doctors:', $count]);
            break;
            
        case 'patients':
            // Export patients
            fputcsv($output, [
                'ID', 
                'Name', 
                'Email', 
                'Contact', 
                'Status', 
                'Total Appointments', 
                'Registered On'
            ]);
            
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, 
                    u.full_name, 
                    u.email, 
                    u.contact, 
                    u.status, 
                    u.created_at,
                    COUNT(a.id) as appointment_count
                FROM users u
                LEFT JOIN appointments a ON a.patient_id = u.id
                WHERE u.role = 'patient'
                GROUP BY u.id
                ORDER BY u.full_name
            ");
            $stmt->execute();
            
            $count = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['id'],
                    $row['full_name'],
                    $row['email'],
                    $row['contact'],
                    ucfirst($row['status']),
                    $row['appointment_count'],
                    date('Y-m-d', strtotime($row['created_at']))
                ]);
                $count++;
            }
            
            fputcsv($output, []);
            fputcsv($output, ['Total Patients:', $count]);
            break;
            
        case 'revenue':
            // Export revenue report
            fputcsv($output, [
                'Month', 
                'Total Appointments', 
                'Completed', 
                'Cancelled', 
                'Revenue', 
                'Potential Revenue'
            ]);
            
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(a.date, '%Y-%m') as month,
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN a.status = 'completed' THEN COALESCE(dp.fee, 0) ELSE 0 END) as revenue,
                    SUM(CASE WHEN a.status IN ('booked', 'confirmed') THEN COALESCE(dp.fee, 0) ELSE 0 END) as potential_revenue
                FROM appointments a
                LEFT JOIN doctors_profiles dp ON dp.user_id = a.doctor_id
                GROUP BY month
                ORDER BY month DESC
            ");
            $stmt->execute();
            
            $total_revenue = 0;
            $total_potential = 0;
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['month'],
                    $row['total_appointments'],
                    $row['completed'],
                    $row['cancelled'],
                    'Rs ' . number_format($row['revenue'], 2),
                    'Rs ' . number_format($row['potential_revenue'], 2)
                ]);
                
                $total_revenue += $row['revenue'];
                $total_potential += $row['potential_revenue'];
            }
            
            fputcsv($output, []);
            fputcsv($output, ['Total Revenue:', 'Rs ' . number_format($total_revenue, 2)]);
            fputcsv($output, ['Total Potential:', 'Rs ' . number_format($total_potential, 2)]);
            break;
            
        default:
            fputcsv($output, ['ERROR', 'Invalid export type: ' . $export_type]);
            fputcsv($output, ['Valid types:', 'appointments, doctors, patients, revenue']);
    }
    
    // Add footer
    fputcsv($output, []);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['By:', $_SESSION['username'] ?? 'Admin']);
    
} catch (PDOException $e) {
    // Log the error
    error_log("CSV Export Error: " . $e->getMessage());
    
    // Write error to CSV
    fputcsv($output, []);
    fputcsv($output, ['ERROR OCCURRED']);
    fputcsv($output, ['Message:', $e->getMessage()]);
    fputcsv($output, ['Please contact system administrator']);
} catch (Exception $e) {
    error_log("CSV Export Error: " . $e->getMessage());
    fputcsv($output, []);
    fputcsv($output, ['ERROR', $e->getMessage()]);
}

fclose($output);
exit;
?>