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

if (empty($session_id)) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Session ID is required.'
    ]);
    exit;
}

$conn = (new db_connect())->connect();

// Validate session
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

// Get student's LRN
$lrn_stmt = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$lrn_stmt->bind_param("i", $user_id);
$lrn_stmt->execute();
$lrn_stmt->bind_result($student_lrn);
$lrn_stmt->fetch();
$lrn_stmt->close();

if (!$student_lrn) {
    http_response_code(404);
    echo json_encode([
        'status' => 404,
        'message' => 'Student not found.'
    ]);
    exit;
}

// Get teacher ID
$assign_stmt = $conn->prepare("SELECT s.teacher_id FROM student_teacher_assignments AS sta
JOIN sections AS s ON sta.section_id = s.id WHERE sta.student_lrn = ?");
$assign_stmt->bind_param("s", $student_lrn);
$assign_stmt->execute();
$assign_stmt->bind_result($teacher_id);
$assign_stmt->fetch();
$assign_stmt->close();

if (!$teacher_id) {
    http_response_code(404);
    echo json_encode([
        'status' => 404,
        'message' => 'No teacher assigned to this student.'
    ]);
    exit;
}

// Fetch all levels with aralin for this teacher
$join_stmt = $conn->prepare("
    SELECT 
        l.id AS level_id,
        l.teacher_id,
        l.level,
        a.id AS aralin_id,
        a.aralin_no,
        a.aralin_title,
        a.summary,
        a.details,
        a.attachment_filename
    FROM levels l
    LEFT JOIN aralin a ON a.level_id = l.id
    WHERE l.teacher_id = ?
    ORDER BY l.level ASC, a.aralin_no ASC
");
$join_stmt->bind_param("i", $teacher_id);
$join_stmt->execute();
$result = $join_stmt->get_result();

$levels = [];
// Keep track of the previous level's completion status to cascade unlocks
$previous_level_done = true; 

while ($row = $result->fetch_assoc()) {
    $level_id = $row['level_id'];

    if (!isset($levels[$level_id])) {
        $title = '';
        switch ($row['level']) {
            case 1: $title = 'Unang markahan'; break;
            case 2: $title = 'Pangalawang markahan'; break;
            case 3: $title = 'Pangatlong markahan'; break;
            case 4: $title = 'Ika-apat na Markahan'; break;
        }

        // FIX: Count total assessments in this level vs. how many the student PASSED
        $check_done_stmt = $conn->prepare("
            SELECT 
                (SELECT COUNT(a.id) FROM assessments a JOIN aralin ar ON a.aralin_id = ar.id WHERE ar.level_id = ?) as total_assessments,
                (SELECT COUNT(DISTINCT at.assessment_id) FROM assessment_results at JOIN assessments a ON at.assessment_id = a.id JOIN aralin ar ON a.aralin_id = ar.id WHERE ar.level_id = ? AND at.lrn = ? AND at.is_completed = 1) as passed_assessments
        ");

        $check_done_stmt->bind_param("iis", $level_id, $level_id, $student_lrn);
        $check_done_stmt->execute();
        $check_done_stmt->bind_result($total_assessments, $passed_assessments);
        $check_done_stmt->fetch();
        $check_done_stmt->close();

        // A markahan is ONLY done if there are assessments AND the student passed all of them
        $is_done = ($total_assessments > 0 && $total_assessments == $passed_assessments);

        $levels[$level_id] = [
            'id' => $level_id,
            'teacher_id' => $row['teacher_id'],
            'level' => $row['level'],
            'title' => $title,
            'is_done' => $is_done,
            'is_unlocked' => $previous_level_done, // Added explicitly for safety
            'aralins' => []
        ];
        
        // Update variable for the NEXT level to check in the loop
        $previous_level_done = $is_done; 
    }

    if (!empty($row['aralin_id'])) {
        $levels[$level_id]['aralins'][] = [
            'id'                  => $row['aralin_id'],
            'aralin_no'           => $row['aralin_no'],
            'aralin_title'        => $row['aralin_title'] ?? '',
            'summary'             => $row['summary']             ?? '',
            'details'             => $row['details']             ?? '',
            'attachment_filename' => $row['attachment_filename'] ?? '',
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'data' => array_values($levels)
]);
exit;