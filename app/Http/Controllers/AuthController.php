<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\OTPMail;
use App\Mail\PasswordResetOTPMail;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->json()->all();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            return response()->json(['error' => 'Username and password are required'], 400);
        }

        try {
            $user = DB::table('users')
                ->where('username', $username)
                ->orWhere('email', $username)
                ->first();

            // Check for pending OTP verification if user not found
            if (!$user) {
                // Check if username or email exists in otp_verifications (unverified account)
                $pendingOtp = DB::table('otp_verifications')
                    ->where(function($query) use ($username) {
                        $query->where('username', $username)
                              ->orWhere('email', $username);
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($pendingOtp) {
                    // Verify password matches the unverified account
                    if (Hash::check($password, $pendingOtp->password)) {
                        // Password matches - account exists but not verified
                        $emailForVerification = $pendingOtp->email;
                        return response()->json([
                            'error' => 'Please verify your account first. Check your email for the verification code.',
                            'verify_required' => true,
                            'email' => $emailForVerification
                        ], 403);
                    }
                    // Password doesn't match - could be wrong account or wrong password
                }
                
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            if (!Hash::check($password, $user->password)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            $token = hash('sha256', uniqid() . time() . random_bytes(10));
            DB::table('users')->where('id', $user->id)->update(['token' => $token]);

            $userArray = (array)$user;
            unset($userArray['password']);
            $userArray['token'] = $token;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => $userArray
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Login failed: ' . $e->getMessage()], 500);
        }
    }

    public function register(Request $request)
    {
        $data = $request->json()->all();
        
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $password = $data['password'] ?? '';
        $profile_pic = $data['profile_pic'] ?? '';
        $role = 'customer'; // Always customer for new registrations

        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
            return response()->json(['error' => 'All required fields must be filled'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'Invalid email format'], 400);
        }

        try {
            // Check if username or email already exists in verified users table
            $existing = DB::table('users')
                ->where('username', $username)
                ->orWhere('email', $email)
                ->first();

            if ($existing) {
                return response()->json(['error' => 'Username or email already exists'], 409);
            }

            // Check if there's an unverified account (in otp_verifications but not in users)
            // This allows resending OTP for unverified accounts
            $unverifiedAccount = DB::table('otp_verifications')
                ->where('email', $email)
                ->orderBy('created_at', 'desc')
                ->first();

            // Generate OTP
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addMinutes(10);

            // Delete existing OTPs for this email
            DB::table('otp_verifications')->where('email', $email)->delete();
            
            // If resending for unverified account, use existing data, otherwise use new registration data
            if ($unverifiedAccount) {
                // Resending OTP - use existing data from otp_verifications
                $first_name = $unverifiedAccount->first_name;
                $last_name = $unverifiedAccount->last_name;
                $username = $unverifiedAccount->username;
                $phone = $unverifiedAccount->phone ?? '';
                $hashedPassword = $unverifiedAccount->password; // Already hashed
                $profile_pic = $unverifiedAccount->profile_pic ?? '';
                $role = $unverifiedAccount->role ?? 'customer';
            } else {
                // New registration - hash the password
                $hashedPassword = Hash::make($password);
            }

            // Store OTP with user data
            DB::table('otp_verifications')->insert([
                'email' => $email,
                'otp_code' => $otp,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'username' => $username,
                'phone' => $phone,
                'password' => $hashedPassword,
                'profile_pic' => $profile_pic,
                'role' => $role,
                'expires_at' => $expiresAt,
                'created_at' => now()
            ]);

            // Send OTP email using Laravel Mail
            $emailSent = false;
            $emailError = null;
            try {
                $userName = trim($first_name . ' ' . $last_name);
                
                // Check mail configuration
                $mailDriver = config('mail.default');
                $mailHost = config('mail.mailers.smtp.host');
                $mailUsername = config('mail.mailers.smtp.username');
                
                \Log::info("Mail Configuration - Driver: {$mailDriver}, Host: {$mailHost}, Username: " . ($mailUsername ? 'SET' : 'NOT SET'));
                
                if ($mailDriver === 'log') {
                    \Log::warning("Mail driver is set to 'log' - emails will not be sent, only logged!");
                    $emailError = "Mail driver is set to 'log'. Please configure MAIL_MAILER=smtp in .env file.";
                } else {
                    // Try to send email
                    try {
                        Mail::to($email)->send(new OTPMail($otp, $userName, $email));
                        $emailSent = true;
                        \Log::info("OTP email sent successfully to: {$email}");
                    } catch (\Exception $mailException) {
                        // Catch all mail-related exceptions
                        $errorMsg = $mailException->getMessage();
                        $emailError = "Email delivery failed: " . $errorMsg;
                        
                        // Check for common error patterns in the exception message
                        $errorLower = strtolower($errorMsg);
                        if (strpos($errorLower, '550') !== false || strpos($errorLower, 'does not exist') !== false || 
                            strpos($errorLower, 'nosuchuser') !== false || strpos($errorLower, 'address not found') !== false) {
                            $emailError = "The email address '{$email}' does not exist or cannot receive mail. Please use a valid email address.";
                        } elseif (strpos($errorLower, '553') !== false || strpos($errorLower, 'relay') !== false) {
                            $emailError = "Email address '{$email}' is not allowed. Please use a different email address.";
                        } elseif (strpos($errorLower, 'authentication') !== false || strpos($errorLower, 'login') !== false) {
                            $emailError = "Email authentication failed. Please check your email server configuration.";
                        }
                        
                        \Log::error("Mail Exception sending OTP to {$email}: " . $errorMsg);
                        \Log::error("Exception class: " . get_class($mailException));
                    }
                }
            } catch (\Exception $e) {
                $emailError = $e->getMessage();
                \Log::error("Failed to send OTP email to {$email}: " . $e->getMessage());
                \Log::error("Stack trace: " . $e->getTraceAsString());
            }

            return response()->json([
                'success' => true,
                'message' => $emailSent 
                    ? 'OTP has been sent to your email. Please check your inbox and verify your email.'
                    : 'Registration successful, but email could not be sent. Please contact support.',
                'email_sent' => $emailSent,
                'email_error' => $emailError,
                'otp' => $emailSent ? null : $otp // Include OTP in response if email failed (for testing)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->json()->all();
        $email = trim($data['email'] ?? $data['Email'] ?? $data['EMAIL'] ?? '');
        $otp = trim($data['otp'] ?? $data['Otp'] ?? $data['OTP'] ?? $data['otp_code'] ?? $data['otpCode'] ?? '');

        if (empty($email) || empty($otp)) {
            return response()->json(['error' => 'Email and OTP are required'], 400);
        }

        try {
            $otpRecord = DB::table('otp_verifications')
                ->where('email', $email)
                ->where('otp_code', $otp)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$otpRecord) {
                return response()->json(['error' => 'Invalid OTP code. Please check the code and try again.'], 400);
            }

            // Check expiration
            if (now() > $otpRecord->expires_at) {
                return response()->json(['error' => 'OTP has expired. Please request a new one.'], 400);
            }

            // Check if user already exists
            $existing = DB::table('users')
                ->where('username', $otpRecord->username)
                ->orWhere('email', $otpRecord->email)
                ->first();

            if ($existing) {
                DB::table('otp_verifications')->where('id', $otpRecord->id)->delete();
                return response()->json(['error' => 'User already exists'], 409);
            }

            // Create user account
            $userId = DB::table('users')->insertGetId([
                'first_name' => $otpRecord->first_name,
                'last_name' => $otpRecord->last_name,
                'username' => $otpRecord->username,
                'email' => $otpRecord->email,
                'phone' => $otpRecord->phone,
                'password' => $otpRecord->password,
                'profile_pic' => $otpRecord->profile_pic ?? '',
                'role' => $otpRecord->role,
                'created_at' => now()
            ]);

            // Delete used OTP
            DB::table('otp_verifications')->where('id', $otpRecord->id)->delete();
            DB::table('otp_verifications')->where('email', $email)->where('expires_at', '<=', now())->delete();

            return response()->json([
                'success' => true,
                'message' => 'Email verified and account created successfully',
                'user_id' => $userId
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Verification failed: ' . $e->getMessage()], 500);
        }
    }

    public function requestPasswordReset(Request $request)
    {
        $data = $request->json()->all();
        $email = trim($data['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'A valid email is required.'], 400);
        }

        try {
            $user = DB::table('users')->where('email', $email)->first();

            if (!$user) {
                return response()->json(['error' => 'No account found with that email.'], 404);
            }

            // Generate OTP
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addMinutes(10);

            // Clear existing OTPs
            DB::table('otp_verifications')->where('email', $email)->delete();

            // Insert OTP record
            DB::table('otp_verifications')->insert([
                'email' => $user->email,
                'otp_code' => $otp,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'username' => $user->username ?? $user->email,
                'phone' => $user->phone ?? '',
                'password' => $user->password,
                'profile_pic' => $user->profile_pic ?? '',
                'role' => $user->role ?? 'customer',
                'expires_at' => $expiresAt,
                'created_at' => now()
            ]);

            // Send OTP email
            try {
                $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                Mail::to($email)->send(new PasswordResetOTPMail($otp, $userName ?: 'User', $email));
                $emailSent = true;
            } catch (\Exception $e) {
                \Log::error("Failed to send password reset OTP: " . $e->getMessage());
                $emailSent = false;
            }

            return response()->json([
                'success' => true,
                'message' => 'An OTP has been sent to your email. Please check your inbox.',
                'email_sent' => $emailSent
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send OTP. Please try again later.'], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $data = $request->json()->all();
        $email = trim($data['email'] ?? '');
        $otp = trim($data['otp'] ?? '');
        $newPassword = $data['new_password'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'A valid email is required.'], 400);
        }

        if (empty($otp)) {
            return response()->json(['error' => 'OTP is required.'], 400);
        }

        if (empty($newPassword) || strlen($newPassword) < 6) {
            return response()->json(['error' => 'New password must be at least 6 characters.'], 400);
        }

        try {
            $otpRecord = DB::table('otp_verifications')
                ->where('email', $email)
                ->where('otp_code', $otp)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$otpRecord) {
                return response()->json(['error' => 'Invalid OTP. Please check the code and try again.'], 400);
            }

            // Check expiration
            if (now() > $otpRecord->expires_at) {
                return response()->json(['error' => 'OTP has expired. Please request a new one.'], 400);
            }

            $user = DB::table('users')->where('email', $email)->first();

            if (!$user) {
                return response()->json(['error' => 'No account found with that email.'], 404);
            }

            // Update password
            DB::table('users')
                ->where('id', $user->id)
                ->update(['password' => Hash::make($newPassword)]);

            // Delete OTP records
            DB::table('otp_verifications')->where('email', $email)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset. You can now log in with the new password.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to reset password. Please try again later.'], 500);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->get('auth_user');
        
        if ($user) {
            DB::table('users')->where('id', $user['id'])->update(['token' => null]);
        }

        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }
}
