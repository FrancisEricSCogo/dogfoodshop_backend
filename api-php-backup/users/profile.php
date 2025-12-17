<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = verifyToken($pdo);
    unset($user['password']);
    echo json_encode(['success' => true, 'user' => $user]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $user = verifyToken($pdo);
    $data = json_decode(file_get_contents('php://input'), true);
    
    $first_name = $data['first_name'] ?? $user['first_name'];
    $last_name = $data['last_name'] ?? $user['last_name'];
    $email = $data['email'] ?? $user['email'];
    $phone = $data['phone'] ?? $user['phone'];
    $address = $data['address'] ?? $user['address'] ?? '';
    $postal_code = $data['postal_code'] ?? $user['postal_code'] ?? '';
    $city = $data['city'] ?? $user['city'] ?? '';
    
    try {
        // Check if columns exist, if not use address only
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'postal_code'");
        $hasPostalCode = $stmt->rowCount() > 0;
        
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'city'");
        $hasCity = $stmt->rowCount() > 0;
        
        if ($hasPostalCode && $hasCity) {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, postal_code = ?, city = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $phone, $address, $postal_code, $city, $user['id']]);
        } else {
            // Fallback to address only if columns don't exist
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $phone, $address, $user['id']]);
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $updatedUser = $stmt->fetch();
        unset($updatedUser['password']);
        
        echo json_encode(['success' => true, 'message' => 'Profile updated', 'user' => $updatedUser]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
    }
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>

