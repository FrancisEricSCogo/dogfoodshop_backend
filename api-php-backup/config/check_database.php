<?php
// Database Check Script
// Run this file in your browser to check if database and tables are set up correctly
// Access: http://localhost/dogfoodshop/api/config/check_database.php

header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'dogfoodshop';
$username = 'root';
$password = '';

echo "<h2>Database Setup Check</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
</style>";

try {
    // Check database connection
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p class='success'>✓ Connected to MySQL server</p>";
    
    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbname'");
    $dbExists = $stmt->rowCount() > 0;
    
    if ($dbExists) {
        echo "<p class='success'>✓ Database '$dbname' exists</p>";
        
        // Connect to the database
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check required tables
        $requiredTables = [
            'users' => ['id', 'first_name', 'last_name', 'username', 'email', 'password', 'role'],
            'products' => ['id', 'supplier_id', 'name', 'price', 'stock'],
            'orders' => ['id', 'customer_id', 'product_id', 'quantity', 'status'],
            'notifications' => ['id', 'user_id', 'message', 'type'],
            'otp_verifications' => ['id', 'email', 'otp_code', 'expires_at']
        ];
        
        echo "<h3>Table Status:</h3>";
        echo "<table>";
        echo "<tr><th>Table Name</th><th>Status</th><th>Missing Columns</th></tr>";
        
        $allTablesExist = true;
        $otpTableExists = false;
        
        foreach ($requiredTables as $tableName => $requiredColumns) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
            $tableExists = $stmt->rowCount() > 0;
            
            if ($tableExists) {
                if ($tableName === 'otp_verifications') {
                    $otpTableExists = true;
                }
                
                // Check columns
                $stmt = $pdo->query("SHOW COLUMNS FROM $tableName");
                $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $missingColumns = array_diff($requiredColumns, $existingColumns);
                
                if (empty($missingColumns)) {
                    echo "<tr><td><strong>$tableName</strong></td><td class='success'>✓ Exists</td><td>-</td></tr>";
                } else {
                    echo "<tr><td><strong>$tableName</strong></td><td class='warning'>⚠ Exists but incomplete</td><td>" . implode(', ', $missingColumns) . "</td></tr>";
                    $allTablesExist = false;
                }
            } else {
                echo "<tr><td><strong>$tableName</strong></td><td class='error'>✗ Missing</td><td>All columns</td></tr>";
                $allTablesExist = false;
            }
        }
        
        echo "</table>";
        
        // Check OTP table specifically
        if (!$otpTableExists) {
            echo "<h3 class='error'>⚠ OTP Table Missing!</h3>";
            echo "<p>You need to run the SQL to create the <code>otp_verifications</code> table.</p>";
            echo "<p><strong>Run this SQL in phpMyAdmin:</strong></p>";
            echo "<pre style='background: #f4f4f4; padding: 15px; border-left: 4px solid #4CAF50;'>";
            echo "CREATE TABLE IF NOT EXISTS otp_verifications (\n";
            echo "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
            echo "    email VARCHAR(100) NOT NULL,\n";
            echo "    otp_code VARCHAR(6) NOT NULL,\n";
            echo "    first_name VARCHAR(100) NOT NULL,\n";
            echo "    last_name VARCHAR(100) NOT NULL,\n";
            echo "    username VARCHAR(50) NOT NULL,\n";
            echo "    phone VARCHAR(20),\n";
            echo "    password VARCHAR(255) NOT NULL,\n";
            echo "    profile_pic VARCHAR(255) NULL,\n";
            echo "    role ENUM('customer', 'supplier') NOT NULL DEFAULT 'customer',\n";
            echo "    expires_at TIMESTAMP NOT NULL,\n";
            echo "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
            echo "    INDEX idx_email_otp (email, otp_code),\n";
            echo "    INDEX idx_expires (expires_at)\n";
            echo ");";
            echo "</pre>";
        } else {
            // Check if profile_pic column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM otp_verifications LIKE 'profile_pic'");
            $profilePicExists = $stmt->rowCount() > 0;
            
            if (!$profilePicExists) {
                echo "<h3 class='warning'>⚠ OTP Table Missing profile_pic Column!</h3>";
                echo "<p>You need to add the <code>profile_pic</code> column to the existing table.</p>";
                echo "<p><strong>Run this SQL in phpMyAdmin:</strong></p>";
                echo "<pre style='background: #f4f4f4; padding: 15px; border-left: 4px solid #ff9800;'>";
                echo "USE dogfoodshop;\n\n";
                echo "ALTER TABLE otp_verifications \n";
                echo "ADD COLUMN profile_pic VARCHAR(255) NULL AFTER password;";
                echo "</pre>";
            } else {
                echo "<p class='success'><strong>✓ OTP table exists with profile_pic column - Email verification is ready!</strong></p>";
            }
        }
        
        if ($allTablesExist && $otpTableExists) {
            echo "<h3 class='success'>✓ All tables are set up correctly!</h3>";
        }
        
    } else {
        echo "<p class='error'>✗ Database '$dbname' does not exist</p>";
        echo "<p><strong>You need to create the database first.</strong></p>";
        echo "<p>Run the SQL file: <code>api/config/database_setup.sql</code> in phpMyAdmin</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Make sure:</p>";
    echo "<ul>";
    echo "<li>XAMPP MySQL is running</li>";
    echo "<li>Database credentials in <code>api/config/database.php</code> are correct</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='../../views/guest/index.html'>← Back to Home</a></p>";
?>

