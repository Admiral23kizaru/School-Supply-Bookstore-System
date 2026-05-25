<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendApprovalMail(string $toEmail, string $accountType = 'User'): bool
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
        $mail->Subject = 'Account Approved';

        $safeType = htmlspecialchars($accountType, ENT_QUOTES, 'UTF-8');
        $mail->Body = '
        <div style="font-family: sans-serif; background: #f8f9fa; padding: 20px;">
            <div style="max-width: 520px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #e1e4e8; text-align: center;">
                <h2 style="color: #1a1a1a; margin-top: 0;">Account Approved</h2>
                <p style="color: #4a4a4a; font-size: 15px; margin-bottom: 10px;">
                    Your ' . $safeType . ' account has been approved by the admin.
                </p>
                <p style="color: #4a4a4a; font-size: 15px; margin-bottom: 20px;">
                    You can now sign in and use your account.
                </p>
                <p style="color: #6c757d; font-size: 12px; margin-top: 25px;">
                    If this was not expected, please contact support.
                </p>
            </div>
        </div>';
        $mail->AltBody = "Your {$accountType} account has been approved by the admin. You can now sign in.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
