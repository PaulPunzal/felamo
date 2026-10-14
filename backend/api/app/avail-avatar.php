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
$avatar_price = (int)$avatar_price;

$user_stmt = $conn->prepare("SELECT points, owned_avatars FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_points, $owned_json);
$user_stmt->fetch();
$user_stmt->close();

$user_points = (int)$user_points;
$owned_ids = array_map('intval', json_decode($owned_json ?: '[]', true) ?: []);

if (in_array((int)$avatar_id, $owned_ids, true)) {
    http_response_code(409);
    echo json_encode([
        'status' => 409,
        'message' => 'You already own this avatar.'
    ]);
    exit;
}

if ($user_points < $avatar_price) {
    http_response_code(403);
    echo json_encode([
        'status' => 403,
        'message' => 'Insufficient points to buy this avatar.'
    ]);
    exit;
}

$owned_ids[] = (int)$avatar_id;
$new_owned_json = json_encode(array_values(array_unique($owned_ids)));

$conn->begin_transaction();

$update_stmt = $conn->prepare(
    "UPDATE users 
     SET points = points - ?, owned_avatars = ? 
     WHERE id = ? AND points >= ? AND owned_avatars = ?"
);
$update_stmt->bind_param("isiis", $avatar_price, $new_owned_json, $user_id, $avatar_price, $owned_json);
$ok = $update_stmt->execute();
$affected = $update_stmt->affected_rows;
$update_stmt->close();

if (!$ok || $affected === 0) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Failed to save avatar purchase.'
    ]);
    exit;
}

$conn->commit();

echo json_encode([
    'status' => 'success',
    'message' => 'Avatar purchased successfully.',
    'remaining_points' => $user_points - $avatar_price
]);
exit;