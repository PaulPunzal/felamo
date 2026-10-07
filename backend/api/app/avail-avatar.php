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

$owned_stmt = $conn->prepare("SELECT 1 FROM user_avatars WHERE avatar_id = ? AND user_id = ?");
$owned_stmt->bind_param("ii", $avatar_id, $user_id);
$owned_stmt->execute();
$owned_stmt->store_result();

if ($owned_stmt->num_rows > 0) {
    http_response_code(409);
    echo json_encode([
        'status' => 409,
        'message' => 'You already own this avatar.'
    ]);
    exit;
}
$owned_stmt->close();

$avatar_stmt = $conn->prepare("SELECT price FROM avatars WHERE id = ?");
$avatar_stmt->bind_param("i", $avatar_id);
$avatar_stmt->execute();
$avatar_stmt->bind_result($avatar_price);
$avatar_stmt->fetch();
$avatar_stmt->close();

if ($avatar_price === null) {
    http_response_code(404);
    echo json_encode([
        'status' => 404,
        'message' => 'Avatar not found.'
    ]);
    exit;
}

$user_stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_points);
$user_stmt->fetch();
$user_stmt->close();

if ($user_points < $avatar_price) {
    http_response_code(403);
    echo json_encode([
        'status' => 403,
        'message' => 'Insufficient points to buy this avatar.'
    ]);
    exit;
}

$insert_stmt = $conn->prepare("INSERT INTO user_avatars (user_id, avatar_id) VALUES (?, ?)");
$insert_stmt->bind_param("ii", $user_id, $avatar_id);
$insert_success = $insert_stmt->execute();
$insert_stmt->close();

if (!$insert_success) {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Failed to save avatar purchase.'
    ]);
    exit;
}

$new_points = $user_points - $avatar_price;
$update_stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?");
$update_stmt->bind_param("iii", $avatar_price, $user_id, $avatar_price);
$update_stmt->execute();
if ($update_stmt->affected_rows === 0)
$update_stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Avatar purchased successfully.',
    'remaining_points' => $new_points
]);
exit;
