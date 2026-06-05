<?php
include_once(__DIR__ . '/../../controller/NotificationsController.php');

$requestType = $_POST['requestType'];

$controller = new NotificationsController();

if ($requestType == "GetCreatedNotification") {
    $created_by = $_POST['auth_user_id'];
    $controller->GetCreatedNotification($created_by);
} elseif ($requestType == "CreateNotification") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $created_by = $_POST['auth_user_id'];
    $section_id = $_POST['section_id'];
    $controller->CreateNotification($title, $description, $created_by, $section_id);
} elseif ($requestType == "GetNotificationReadStatus") {
    $notification_id = $_POST['notification_id'];
    $section_id = $_POST['section_id'];
    $controller->GetNotificationReadStatus($notification_id, $section_id);
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}
