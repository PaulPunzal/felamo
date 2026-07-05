<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include('../backend/controller/AuthController.php');

$AuthController = new AuthController();

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

$auth_user_id = $_SESSION['id'];

$userResult = $AuthController->GetUser($auth_user_id);

if ($userResult->num_rows > 0) {
    $user = $userResult->fetch_assoc();
} else {
    header("Location: ../logout.php");
    exit;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Felamo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-main">
        <div class="container">
            <a class="navbar-brand fw-bold" href="home.php">
                <img src="../backend/storage/assets/logo.png" alt="Felamo" style="width: 100px;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto">
                    <?php
                    if ($user['role'] == "teacher") {
                    ?>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="levels.php">Markahan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="my_sections.php">My Sections</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="students.php">My Students</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="leader_boards.php">Leader Boards</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="notifications.php">Notifications</a>
                        </li>
                    <?php
                    }
                    ?>
                    <?php
                    if ($user['role'] == "super_admin") {
                    ?>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="teachers.php">Teachers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="students.php">Students</a>
                        </li>
                        <?php
                    }
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?= htmlspecialchars($currentUserName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end bg-main" aria-labelledby="userDropdown">

                            <li></li>
                            <li><a class="dropdown-item text-light" href="profile.php">Profile</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-light" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>

            </div>
        </div>
    </nav>

    <div id="alert" style="position: absolute; top:10px; right:10px; font-size: 12px;"></div>