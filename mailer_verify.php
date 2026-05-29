<?php
require_once __DIR__ . '/vendor/autoload.php';

// Include mailer.php to access the env_value helper
require_once __DIR__ . '/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationMail(string $toEmail, string $token): bool
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
        if ($fromEmail === '') return false;

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email Address';
        
        // Use current domain and resolve the app root even when called from /auth.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if (basename($scriptDir) === 'auth') {
            $scriptDir = dirname($scriptDir);
        }
        $scriptDir = rtrim($scriptDir, '/');
        $verifyLink = $protocol . $host . $scriptDir . "/index.php?action=verify_email&token=" . urlencode($token);

        $mail->Body = '
        <div style="font-family: sans-serif; background: #f8f9fa; padding: 20px;">
            <div style="max-width: 500px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #e1e4e8; text-align: center;">
                <img src="https://cdn-icons-png.flaticon.com/512/873/873117.png" width="60" style="margin-bottom: 20px;">
                <h2 style="color: #1a1a1a; margin-top: 0;">Welcome!</h2>
                <p style="color: #4a4a4a; font-size: 15px; margin-bottom: 25px;">Thank you for registering. Please click the button below to verify your email address and activate your account.</p>
                <a href="' . $verifyLink . '" style="display: inline-block; background: #1a1a1a; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; letter-spacing: 0.5px;">Verify Email Address</a>
                <p style="color: #6c757d; font-size: 12px; margin-top: 30px;">If you did not create an account, no further action is required.</p>
            </div>
        </div>';
        $mail->AltBody = "Verify your email by going to this link: " . $verifyLink;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
