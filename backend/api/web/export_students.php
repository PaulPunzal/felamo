<?php
// backend/api/web/export_students.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../../db/db.php');

$database = new db_connect();
$conn = $database->connect();

$teacher_id = $_GET['teacher_id'] ?? null;
$is_admin = $_GET['is_admin'] ?? 'false';

// Resolve the logged-in web user's name for the "Printed by" line
$preparedBy = '';
if (isset($_SESSION['id'])) {
    $whoStmt = $conn->prepare("SELECT first_name, last_name FROM web_users WHERE id = ?");
    $whoStmt->bind_param("i", $_SESSION['id']);
    $whoStmt->execute();
    $whoRow = $whoStmt->get_result()->fetch_assoc();
    $whoStmt->close();
    if ($whoRow) {
        $preparedBy = trim(($whoRow['first_name'] ?? '') . ' ' . ($whoRow['last_name'] ?? ''));
    }
}

// Query to get Student Data
$sql = "SELECT 
            u.lrn AS 'LRN', 
            u.last_name AS 'Last Name', 
            u.first_name AS 'First Name', 
            u.email AS 'Email', 
            s.section_name AS 'Section'
        FROM student_teacher_assignments AS sta
        LEFT JOIN users AS u ON sta.student_lrn = u.lrn
        LEFT JOIN sections AS s ON sta.section_id = s.id
        WHERE s.teacher_id = ?
        ORDER BY s.section_name ASC, u.last_name ASC";

$params = [$teacher_id];
$types = "i";

if ($is_admin === 'true') {
    $sql = "SELECT lrn, last_name, first_name, email, 'N/A' as section_name FROM users WHERE is_active = 1";
    $params = [];
    $types = "";
}

$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Force Download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="student_list.csv"');

$output = fopen('php://output', 'w');

if ($preparedBy !== '') {
    fputcsv($output, array('Printed by: ' . $preparedBy . ' | Generated: ' . date('F j, Y g:i A')));
    fputcsv($output, array()); // blank spacer row
}

fputcsv($output, array('LRN', 'Last Name', 'First Name', 'Email', 'Section'));

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);
exit();
?>