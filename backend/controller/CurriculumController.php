<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class CurriculumController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    public function GetCurriculum()
    {
        $q = $this->conn->prepare("
            SELECT * FROM `curriculum` LIMIT 1
        ");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        if ($q->execute()) {
            $result = $q->get_result();
            $curriculum = $result->fetch_assoc(); // gets only one row

            echo json_encode([
                'status' => 'success',
                'message' => 'Curriculum fetched successfully.',
                'data' => $curriculum
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }

    public function EditCurriculum($curriculum)
    {
        if (empty($curriculum)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Curriculum value is required.'
            ]);
            return;
        }

        $stmt = $this->conn->prepare("
        UPDATE `curriculum`
        SET `curriculum` = ?
        WHERE `id` = 1
    ");

        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $stmt->bind_param("s", $curriculum);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Curriculum updated successfully.'
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
