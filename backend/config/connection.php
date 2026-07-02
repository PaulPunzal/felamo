<?php
require_once __DIR__ . '/env.php';

$servername = app_env('DB_HOST', 'localhost');
$username   = app_env('DB_USER', 'devuser');
$password   = app_env('DB_PASS', 'DevPass123!');
$dbname     = app_env('DB_NAME', 'felamo');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>