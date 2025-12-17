<?php
/**
 * Migration Script: Add Address Column
 * This script adds the address column to the users table
 * 
 * Usage: Open in browser: http://localhost/dogfoodshop/api/config/migrate_add_address.php
 */

require_once __DIR__ . '/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Address Column Migration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 10px;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #10b981;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #ef4444;
        }
        .info {
            background: #dbeafe;
            color: #1e40af;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #3b82f6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Add Address Column Migration</h1>
        
        <?php
        try {
            // Check if address column already exists
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'address'");
            $columnExists = $stmt->rowCount() > 0;
            
            if ($columnExists) {
                echo '<div class="info">ℹ️ Address column already exists in the users table.</div>';
            } else {
                // Add address column
                $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT NULL AFTER phone");
                echo '<div class="success">✅ Successfully added address column to users table!</div>';
            }
            
            // Verify the column
            $stmt = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'address'");
            $column = $stmt->fetch();
            
            if ($column) {
                echo '<div class="info">';
                echo '<h3>Column Details:</h3>';
                echo '<p><strong>Field:</strong> ' . htmlspecialchars($column['Field']) . '</p>';
                echo '<p><strong>Type:</strong> ' . htmlspecialchars($column['Type']) . '</p>';
                echo '<p><strong>Null:</strong> ' . htmlspecialchars($column['Null']) . '</p>';
                echo '</div>';
            }
            
        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<h2>❌ Error!</h2>';
            echo '<p>Failed to add address column: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e2e8f0;">
            <p><a href="../../views/customer/profile.html" style="color: #4f46e5; text-decoration: none; font-weight: 600;">→ Go to Profile</a></p>
        </div>
    </div>
</body>
</html>

