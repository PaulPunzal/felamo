<?php
// $servername = "localhost";
// $username   = "u240756803_felamov3";     
// $password   = "hehcE6-fotcab-viskaj";          
// $dbname     = "u240756803_felamov3";

$servername = "localhost";
// Use the local dev credentials matching backend/db/db.php
$username   = "devuser";     
$password   = "DevPass123!";          
$dbname     = "felamo";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>