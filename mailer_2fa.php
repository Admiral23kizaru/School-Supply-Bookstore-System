<?php
require_once __DIR__ . '/vendor/autoload.php';

// Include mailer.php to access the env_value helper function
require_once __DIR__ . '/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send2FAMail(string $toEmail, string $otp): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = (string) env_value('SMTP_HOST', '');
        $mail->SMTPAuth = true;
        $mail->Username = (string) env_value('SMTP_USERNAME', '');
        $mail->Password = (string) env_value('SMTP_PASSWORD', '');
        $mail->SMTPSecure = (string) env_value('SMTP_ENCRYPTION', 'tls');
        $mail->Port = (int) env_value('SMTP_PORT', '587');

        $fromEmail = (string) env_value('SMTP_FROM_EMAIL', '');
        $fromName = (string) env_value('SMTP_FROM_NAME', 'School Supply Bookstore System');
        if ($fromEmail === '') {
            return false;
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Your 2FA Login Verification Code';
        $mail->Body = '
        <div style="font-family: sans-serif; background: #f8f9fa; padding: 20px;">
            <div style="max-width: 500px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #e1e4e8; text-align: center;">
                <img src="https://cdn-icons-png.flaticon.com/512/6146/6146114.png" width="60" style="margin-bottom: 20px;">
                <h2 style="color: #1a1a1a; margin-top: 0;">Secure Login Verification</h2>
                <p style="color: #4a4a4a; font-size: 15px; margin-bottom: 25px;">Please use the following 6-digit verification code to securely access your account.</p>
                <div style="background: #f1f5f9; padding: 15px; border-radius: 6px; display: inline-block;">
                    <strong style="font-size: 32px; letter-spacing: 6px; color: #1a1a1a;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</strong>
                </div>
                <p style="color: #6c757d; font-size: 13px; margin-top: 30px;">This code will expire in 5 minutes.<br>If you did not request this login, please change your password immediately.</p>
            </div>
        </div>';
        $mail->AltBody = "Your 2FA Login Verification Code is: {$otp}. This code expires in 5 minutes.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
