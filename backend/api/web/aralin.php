<?php
// Prevent PHP notices/warnings from breaking the JSON response
error_reporting(0);
ini_set('display_errors', 0);

// DETECT IF FILE EXCEEDED PHP.INI LIMITS
if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'The uploaded video is too large! Please increase post_max_size and upload_max_filesize in your php.ini file.'
    ]);
    exit;
}

include_once(__DIR__ . '/../../controller/AralinController.php');

if (!isset($_POST['requestType'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'requestType is missing. The server did not receive the form data.',
        'raw_post' => $_POST,
        'raw_files' => $_FILES
    ]);
    exit;
}

$requestType = $_POST['requestType'];
$controller = new AralinController();

if ($requestType == "GetAralin") {
    $level_id = $_POST['level_id'];
    $controller->GetAralins($level_id);
} elseif ($requestType == "InsertAralin") {
    $post = $_POST;
    $files = $_FILES;
    $controller->InsertAralin($post);
} elseif ($requestType == "EditAralin") {
    $post = $_POST;
    $controller->EditAralin($post);
} elseif ($requestType == "GetDoneAralin") {
    $id = $_POST['userId'];
    $controller->GetDoneAralin($id);
} elseif ($requestType == "GetWatchHistory") {
    $id = $_POST['aralin_id'];
    $controller->GetWatchHistory($id);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid requestType']);
}