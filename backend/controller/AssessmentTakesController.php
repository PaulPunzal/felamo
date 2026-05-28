<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class AssessmentTakesController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function GetTakenAssessments($level_id, $filter)
    {
        // 1. ADDED BACK: COLLATE utf8mb4_general_ci to fix the collation mismatch!
        // 2. ADDED: JOIN student_teacher_assignments and sections to get section_name
        $sql = "
            SELECT 
                at.id,
                at.lrn, 
                at.points, 
                at.assessment_id, 
                at.created_at, 
                u.first_name, 
                u.last_name, 
                at.total,
                a.assessment_title,
                ar.aralin_title,
                s.section_name,
                COUNT(atl.id) AS total_attempts
            FROM assessment_results AS at 
            JOIN assessments AS a ON at.assessment_id = a.id
            JOIN aralin AS ar ON a.aralin_id = ar.id
            JOIN levels AS l ON ar.level_id = l.id
            JOIN users AS u ON at.lrn = u.lrn
            LEFT JOIN student_teacher_assignments AS sta ON u.lrn = sta.student_lrn
            LEFT JOIN sections AS s ON sta.section_id = s.id
            LEFT JOIN assessment_attempt_logs AS atl 
                ON at.assessment_id = atl.assessment_id AND at.lrn = atl.lrn COLLATE utf8mb4_general_ci
            WHERE l.id = ?
        ";

        // Append pass/fail filter BEFORE GROUP BY
        if ($filter === "PASSED") {
            $sql .= " AND at.total > 0 AND at.points >= (at.total * 0.80)";
        } elseif ($filter === "FAILED") {
            $sql .= " AND (at.total = 0 OR at.points < (at.total * 0.80))";
        }

        $sql .= " GROUP BY at.id, at.lrn, at.assessment_id, s.section_name
                  ORDER BY at.created_at DESC";

        $q = $this->conn->prepare($sql);

        if (!$q) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $level_id);

        if ($q->execute()) {
            $result = $q->get_result();
            $taken_assessments = [];

            while ($row = $result->fetch_assoc()) {
                $row['student_name'] = $row['first_name'] . ' ' . $row['last_name'];
                $taken_assessments[] = $row;
            }

            echo json_encode([
                'status'  => 'success',
                'message' => 'success',
                'data'    => $taken_assessments
            ]);
        } else {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }
}
?>