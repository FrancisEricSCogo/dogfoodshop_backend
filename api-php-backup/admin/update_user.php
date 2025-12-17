<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
$userId = $data['user_id'] ?? 0;

if (empty($userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    exit();
}

// Check if trying to modify super admin - allow but with restrictions
$isSuperAdmin = ($userId == 1);

try {
    // Check if username or email already exists (if being changed)
    if (isset($data['username']) || isset($data['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $checkUsername = $data['username'] ?? '';
        $checkEmail = $data['email'] ?? '';
        $stmt->execute([$checkUsername ?: $checkEmail, $checkEmail ?: $checkUsername, $userId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Username or email already exists']);
            exit();
        }
    }
    
    $updates = [];
    $params = [];
    
    if (isset($data['first_name'])) {
        $updates[] = "first_name = ?";
        $params[] = $data['first_name'];
    }
    if (isset($data['last_name'])) {
        $updates[] = "last_name = ?";
        $params[] = $data['last_name'];
    }
    if (isset($data['username'])) {
        $updates[] = "username = ?";
        $params[] = $data['username'];
    }
    if (isset($data['email'])) {
        $updates[] = "email = ?";
        $params[] = $data['email'];
    }
    if (isset($data['phone'])) {
        $updates[] = "phone = ?";
        $params[] = $data['phone'];
    }
    // Super admin cannot have role changed
    if (isset($data['role']) && !$isSuperAdmin) {
        $updates[] = "role = ?";
        $params[] = $data['role'];
    }
    if (isset($data['password']) && !empty($data['password'])) {
        $updates[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit();
    }
    
    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, username, email, phone, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch();
    
    echo json_encode(['success' => true, 'message' => 'User updated', 'user' => $updatedUser]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update user: ' . $e->getMessage()]);
}
?>

