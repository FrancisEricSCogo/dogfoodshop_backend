<?php
/**
 * Test Email Sending
 * Run this file in your browser: http://localhost/dogfoodshop/api/config/test_email.php
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/email.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Email Sending</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 10px;
        }
        .success {
            color: #10b981;
            background: #d1fae5;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #10b981;
        }
        .error {
            color: #ef4444;
            background: #fee2e2;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #ef4444;
        }
        .info {
            color: #3b82f6;
            background: #dbeafe;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #3b82f6;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #1e293b;
            color: #f1f5f9;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Test Email Configuration</h1>
        
        <?php
        // Check if .env file exists
        $envPath = __DIR__ . '/../../.env';
        $envExists = file_exists($envPath);
        
        if (!$envExists) {
            echo '<div class="error">';
            echo '<strong>✗ Error:</strong> The <code>.env</code> file does not exist!';
            echo '<p>Create a <code>.env</code> file in the project root with the following content:</p>';
            echo '<pre>MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_ENCRYPTION=tls</pre>';
            echo '</div>';
        } else {
            echo '<div class="success">✓ <code>.env</code> file exists</div>';
            
            // Display current configuration
            echo '<div class="info">';
            echo '<strong>Current Email Configuration:</strong><br>';
            echo 'Host: <code>' . htmlspecialchars(getenv('MAIL_HOST') ?: 'smtp.gmail.com') . '</code><br>';
            echo 'Port: <code>' . htmlspecialchars(getenv('MAIL_PORT') ?: '587') . '</code><br>';
            echo 'Username: <code>' . htmlspecialchars(getenv('MAIL_USERNAME') ?: 'NOT SET') . '</code><br>';
            echo 'Password: <code>' . (getenv('MAIL_PASSWORD') ? '***SET***' : 'NOT SET') . '</code><br>';
            echo 'From Address: <code>' . htmlspecialchars(getenv('MAIL_FROM_ADDRESS') ?: 'NOT SET') . '</code><br>';
            echo 'Encryption: <code>' . htmlspecialchars(getenv('MAIL_ENCRYPTION') ?: 'tls') . '</code>';
            echo '</div>';
            
            // Test email sending
            if (isset($_GET['test']) && $_GET['test'] === 'send') {
                $testEmail = $_GET['email'] ?? getenv('MAIL_USERNAME');
                
                if (empty($testEmail)) {
                    echo '<div class="error">Please provide an email address to test</div>';
                } else {
                    echo '<div class="info">Attempting to send test email to: <code>' . htmlspecialchars($testEmail) . '</code>...</div>';
                    
                    try {
                        $emailSender = new EmailSender();
                        $otp = '123456';
                        $result = $emailSender->sendOTP($testEmail, $otp, 'Test User');
                        
                        if ($result) {
                            echo '<div class="success">';
                            echo '<strong>✓ Success!</strong> Test email sent successfully!';
                            echo '<p>Check your inbox (and spam folder) for the email with OTP: <strong>123456</strong></p>';
                            echo '</div>';
                        } else {
                            echo '<div class="error">';
                            echo '<strong>✗ Failed:</strong> Email could not be sent.';
                            echo '<p>Check the error logs or try the following:</p>';
                            echo '<ul>';
                            echo '<li>Verify your Gmail credentials are correct</li>';
                            echo '<li>Make sure you\'re using an App Password (not your regular Gmail password)</li>';
                            echo '<li>Check if "Less secure app access" is enabled (if using regular password)</li>';
                            echo '<li>Check PHP error logs: <code>' . ini_get('error_log') . '</code></li>';
                            echo '</ul>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="error">';
                        echo '<strong>✗ Exception:</strong> ' . htmlspecialchars($e->getMessage());
                        echo '</div>';
                    }
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
        }
        ?>
        
        <hr>
        <p><a href="../../views/guest/register.html">← Back to Registration</a></p>
    </div>
</body>
</html>

