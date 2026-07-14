<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila');

// Gives us app_env() so credentials come from backend/.env instead of
// being hardcoded in this file.
require_once __DIR__ . '/../config/env.php';

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SendEmailController
{
    public function test()
    {
        echo "login";
    }

    /**
     * Builds and configures a PHPMailer instance from .env values.
     * Centralized here so both SendCode() and SendForgotPasswordCode()
     * stay in sync and never drift out of date with each other.
     */
    private function configureMailer(): PHPMailer
    {
        $mail = new PHPMailer(true); // true = throw exceptions on failure

        $mail->isSMTP();
        $mail->Host       = app_env('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = app_env('MAIL_USERNAME');
        $mail->Password   = app_env('MAIL_PASSWORD');
        $mail->Port       = (int) app_env('MAIL_PORT', '465');
        $mail->SMTPSecure = 'ssl';

        $mail->setFrom(
            app_env('MAIL_FROM_ADDRESS', $mail->Username),
            app_env('MAIL_FROM_NAME', 'Felamo')
        );

        return $mail;
    }

    /**
     * Sends the account/email verification code (used during registration).
     * Echoes "200" or "400" to match how verify-email.php / signup.dart
     * already read this method's output via ob_get_clean().
     */
    public function SendCode($email, $code, $firstName, $lastName)
    {
        try {
            $mail = $this->configureMailer();

            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Felamo Verification Code';
            $mail->Body = '
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f1f1f1;
                        padding: 20px;
                    }
                    .container {
                        background-color: #fff;
                        border-radius: 5px;
                        padding: 20px;
                        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                    }
                    h1 {
                        color: #333;
                    }
                    p {
                        color: #777;
                        margin-bottom: 10px;
                    }
                    .verification-code {
                        font-size: 24px;
                        font-weight: bold;
                        color: #007bff;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>Felamo Verification Code</h1>
                    <p>Hi ' . htmlspecialchars($firstName) . ' ' . htmlspecialchars($lastName) . ',</p>
                    <p>Your verification code is:</p>
                    <p class="verification-code">' . htmlspecialchars($code) . '</p>
                </div>
            </body>
            </html>';

            $mail->send();
            echo "200";
        } catch (Exception $e) {
            error_log('SendCode mail error: ' . (isset($mail) ? $mail->ErrorInfo : $e->getMessage()));
            echo "400";
        }
    }

    /**
     * Sends the "login using OTP" / forgot-password code.
     * Returns "200" or "400" (not echoed) to match how forgot-password.php
     * and login-using-otp.php already consume this method's return value.
     */
    public function SendForgotPasswordCode($email, $code, $firstName, $lastName)
    {
        try {
            $mail = $this->configureMailer();

            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Felamo Login Using OTP';
            $mail->Body = '
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f1f1f1;
                        padding: 20px;
                    }
                    .container {
                        background-color: #fff;
                        border-radius: 5px;
                        padding: 20px;
                        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                    }
                    h1 {
                        color: #333;
                    }
                    p {
                        color: #777;
                        margin-bottom: 10px;
                    }
                    .verification-code {
                        font-size: 24px;
                        font-weight: bold;
                        color: #007bff;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>Felamo Login using OTP</h1>
                    <p>Hi ' . htmlspecialchars($firstName) . ' ' . htmlspecialchars($lastName) . ',</p>
                    <p>Your One Time Password is:</p>
                    <p class="verification-code">' . htmlspecialchars($code) . '</p>
                </div>
            </body>
            </html>';

            $mail->send();
            return "200";
        } catch (Exception $e) {
            error_log('SendForgotPasswordCode mail error: ' . (isset($mail) ? $mail->ErrorInfo : $e->getMessage()));
            return "400";
        }
    }
}