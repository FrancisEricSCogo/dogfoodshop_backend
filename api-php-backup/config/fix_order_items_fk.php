<?php
require_once __DIR__ . '/database.php';

try {
    echo "Checking order_items table structure...\n";
    
    // Check if order_items table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'order_items'");
    if ($stmt->rowCount() == 0) {
        echo "order_items table does not exist. Creating it...\n";
        $pdo->exec("CREATE TABLE order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
        echo "✓ Created order_items table\n";
    } else {
        echo "order_items table exists. Checking foreign keys...\n";
        
        // Get foreign key constraints
        $stmt = $pdo->query("
            SELECT 
                CONSTRAINT_NAME,
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = 'dogfoodshop'
            AND TABLE_NAME = 'order_items'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($fks)) {
            echo "No foreign keys found. Adding them...\n";
            
            // Drop table and recreate with foreign keys
            $pdo->exec("DROP TABLE IF EXISTS order_items");
            $pdo->exec("CREATE TABLE order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity INT NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )");
            echo "✓ Recreated order_items table with foreign keys\n";
        } else {
            echo "Foreign keys found:\n";
            foreach ($fks as $fk) {
                echo "  - {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
            }
        }
    }
    
    // Verify orders table structure
    echo "\nChecking orders table structure...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Orders table columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    // Check if order_number and total_amount exist
    $hasOrderNumber = false;
    $hasTotalAmount = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'order_number') $hasOrderNumber = true;
        if ($col['Field'] === 'total_amount') $hasTotalAmount = true;
    }
    
    if (!$hasOrderNumber) {
        echo "\nAdding order_number column...\n";
        $pdo->exec("ALTER TABLE orders ADD COLUMN order_number VARCHAR(50) UNIQUE NULL AFTER id");
        echo "✓ Added order_number column\n";
    }
    
    if (!$hasTotalAmount) {
        echo "\nAdding total_amount column...\n";
        $pdo->exec("ALTER TABLE orders ADD COLUMN total_amount DECIMAL(10, 2) NULL AFTER status");
        echo "✓ Added total_amount column\n";
    }
    
    echo "\n✓ All checks completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>

