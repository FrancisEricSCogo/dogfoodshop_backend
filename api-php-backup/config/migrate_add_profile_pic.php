<?php
/**
 * Migration Script: Add profile_pic column to otp_verifications table
 * Run this file in your browser: http://localhost/dogfoodshop/api/config/migrate_add_profile_pic.php
 */

require_once __DIR__ . '/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Add profile_pic Column</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 10px;
        }
        .success {
            color: #10b981;
            background: #d1fae5;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #10b981;
        }
        .error {
            color: #ef4444;
            background: #fee2e2;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #ef4444;
        }
        .info {
            color: #3b82f6;
            background: #dbeafe;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #3b82f6;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #1e293b;
            color: #f1f5f9;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            background: #4f46e5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #4338ca;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Migration: Add profile_pic Column</h1>
        
        <?php
        try {
            // Connect to database
            $host = 'localhost';
            $dbname = 'dogfoodshop';
            $username = 'root';
            $password = '';
            
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'otp_verifications'");
            $tableExists = $stmt->rowCount() > 0;
            
            if (!$tableExists) {
                echo '<div class="error">';
                echo '<strong>✗ Error:</strong> The <code>otp_verifications</code> table does not exist.';
                echo '<p>Please run <code>api/config/database_setup.sql</code> first to create all tables.</p>';
                echo '</div>';
            } else {
                echo '<div class="success">✓ Table <code>otp_verifications</code> exists</div>';
                
                // Check if column exists
                $stmt = $pdo->query("SHOW COLUMNS FROM otp_verifications LIKE 'profile_pic'");
                $columnExists = $stmt->rowCount() > 0;
                
                if ($columnExists) {
                    echo '<div class="info">';
                    echo '<strong>ℹ Info:</strong> The <code>profile_pic</code> column already exists in the <code>otp_verifications</code> table.';
                    echo '<p>No migration needed. Your database is up to date!</p>';
                    echo '</div>';
                } else {
                    // Add the column
                    echo '<div class="info">';
                    echo '<strong>⏳ Adding column...</strong>';
                    echo '</div>';
                    
                    try {
                        $pdo->exec("ALTER TABLE otp_verifications ADD COLUMN profile_pic VARCHAR(255) NULL AFTER password");
                        
                        echo '<div class="success">';
                        echo '<strong>✓ Success!</strong> The <code>profile_pic</code> column has been added to the <code>otp_verifications</code> table.';
                        echo '<p>You can now use the registration form with profile pictures.</p>';
                        echo '</div>';
                        
                        // Verify the column was added
                        $stmt = $pdo->query("SHOW COLUMNS FROM otp_verifications");
                        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo '<div class="info">';
                        echo '<strong>Current columns in otp_verifications table:</strong>';
                        echo '<ul>';
                        foreach ($columns as $column) {
                            echo '<li><code>' . htmlspecialchars($column['Field']) . '</code> - ' . htmlspecialchars($column['Type']) . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                        
                    } catch (PDOException $e) {
                        echo '<div class="error">';
                        echo '<strong>✗ Error adding column:</strong> ' . htmlspecialchars($e->getMessage());
                        echo '<p><strong>Manual fix:</strong> Run this SQL in phpMyAdmin:</p>';
                        echo '<pre>USE dogfoodshop;

ALTER TABLE otp_verifications 
ADD COLUMN profile_pic VARCHAR(255) NULL AFTER password;</pre>';
                        echo '</div>';
                    }
                }
            }
            
        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<strong>✗ Database connection failed:</strong> ' . htmlspecialchars($e->getMessage());
            echo '<p>Make sure:</p>';
            echo '<ul>';
            echo '<li>XAMPP MySQL is running</li>';
            echo '<li>Database credentials in <code>api/config/database.php</code> are correct</li>';
            echo '</ul>';
            echo '</div>';
        }
        ?>
        
        <hr>
        <a href="../../views/guest/register.html" class="btn">← Back to Registration</a>
        <a href="check_database.php" class="btn" style="background: #64748b; margin-left: 10px;">Check Database Status</a>
    </div>
</body>
</html>

