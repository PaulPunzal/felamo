<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

date_default_timezone_set('Asia/Manila');

include_once(__DIR__ . '/../../db/db.php');
require_once(__DIR__ . '/../../controller/SendEmailController.php');
require_once(__DIR__ . '/../../controller/OtpController.php');

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

$errors = [];
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
if (empty($lrn) || !preg_match('/^\d{10,}$/', $lrn)) $errors[] = "LRN must be at least 10 digits.";
if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}

$conn = (new db_connect())->connect();

// LRN must belong to a teacher/section before anyone can register with it
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

// Block only if THIS lrn already has a REAL (password-set) account
$lrn_stmt = $conn->prepare("SELECT password FROM users WHERE lrn = ?");
$lrn_stmt->bind_param("s", $lrn);
$lrn_stmt->execute();
$lrn_row = $lrn_stmt->get_result()->fetch_assoc();
$lrn_stmt->close();

if ($lrn_row && !empty($lrn_row['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'LRN already has an account.']);
    exit;
}

// Block if the email is already used by a DIFFERENT lrn
$email_stmt = $conn->prepare("SELECT lrn FROM users WHERE email = ? AND lrn != ?");
$email_stmt->bind_param("ss", $email, $lrn);
$email_stmt->execute();
$email_stmt->store_result();
if ($email_stmt->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Email is already used by another student.']);
    exit;
}
$email_stmt->close();

$otp = (string) rand(100000, 999999);

$otpController = new OtpController();
$stored = $otpController->StoreOTP($email, 'user', 'register', $otp);

if (!$stored) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to generate OTP. Please try again.'
    ]);
    exit;
}

$SendEmail = new SendEmailController();
ob_start();
$SendEmail->SendCode($email, $otp, $email, ''); // no first/last name collected yet
$result = ob_get_clean();

if ($result === "200") {
    echo json_encode([
        'status' => 200,
        'message' => 'OTP has been sent to your email.'
        // NOTE: intentionally NOT returning 'otp' here — the client must
        // get it from the actual email, not the API response.
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to send OTP. Please check your email address and try again.'
    ]);
}