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

$limit  = max(1, min(50, (int)($input['limit'] ?? 13)));
$offset = max(0, (int)($input['offset'] ?? 0));

// Ranked list of the student's own section
$base = "
    SELECT u.id, u.first_name, u.last_name, u.total_points,
           ROW_NUMBER() OVER (ORDER BY u.total_points DESC, u.id ASC) AS rnk
    FROM student_teacher_assignments AS sta
    JOIN users AS u ON sta.student_lrn = u.lrn
    WHERE sta.section_id = ?
";

// 1. The requested page
$stmt = $conn->prepare("SELECT * FROM ($base) AS ranked ORDER BY rnk ASC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $section_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'           => (int)$row['id'],
        'first_name'   => $row['first_name'],
        'last_name'    => $row['last_name'],
        'total_points' => (int)$row['total_points'],
        'rank'         => (int)$row['rnk'],
    ];
}
$stmt->close();

// 2. Total students in the section
$cnt = $conn->prepare("SELECT COUNT(*) FROM ($base) AS ranked");
$cnt->bind_param("i", $section_id);
$cnt->execute();
$cnt->bind_result($total);
$cnt->fetch();
$cnt->close();

// 3. This student's own rank
$me = $conn->prepare("SELECT rnk FROM ($base) AS ranked WHERE id = ?");
$me->bind_param("ii", $section_id, $user_id);
$me->execute();
$me->bind_result($my_rank);
$has_me = $me->fetch();
$me->close();

echo json_encode([
    'status'   => 'success',
    'data'     => $data,
    'total'    => (int)$total,
    'has_more' => ($offset + count($data)) < (int)$total,
    'my_rank'  => $has_me ? (int)$my_rank : null,
]);
exit;