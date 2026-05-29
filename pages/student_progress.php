<?php
// Let components/header.php handle the session and variables
include("components/header.php");
require_once '../backend/config/connection.php'; 

// Fetch user context from your existing header variables
$teacher_id = isset($auth_user_id) ? $auth_user_id : 0;
$isSuperAdmin = isset($user['role']) && $user['role'] === 'super_admin';

// 1. Fetch the overall progress 
if ($isSuperAdmin) {
    // Super admins see all students
    $query = "
        SELECT 
            u.id AS user_id, u.first_name, u.last_name, u.lrn, s.section_name,
            (SELECT COUNT(*) FROM student_aralin_progress sap WHERE sap.user_id = u.id) AS videos_watched,
            (SELECT COUNT(*) FROM aralin) AS total_videos,
            (SELECT COUNT(DISTINCT assessment_id) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS quizzes_passed,
            (SELECT COUNT(*) FROM assessments) AS total_quizzes,
            (SELECT ROUND(AVG((points / total) * 100), 2) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS average_score,
            u.last_login AS last_activity
        FROM users u
        LEFT JOIN student_teacher_assignments sta ON u.lrn = sta.student_lrn
        LEFT JOIN sections s ON sta.section_id = s.id
        ORDER BY s.section_name, u.last_name
    ";
    $stmt = $conn->prepare($query);
} else {
    // Teachers only see their assigned students
    $query = "
        SELECT 
            u.id AS user_id, u.first_name, u.last_name, u.lrn, s.section_name,
            (SELECT COUNT(*) FROM student_aralin_progress sap WHERE sap.user_id = u.id) AS videos_watched,
            (SELECT COUNT(*) FROM aralin) AS total_videos,
            (SELECT COUNT(DISTINCT assessment_id) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS quizzes_passed,
            (SELECT COUNT(*) FROM assessments) AS total_quizzes,
            (SELECT ROUND(AVG((points / total) * 100), 2) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS average_score,
            u.last_login AS last_activity
        FROM users u
        JOIN student_teacher_assignments sta ON u.lrn = sta.student_lrn
        LEFT JOIN sections s ON sta.section_id = s.id
        WHERE sta.teacher_id = ?
        ORDER BY s.section_name, u.last_name
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
}

$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<style>
    /* CRITICAL FIX: Hide the duplicate top navbar and restore dashboard layout */
    .navbar { display: none !important; }
    
    body { background-color: #f4f6f9; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; min-height: 100vh; width: 100%; overflow-x: hidden; }

    /* SIDEBAR */
    .sidebar { width: 280px; background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%); color: white; display: flex; flex-direction: column; padding: 20px; position: fixed; height: 100vh; z-index: 1000; left: 0; transition: all 0.3s ease; overflow: visible !important; }
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; max-width: 100%; display: block; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; }
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover { background-color: rgba(255, 255, 255, 0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107; color: #333; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; text-align: center; text-decoration: none; cursor: pointer; }
    .logout-btn:hover { background-color: #e0a800; color: black; }
    .sidebar-toggle { position: absolute; right: -15px; top: 50%; width: 30px; height: 60px; background-color: #FFC107; border-radius: 0 4px 4px 0; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #333; transition: right 0.3s ease; z-index: 1001; }
    .sidebar-toggle i { transition: transform 0.3s ease; }
    .dashboard-wrapper.toggled .sidebar { left: -280px; }
    .dashboard-wrapper.toggled .main-content { margin-left: 0; }
    .dashboard-wrapper.toggled .sidebar-toggle { right: -30px; }
    .dashboard-wrapper.toggled .sidebar-toggle i { transform: rotate(180deg); }

    /* CONTENT & HEADER */
    .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; transition: all 0.3s ease; }
    .page-header { background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%); color: white; padding: 15px 30px; border-radius: 8px; font-weight: bold; font-size: 1.5rem; margin-bottom: 20px; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
</style>

<div class="dashboard-wrapper">
    <?php include("components/sidebar.php"); ?>
    
    <main class="main-content">
        <div class="page-header">
            <div><i class="bi bi-bar-chart-line-fill me-2"></i> Progreso ng Mag-aaral</div>
        </div>

        <div class="card shadow-sm rounded-3">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="progressTable" class="table table-striped table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>LRN</th>
                                <th>Section</th>
                                <th class="text-center">Videos Watched</th>
                                <th class="text-center">Quizzes Passed</th>
                                <th class="text-center">Average Score</th>
                                <th>Last Activity</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $row): 
                                $score = $row['average_score'] ?? 0;
                                $badgeClass = 'bg-danger'; 
                                if ($score >= 90) $badgeClass = 'bg-success';
                                elseif ($score >= 75) $badgeClass = 'bg-warning text-dark';
                            ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
                                <td><?= htmlspecialchars($row['lrn']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['section_name'] ?? 'N/A') ?></span></td>
                                <td class="text-center"><?= $row['videos_watched'] ?> / <?= $row['total_videos'] ?></td>
                                <td class="text-center"><?= $row['quizzes_passed'] ?> / <?= $row['total_quizzes'] ?></td>
                                <td class="text-center">
                                    <?php if ($score > 0): ?>
                                        <span class="badge <?= $badgeClass ?>"><?= $score ?>%</span>
                                    <?php else: ?>
                                        <span class="text-muted">No Quizzes</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $row['last_activity'] ? date('M d, Y', strtotime($row['last_activity'])) : 'Never' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger view-details-btn" 
                                            data-user-id="<?= $row['user_id'] ?>" 
                                            data-lrn="<?= $row['lrn'] ?>"
                                            data-name="<?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>"
                                            title="View Breakdown">
                                        <i class="bi bi-eye-fill"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="progressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%);">
                <h5 class="modal-title" id="modalStudentName">Student Progress Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalProgressBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-danger" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("components/footer-scripts.php"); ?>

<script>
    $(document).ready(function() {
        // Sidebar Toggle Logic
        $(document).off('click', '.sidebar-toggle').on('click', '.sidebar-toggle', function() {
            $(".dashboard-wrapper").toggleClass("toggled");
        });

        // Initialize DataTable for search/sort
        if ($.fn.DataTable) {
            $('#progressTable').DataTable({
                "order": [[ 0, "asc" ]],
                "pageLength": 15
            });
        }

        // Fetch Modal Data via AJAX
        $(document).on("click", ".view-details-btn", function(e) {
            e.preventDefault();
            const btn = $(this);
            const userId = btn.data("user-id");
            const lrn = btn.data("lrn");
            const studentName = btn.data("name");

            $("#modalStudentName").text("Progress Breakdown: " + studentName);
            $("#modalProgressBody").html('<div class="text-center py-4"><div class="spinner-border text-danger"></div><p>Fetching data...</p></div>');
            
            new bootstrap.Modal(document.getElementById('progressModal')).show();

            // Calls the API file we created earlier
            $.ajax({
                type: "GET",
                url: `../backend/api/web/get_student_progress_details.php?user_id=${userId}&lrn=${lrn}`,
                success: function(res) {
                    if (res.error) {
                        $("#modalProgressBody").html(`<div class="alert alert-danger">${res.error}</div>`);
                        return;
                    }

                    const data = res.data;
                    let html = '';

                    for (const [markahan, lessons] of Object.entries(data)) {
                        html += `<h5 class="mt-3 border-bottom pb-2 text-danger">Markahan ${markahan}</h5>`;
                        html += `<table class="table table-sm table-bordered">
                                    <thead class="table-light"><tr><th>Aralin</th><th>Video Status</th><th>Quiz Score</th></tr></thead><tbody>`;

                        lessons.forEach(lesson => {
                            const videoStatus = lesson.video_watched_date 
                                ? `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Watched</span><br><small class="text-muted">${new Date(lesson.video_watched_date).toLocaleDateString()}</small>` 
                                : `<span class="text-secondary">Not Started</span>`;

                            let quizStatus = `<span class="text-secondary">Not Taken</span>`;
                            if (lesson.points !== null) {
                                const percentage = (lesson.points / lesson.total) * 100;
                                const textColor = percentage >= 75 ? 'text-success' : 'text-danger';
                                quizStatus = `<span class="${textColor} fw-bold">${lesson.points} / ${lesson.total}</span>
                                              <br><small class="text-muted">${new Date(lesson.quiz_taken_date).toLocaleDateString()}</small>`;
                            }
                            html += `<tr><td><strong>Aralin ${lesson.aralin_no}:</strong> ${lesson.aralin_title}</td><td>${videoStatus}</td><td>${quizStatus}</td></tr>`;
                        });
                        html += `</tbody></table>`;
                    }
                    if (html === '') html = '<p class="text-muted text-center mt-4">No data available for this student.</p>';
                    $("#modalProgressBody").html(html);
                },
                error: function() {
                    $("#modalProgressBody").html(`<div class="alert alert-danger">Network Error fetching data.</div>`);
                }
            });
        });
    });
</script>

<?php include("components/footer.php"); ?>