<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

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
$session_id = trim($input['session_id'] ?? '');
$level_id = trim($input['level_id'] ?? '');

$errors = [];

if (empty($level_id)) $errors[] = "Level Id is required.";

if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}

if (empty($session_id)) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Session ID is required.'
    ]);
    exit;
}

$conn = (new db_connect())->connect();

$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_stmt->store_result();

if ($session_stmt->num_rows === 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Invalid or expired session.'
    ]);
    exit;
}

$session_stmt->bind_result($user_id);
$session_stmt->fetch();
$session_stmt->close();

$stmt = $conn->prepare("SELECT * FROM aralin WHERE level_id = ?");
$stmt->bind_param("i", $level_id);
$stmt->execute();
$result = $stmt->get_result();

$aralin_data = [];

while ($row = $result->fetch_assoc()) {
    // FIX: Select needs_rewatch instead of just '1'
    $done_stmt = $conn->prepare("SELECT needs_rewatch FROM student_aralin_progress WHERE aralin_id = ? AND user_id = ? LIMIT 1");
    $done_stmt->bind_param("ii", $row['id'], $user_id);
    $done_stmt->execute();
    $done_stmt->store_result();

    $is_done = false;
    $needs_rewatch = false;

    if ($done_stmt->num_rows > 0) {
        $done_stmt->bind_result($nw_flag);
        $done_stmt->fetch();
        $is_done = true;
        $needs_rewatch = ($nw_flag == 1);
    }
    $done_stmt->close();

    $row['is_done'] = $is_done;
    $row['needs_rewatch'] = $needs_rewatch; // Pass this to Flutter

    $aralin_data[] = $row;
}

echo json_encode([
    'status' => 'success',
    'data' => $aralin_data
]);
exit;
