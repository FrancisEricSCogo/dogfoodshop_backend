<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

try {
    // Get user info before deletion
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    // Protect Super Admin (ID: 1 or username 'admin')
    // Super admin cannot be deleted by anyone, including themselves
    if ($userId == 1 || strtolower($user['username']) === 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Super admin cannot be deleted. This account is protected and cannot be deleted by anyone.']);
        exit();
    }
    
    // Prevent self-deletion
    // Regular admin cannot delete themselves - must use another admin
    if ($userId == $admin['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot delete your own account. Please logout and have another admin delete it.']);
        exit();
    }
    
    // Check if the user being deleted is an admin
    // Only super admin can delete regular admins
    if ($user['role'] === 'admin') {
        // Check if the current admin is super admin (ID: 1 or username 'admin')
        $isSuperAdmin = ($admin['id'] == 1 || strtolower($admin['username']) === 'admin');
        
        if (!$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Only super admin can delete other admin accounts. Regular admins cannot delete other admins.']);
            exit();
        }
    }
    
    // Delete user (cascading deletes will handle related data)
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully',
        'deleted_user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete user: ' . $e->getMessage()]);
}
?>

