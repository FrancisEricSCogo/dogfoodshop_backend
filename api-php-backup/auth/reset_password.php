<?php
// Suppress error display, log errors instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();

try {
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Initialization failed: ' . $e->getMessage()]);
    exit();
}

ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$otp = trim($data['otp'] ?? '');
$newPassword = $data['new_password'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'A valid email is required.']);
    exit();
}

if (empty($otp)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'OTP is required.']);
    exit();
}

if (empty($newPassword) || strlen($newPassword) < 6) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'New password must be at least 6 characters.']);
    exit();
}

if (!isset($pdo)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection not available']);
    exit();
}

try {
    // Check OTP validity
    $stmt = $pdo->prepare("SELECT * FROM otp_verifications WHERE email = ? AND otp_code = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email, $otp]);
    $otpRecord = $stmt->fetch();

    if (!$otpRecord) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid OTP. Please check the code and try again.']);
        exit();
    }

    // Expiry check
    $expiresAt = new DateTime($otpRecord['expires_at']);
    $now = new DateTime();
    if ($now > $expiresAt) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'OTP has expired. Please request a new one.']);
        exit();
    }

    // Ensure user exists
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No account found with that email.']);
        exit();
    }

    // Update password
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->execute([$hashed, $user['id']]);

    // Cleanup OTPs for this email
    $delStmt = $pdo->prepare("DELETE FROM otp_verifications WHERE email = ?");
    $delStmt->execute([$email]);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Password has been reset. You can now log in with the new password.']);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Reset password error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to reset password. Please try again later.']);
}
?>

