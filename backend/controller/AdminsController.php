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

    public function InsertTeacher($name, $email, $plainPassword)
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $role = 'teacher';
        $is_active = 1;

        $stmt = $this->conn->prepare("
        INSERT INTO `web_users` (`first_name`, `last_name`, `email`, `password`, `role`, `grade_level`, `is_active`)
        VALUES (?, ?, ?, ?, ?, 7, ?)
    ");

        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare statement.'
            ]);
            return;
        }

        $stmt->bind_param("ssssi", $name, $email, $hashedPassword, $role, $is_active);

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
            echo json_encode([
                'status' => 'error',
                'message' => 'Insert failed: ' . $stmt->error
            ]);
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

    public function ImportTeacherAccounts($accs)
    {
        $successCount = 0;
        $skippedCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($accs as $acc) {
            $checkStmt = $this->conn->prepare("
            SELECT 1 FROM `web_users`
            WHERE `email` = ?
        ");

            $name = $acc['name'];
            $email = $acc['email'];
            $password = $acc['password'];

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            if (!$checkStmt) {
                $failCount++;
                $errors[] = "Failed to prepare SELECT for EMAIL $email";
                continue;
            }

            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $skippedCount++;
                $checkStmt->close();
                continue;
            }

            $checkStmt->close();

            $insertStmt = $this->conn->prepare("
            INSERT INTO `web_users` (`first_name`, `last_name`, `email`, `password`, `role`, `grade_level`, `is_active`)
            VALUES (?, ?, ?, 'teacher', 7, 1)
        ");

            if (!$insertStmt) {
                $failCount++;
                $errors[] = "Failed to prepare INSERT for Email $email";
                continue;
            }

            $insertStmt->bind_param("sss", $name, $email, $hashedPassword);

            if ($insertStmt->execute()) {

                $teacher_id = $this->conn->insert_id;

                $antas_stmt = $this->conn->prepare("INSERT INTO `levels` (`teacher_id`, `level`) VALUES (?, ?)");
                if ($antas_stmt) {
                    for ($i = 1; $i <= 4; $i++) {
                        $antas_stmt->bind_param("ii", $teacher_id, $i);
                        $antas_stmt->execute();
                    }
                    $antas_stmt->close();
                }

                $successCount++;
            } else {
                $failCount++;
                $errors[] = "Insert failed for Email $email: " . $insertStmt->error;
            }

            $insertStmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'message' => "$successCount assigned, $skippedCount skipped (already exists), $failCount failed.",
            'errors' => $errors
        ]);
    }

    public function ValidateTeacherImport($users)
    {
        $validatedData = [];
        
        foreach ($users as $user) {
            // Match headers from import-teacher-template.csv
            $email = isset($user['Email']) ? $user['Email'] : '';
            $name = isset($user['Name']) ? $user['Name'] : '';

            $exists = false;
            
            // Check if email is already in web_users
            if (!empty($email)) {
                $checkStmt = $this->conn->prepare("SELECT id FROM `web_users` WHERE email = ?");
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                $checkStmt->store_result();
                
                if ($checkStmt->num_rows > 0) {
                    $exists = true;
                }
                $checkStmt->close();
            }

            $user['exists'] = $exists;
            $user['invalid'] = empty($email) || empty($name);
            
            $validatedData[] = $user;
        }

        echo json_encode(['status' => 'success', 'data' => $validatedData]);
    }

    public function ValidateStudentImport($users)
    {
        $validatedData = [];
        
        foreach ($users as $user) {
            // Match headers from TEST-STUDENT-IMPORT(2).csv
            $lrn = isset($user['lrn']) ? $user['lrn'] : '';
            $email = isset($user['email']) ? $user['email'] : '';
            $firstName = isset($user['first_name']) ? $user['first_name'] : '';

            $exists = false;
            
            if (!empty($lrn) || !empty($email)) {
                // NOTE: Assuming LRN is stored in the 'username' column for students. 
                // Change 'username = ?' to 'lrn = ?' if you have a specific LRN column.
                $checkStmt = $this->conn->prepare("SELECT id FROM `web_users` WHERE email = ? OR username = ?");
                $checkStmt->bind_param("ss", $email, $lrn);
                $checkStmt->execute();
                $checkStmt->store_result();
                
                if ($checkStmt->num_rows > 0) {
                    $exists = true;
                }
                $checkStmt->close();
            }

            $user['exists'] = $exists;
            $user['invalid'] = empty($lrn) || empty($firstName);
            
            $validatedData[] = $user;
        }

        echo json_encode(['status' => 'success', 'data' => $validatedData]);
    }
}
