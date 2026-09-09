<?php
// backend/api/web/export_dashboard.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../../db/db.php');

$database = new db_connect();
$conn = $database->connect();

$user_id = $_GET['user_id'] ?? null;
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

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="dashboard_summary.csv"');
$output = fopen('php://output', 'w');

if ($preparedBy !== '') {
    fputcsv($output, array('Printed by: ' . $preparedBy . ' | Generated: ' . date('F j, Y g:i A')));
    fputcsv($output, array()); // blank spacer row
}

if ($is_admin === 'true') {
    // --- SUPER ADMIN REPORT ---
    fputcsv($output, array('Metric', 'Count'));
    
    // Total Users
    $res = $conn->query("SELECT COUNT(*) as c FROM users");
    $row = $res->fetch_assoc();
    fputcsv($output, array('Total App Users', $row['c']));

    // Total Web Users
    $res = $conn->query("SELECT COUNT(*) as c FROM admin");
    $row = $res->fetch_assoc();
    fputcsv($output, array('Total Web Users', $row['c']));

    // Videos Uploaded
    fputcsv($output, array('', ''));
    fputcsv($output, array('Markahan', 'Videos Uploaded'));
    
    $vidQuery = "SELECT l.level, COUNT(a.id) as c FROM levels l LEFT JOIN aralin a ON a.level_id = l.id GROUP BY l.level";
    $vidRes = $conn->query($vidQuery);
    while($row = $vidRes->fetch_assoc()) {
        fputcsv($output, array('Level ' . $row['level'], $row['c']));
    }

} else {
    // --- TEACHER REPORT ---
    fputcsv($output, array('Dashboard Summary for Teacher ID: ' . $user_id));
    fputcsv($output, array(''));
    
    // General Stats
    $res = $conn->query("SELECT COUNT(*) as c FROM sections WHERE teacher_id = $user_id");
    $row = $res->fetch_assoc();
    fputcsv($output, array('Total Sections', $row['c']));

    fputcsv($output, array(''));
    fputcsv($output, array('Markahan', 'Passed Students', 'Failed Students'));

    // We manually query levels 1-4 for this teacher
    for ($i = 1; $i <= 4; $i++) {
        $sql = "SELECT 
            COUNT(CASE WHEN at.points >= (at.total * 0.5) THEN 1 END) AS passed,
            COUNT(CASE WHEN at.points < (at.total * 0.5) THEN 1 END) AS failed
        FROM levels l
        LEFT JOIN assessments a ON a.level_id = l.id
        LEFT JOIN assessment_results at ON at.assessment_id = a.id
        WHERE l.level = $i AND l.teacher_id = $user_id";
        
        $res = $conn->query($sql);
        $data = $res->fetch_assoc();
        
        $markahanName = ['Unang', 'Pangalawa', 'Pangatlo', 'Ika-apat'];
        fputcsv($output, array($markahanName[$i-1], $data['passed'], $data['failed']));
    }
}

fclose($output);
exit();
?>