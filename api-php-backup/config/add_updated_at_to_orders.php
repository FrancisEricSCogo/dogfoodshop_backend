<?php
require_once __DIR__ . '/database.php';

try {
    echo "Adding updated_at column to orders table...\n";
    
    // Check if updated_at column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'updated_at'");
    if ($stmt->rowCount() == 0) {
        // Add updated_at column with ON UPDATE CURRENT_TIMESTAMP
        $pdo->exec("ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "✓ Added updated_at column to orders table\n";
        
        // Update existing records to set updated_at = created_at for orders that haven't been updated yet
        $pdo->exec("UPDATE orders SET updated_at = created_at WHERE updated_at IS NULL OR updated_at = created_at");
        echo "✓ Updated existing records\n";
    } else {
        echo "✓ updated_at column already exists\n";
        
        // Check if it has ON UPDATE CURRENT_TIMESTAMP
        $stmt = $pdo->query("SHOW COLUMNS FROM orders WHERE Field = 'updated_at'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($column && strpos($column['Extra'], 'on update CURRENT_TIMESTAMP') === false) {
            echo "Updating updated_at column to include ON UPDATE CURRENT_TIMESTAMP...\n";
            $pdo->exec("ALTER TABLE orders MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            echo "✓ Updated updated_at column to auto-update\n";
        } else {
            echo "✓ updated_at column already has ON UPDATE CURRENT_TIMESTAMP\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

