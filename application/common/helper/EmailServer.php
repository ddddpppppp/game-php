<?php

namespace app\common\helper;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Email server class using PHPMailer
 * Specifically designed for sending verification emails
 */
class EmailServer
{
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $smtp_secure;
    private $from_name;
    private $error_msg = '';
    private $debug = false;

    public function __construct($config = [])
    {
        $this->smtp_host     = $config['smtp_host'] ?? 'smtp.qq.com';
        $this->smtp_port     = $config['smtp_port'] ?? 587;
        $this->smtp_username = $config['smtp_username'] ?? '';
        $this->smtp_password = $config['smtp_password'] ?? '';
        $this->smtp_secure   = $config['smtp_secure'] ?? 'tls';
        $this->from_name     = $config['from_name'] ?? 'Gaming Platform';
        $this->debug         = $config['debug'] ?? false;
    }

    /**
     * Send verification code email using PHPMailer
     */
    public function sendVerificationCode($to_email, $code, $type = 'register')
    {
        if (!$this->validateEmail($to_email)) {
            $this->error_msg = 'Invalid email format';
            return false;
        }

        if (empty($this->smtp_username) || empty($this->smtp_password)) {
            $this->error_msg = 'Incomplete SMTP configuration';
            return false;
        }

        try {
            // Create PHPMailer instance
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host       = $this->smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtp_username;
            $mail->Password   = $this->smtp_password;

            // Set encryption and port
            if ($this->smtp_secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = $this->smtp_port ?: 465;
            } elseif ($this->smtp_secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $this->smtp_port ?: 587;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
                $mail->Port       = $this->smtp_port ?: 25;
            }

            // Additional SMTP options for better compatibility
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Debug settings
            if ($this->debug) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) {
                    error_log("PHPMailer Debug: $str");
                };
            }

            // Recipients
            $mail->setFrom($this->smtp_username, $this->from_name);
            $mail->addAddress($to_email);

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';

            $subject = $type === 'register' ? 'Registration Verification Code' : 'Password Reset Verification Code';
            $mail->Subject = $subject;
            $mail->Body    = $this->getEmailTemplate($code, $type);

            // Send email
            $result = $mail->send();

            if ($result) {
                $this->writeLog('INFO', "Verification code sent to {$to_email}: {$code}");
                return true;
            } else {
                $this->error_msg = 'Failed to send email';
                return false;
            }
        } catch (Exception $e) {
            $this->error_msg = "Mailer Error: {$mail->ErrorInfo}";
            $this->writeLog('ERROR', $this->error_msg);
            return false;
        }
    }

    /**
     * Email template for verification codes
     */
    private function getEmailTemplate($code, $type)
    {
        $title = $type === 'register' ? 'Registration Verification' : 'Password Reset';

        return "
        <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>{$this->from_name}</h1>
            </div>
            <div style='padding: 40px; background: #f8f9fa;'>
                <h2 style='color: #333; text-align: center; margin-bottom: 30px; font-size: 24px;'>{$title} Code</h2>
                <div style='background: white; border-radius: 8px; padding: 30px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    <p style='color: #666; font-size: 16px; margin-bottom: 20px;'>Your verification code is:</p>
                    <div style='font-size: 36px; color: #667eea; font-weight: bold; letter-spacing: 8px; margin: 20px 0; padding: 20px; background: #f0f8ff; border-radius: 8px; border: 2px dashed #667eea;'>
                        {$code}
                    </div>
                    <p style='color: #999; font-size: 14px; margin-top: 20px; line-height: 1.5;'>
                        This code is valid for <strong>10 minutes</strong>. Please use it promptly.<br>
                        If you didn't request this code, please ignore this email.
                    </p>
                </div>
            </div>
            <div style='background: #333; padding: 20px; text-align: center;'>
                <p style='color: #999; margin: 0; font-size: 12px;'>
                    This email was sent automatically. Please do not reply.
                </p>
            </div>
        </div>";
    }

    /**
     * Test SMTP connection
     */
    public function testConnection()
    {
        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = $this->smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtp_username;
            $mail->Password   = $this->smtp_password;

            if ($this->smtp_secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = $this->smtp_port ?: 465;
            } elseif ($this->smtp_secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $this->smtp_port ?: 587;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
                $mail->Port       = $this->smtp_port ?: 25;
            }

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Test connection without sending
            $mail->smtpConnect();
            $mail->smtpClose();

            return true;
        } catch (Exception $e) {
            $this->error_msg = "Connection test failed: {$e->getMessage()}";
            return false;
        }
    }

    /**
     * Validate email format
     */
    private function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Write log
     */
    private function writeLog($level, $message)
    {
        $log_file = dirname(__FILE__) . '/../../runtime/log/email_' . date('Y-m-d') . '.log';
        $log_dir = dirname($log_file);

        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_content = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $message . PHP_EOL;
        file_put_contents($log_file, $log_content, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get error message
     */
    public function getError()
    {
        return $this->error_msg;
    }

    /**
     * Set debug mode
     */
    public function setDebug($debug = true)
    {
        $this->debug = $debug;
    }
}
