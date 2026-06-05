<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

include(__DIR__ . '/../../db/db.php');

$input = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');
$action = trim($input['action'] ?? 'single'); // Default to 'single' if not provided
$notification_id = $input['notification_id'] ?? null;

if (empty($session_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing session_id.']);
    exit;
}

$conn = (new db_connect())->connect();

// Validate session and get LRN
$stmt = $conn->prepare("SELECT u.lrn FROM sessions s JOIN users u ON s.user_id = u.id WHERE s.id = ? AND s.expiration > NOW()");
$stmt->bind_param("s", $session_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid session.']);
    exit;
}
$lrn = $result->fetch_assoc()['lrn'];
$stmt->close();

$success = false;

if ($action === 'all') {
    // BULK ACTION: Mark all unread notifications as read
    $insertStmt = $conn->prepare("
        INSERT IGNORE INTO notification_reads (notification_id, student_lrn)
        SELECT n.id, ? 
        FROM student_teacher_assignments AS sta
        JOIN sections AS s ON sta.section_id = s.id
        JOIN notifications AS n ON s.id = n.section_id
        WHERE sta.student_lrn = ?
    ");
    $insertStmt->bind_param("ss", $lrn, $lrn);
    $success = $insertStmt->execute();
    $insertStmt->close();

} else {
    // SINGLE ACTION: Mark a specific notification as read
    if (empty($notification_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing notification_id.']);
        exit;
    }
    $insertStmt = $conn->prepare("INSERT IGNORE INTO notification_reads (notification_id, student_lrn) VALUES (?, ?)");
    $insertStmt->bind_param("is", $notification_id, $lrn);
    $success = $insertStmt->execute();
    $insertStmt->close();
}

if ($success) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database operation failed.']);
}