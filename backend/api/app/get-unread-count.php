<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

include(__DIR__ . '/../../db/db.php');

$input = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');

if (empty($session_id)) {
    echo json_encode(['status' => 'error', 'count' => 0]);
    exit;
}

$conn = (new db_connect())->connect();

// 1. Get Student LRN
$stmt = $conn->prepare("SELECT u.lrn FROM sessions s JOIN users u ON s.user_id = u.id WHERE s.id = ? AND s.expiration > NOW()");
$stmt->bind_param("s", $session_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'count' => 0]);
    exit;
}
$lrn = $result->fetch_assoc()['lrn'];
$stmt->close();

// 2. Count unread notifications
$countStmt = $conn->prepare("
    SELECT COUNT(n.id) AS unread_count
    FROM student_teacher_assignments AS sta
    JOIN sections AS s ON sta.section_id = s.id
    JOIN notifications AS n ON s.id = n.section_id
    LEFT JOIN notification_reads AS nr ON n.id = nr.notification_id AND nr.student_lrn = ?
    WHERE sta.student_lrn = ? AND nr.id IS NULL
");
$countStmt->bind_param("ss", $lrn, $lrn);
$countStmt->execute();
$countResult = $countStmt->get_result();
$unread_count = $countResult->fetch_assoc()['unread_count'];
$countStmt->close();

echo json_encode(['status' => 'success', 'count' => $unread_count]);