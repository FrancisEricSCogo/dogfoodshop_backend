<?php
require_once __DIR__ . '/env.php';

class EmailSender {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromAddress;
    private $fromName;
    private $encryption;
    
    public function __construct() {
        $this->smtpHost = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $this->smtpPort = (int)(getenv('MAIL_PORT') ?: 587);
        $this->smtpUsername = getenv('MAIL_USERNAME') ?: '';
        $this->smtpPassword = getenv('MAIL_PASSWORD') ?: '';
        $this->fromAddress = getenv('MAIL_FROM_ADDRESS') ?: '';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: 'Dog Food Shop';
        $this->encryption = getenv('MAIL_ENCRYPTION') ?: 'tls';
        
        // Debug: Log configuration (remove password from log)
        if (empty($this->smtpUsername) || empty($this->smtpPassword)) {
            error_log("EmailSender: Missing email credentials. Username: " . ($this->smtpUsername ?: 'NOT SET') . ", Password: " . ($this->smtpPassword ? 'SET' : 'NOT SET'));
        } else {
            error_log("EmailSender: Configuration loaded. Host: {$this->smtpHost}, Port: {$this->smtpPort}, Username: {$this->smtpUsername}, From: {$this->fromAddress}");
        }
    }
    
    public function sendOTP($toEmail, $otp, $userName = 'User') {
        $subject = "Your OTP for Registration - Dog Food Shop";
        $message = $this->buildOtpEmail(
            $otp,
            $userName,
            "Thank you for registering with Dog Food Shop!",
            "Please use the following OTP (One-Time Password) to complete your registration:",
            "Registration OTP"
        );
        return $this->sendEmail($toEmail, $subject, $message);
    }

    public function sendPasswordResetOTP($toEmail, $otp, $userName = 'User') {
        $subject = "Your OTP for Password Reset - Dog Food Shop";
        $message = $this->buildOtpEmail(
            $otp,
            $userName,
            "You requested a password reset.",
            "Use this OTP to reset your password. If you didn't request this, you can ignore this email.",
            "Reset Password OTP"
        );
        return $this->sendEmail($toEmail, $subject, $message);
    }

    private function buildOtpEmail($otp, $userName, $leadText, $bodyText, $headline) {
        $year = date('Y');
        $brandColor = '#4f46e5';
        $brandGradient = 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)';
        return "
        <html>
        <head>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; margin:0; padding:0; background:#f8fafc; }
                .container { max-width: 640px; margin: 0 auto; padding: 0 16px 32px; }
                .header {
                    background: {$brandGradient};
                    color: white;
                    padding: 22px;
                    text-align: center;
                    border-radius: 12px 12px 0 0;
                }
                .header h2 { margin: 0; font-size: 22px; font-weight: 800; }
                .content {
                    padding: 24px;
                    background: #ffffff;
                    border: 1px solid #e2e8f0;
                    border-top: none;
                    border-radius: 0 0 12px 12px;
                    box-shadow: 0 8px 24px rgba(79, 70, 229, 0.10);
                }
                .content h3 { margin: 0 0 10px 0; color: #0f172a; font-size: 18px; }
                .content p { margin: 6px 0; color: #475569; font-size: 14px; line-height: 1.6; }
                .otp-box {
                    background: #f8fafc;
                    border: 2px dashed {$brandColor};
                    padding: 18px;
                    text-align: center;
                    margin: 18px 0;
                    border-radius: 10px;
                }
                .otp-code { font-size: 30px; font-weight: 800; color: {$brandColor}; letter-spacing: 6px; }
                .footer { text-align: center; padding: 12px; color: #94a3b8; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🐕 Dog Food Shop</h2>
                    <div style='font-size:12px; opacity:0.9;'>{$headline}</div>
                </div>
                <div class='content'>
                    <h3>Hello {$userName},</h3>
                    <p>{$leadText}</p>
                    <p>{$bodyText}</p>
                    <div class='otp-box'>
                        <div class='otp-code'>{$otp}</div>
                    </div>
                    <p>This OTP will expire in 10 minutes.</p>
                    <p>If you didn't request this OTP, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>© {$year} Dog Food Shop. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function sendEmail($to, $subject, $htmlMessage) {
        // Use PHPMailer if available, otherwise use simple SMTP
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendWithPHPMailer($to, $subject, $htmlMessage);
        } else {
            return $this->sendWithSMTP($to, $subject, $htmlMessage);
        }
    }
    
    private function sendWithSMTP($to, $subject, $htmlMessage) {
        // Simple SMTP implementation using socket
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromAddress}>\r\n";
        $headers .= "Reply-To: {$this->fromAddress}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // For Gmail, we need to use PHPMailer or similar library
        // This is a fallback that might not work with Gmail's SMTP
        // For production, PHPMailer is recommended
        
        // Try using mail() function first (works if server is configured)
        $plainMessage = strip_tags($htmlMessage);
        if (mail($to, $subject, $plainMessage, $headers)) {
            return true;
        }
        
        // If mail() fails, try socket-based SMTP
        return $this->sendViaSocket($to, $subject, $htmlMessage);
    }
    
    private function sendViaSocket($to, $subject, $htmlMessage) {
        // Check if credentials are set
        if (empty($this->smtpUsername) || empty($this->smtpPassword) || empty($this->fromAddress)) {
            error_log("EmailSender: Missing email configuration. Username: " . ($this->smtpUsername ?: 'NOT SET') . ", From: " . ($this->fromAddress ?: 'NOT SET'));
            return false;
        }
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $socket = @stream_socket_client(
            "tcp://{$this->smtpHost}:{$this->smtpPort}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            error_log("SMTP Connection failed to {$this->smtpHost}:{$this->smtpPort} - $errstr ($errno)");
            return false;
        }
        
        // Helper function to read SMTP response
        $readResponse = function($socket) {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) == ' ') {
                    break;
                }
            }
            return $response;
        };
        
        // Read server greeting
        $response = $readResponse($socket);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return false;
        }
        
        // Send EHLO
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $response = $readResponse($socket);
        
        // Start TLS if required
        if ($this->encryption == 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = $readResponse($socket);
            if (substr($response, 0, 3) == '220') {
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                    // Try alternative method
                    $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                    if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                        fclose($socket);
                        error_log("TLS handshake failed - tried both TLSv1.2 and TLS");
                        return false;
                    }
                }
                error_log("TLS handshake successful");
                fputs($socket, "EHLO " . gethostname() . "\r\n");
                $response = $readResponse($socket);
                error_log("EHLO response: " . trim($response));
            } else {
                error_log("STARTTLS failed. Response: " . trim($response));
            }
        }
        
        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = $readResponse($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return false;
        }
        
        fputs($socket, base64_encode($this->smtpUsername) . "\r\n");
        $response = $readResponse($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return false;
        }
        
        fputs($socket, base64_encode($this->smtpPassword) . "\r\n");
        $response = $readResponse($socket);
        
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            $errorMsg = trim($response);
            error_log("SMTP Authentication failed for {$this->smtpUsername}: $errorMsg");
            error_log("Response code: " . substr($response, 0, 3));
            return false;
        }
        
        error_log("SMTP Authentication successful for {$this->smtpUsername}");
        
        // Send email
        fputs($socket, "MAIL FROM: <{$this->fromAddress}>\r\n");
        $response = $readResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return false;
        }
        
        fputs($socket, "RCPT TO: <{$to}>\r\n");
        $response = $readResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return false;
        }
        
        fputs($socket, "DATA\r\n");
        $response = $readResponse($socket);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            return false;
        }
        
        $headers = "From: {$this->fromName} <{$this->fromAddress}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: " . $this->encodeHeader($subject) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        
        fputs($socket, $headers . "\r\n" . $htmlMessage . "\r\n.\r\n");
        $response = $readResponse($socket);
        
        $success = substr($response, 0, 3) == '250';
        
        if (!$success) {
            error_log("SMTP Send failed. Response: " . trim($response));
        } else {
            error_log("Email sent successfully to: $to");
        }
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return $success;
    }
    
    private function encodeHeader($text) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
    
    private function sendWithPHPMailer($to, $subject, $htmlMessage) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUsername;
            $mail->Password = $this->smtpPassword;
            $mail->SMTPSecure = $this->encryption;
            $mail->Port = $this->smtpPort;
            
            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlMessage;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
?>

