<?php
require_once __DIR__ . '/database.php';

try {
    // Check if supplier already exists
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
    $stmt->execute(['supplier', 'supplier@dogfoodshop.com']);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo "Supplier account already exists:\n";
        echo "ID: {$existing['id']}\n";
        echo "Username: {$existing['username']}\n";
        echo "Email: {$existing['email']}\n";
        echo "\nYou can use these credentials to login:\n";
        echo "Username: supplier\n";
        echo "Email: supplier@dogfoodshop.com\n";
        echo "Password: supplier123\n";
        exit();
    }
    
    // Create supplier account
    $first_name = 'Supplier';
    $last_name = 'Account';
    $username = 'supplier';
    $email = 'supplier@dogfoodshop.com';
    $phone = '1234567890';
    $password = 'supplier123'; // Plain password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = 'supplier';
    
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([$first_name, $last_name, $username, $email, $phone, $hashedPassword, $role]);
    
    if ($result) {
        $supplierId = $pdo->lastInsertId();
        echo "✓ Supplier account created successfully!\n\n";
        echo "Account Details:\n";
        echo "================\n";
        echo "ID: {$supplierId}\n";
        echo "Name: {$first_name} {$last_name}\n";
        echo "Username: {$username}\n";
        echo "Email: {$email}\n";
        echo "Phone: {$phone}\n";
        echo "Role: {$role}\n";
        echo "\nLogin Credentials:\n";
        echo "==================\n";
        echo "Username: {$username}\n";
        echo "Email: {$email}\n";
        echo "Password: {$password}\n";
        echo "\nYou can now login with these credentials!\n";
    } else {
        echo "Failed to create supplier account.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo "\nA supplier account with this username or email already exists.\n";
        echo "Try logging in with:\n";
        echo "Username: supplier\n";
        echo "Email: supplier@dogfoodshop.com\n";
        echo "Password: supplier123\n";
    }
}
?>

