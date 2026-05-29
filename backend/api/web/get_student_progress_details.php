<?php
// backend/api/web/get_student_progress_details.php
header('Content-Type: application/json');

// Use your standard connection file which provides the $conn object
require_once '../../config/connection.php'; 

if (!isset($_GET['user_id']) || !isset($_GET['lrn'])) {
    echo json_encode(['error' => 'Missing student ID or LRN']);
    exit;
}

$user_id = intval($_GET['user_id']);
$lrn = $_GET['lrn'];

try {
    $query = "
        SELECT 
            l.level AS markahan,
            a.aralin_no,
            a.aralin_title,
            sap.completed_at AS video_watched_date,
            ass.assessment_title,
            ar.points,
            ar.total,
            ar.created_at AS quiz_taken_date
        FROM levels l
        JOIN aralin a ON l.id = a.level_id
        LEFT JOIN student_aralin_progress sap ON a.id = sap.aralin_id AND sap.user_id = ?
        LEFT JOIN assessments ass ON a.id = ass.aralin_id
        LEFT JOIN assessment_results ar ON ass.id = ar.assessment_id AND ar.lrn = ? AND ar.is_completed = 1
        ORDER BY l.level, a.aralin_no
    ";

    // Use MySQLi syntax ($conn) instead of PDO ($pdo)
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $lrn); // "i" for integer, "s" for string
    $stmt->execute();
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Group the results by Markahan for easier frontend rendering
    $groupedData = [];
    foreach ($results as $row) {
        $level = $row['markahan'];
        if (!isset($groupedData[$level])) {
            $groupedData[$level] = [];
        }
        $groupedData[$level][] = $row;
    }

    echo json_encode(['success' => true, 'data' => $groupedData]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>