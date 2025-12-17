<?php
// Suppress error display, log errors instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any accidental output
ob_start();

try {
    require_once __DIR__ . '/../config/database.php';
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

// Clear any accidental output
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$rawInput = file_get_contents('php://input');

// Log for debugging
error_log("Verify OTP - Raw input: " . $rawInput);
error_log("Verify OTP - Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'NOT SET'));

// Try to decode JSON
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Invalid JSON data: ' . json_last_error_msg(),
        'raw_input' => substr($rawInput, 0, 200) // First 200 chars for debugging
    ]);
    exit();
}

// Log decoded data
error_log("Verify OTP - Decoded data: " . print_r($data, true));

// Get email and OTP with fallback to different key names
$email = '';
$otp = '';

if (isset($data['email'])) {
    $email = trim($data['email']);
} elseif (isset($data['Email'])) {
    $email = trim($data['Email']);
} elseif (isset($data['EMAIL'])) {
    $email = trim($data['EMAIL']);
}

if (isset($data['otp'])) {
    $otp = trim($data['otp']);
} elseif (isset($data['Otp'])) {
    $otp = trim($data['Otp']);
} elseif (isset($data['OTP'])) {
    $otp = trim($data['OTP']);
} elseif (isset($data['otp_code'])) {
    $otp = trim($data['otp_code']);
} elseif (isset($data['otpCode'])) {
    $otp = trim($data['otpCode']);
}

error_log("Verify OTP - Email: " . ($email ?: 'EMPTY') . " (length: " . strlen($email) . "), OTP: " . ($otp ?: 'EMPTY') . " (length: " . strlen($otp) . ")");

if (empty($email) || empty($otp)) {
    ob_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Email and OTP are required',
        'debug' => [
            'email_provided' => !empty($email),
            'otp_provided' => !empty($otp),
            'email_length' => strlen($email),
            'otp_length' => strlen($otp),
            'data_keys' => array_keys($data ?? []),
            'raw_input_preview' => substr($rawInput, 0, 100)
        ]
    ]);
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
    // First, let's check if there's any OTP record for this email (for debugging)
    try {
        $debugStmt = $pdo->prepare("SELECT email, otp_code, expires_at, created_at, NOW() as server_time FROM otp_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        $debugStmt->execute([$email]);
        $debugRecord = $debugStmt->fetch();
        
        if ($debugRecord) {
            error_log("OTP Debug - Email: $email, Stored OTP: {$debugRecord['otp_code']}, Provided OTP: $otp, Expires: {$debugRecord['expires_at']}, Current: {$debugRecord['server_time']}");
        } else {
            error_log("OTP Debug - No OTP record found for email: $email");
        }
    } catch (PDOException $e) {
        error_log("OTP Debug query failed: " . $e->getMessage());
    }
    
    // First, find any OTP record for this email (for debugging)
    $checkStmt = $pdo->prepare("SELECT * FROM otp_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    $checkStmt->execute([$email]);
    $latestRecord = $checkStmt->fetch();
    
    if ($latestRecord) {
        error_log("Latest OTP for email $email: Code={$latestRecord['otp_code']}, Expires={$latestRecord['expires_at']}, Created={$latestRecord['created_at']}");
        error_log("User provided OTP: $otp");
        error_log("OTP Match: " . ($latestRecord['otp_code'] === $otp ? 'YES' : 'NO'));
    }
    
    // Find OTP record - check expiration with timezone consideration
    $stmt = $pdo->prepare("SELECT * FROM otp_verifications WHERE email = ? AND otp_code = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email, $otp]);
    $otpRecord = $stmt->fetch();
    
    if (!$otpRecord) {
        // Check if there's a record with different OTP
        if ($latestRecord) {
            error_log("OTP mismatch. Expected: {$latestRecord['otp_code']}, Got: $otp");
            ob_clean();
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Invalid OTP code. Please check the code and try again.',
                'hint' => 'Make sure you entered the most recent code sent to your email.'
            ]);
            exit();
        } else {
            error_log("No OTP record found for email: $email");
            ob_clean();
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No OTP found for this email. Please request a new one.']);
            exit();
        }
    }
    
    // Check if OTP has expired
    $expiresAt = new DateTime($otpRecord['expires_at']);
    $now = new DateTime();
    
    error_log("OTP expiration check - Expires: {$otpRecord['expires_at']}, Current: " . $now->format('Y-m-d H:i:s'));
    
    if ($now > $expiresAt) {
        error_log("OTP found but expired. Expires: {$otpRecord['expires_at']}, Current: " . $now->format('Y-m-d H:i:s'));
        ob_clean();
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'OTP has expired. Please request a new one.',
            'expired_at' => $otpRecord['expires_at'],
            'current_time' => $now->format('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    // Check if user already exists (shouldn't happen, but just in case)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$otpRecord['username'], $otpRecord['email']]);
    if ($stmt->fetch()) {
        // Delete OTP record
        $stmt = $pdo->prepare("DELETE FROM otp_verifications WHERE id = ?");
        $stmt->execute([$otpRecord['id']]);
        
        http_response_code(409);
        echo json_encode(['error' => 'User already exists']);
        exit();
    }
    
    // Create user account
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, phone, password, profile_pic, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $otpRecord['first_name'],
        $otpRecord['last_name'],
        $otpRecord['username'],
        $otpRecord['email'],
        $otpRecord['phone'],
        $otpRecord['password'],
        $otpRecord['profile_pic'] ?? '',
        $otpRecord['role']
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Delete used OTP record
    $stmt = $pdo->prepare("DELETE FROM otp_verifications WHERE id = ?");
    $stmt->execute([$otpRecord['id']]);
    
    // Also delete any other expired OTPs for this email
    $stmt = $pdo->prepare("DELETE FROM otp_verifications WHERE email = ? AND expires_at <= NOW()");
    $stmt->execute([$email]);
    
    ob_clean();
    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Email verified and account created successfully',
        'user_id' => $userId
    ]);
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("OTP Verification PDO Error: " . $e->getMessage());
    if ($e->getCode() == 23000) {
        echo json_encode(['error' => 'Username or email already exists']);
    } else {
        echo json_encode(['error' => 'Verification failed: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("OTP Verification Error: " . $e->getMessage());
    echo json_encode(['error' => 'Verification failed: ' . $e->getMessage()]);
}
?>

