<?php
require_once __DIR__ . '/database.php';

try {
    // Create order_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
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
    
    // Check and add order_number column
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_number'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN order_number VARCHAR(50) UNIQUE NULL AFTER id");
        echo "✓ Added order_number column\n";
    } else {
        echo "✓ order_number column already exists\n";
    }
    
    // Check and add total_amount column
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'total_amount'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN total_amount DECIMAL(10, 2) NULL AFTER status");
        echo "✓ Added total_amount column\n";
    } else {
        echo "✓ total_amount column already exists\n";
    }
    
    // Create index for order_number
    try {
        $pdo->exec("CREATE INDEX idx_order_number ON orders(order_number)");
        echo "✓ Created index on order_number\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') === false) {
            throw $e;
        }
        echo "✓ Index on order_number already exists\n";
    }
    
    echo "\nMigration completed successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

