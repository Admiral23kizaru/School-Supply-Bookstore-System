<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function env_value(string $key, ?string $default = null): ?string
{
    static $values = null;
    if ($values === null) {
        $values = [];
        $envPath = __DIR__ . '/.env';
        if (is_file($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $values[trim($k)] = trim($v);
            }
        }
    }

    return $values[$key] ?? $default;
}

function sendOtpMail(string $toEmail, string $otp): bool
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
        $mail->Subject = 'Password Reset OTP';
        $mail->Body = '<p>Your OTP is: <strong style="font-size:18px;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</strong></p><p>This code expires in 10 minutes.</p>';
        $mail->AltBody = "Your OTP is: {$otp}. This code expires in 10 minutes.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
