<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

date_default_timezone_set('Asia/Manila');

include_once(__DIR__ . '/../../db/db.php');

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod !== "POST") {
    http_response_code(405);
    echo json_encode([
        'status' => 405,
        'message' => "$requestMethod method not allowed."
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$email            = trim($input['email'] ?? '');
$lrn              = trim($input['lrn'] ?? '');
$password         = $input['password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';
$otp              = trim($input['otp'] ?? '');

$errors = [];
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
if (empty($lrn) || !preg_match('/^\d{10,}$/', $lrn)) $errors[] = "LRN must be at least 10 digits.";
if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
if (empty($otp)) $errors[] = "OTP is required.";

if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}

$conn = (new db_connect())->connect();

// --- Verify OTP ---
$now = date('Y-m-d H:i:s');
$otpStmt = $conn->prepare("
    SELECT id, otp
    FROM user_otps
    WHERE email = ?
      AND user_type = 'user'
      AND otp_type = 'register'
      AND expiration_date >= ?
    ORDER BY expiration_date DESC
    LIMIT 1
");
$otpStmt->bind_param("ss", $email, $now);
$otpStmt->execute();
$otpData = $otpStmt->get_result()->fetch_assoc();
$otpStmt->close();

if (!$otpData) {
    echo json_encode([
        'status' => 'error',
        'message' => 'OTP expired or not found. Please request a new one.'
    ]);
    exit;
}

if ($otpData['otp'] != $otp) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Incorrect OTP.'
    ]);
    exit;
}

// --- Re-check LRN assignment + duplicates (in case anything changed since OTP was sent) ---
$lrn_check_stmt = $conn->prepare("SELECT 1 FROM student_teacher_assignments WHERE student_lrn = ? LIMIT 1");
$lrn_check_stmt->bind_param("s", $lrn);
$lrn_check_stmt->execute();
$lrn_check_stmt->store_result();

if ($lrn_check_stmt->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'LRN is not assigned to any teacher / section.'
    ]);
    exit;
}
$lrn_check_stmt->close();

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR lrn = ?");
$stmt->bind_param("ss", $email, $lrn);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Email or LRN already exists.'
    ]);
    exit;
}
$stmt->close();

// --- Create the account ---
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

$insert = $conn->prepare("INSERT INTO users (lrn, email, password, points) VALUES (?, ?, ?, 0)");
$insert->bind_param("sss", $lrn, $email, $hashed_password);

if (!$insert->execute()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to register: ' . $insert->error
    ]);
    exit;
}

$user_id = $insert->insert_id;
$insert->close();

// Invalidate the OTP so it can't be reused
$delete_otp = $conn->prepare("DELETE FROM user_otps WHERE id = ?");
$delete_otp->bind_param("i", $otpData['id']);
$delete_otp->execute();
$delete_otp->close();

// --- Create session (log them straight in, matching register.php's old behavior) ---
$session_id = bin2hex(random_bytes(32));
$expiration = date('Y-m-d H:i:s', strtotime('+7 days'));

$session_stmt = $conn->prepare("INSERT INTO sessions (id, user_id, expiration) VALUES (?, ?, ?)");
$session_stmt->bind_param("sis", $session_id, $user_id, $expiration);

if ($session_stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Registration successful.',
        'session' => [
            'id' => $session_id,
            'user_id' => $user_id,
            'expires_at' => $expiration
        ]
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'message' => 'Registered, but auto-login failed. Please log in manually.'
    ]);
}