<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user = verifyToken($pdo);

if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit();
}

try {
    $role = $_GET['role'] ?? null;
    
    if ($role) {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, username, email, phone, role, created_at FROM users WHERE role = ? ORDER BY created_at DESC");
        $stmt->execute([$role]);
    } else {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, username, email, phone, role, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
    }
    
    $users = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'users' => $users]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch users: ' . $e->getMessage()]);
}
?>

