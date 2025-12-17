<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$admin = verifyToken($pdo);

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$first_name = $data['first_name'] ?? '';
$last_name = $data['last_name'] ?? '';
$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'customer';

if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'All required fields must be filled']);
    exit();
}

if (!in_array($role, ['customer', 'supplier', 'admin'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit();
}

try {
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Username or email already exists']);
        exit();
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Create user account directly (admin-created users bypass OTP verification)
    // Users created by admin are automatically verified and can login immediately
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$first_name, $last_name, $username, $email, $phone, $hashedPassword, $role]);
    
    $userId = $pdo->lastInsertId();
    
    // Get created user
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, username, email, phone, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $newUser = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'User created successfully',
        'user' => $newUser
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create user: ' . $e->getMessage()]);
}
?>

