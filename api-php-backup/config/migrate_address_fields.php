<?php
require_once __DIR__ . '/database.php';

try {
    // Check if postal_code column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'postal_code'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN postal_code VARCHAR(20) NULL AFTER address");
        echo "✓ Added postal_code column\n";
    } else {
        echo "✓ postal_code column already exists\n";
    }
    
    // Check if city column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'city'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL AFTER postal_code");
        echo "✓ Added city column\n";
    } else {
        echo "✓ city column already exists\n";
    }
    
    echo "\nMigration completed successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

