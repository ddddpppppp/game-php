<?php

namespace app\api\service;

use app\common\model\Users;

class User
{
    /**
     * Generate JWT token
     */
    public static function generateToken($userId)
    {
        $payload = [
            'user_id' => $userId,
            'exp' => time() + (30 * 24 * 60 * 60), // 30 days
            'iat' => time()
        ];

        // Simple token generation (in production, use proper JWT library)
        $token = base64_encode(json_encode($payload)) . '.' . hash_hmac('sha256', base64_encode(json_encode($payload)), JWT_KEY);

        return $token;
    }


    /**
     * Verify JWT token
     */
    public static function getUserIdByToken($token)
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return false;
        }

        $payload = $parts[0];
        $signature = $parts[1];

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $payload, JWT_KEY);

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $data = json_decode(base64_decode($payload), true);

        if (!$data || !isset($data['user_id']) || !isset($data['exp'])) {
            return false;
        }

        // Check expiration
        if ($data['exp'] < time()) {
            return false;
        }

        return $data['user_id'];
    }


    /**
     * Get email template
     */
    public static function getEmailTemplate($code, $type)
    {
        $subject = $type === 'register' ? 'Complete Your Registration' : 'Reset Your Password';

        return "
        <html>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #333;'>{$subject}</h2>
            <p>Your verification code is:</p>
            <div style='background: #f5f5f5; padding: 20px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 3px; margin: 20px 0;'>
                {$code}
            </div>
            <p>This code will expire in 10 minutes.</p>
            <p>If you didn't request this code, please ignore this email.</p>
        </body>
        </html>";
    }
}
