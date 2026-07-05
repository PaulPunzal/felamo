<?php
include_once(__DIR__ . '/../../controller/AdminsController.php');

$requestType = $_POST['requestType'];

$controller = new AdminsController();

if ($requestType == "GetTeachers") {
    $controller->GetTeachers();
} elseif ($requestType == "InsertTeacher") {
    $first_name    = $_POST['first_name'];
    $middle_name   = $_POST['middle_name'] ?? '';
    $last_name     = $_POST['last_name'];
    $email         = $_POST['email'];
    $plainPassword = $_POST['password'];
    $controller->InsertTeacher($first_name, $middle_name, $last_name, $email, $plainPassword);
} elseif ($requestType == "UpdateTeacher") {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $grade = $_POST['grade'];
    $section = $_POST['section'];
    $email = $_POST['email'];
    $plainPassword = $_POST['password'];
    $is_active = $_POST['is_active'];

    $controller->UpdateTeacher($id, $name, $grade, $section, $email, $plainPassword, $is_active);
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}