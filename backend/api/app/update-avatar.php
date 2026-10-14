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
$avatar_id = trim($input['avatar_id'] ?? '');

if (empty($session_id) || empty($avatar_id)) {
    http_response_code(400);
    echo json_encode([
        'status' => 400,
        'message' => 'Session ID and Avatar ID are required.'
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

$own_stmt = $conn->prepare("SELECT owned_avatars FROM users WHERE id = ?");
$own_stmt->bind_param("i", $user_id);
$own_stmt->execute();
$own_stmt->bind_result($owned_json);
$own_stmt->fetch();
$own_stmt->close();

$owned_ids = array_map('intval', json_decode($owned_json ?: '[]', true) ?: []);

if (!in_array((int)$avatar_id, $owned_ids, true)) {
    http_response_code(403);
    echo json_encode([
        'status' => 403,
        'message' => 'You do not own this avatar.'
    ]);
    exit;
}

$update_stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$update_stmt->bind_param("ii", $avatar_id, $user_id);
$update_success = $update_stmt->execute();
$update_stmt->close();

if (!$update_success) {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Failed to update avatar.'
    ]);
    exit;
}

$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Avatar equipped successfully.',
    'user' => $user_data
]);
exit;
