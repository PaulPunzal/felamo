<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class UsersController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    public function GetStudents()
    {
        $q = $this->conn->prepare("SELECT * FROM `users` WHERE `is_active` = 1");

        if ($q->execute()) {
            $result = $q->get_result();

            $students = [];

            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $students
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Something went wrong.'
            ]);
        }
        
    }
}
