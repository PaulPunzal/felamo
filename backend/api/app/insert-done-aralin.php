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
$aralin_id = trim($input['aralin_id'] ?? '');

$errors = [];

if (empty($aralin_id)) $errors[] = "Aralin ID is required.";
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
$session_stmt->bind_result($user_id);
$session_stmt->fetch();
$session_stmt->close();

if (empty($user_id)) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Invalid or expired session.'
    ]);
    exit;
}

$aralin_stmt = $conn->prepare("SELECT id FROM aralin WHERE id = ?");
$aralin_stmt->bind_param("i", $aralin_id);
$aralin_stmt->execute();
$aralin_stmt->store_result();

if ($aralin_stmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'status' => 404,
        'message' => 'Aralin not found.'
    ]);
    exit;
}
$aralin_stmt->close();

$chk = $conn->prepare(
    "SELECT id, video_reward_claimed FROM student_aralin_progress WHERE user_id = ? AND aralin_id = ?"
);
$chk->bind_param("ii", $user_id, $aralin_id);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();

$points_awarded = 0;

if (!$existing) {
    // First time watching — insert and award halo-halo points (50 pts)
    $ins = $conn->prepare(
        "INSERT INTO student_aralin_progress (user_id, aralin_id, completed_at, needs_rewatch, video_reward_claimed)
         VALUES (?, ?, NOW(), 0, 1)"
    );
    $ins->bind_param("ii", $user_id, $aralin_id);
    $ins->execute();

    $points_awarded = 50;
    $upd = $conn->prepare("UPDATE users SET points = points + 50, total_points = total_points + 50 WHERE id = ?");
    $upd->bind_param("i", $user_id);
    $upd->execute();

} else {
    // Re-watch after a failed attempt — clear the rewatch flag, NO bonus
    $upd = $conn->prepare(
        "UPDATE student_aralin_progress SET needs_rewatch = 0, completed_at = NOW()
         WHERE user_id = ? AND aralin_id = ?"
    );
    $upd->bind_param("ii", $user_id, $aralin_id);
    $upd->execute();
}

echo json_encode([
    'status'          => 'success',
    'message'         => $points_awarded > 0 ? 'Aralin marked done.' : 'Aralin re-watched — quiz unlocked.',
    'points_received' => $points_awarded,
    'first_watch'     => $points_awarded > 0,
]);