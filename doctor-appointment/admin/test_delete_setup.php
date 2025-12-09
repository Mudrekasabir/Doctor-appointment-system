<?php
// admin/test_delete_setup.php
// REMOVE THIS FILE AFTER TESTING!

require_once __DIR__ . '/../inc/auth_checks.php';
require_role('admin');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Delete Setup Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        h1 { color: #1f2937; }
        h2 { color: #374151; margin-top: 0; }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 14px;
        }
        pre {
            background: #1f2937;
            color: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔧 Delete Patient Setup Test</h1>
    <p><strong>⚠️ IMPORTANT:</strong> Delete this file after testing!</p>

    <!-- Test 1: CSRF Functions -->
    <div class="test-section">
        <h2>1. CSRF Token Functions</h2>
        <?php
        echo "<p><strong>Testing CSRF functions:</strong></p>";
        
        if (function_exists('csrf_token')) {
            echo "<p class='success'>✓ csrf_token() exists</p>";
            $token = csrf_token();
            echo "<p>Generated token: <code>" . htmlspecialchars(substr($token, 0, 20)) . "...</code></p>";
        } else {
            echo "<p class='error'>✗ csrf_token() not found</p>";
        }
        
        if (function_exists('validate_csrf')) {
            echo "<p class='success'>✓ validate_csrf() exists</p>";
        } elseif (function_exists('csrf_verify')) {
            echo "<p class='success'>✓ csrf_verify() exists</p>";
        } else {
            echo "<p class='warning'>⚠ No CSRF validation function found</p>";
        }
        
        if (isset($_SESSION['csrf_token'])) {
            echo "<p class='success'>✓ Session CSRF token is set</p>";
        } else {
            echo "<p class='warning'>⚠ No session CSRF token</p>";
        }
        ?>
    </div>

    <!-- Test 2: Database Tables -->
    <div class="test-section">
        <h2>2. Database Tables</h2>
        <?php
        $tables = ['users', 'appointments', 'patients_profiles', 'logs'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                echo "<p class='success'>✓ Table '<code>{$table}</code>' exists ({$count} records)</p>";
            } catch (Exception $e) {
                echo "<p class='error'>✗ Table '<code>{$table}</code>' not found or error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        ?>
    </div>

    <!-- Test 3: Sample Patient Data -->
    <div class="test-section">
        <h2>3. Sample Patient for Testing</h2>
        <?php
        try {
            $stmt = $pdo->query("SELECT id, username, full_name, email FROM users WHERE role='patient' LIMIT 1");
            $patient = $stmt->fetch();
            
            if ($patient) {
                echo "<p class='success'>✓ Found test patient:</p>";
                echo "<pre>";
                echo "ID: " . $patient['id'] . "\n";
                echo "Username: " . htmlspecialchars($patient['username']) . "\n";
                echo "Full Name: " . htmlspecialchars($patient['full_name']) . "\n";
                echo "Email: " . htmlspecialchars($patient['email']);
                echo "</pre>";
            } else {
                echo "<p class='warning'>⚠ No patients in database</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>

    <!-- Test 4: JavaScript Test -->
    <div class="test-section">
        <h2>4. JavaScript Delete Modal Test</h2>
        <button onclick="testDeleteModal()" style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer;">
            Test Delete Modal
        </button>
        <div id="modalTest" style="margin-top: 15px;"></div>
    </div>

    <!-- Test 5: Form Submission Test -->
    <div class="test-section">
        <h2>5. Test Form Submission (Dry Run)</h2>
        <p>This tests if the form would submit correctly without actually deleting:</p>
        <button onclick="testFormSubmission()" style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer;">
            Test Form Structure
        </button>
        <div id="formTest" style="margin-top: 15px;"></div>
    </div>

    <script>
        function testDeleteModal() {
            const testDiv = document.getElementById('modalTest');
            testDiv.innerHTML = '<p class="success">✓ JavaScript is working!</p>';
            
            // Test if the modal would work
            if (typeof showDeleteModal === 'function') {
                testDiv.innerHTML += '<p class="success">✓ showDeleteModal function exists</p>';
            } else {
                testDiv.innerHTML += '<p class="error">✗ showDeleteModal function not found (this is OK - it\'s in manage_patients.php)</p>';
            }
        }

        function testFormSubmission() {
            const testDiv = document.getElementById('formTest');
            
            // Create a test form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_patient.php';
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo csrf_token(); ?>';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = '999'; // Test ID
            
            form.appendChild(csrfInput);
            form.appendChild(idInput);
            
            testDiv.innerHTML = `
                <p class="success">✓ Form structure is correct:</p>
                <pre>
Action: ${form.action}
Method: ${form.method}
CSRF Token: ${csrfInput.value.substring(0, 20)}...
Patient ID: ${idInput.value}
                </pre>
                <p class="warning">⚠️ Form not submitted (dry run only)</p>
            `;
        }
    </script>

    <div style="margin-top: 40px; padding: 20px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
        <strong>⚠️ SECURITY WARNING:</strong> Delete this test file (<code>test_delete_setup.php</code>) immediately after verifying your setup!
    </div>

    <p style="margin-top: 20px; text-align: center;">
        <a href="manage_patients.php" style="color: #3b82f6; text-decoration: none; font-weight: bold;">← Back to Manage Patients</a>
    </p>
</body>
</html>