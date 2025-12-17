<?php
require_once __DIR__ . '/database.php';

try {
    echo "Fixing orders table structure...\n";
    
    // Check if product_id has a foreign key constraint
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'dogfoodshop'
        AND TABLE_NAME = 'orders'
        AND COLUMN_NAME = 'product_id'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fkConstraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Drop foreign key constraints on product_id if they exist
    foreach ($fkConstraints as $fk) {
        $constraintName = $fk['CONSTRAINT_NAME'];
        echo "Dropping foreign key constraint: {$constraintName}\n";
        try {
            $pdo->exec("ALTER TABLE orders DROP FOREIGN KEY {$constraintName}");
            echo "✓ Dropped foreign key constraint: {$constraintName}\n";
        } catch (PDOException $e) {
            echo "Note: Could not drop constraint {$constraintName}: " . $e->getMessage() . "\n";
        }
    }
    
    // Make product_id nullable (since we're using order_items now)
    $stmt = $pdo->query("SHOW COLUMNS FROM orders WHERE Field = 'product_id'");
    $productIdColumn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($productIdColumn && $productIdColumn['Null'] === 'NO') {
        echo "Making product_id nullable...\n";
        $pdo->exec("ALTER TABLE orders MODIFY COLUMN product_id INT NULL");
        echo "✓ Made product_id nullable\n";
    } else {
        echo "✓ product_id is already nullable\n";
    }
    
    // Make quantity nullable as well (since it's in order_items now)
    $stmt = $pdo->query("SHOW COLUMNS FROM orders WHERE Field = 'quantity'");
    $quantityColumn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($quantityColumn && $quantityColumn['Null'] === 'NO') {
        echo "Making quantity nullable...\n";
        $pdo->exec("ALTER TABLE orders MODIFY COLUMN quantity INT NULL");
        echo "✓ Made quantity nullable\n";
    } else {
        echo "✓ quantity is already nullable\n";
    }
    
    echo "\n✓ Orders table structure fixed successfully!\n";
    echo "The orders table now supports both old and new order structures.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>

