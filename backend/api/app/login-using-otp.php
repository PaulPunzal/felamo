<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

date_default_timezone_set('Asia/Manila');

include(__DIR__ . '/../../db/db.php');
require_once(__DIR__ . '/../../controller/SendEmailController.php');

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
$email = trim($input['email'] ?? '');
$otp   = trim($input['otp'] ?? '');

$errors = [];
if (!$email) $errors[] = "Email is required.";
if (!$otp) $errors[] = "OTP is required.";

if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    return;
}

$conn = (new db_connect())->connect();

$stmt = $conn->prepare("
    SELECT users.*, avatars.filename AS avatar_file_name 
    FROM users 
    LEFT JOIN avatars ON users.avatar = avatars.id 
    WHERE users.email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode([
        'status' => 404,
        'message' => 'No user found with that email.'
    ]);
    return;
}

$now = date('Y-m-d H:i:s');
$otpStmt = $conn->prepare("
    SELECT otp 
    FROM user_otps 
    WHERE email = ? 
      AND user_type = 'user' 
      AND otp_type = 'forgot_password' 
      AND expiration_date >= ? 
    ORDER BY expiration_date DESC 
    LIMIT 1
");
$otpStmt->bind_param("ss", $email, $now);
$otpStmt->execute();
$otpData = $otpStmt->get_result()->fetch_assoc();

if (!$otpData) {
    echo json_encode([
        'status' => 404,
        'message' => 'OTP expired or not found.'
    ]);
    return;
}

if ($otpData['otp'] != $otp) {
    echo json_encode([
        'status' => 400,
        'message' => 'Incorrect OTP.'
    ]);
    return;
}

$session_id = bin2hex(random_bytes(32));
$expiration = date('Y-m-d H:i:s', strtotime('+7 days'));
$user_id = $user['id'];

$session_stmt = $conn->prepare("INSERT INTO sessions (id, user_id, expiration) VALUES (?, ?, ?)");
$session_stmt->bind_param("sis", $session_id, $user_id, $expiration);

if (!$session_stmt->execute()) {
    echo json_encode([
        'status' => 500,
        'message' => 'Login succeeded but session creation failed.'
    ]);
    return;
}

$date_today = date('Y-m-d');
$points = 0;

$check_today_stmt = $conn->prepare("SELECT id FROM daily_login WHERE user_id = ? AND date = ?");
$check_today_stmt->bind_param("is", $user_id, $date_today);
$check_today_stmt->execute();
$check_today_stmt->store_result();

if ($check_today_stmt->num_rows === 0) {
    $streak = 0;
    for ($i = 1; $i <= 6; $i++) {
        $check_date = date('Y-m-d', strtotime("-$i days"));
        $check_streak_stmt = $conn->prepare("SELECT id FROM daily_login WHERE user_id = ? AND date = ?");
        $check_streak_stmt->bind_param("is", $user_id, $check_date);
        $check_streak_stmt->execute();
        $check_streak_stmt->store_result();

        if ($check_streak_stmt->num_rows > 0) $streak++;
        else break;

        $check_streak_stmt->close();
    }

    $type = ($streak === 6) ? '7th streak login' : 'login';
    $points = ($streak === 6) ? 100 : 10;

    $insert_stmt = $conn->prepare("INSERT INTO daily_login (user_id, date, type, points) VALUES (?, ?, ?, ?)");
    $insert_stmt->bind_param("issi", $user_id, $date_today, $type, $points);
    $insert_stmt->execute();
    $insert_stmt->close();

    if ($points > 0) {
        $update_points_stmt = $conn->prepare("UPDATE users SET points = points + ?, total_points = total_points + ? WHERE id = ?");
        $update_points_stmt->bind_param("iii", $points, $points, $user_id);
        $update_points_stmt->execute();
        $update_points_stmt->close();

        $user['points'] += $points;
    }
}

$check_today_stmt->close();

$current_streak = 0;

$yesterday = date('Y-m-d', strtotime('-1 day'));
$prev_stmt = $conn->prepare("SELECT type FROM daily_login WHERE user_id = ? AND date = ?");
$prev_stmt->bind_param("is", $user_id, $yesterday);
$prev_stmt->execute();
$prev_result = $prev_stmt->get_result();
$reset_streak = false;
if ($prev_row = $prev_result->fetch_assoc()) {
    if ($prev_row['type'] === '7th streak login') $reset_streak = true;
}
$prev_stmt->close();

if (!$reset_streak) {
    for ($i = 1; $i <= 6; $i++) {
        $check_date = date('Y-m-d', strtotime("-$i days"));
        $check_stmt = $conn->prepare("SELECT id FROM daily_login WHERE user_id = ? AND date = ?");
        $check_stmt->bind_param("is", $user_id, $check_date);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) $current_streak++;
        else break;

        $check_stmt->close();
    }

    // Include today
    $today_stmt = $conn->prepare("SELECT id FROM daily_login WHERE user_id = ? AND date = ?");
    $today_stmt->bind_param("is", $user_id, $date_today);
    $today_stmt->execute();
    $today_stmt->store_result();
    if ($today_stmt->num_rows > 0) $current_streak++;
    $today_stmt->close();
}

echo json_encode([
    'status' => 200,
    'message' => 'Login successful.',
    'session' => [
        'id' => $session_id,
        'user_id' => $user_id,
        'expires_at' => $expiration
    ],
    'user' => $user,
    'points_received' => $points,
    'current_streak' => $current_streak
]);
