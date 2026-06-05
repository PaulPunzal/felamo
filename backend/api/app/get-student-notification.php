<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    http_response_code(405);
    echo json_encode([
        'status' => 405,
        'message' => "POST method required."
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');

if (empty($session_id)) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
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
        'status' => 'error',
        'message' => 'Invalid or expired session.'
    ]);
    exit;
}

$session_stmt->bind_result($user_id);
$session_stmt->fetch();
$session_stmt->close();
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");

if (!$user_stmt) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'User query prepare failed: ' . $conn->error
    ]);
    exit;
}

$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_data || empty($user_data['lrn'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User LRN not found.'
    ]);
    exit;
}

$user_lrn = $user_data['lrn'];

// Notice we bind the LRN twice: once for the JOIN, once for the WHERE clause
$stmt = $conn->prepare("
    SELECT 
        n.id,
        n.title,
        n.description,
        n.created_at,
        n.section_id,
        IF(nr.read_at IS NOT NULL, 1, 0) AS is_read
    FROM student_teacher_assignments AS sta
    JOIN sections AS s ON sta.section_id = s.id
    JOIN notifications AS n ON s.id = n.section_id
    LEFT JOIN notification_reads AS nr ON n.id = nr.notification_id AND nr.student_lrn = ?
    WHERE sta.student_lrn = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare notification query: ' . $conn->error]);
    exit;
}

// Bind LRN twice ("ss" for two strings)
$stmt->bind_param("ss", $user_lrn, $user_lrn);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to execute notification query: ' . $stmt->error
    ]);
    exit;
}

$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// if (empty($notifications)) {
//     http_response_code(404);
//     echo json_encode([
//         'status' => 'error',
//         'message' => 'No notifications found.'
//     ]);
//     exit;
// }

echo json_encode([
    'status' => 'success',
    'data' => $notifications
]);
exit;
