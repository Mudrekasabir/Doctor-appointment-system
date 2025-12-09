<?php
/* ------------------------------
   DATABASE CONFIGURATION
------------------------------ */

$host = "localhost";              // XAMPP default
$dbname = "doctor_appointment";   // your DB name
$username = "root";               // XAMPP default user
$password = "";                   // XAMPP default password

/* ------------------------------
   PDO CONNECTION
------------------------------ */

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Enable exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch assoc
            PDO::ATTR_PERSISTENT => false,                 // Disable persistent conn (safer)
        ]
    );
    // echo "DB Connected";  // For debugging
} 
catch (PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}
?>
