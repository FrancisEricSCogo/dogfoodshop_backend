<?php
header('Content-Type: application/json');

// Check if MySQL is running first
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    require_once __DIR__ . '/../config/database.php';
    
    // Ensure connection is active
    $pdo = ensureConnection($pdo);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => 'Please make sure MySQL is running in XAMPP. Error: ' . $e->getMessage()
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit();
}

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required']);
    exit();
}

try {
    // Ensure connection before query
    $pdo = ensureConnection($pdo);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    // Helper to check pending OTP for this email
    $checkPendingOtp = function($email) use ($pdo) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $pendingStmt = $pdo->prepare("SELECT id FROM otp_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        $pendingStmt->execute([$email]);
        return (bool)$pendingStmt->fetch();
    };
    
    if (!$user) {
        // If user not found, see if there is a pending verification by email
        $emailCandidate = filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : '';
        if ($checkPendingOtp($emailCandidate)) {
            http_response_code(403);
            echo json_encode([
                'error' => 'Please verify your account first.',
                'verify_required' => true,
                'email' => $emailCandidate
            ]);
            exit();
        }
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit();
    }
    
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit();
    }
    
    // Ensure connection before update
    $pdo = ensureConnection($pdo);
    
    $token = hash('sha256', uniqid() . time() . random_bytes(10));
    
    $stmt = $pdo->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt->execute([$token, $user['id']]);
    
    unset($user['password']);
    $user['token'] = $token;
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    
    // Check if it's a "MySQL server has gone away" error
    if (strpos($e->getMessage(), '2006') !== false || strpos($e->getMessage(), 'gone away') !== false) {
        echo json_encode([
            'error' => 'Database connection lost',
            'message' => 'MySQL server connection was lost. Please check if MySQL is running in XAMPP and try again.',
            'hint' => 'Make sure XAMPP MySQL is running and the database exists.'
        ]);
    } else {
        echo json_encode([
            'error' => 'Login failed',
            'message' => $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Login failed',
        'message' => $e->getMessage()
    ]);
}
?>

