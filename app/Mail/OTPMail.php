<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OTPMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $userName;

    public $email;

    public function __construct($otp, $userName = 'User', $email = '')
    {
        $this->otp = $otp;
        $this->userName = $userName;
        $this->email = $email;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your OTP for Email Verification - Dog Food Shop',
        );
    }

    public function content(): Content
    {
        $brandColor = '#4f46e5';
        $brandGradient = 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)';
        $year = date('Y');
        
        $html = "
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
                    <div style='font-size:12px; opacity:0.9;'>Registration OTP</div>
                </div>
                <div class='content'>
                    <h3>Hello {$this->userName},</h3>
                    <p>Thank you for registering with Dog Food Shop!</p>
                    <p>Please use the following OTP (One-Time Password) to complete your registration:</p>
                    <div class='otp-box'>
                        <div class='otp-code'>{$this->otp}</div>
                    </div>
                    <p>This OTP will expire in 10 minutes.</p>
                    " . ($this->email ? "
                    <div style='margin: 24px 0; padding: 16px; background: #f8fafc; border-radius: 10px; text-align: center; border: 2px solid {$brandColor};'>
                        <p style='margin: 0 0 12px 0; color: #475569; font-size: 14px; font-weight: 600;'>Click the button below to go directly to the verification page:</p>
                        <a href='https://francisericscogo.github.io/dogfoodshop/frontend/views/guest/verify_email.html?email=" . urlencode($this->email) . "' style='display: inline-block; padding: 12px 24px; background: {$brandGradient}; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;'>Verify Email Now</a>
                    </div>
                    " : "") . "
                    <p>If you didn't request this OTP, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>© {$year} Dog Food Shop. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return new Content(
            htmlString: $html,
        );
    }
}
