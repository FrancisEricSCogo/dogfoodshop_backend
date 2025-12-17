<?php
/**
 * Debug Email Configuration
 * This will show detailed information about email sending
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/email.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Debug Information</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4f46e5; padding-bottom: 10px; }
        .success { color: #10b981; background: #d1fae5; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #10b981; }
        .error { color: #ef4444; background: #fee2e2; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ef4444; }
        .info { color: #3b82f6; background: #dbeafe; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #3b82f6; }
        pre { background: #1e293b; color: #f1f5f9; padding: 15px; border-radius: 5px; overflow-x: auto; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Email Debug Information</h1>
        
        <?php
        // Check .env file
        $envPath = __DIR__ . '/../../.env';
        echo '<div class="info">';
        echo '<strong>Environment File:</strong><br>';
        if (file_exists($envPath)) {
            echo '✓ .env file exists at: <code>' . htmlspecialchars($envPath) . '</code><br>';
        } else {
            echo '✗ .env file NOT found at: <code>' . htmlspecialchars($envPath) . '</code><br>';
        }
        echo '</div>';
        
        // Display environment variables
        echo '<div class="info">';
        echo '<strong>Email Configuration:</strong><br>';
        echo 'MAIL_HOST: <code>' . htmlspecialchars(getenv('MAIL_HOST') ?: 'NOT SET') . '</code><br>';
        echo 'MAIL_PORT: <code>' . htmlspecialchars(getenv('MAIL_PORT') ?: 'NOT SET') . '</code><br>';
        echo 'MAIL_USERNAME: <code>' . htmlspecialchars(getenv('MAIL_USERNAME') ?: 'NOT SET') . '</code><br>';
        echo 'MAIL_PASSWORD: <code>' . (getenv('MAIL_PASSWORD') ? '***SET***' : 'NOT SET') . '</code><br>';
        echo 'MAIL_FROM_ADDRESS: <code>' . htmlspecialchars(getenv('MAIL_FROM_ADDRESS') ?: 'NOT SET') . '</code><br>';
        echo 'MAIL_FROM_NAME: <code>' . htmlspecialchars(getenv('MAIL_FROM_NAME') ?: 'NOT SET') . '</code><br>';
        echo 'MAIL_ENCRYPTION: <code>' . htmlspecialchars(getenv('MAIL_ENCRYPTION') ?: 'NOT SET') . '</code><br>';
        echo '</div>';
        
        // Check PHPMailer
        echo '<div class="info">';
        echo '<strong>PHPMailer Status:</strong><br>';
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo '✓ PHPMailer is available<br>';
        } else {
            echo '✗ PHPMailer is NOT available (using socket-based SMTP)<br>';
            echo '<small>For better reliability with Gmail, consider installing PHPMailer via Composer</small>';
        }
        echo '</div>';
        
        // Test email sending
        if (isset($_GET['test']) && $_GET['test'] === 'send') {
            $testEmail = $_GET['email'] ?? getenv('MAIL_USERNAME');
            
            echo '<div class="info">';
            echo '<strong>Testing Email Send...</strong><br>';
            echo 'Sending to: <code>' . htmlspecialchars($testEmail) . '</code><br>';
            echo '</div>';
            
            try {
                $emailSender = new EmailSender();
                $otp = '123456';
                
                // Enable error logging
                ini_set('log_errors', 1);
                ini_set('error_log', __DIR__ . '/../../php_error.log');
                
                $result = $emailSender->sendOTP($testEmail, $otp, 'Test User');
                
                if ($result) {
                    echo '<div class="success">';
                    echo '<strong>✓ Success!</strong> Test email sent successfully!<br>';
                    echo 'Check your inbox (and spam folder) for the email with OTP: <strong>123456</strong>';
                    echo '</div>';
                } else {
                    echo '<div class="error">';
                    echo '<strong>✗ Failed:</strong> Email could not be sent.<br>';
                    echo '<p>Check the error log file: <code>' . __DIR__ . '/../../php_error.log</code></p>';
                    echo '<p>Common issues:</p>';
                    echo '<ul>';
                    echo '<li>Gmail requires App Passwords (not regular passwords)</li>';
                    echo '<li>Check if 2-Step Verification is enabled on your Google account</li>';
                    echo '<li>Firewall might be blocking SMTP connection</li>';
                    echo '<li>Socket-based SMTP might not work - consider installing PHPMailer</li>';
                    echo '</ul>';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<strong>✗ Exception:</strong> ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
        } else {
            echo '<div class="info">';
            echo '<strong>Test Email Sending:</strong><br>';
            $testEmail = getenv('MAIL_USERNAME') ?: '';
            echo '<form method="GET">';
            echo '<input type="hidden" name="test" value="send">';
            echo '<label>Email Address: <input type="email" name="email" value="' . htmlspecialchars($testEmail) . '" required></label><br><br>';
            echo '<button type="submit" style="background: #4f46e5; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Send Test Email</button>';
            echo '</form>';
            echo '</div>';
        }
        
        // Show recent error log entries
        $errorLog = __DIR__ . '/../../php_error.log';
        if (file_exists($errorLog)) {
            $lines = file($errorLog);
            $recentLines = array_slice($lines, -20); // Last 20 lines
            echo '<div class="info">';
            echo '<strong>Recent Error Log Entries (last 20):</strong>';
            echo '<pre>' . htmlspecialchars(implode('', $recentLines)) . '</pre>';
            echo '</div>';
        }
        ?>
        
        <hr>
        <p><a href="../../views/guest/register.html">← Back to Registration</a></p>
    </div>
</body>
</html>

