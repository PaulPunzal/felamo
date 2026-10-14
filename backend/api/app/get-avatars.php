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

$current_avatar_id = null;
$owned_json = '[]';
$user_stmt = $conn->prepare("SELECT avatar, owned_avatars FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($current_avatar_id, $owned_json);
$user_stmt->fetch();
$user_stmt->close();

$owned_ids = array_map('intval', json_decode($owned_json ?: '[]', true) ?: []);

$avatar_stmt = $conn->prepare("SELECT * FROM avatars");
$avatar_stmt->execute();
$avatar_result = $avatar_stmt->get_result();

$avatars = [];

while ($row = $avatar_result->fetch_assoc()) {
    $row['owned'] = in_array((int)$row['id'], $owned_ids, true);
    $row['is_using'] = ($current_avatar_id == $row['id']);
    $avatars[] = $row;
}

echo json_encode([
    'status' => 'success',
    'data' => $avatars
]);
exit;
