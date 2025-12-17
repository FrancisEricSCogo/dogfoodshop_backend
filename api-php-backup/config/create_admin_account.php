<?php
require_once __DIR__ . '/database.php';

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
    $stmt->execute(['admin', 'admin@dogfoodshop.com']);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo "Admin account already exists:\n";
        echo "ID: {$existing['id']}\n";
        echo "Username: {$existing['username']}\n";
        echo "Email: {$existing['email']}\n";
        echo "\nYou can use these credentials to login:\n";
        echo "Username: admin\n";
        echo "Email: admin@dogfoodshop.com\n";
        echo "Password: admin123\n";
        exit();
    }
    
    // Create admin account
    $first_name = 'Admin';
    $last_name = 'User';
    $username = 'admin';
    $email = 'admin@dogfoodshop.com';
    $phone = '1234567890';
    $password = 'admin123'; // Plain password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = 'admin';
    
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([$first_name, $last_name, $username, $email, $phone, $hashedPassword, $role]);
    
    if ($result) {
        $adminId = $pdo->lastInsertId();
        echo "✓ Admin account created successfully!\n\n";
        echo "Account Details:\n";
        echo "ID: {$adminId}\n";
        echo "Username: {$username}\n";
        echo "Email: {$email}\n";
        echo "Password: {$password}\n";
        echo "Role: {$role}\n\n";
        echo "You can now login with these credentials.\n";
    } else {
        echo "✗ Failed to create admin account.\n";
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo "Admin account already exists.\n";
        echo "Username: admin\n";
        echo "Email: admin@dogfoodshop.com\n";
        echo "Password: admin123\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>

