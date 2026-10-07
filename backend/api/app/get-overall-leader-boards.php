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

$errors = [];
if (empty($session_id)) $errors[] = "Session ID is required.";

if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Validation failed.',
        'errors' => $errors
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

$user_stmt = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->store_result();

if ($user_stmt->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not found.'
    ]);
    exit;
}

$user_stmt->bind_result($lrn);
$user_stmt->fetch();
$user_stmt->close();

$section_stmt = $conn->prepare("SELECT section_id FROM student_teacher_assignments WHERE student_lrn = ?");
$section_stmt->bind_param("s", $lrn);
$section_stmt->execute();
$section_stmt->store_result();

if ($section_stmt->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Section not found for this student.'
    ]);
    exit;
}

$section_stmt->bind_result($section_id);
$section_stmt->fetch();
$section_stmt->close();

$query = "
    SELECT u.id, u.first_name, u.last_name, u.total_points, u.points
    FROM student_teacher_assignments AS sta
    JOIN users AS u ON sta.student_lrn = u.lrn
    WHERE sta.section_id = ?
    ORDER BY u.total_points DESC, u.id ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $section_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    'status' => 'success',
    'my_user_id' => (int)$user_id,
    'data' => $data
]);
exit;
