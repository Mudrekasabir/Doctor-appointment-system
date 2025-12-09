<?php
require_once __DIR__ . "/inc/db.php";   // adjust path if needed

echo "<h2>🔍 Testing Database Connection...</h2>";

try {
    // Try a simple query
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color:green; font-size:20px;'>✔ Database Connected Successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red; font-size:20px;'>❌ Connection Failed:<br>" . $e->getMessage() . "</p>";
}
?>
