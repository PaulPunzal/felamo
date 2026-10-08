<?php
include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/SendEmailController.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController extends db_connect
{
    // user_type stored in user_otps for web accounts (mobile uses 'user')
    private const WEB_OTP_USER_TYPE = 'web_user';
    private const OTP_TYPE_FORGOT   = 'forgot_password';
    private const OTP_MAX_ATTEMPTS  = 5;

    public function __construct()
    {
        $this->connect();
    }

    // Safe wrapper: ob_clean() throws a notice if no buffer is active
    private function cleanBuffer()
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }
    }

    public function GetUser($id)
    {
        $q = $this->conn->prepare("SELECT * FROM `web_users` WHERE `id` = ? AND `is_active` = 1");
        $q->bind_param("i", $id);
        if ($q->execute()) { return $q->get_result(); } else { return null; }
    }

    public function GetUsingId($table, $id)
    {
        // Whitelist tables since the name is interpolated into the query
        $allowed = ['web_users', 'users', 'sections', 'levels', 'aralin', 'assessments'];
        if (!in_array($table, $allowed, true)) {
            return false;
        }

        $q = $this->conn->prepare("SELECT * FROM `$table` WHERE `id` = ?");
        if ($q) {
            $q->bind_param("i", $id);
            if ($q->execute()) {
                return $q->get_result();
            }
        }
        return false;
    }

    public function GetUser2($id)
    {
        $this->cleanBuffer();
        $q = $this->conn->prepare(
            "SELECT * FROM `web_users` WHERE `id` = ? AND `is_active` = 1"
        );
        $q->bind_param("i", $id);
        if ($q->execute()) {
            $result = $q->get_result();
            $user   = $result->fetch_assoc();

            if (!$user) {
                echo json_encode(['status' => 'error', 'message' => 'User not found.']);
                return;
            }

            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'id'         => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'name'       => $user['first_name'] . ' ' . $user['last_name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                ]
            ]);
        }
    }

    public function Login($data)
    {
        $this->cleanBuffer();
        $email = $data['email'];
        $password = $data['password'];

        $q = $this->conn->prepare("SELECT * FROM `web_users` WHERE `email` = ? AND `is_active` = 1");
        $q->bind_param("s", $email);

        if ($q->execute()) {
            $result = $q->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                session_set_cookie_params(0, '/');
                if (session_status() === PHP_SESSION_NONE) session_start();
                $_SESSION['id'] = $user['id'];
                session_write_close();

                $this->cleanBuffer();
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Logged in',
                    'user' => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]
                ]);
            } else {
                $this->cleanBuffer();
                echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
            }
        } else {
            $this->cleanBuffer();
            echo json_encode(['status' => 'error', 'message' => 'Something went wrong.']);
        }
    }

    // --- UPDATE PROFILE PICTURE ---
    public function UpdateProfilePicture($id, $file)
    {
        $this->cleanBuffer();

        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'File upload error code: ' . $file['error']]);
            return;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
            return;
        }

        $uploadDir = __DIR__ . '/../storage/profile-pictures/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory.']);
                return;
            }
        }

        $filename = 'admin_' . $id . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $filename;
        $dbPath = 'backend/storage/profile-pictures/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $stmt = $this->conn->prepare("UPDATE `web_users` SET `profile_picture` = ? WHERE `id` = ?");
            if ($stmt) {
                $stmt->bind_param("si", $dbPath, $id);
                if ($stmt->execute()) {
                    $this->cleanBuffer();
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Profile picture updated.',
                        'new_path' => $dbPath
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database prepare failed.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
        }
    }

    // --- UPDATE USER DETAILS ---
    public function UpdateUser($id, $first_name, $last_name, $email, $newPassword)
    {
        $this->cleanBuffer();

        if (!$this->conn) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
            return;
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
            return;
        }

        $checkEmail = $this->conn->prepare(
            "SELECT id FROM web_users WHERE email = ? AND id != ? LIMIT 1"
        );
        $checkEmail->bind_param("si", $email, $id);
        $checkEmail->execute();
        $checkEmail->store_result();
        if ($checkEmail->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'That email is already used by another account.'
            ]);
            return;
        }
        $checkEmail->close();

        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare(
                "UPDATE web_users SET first_name = ?, last_name = ?, email = ?, password = ? WHERE id = ?"
            );
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $hashedPassword, $id);
        } else {
            $stmt = $this->conn->prepare(
                "UPDATE web_users SET first_name = ?, last_name = ?, email = ? WHERE id = ?"
            );
            $stmt->bind_param("sssi", $first_name, $last_name, $email, $id);
        }

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Query prepare failed.']);
            return;
        }

        if ($stmt->execute()) {
            $this->cleanBuffer();
            echo json_encode(['status' => 'success', 'message' => 'User updated successfully.']);
        } else {
            $this->cleanBuffer();
            echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $stmt->error]);
        }
        $stmt->close();
    }

    // =====================================================================
    // FORGOT PASSWORD / LOGIN USING OTP  (web_users)
    // =====================================================================

    /**
     * Step 1: generate an OTP, store it in user_otps, and email it.
     * Always returns the same success message for unknown emails so the
     * endpoint can't be used to discover which emails are registered.
     */
    public function SendForGotPasswordOtp($email)
    {
        $this->cleanBuffer();
        $email = trim($email);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email.']);
            return;
        }

        $q = $this->conn->prepare(
            "SELECT id, first_name, last_name FROM `web_users` WHERE `email` = ? AND `is_active` = 1"
        );
        $q->bind_param("s", $email);
        $q->execute();
        $user = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$user) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'No account found with that email.'
            ]);
            return;
        }

        $otp      = (string) random_int(100000, 999999);
        $expires  = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $userType = self::WEB_OTP_USER_TYPE;
        $otpType  = self::OTP_TYPE_FORGOT;

        $del = $this->conn->prepare(
            "DELETE FROM user_otps WHERE email = ? AND user_type = ? AND otp_type = ?"
        );
        $del->bind_param("sss", $email, $userType, $otpType);
        $del->execute();
        $del->close();

        $ins = $this->conn->prepare(
            "INSERT INTO user_otps (email, user_type, otp_type, otp, expiration_date) VALUES (?, ?, ?, ?, ?)"
        );
        $ins->bind_param("sssss", $email, $userType, $otpType, $otp, $expires);

        if (!$ins->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Could not generate OTP. Please try again.']);
            return;
        }
        $ins->close();

        $mailer = new SendEmailController();
        $sent = $mailer->SendForgotPasswordCode($email, $otp, $user['first_name'], $user['last_name']);

        $this->cleanBuffer();
        if ($sent === "200") {
            echo json_encode(['status' => 'success', 'message' => 'OTP sent to your email.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send the email. Please try again.']);
        }
    }

    /**
     * Used by login-using-otp.php to make sure there's a live OTP
     * for this email before showing the form.
     */
    public function CheckAvailableOTP($email)
    {
        $now      = date('Y-m-d H:i:s');
        $userType = self::WEB_OTP_USER_TYPE;
        $otpType  = self::OTP_TYPE_FORGOT;

        $q = $this->conn->prepare("
            SELECT id FROM user_otps
            WHERE email = ? AND user_type = ? AND otp_type = ?
              AND expiration_date >= ?
            LIMIT 1
        ");
        $q->bind_param("ssss", $email, $userType, $otpType, $now);
        $q->execute();
        $q->store_result();
        $found = $q->num_rows > 0;
        $q->close();

        return $found;
    }

    /**
     * Step 2: verify the OTP and, if valid, log the user in.
     */
    public function LoginUsingOtp($email, $otp)
    {
        $this->cleanBuffer();

        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(0, '/');
            session_start();
        }

        // Basic brute-force guard for the 6-digit code
        $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
        if ($_SESSION['otp_attempts'] > self::OTP_MAX_ATTEMPTS) {
            session_write_close();
            echo json_encode([
                'status'  => 'error',
                'message' => 'Too many attempts. Please request a new OTP.'
            ]);
            return;
        }

        $email    = trim($email);
        $otp      = trim($otp);
        $now      = date('Y-m-d H:i:s');
        $userType = self::WEB_OTP_USER_TYPE;
        $otpType  = self::OTP_TYPE_FORGOT;

        $q = $this->conn->prepare("
            SELECT id, otp FROM user_otps
            WHERE email = ? AND user_type = ? AND otp_type = ?
              AND expiration_date >= ?
            ORDER BY expiration_date DESC
            LIMIT 1
        ");
        $q->bind_param("ssss", $email, $userType, $otpType, $now);
        $q->execute();
        $otpRow = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$otpRow || !hash_equals((string)$otpRow['otp'], $otp)) {
            session_write_close();
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP.']);
            return;
        }

        $u = $this->conn->prepare(
            "SELECT id, email, role FROM `web_users` WHERE `email` = ? AND `is_active` = 1"
        );
        $u->bind_param("s", $email);
        $u->execute();
        $user = $u->get_result()->fetch_assoc();
        $u->close();

        if (!$user) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'No account found with that email.'
            ]);
            return;
        }

        // OTP is one-time use
        $d = $this->conn->prepare("DELETE FROM user_otps WHERE id = ?");
        $d->bind_param("i", $otpRow['id']);
        $d->execute();
        $d->close();

        session_regenerate_id(true);
        $_SESSION['id'] = $user['id'];
        unset($_SESSION['otp_attempts']);
        session_write_close();

        $this->cleanBuffer();
        echo json_encode([
            'status'  => 'success',
            'message' => 'Logged in',
            'user'    => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]
        ]);
    }
}