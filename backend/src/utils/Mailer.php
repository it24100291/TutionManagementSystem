<?php

class Mailer
{
    public static function sendOtp(string $email, string $otp): void
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            Response::error('Mail service is not configured. Install PHPMailer first.', 500);
        }

        $host = trim((string) env('MAIL_HOST', ''));
        $port = (int) env('MAIL_PORT', 587);
        $username = trim((string) env('MAIL_USERNAME', ''));
        $password = preg_replace('/\s+/', '', (string) env('MAIL_PASSWORD', ''));
        $fromEmail = trim((string) env('MAIL_FROM_ADDRESS', $username));
        $fromName = trim((string) env('MAIL_FROM_NAME', 'Tuition Management System'));
        $encryption = strtolower(trim((string) env('MAIL_ENCRYPTION', 'tls')));

        if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
            Response::error('Mail service credentials are incomplete', 500);
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;

            if ($encryption === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code - Tuition Management System';
            $mail->Body = '
                <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                    <h2 style="margin-bottom: 12px;">Email Verification</h2>
                    <p>Your verification code is:</p>
                    <p style="font-size: 28px; font-weight: 700; letter-spacing: 4px; color: #2563eb;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</p>
                    <p>This code will expire in 5 minutes.</p>
                </div>
            ';
            $mail->AltBody = "Your verification code is: {$otp}\nThis code will expire in 5 minutes.";
            $mail->send();
        } catch (Throwable $e) {
            $message = trim((string) ($mail->ErrorInfo ?: $e->getMessage()));
            Response::error('Failed to send verification email: ' . ($message !== '' ? $message : 'Unknown mail error'), 500);
        }
    }
}

