<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class AdminsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    public function GetTeachers()
    {
        $q = $this->conn->prepare("SELECT * FROM `web_users` WHERE `role` = 'teacher' AND `is_active` = 1");
        // $q->bind_param("s", 'teacher');

        if ($q->execute()) {
            $result = $q->get_result();

            $teachers = [];

            while ($row = $result->fetch_assoc()) {
                $teachers[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $teachers
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Something went wrong.'
            ]);
        }
    }

    public function InsertTeacher($first_name, $middle_name, $last_name, $email, $plainPassword)
    {
        $checkEmail = $this->conn->prepare("SELECT id FROM `web_users` WHERE `email` = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $checkEmail->store_result();
        if ($checkEmail->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email is already existing!'
            ]);
            $checkEmail->close();
            return;
        }
        $checkEmail->close();

        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $role = 'teacher';
        $is_active = 1;

        $stmt = $this->conn->prepare("
            INSERT INTO `web_users` (`first_name`, `middle_name`, `last_name`, `email`, `password`, `role`, `grade_level`, `is_active`)
            VALUES (?, ?, ?, ?, ?, ?, 7, ?)
        ");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement.']);
            return;
        }

        $stmt->bind_param("ssssssi", $first_name, $middle_name, $last_name, $email, $hashedPassword, $role, $is_active);

        if ($stmt->execute()) {
            $teacher_id = $stmt->insert_id;

            $antas_stmt = $this->conn->prepare("INSERT INTO `levels` (`teacher_id`, `level`) VALUES (?, ?)");
            if ($antas_stmt) {
                for ($i = 1; $i <= 4; $i++) {
                    $antas_stmt->bind_param("ii", $teacher_id, $i);
                    $antas_stmt->execute();
                }
                $antas_stmt->close();
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Teacher and antas inserted successfully.',
                'teacher_id' => $teacher_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
        }

        $stmt->close();
    }


    public function UpdateTeacher($id, $name, $grade, $section, $email, $plainPassword, $is_active)
    {
        $sql = "UPDATE `web_users` SET `first_name` = ?, `last_name` = ?, `email` = ?, `role` = ?, `grade_level` = ?, `section` = ?, `is_active` = ?";

        $params = [$name, $email, $grade, $section, $is_active];
        $types = "ssisi";

        if (!empty($plainPassword)) {
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
            $sql .= ", `password` = ?";
            $params[] = $hashedPassword;
            $types .= "s";
        }

        $sql .= " WHERE `id` = ?";
        $params[] = $id;
        $types .= "i";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare statement.'
            ]);
            return;
        }

        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Teacher updated successfully.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Update failed: ' . $stmt->error
            ]);
        }

        $stmt->close();
    }
}
