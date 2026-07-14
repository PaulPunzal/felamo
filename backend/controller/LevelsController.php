<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class LevelsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    public function GetLevels($teacher_id)
    {
        $q = $this->conn->prepare("
        SELECT *
        FROM `levels`
        WHERE teacher_id = ?");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $teacher_id);

        if ($q->execute()) {
            $result = $q->get_result();
            $levels = [];

            while ($row = $result->fetch_assoc()) {
                $levels[] = $row;
            }

            // --- FIX START: Auto-generate levels if they don't exist ---
            if (empty($levels)) {
                $insertStmt = $this->conn->prepare("INSERT INTO levels (teacher_id, level) VALUES (?, ?)");
                
                if ($insertStmt) {
                    // Create levels 1 to 4 (Unang Markahan to Ika-apat)
                    for ($i = 1; $i <= 4; $i++) {
                        $insertStmt->bind_param("ii", $teacher_id, $i);
                        $insertStmt->execute();
                    }
                    $insertStmt->close();

                    // Re-execute the query to fetch the newly created levels
                    $q->execute();
                    $result = $q->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $levels[] = $row;
                    }
                }
            }
            // --- FIX END ---

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $levels
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }
}