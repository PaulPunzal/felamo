<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class LeaderBoardsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    
    public function GetLeaderBoards($teacher_id, $section_id = null)
    {
        // Prevent random PHP warnings from breaking the JSON layout
        if (ob_get_level() > 0) {
            ob_clean();
        }

        if (!empty($section_id)) {
            // Single section: rank by total_points (same as the mobile app)
            $query = "
            SELECT u.*
            FROM `users` AS u
            JOIN `student_teacher_assignments` AS sta ON u.lrn = sta.student_lrn
            JOIN `sections` AS s ON sta.section_id = s.id
            WHERE s.teacher_id = ? AND s.id = ?
            ORDER BY u.total_points DESC, u.id ASC
            ";

            $q = $this->conn->prepare($query);

            if (!$q) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'SQL prepare failed: ' . $this->conn->error
                ]);
                return;
            }

            $q->bind_param("ii", $teacher_id, $section_id);
        } else {
            // All sections: rank by total_points
            $query = "
            SELECT u.*
            FROM `users` AS u
            JOIN `student_teacher_assignments` AS sta ON u.lrn = sta.student_lrn
            JOIN `sections` AS s ON sta.section_id = s.id
            WHERE s.teacher_id = ?
            ORDER BY u.total_points DESC, u.id ASC";

            $q = $this->conn->prepare($query);

            if (!$q) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'SQL prepare failed: ' . $this->conn->error
                ]);
                return;
            }

            $q->bind_param("i", $teacher_id);
        }

        if ($q->execute()) {
            $result = $q->get_result();
            $leaderBoards = [];

            while ($row = $result->fetch_assoc()) {
                // Never send the password hash to the browser
                unset($row['password']);
                $leaderBoards[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $leaderBoards
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
