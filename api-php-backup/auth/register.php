<?php
// Suppress error display, log errors instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any accidental output
ob_start();

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/email.php';
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Initialization failed: ' . $e->getMessage()]);
    exit();
}

// Clear any accidental output that might have been generated
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$first_name = $data['first_name'] ?? '';
$last_name = $data['last_name'] ?? '';
$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
$password = $data['password'] ?? '';
$profile_pic = $data['profile_pic'] ?? '';
$role = $data['role'] ?? 'customer';

if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
    ob_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'All required fields must be filled']);
    exit();
}

// Role is always 'customer' for new registrations - admin can change it later
$role = 'customer';

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid email format']);
    exit();
}

// Check if database connection is available
if (!isset($pdo)) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection not available']);
    exit();
}

try {
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        ob_clean();
        http_response_code(409);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Username or email already exists']);
        exit();
    }
    
    // Generate 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Set OTP expiration (10 minutes from now)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Delete any existing OTP for this email
    $stmt = $pdo->prepare("DELETE FROM otp_verifications WHERE email = ?");
    $stmt->execute([$email]);
    
    // Store OTP with user data
    $stmt = $pdo->prepare("INSERT INTO otp_verifications (email, otp_code, first_name, last_name, username, phone, password, profile_pic, role, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$email, $otp, $first_name, $last_name, $username, $phone, $hashedPassword, $profile_pic, $role, $expiresAt]);
    
    // Send OTP email
    $emailSender = new EmailSender();
    $userName = $first_name . ' ' . $last_name;
    $emailSent = $emailSender->sendOTP($email, $otp, $userName);
    
    if (!$emailSent) {
        // Log error but don't fail registration - OTP is still stored
        error_log("Failed to send OTP email to: $email");
    }
    
    ob_clean();
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'OTP has been sent to your email. Please check your inbox and verify your email.',
        'email_sent' => $emailSent
    ]);
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Registration PDO Error: " . $e->getMessage());
    echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Registration Error: " . $e->getMessage());
    echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
}
?>

