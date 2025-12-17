<?php
require_once __DIR__ . '/database.php';

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
    $stmt->execute(['admin', 'admin@dogfoodshop.com']);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "Admin account not found. Creating new admin account...\n";
        
        // Create admin account
        $first_name = 'Admin';
        $last_name = 'User';
        $username = 'admin';
        $email = 'admin@dogfoodshop.com';
        $phone = '1234567890';
        $password = 'admin123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'admin';
        
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$first_name, $last_name, $username, $email, $phone, $hashedPassword, $role]);
        
        if ($result) {
            echo "✓ Admin account created successfully!\n";
            echo "Username: admin\n";
            echo "Password: admin123\n";
        }
    } else {
        echo "Admin account found. Resetting password...\n";
        echo "ID: {$admin['id']}\n";
        echo "Username: {$admin['username']}\n";
        echo "Email: {$admin['email']}\n\n";
        
        // Reset password to admin123
        $password = 'admin123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashedPassword, $admin['id']]);
        
        if ($result) {
            echo "✓ Password reset successfully!\n\n";
            echo "Login Credentials:\n";
            echo "==================\n";
            echo "Username: admin\n";
            echo "Email: admin@dogfoodshop.com\n";
            echo "Password: admin123\n";
            
            // Verify the password hash
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$admin['id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                echo "\n✓ Password verification successful!\n";
            } else {
                echo "\n✗ Password verification failed!\n";
            }
        } else {
            echo "✗ Failed to reset password.\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

