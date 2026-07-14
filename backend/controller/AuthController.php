<?php
// DELETE THE ERROR LINES THAT WERE HERE (ini_set...)

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    // ... (Keep your GetUser functions as they are) ...
    public function GetUser($id)
    {
        $q = $this->conn->prepare("SELECT * FROM `web_users` WHERE `id` = ? AND `is_active` = 1");
        $q->bind_param("i", $id);
        if ($q->execute()) { return $q->get_result(); } else { return null; }
    }

    public function GetUsingId($table, $id)
    {
        // Safely fetch data from any table using its ID
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
        ob_clean();
        $q = $this->conn->prepare(
            "SELECT * FROM `web_users` WHERE `id` = ? AND `is_active` = 1"
        );
        $q->bind_param("i", $id);
        if ($q->execute()) {
            $result = $q->get_result();
            $user   = $result->fetch_assoc();
            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'id'         => $user['id'],
                    'first_name' => $user['first_name'],  
                    'last_name'  => $user['last_name'],   
                    'name'       => $user['first_name'] . ' ' . $user['last_name'], // backward compat
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                ]
            ]);
        }
    }

    public function Login($data)
    {
        ob_clean(); // Clean start
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

                ob_clean(); // Clean before output
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Logged in',
                    'user' => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]
                ]);
            } else {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
            }
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Something went wrong.']);
        }
    }

    // --- FIX 1: UPDATE PROFILE PICTURE ---
    public function UpdateProfilePicture($id, $file)
    {
        ob_clean(); // 1. Clean garbage output

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
                    ob_clean(); // 2. Clean again before success
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

    // --- FIX 2: UPDATE USER DETAILS ---
    public function UpdateUser($id, $first_name, $last_name, $email, $newPassword)
    {
        ob_clean();

        if (!$this->conn) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
            return;
        }

        // 1. Format validation
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
            return;
        }

        // 2. Check if email is already taken by ANOTHER user
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

        // 3. Build update query
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
            ob_clean();
            echo json_encode(['status' => 'success', 'message' => 'User updated successfully.']);
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $stmt->error]);
        }
        $stmt->close();
    }
    
    // ... (Keep the rest of your functions like OTP, Sections, etc.) ...
     public function SendForGotPasswordOtp($email) {
         // ... Keep your existing code ...
         // Just ensure you add ob_clean() at the start if it has issues too
         ob_clean(); 
         // ... rest of code
     }
     
     // ... (Keep CheckAvailableOTP and LoginUsingOtp) ...
      public function LoginUsingOtp($email, $otp) {
          // ... Keep existing code ...
          // Make sure to remove ini_set here too if you copied it previously
      }
}