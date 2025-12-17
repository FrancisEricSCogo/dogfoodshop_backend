<?php
// Suppress error display, log errors instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch accidental output
ob_start();

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/email.php';
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Initialization failed: ' . $e->getMessage()]);
    exit();
}

// Clear any accidental output
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'A valid email is required.']);
    exit();
}

if (!isset($pdo)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection not available']);
    exit();
}

try {
    // Find existing user by email
    $stmt = $pdo->prepare("SELECT first_name, last_name, username, email, phone, password, role, profile_pic FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No account found with that email.']);
        exit();
    }

    // Generate OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Clear existing OTPs for this email
    $clearStmt = $pdo->prepare("DELETE FROM otp_verifications WHERE email = ?");
    $clearStmt->execute([$email]);

    // Insert OTP record (reuse user data to satisfy NOT NULL columns)
    $insertStmt = $pdo->prepare("
        INSERT INTO otp_verifications (email, otp_code, first_name, last_name, username, phone, password, profile_pic, role, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $user['email'],
        $otp,
        $user['first_name'] ?? '',
        $user['last_name'] ?? '',
        $user['username'] ?? $user['email'],
        $user['phone'] ?? '',
        $user['password'], // existing hashed password (not changed here)
        $user['profile_pic'] ?? '',
        $user['role'] ?? 'customer',
        $expiresAt
    ]);

    // Send OTP email
    $emailSender = new EmailSender();
    $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $emailSent = $emailSender->sendPasswordResetOTP($email, $otp, $userName ?: 'User');

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'An OTP has been sent to your email. Please check your inbox.',
        'email_sent' => $emailSent
    ]);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Password reset OTP error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to send OTP. Please try again later.']);
}
?>

