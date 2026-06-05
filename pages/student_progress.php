<?php
include("components/header.php");
require_once '../backend/config/connection.php'; 

$teacher_id = isset($auth_user_id) ? $auth_user_id : 0;
$isSuperAdmin = isset($user['role']) && $user['role'] === 'super_admin';

// 1. Fetch the overall progress using the EXACT same structure as StudentsController.php
if ($isSuperAdmin) {
    $query = "
        SELECT 
            u.id AS user_id, u.first_name, u.last_name, u.lrn, s.section_name,
            (SELECT COUNT(*) FROM student_aralin_progress sap WHERE sap.user_id = u.id) AS videos_watched,
            (SELECT COUNT(*) FROM aralin) AS total_videos,
            (SELECT COUNT(DISTINCT assessment_id) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS quizzes_passed,
            (SELECT COUNT(*) FROM assessments) AS total_quizzes,
            (SELECT ROUND(AVG((points / total) * 100), 2) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS average_score,
            (SELECT MAX(completed_at) FROM student_aralin_progress sap WHERE sap.user_id = u.id) AS latest_video,
            (SELECT MAX(created_at) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS latest_quiz
        FROM student_teacher_assignments AS sta
        LEFT JOIN users AS u ON sta.student_lrn = u.lrn
        LEFT JOIN sections AS s ON sta.section_id = s.id
        ORDER BY s.section_name, u.last_name
    ";
    $stmt = $conn->prepare($query);
} else {
    $query = "
        SELECT 
            u.id AS user_id, u.first_name, u.last_name, u.lrn, s.section_name,
            (SELECT COUNT(*) FROM student_aralin_progress sap WHERE sap.user_id = u.id) AS videos_watched,
            (SELECT COUNT(*) FROM aralin) AS total_videos,
            (SELECT COUNT(DISTINCT assessment_id) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS quizzes_passed,
            (SELECT COUNT(*) FROM assessments) AS total_quizzes,
            (SELECT ROUND(AVG((points / total) * 100), 2) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS average_score,
            (SELECT MAX(completed_at) FROM student_aralin_progress sap WHERE sap.user_id = u.id) AS latest_video,
            (SELECT MAX(created_at) FROM assessment_results ar WHERE ar.lrn = u.lrn AND ar.is_completed = 1) AS latest_quiz
        FROM student_teacher_assignments AS sta
        LEFT JOIN users AS u ON sta.student_lrn = u.lrn
        LEFT JOIN sections AS s ON sta.section_id = s.id
        WHERE s.teacher_id = ?
        ORDER BY s.section_name, u.last_name
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
}

$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Gather unique sections securely for the filter dropdown
$unique_sections = [];
foreach ($students as $s) {
    $sec = $s['section_name'] ?? 'N/A';
    if (!in_array($sec, $unique_sections)) {
        $unique_sections[] = $sec;
    }
}
?>

<style>
    .navbar { display: none !important; }
    body { background-color: #f4f6f9; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; min-height: 100vh; width: 100%; overflow-x: hidden; }

    /* SIDEBAR */
    .sidebar { width: 280px; background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%); color: white; display: flex; flex-direction: column; padding: 20px; position: fixed; height: 100vh; z-index: 1000; left: 0; transition: all 0.3s ease; }
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; }
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover, .nav-link-custom.active { background-color: rgba(255, 255, 255, 0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107; color: #333; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; }
    .sidebar-toggle { position: absolute; right: -15px; top: 50%; width: 30px; height: 60px; background-color: #FFC107; border-radius: 0 4px 4px 0; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #333; transition: right 0.3s ease; z-index: 1001; }
    .dashboard-wrapper.toggled .sidebar { left: -280px; }
    .dashboard-wrapper.toggled .main-content { margin-left: 0; }
    .dashboard-wrapper.toggled .sidebar-toggle { right: -30px; }

    /* CONTENT & HEADER */
    .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; transition: all 0.3s ease; }
    .page-header { background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%); color: white; padding: 15px 30px; border-radius: 8px; font-weight: bold; font-size: 1.5rem; margin-bottom: 20px; text-transform: uppercase; }

    /* FILTER BAR */
    .filter-bar { background: white; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #dee2e6; display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px; }
    .filter-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 160px; }
    .filter-group label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #6c757d; }
    .btn-filter-reset { background: #f8f9fa; border: 1px solid #dee2e6; color: #495057; font-weight: 600; padding: 8px 18px; border-radius: 6px; transition: 0.2s; white-space: nowrap; }
    .btn-filter-reset:hover { background: #e9ecef; }
</style>

<div class="dashboard-wrapper">
    <?php include("components/sidebar.php"); ?>
    
    <main class="main-content">
        <div class="page-header">
            <div><i class="bi bi-bar-chart-line-fill me-2"></i> Academic Progress Hub</div>
        </div>

        <div class="filter-bar">
            <div class="filter-group">
                <label><i class="bi bi-person me-1"></i>Search Student</label>
                <input type="text" id="filter-text" class="form-control form-control-sm" placeholder="Name or LRN...">
            </div>
            <div class="filter-group">
                <label><i class="bi bi-diagram-3 me-1"></i>Section</label>
                <select id="filter-section" class="form-select form-select-sm">
                    <option value="all">ALL SECTIONS</option>
                    <?php foreach($unique_sections as $sec): ?>
                        <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="bi bi-award me-1"></i>Performance</label>
                <select id="filter-status" class="form-select form-select-sm">
                    <option value="all">All Grades</option>
                    <option value="excellent">Excellent (90%+)</option>
                    <option value="passing">Passing (75% - 89%)</option>
                    <option value="failing">Failing (< 75%)</option>
                </select>
            </div>
            <button class="btn-filter-reset" id="btn-reset-filters"><i class="bi bi-x-circle me-1"></i> Reset</button>
        </div>

        <div class="card shadow-sm rounded-3">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0" style="font-size: 13px;">
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
                        <tbody id="progress-tbody">
                            <?php foreach ($students as $row): 
                                // 1. Safe Variable Extraction (Prevents PHP Crash Loop on Null Data)
                                $fname = $row['first_name'] ?? '';
                                $lname = $row['last_name'] ?? '';
                                $lrn = $row['lrn'] ?? '';
                                $secName = $row['section_name'] ?? 'N/A';
                                $userId = $row['user_id'] ?? 0;

                                // 2. Calculate Score Badge
                                $score = $row['average_score'] ?? 0;
                                $badgeClass = 'bg-danger'; 
                                $statusCat = 'failing';
                                if ($score >= 90) { $badgeClass = 'bg-success'; $statusCat = 'excellent'; }
                                elseif ($score >= 75) { $badgeClass = 'bg-warning text-dark'; $statusCat = 'passing'; }

                                // 3. Calculate Absolute Last Activity
                                $vid_time = !empty($row['latest_video']) ? strtotime($row['latest_video']) : 0;
                                $quiz_time = !empty($row['latest_quiz']) ? strtotime($row['latest_quiz']) : 0;
                                $max_time = max($vid_time, $quiz_time);
                                $last_activity_text = $max_time > 0 ? date('M d, Y', $max_time) : '<span class="text-muted fst-italic" style="font-size:0.8rem;">Never</span>';
                            ?>
                            <tr class="prog-row" 
                                data-name="<?= htmlspecialchars(strtolower($fname . ' ' . $lname)) ?>" 
                                data-lrn="<?= htmlspecialchars($lrn) ?>"
                                data-section="<?= htmlspecialchars($secName) ?>"
                                data-status="<?= $statusCat ?>">
                                
                                <td class="fw-bold"><?= htmlspecialchars($lname . ', ' . $fname) ?></td>
                                <td><?= htmlspecialchars($lrn) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($secName) ?></span></td>
                                <td class="text-center"><?= $row['videos_watched'] ?? 0 ?> / <?= $row['total_videos'] ?? 0 ?></td>
                                <td class="text-center"><?= $row['quizzes_passed'] ?? 0 ?> / <?= $row['total_quizzes'] ?? 0 ?></td>
                                <td class="text-center">
                                    <?php if ($score > 0): ?>
                                        <span class="badge <?= $badgeClass ?>"><?= $score ?>%</span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.8rem;">No Quizzes</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $last_activity_text ?></td>
                                <td>
                                    <?php if($userId > 0): ?>
                                    <button class="btn btn-sm btn-outline-danger view-details-btn" 
                                            data-user-id="<?= $userId ?>" 
                                            data-lrn="<?= htmlspecialchars($lrn) ?>"
                                            data-name="<?= htmlspecialchars($fname . ' ' . $lname) ?>"
                                            title="View Breakdown">
                                        <i class="bi bi-eye-fill"></i> View
                                    </button>
                                    <?php endif; ?>
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
            </div>
        </div>
    </div>
</div>

<?php include("components/footer-scripts.php"); ?>

<script>
    $(document).ready(function() {
        // Sidebar Toggle
        $(document).off('click', '.sidebar-toggle').on('click', '.sidebar-toggle', function() {
            $(".dashboard-wrapper").toggleClass("toggled");
        });

        // DOM Filtering Logic
        const applyFilters = () => {
            const textQ = $("#filter-text").val().toLowerCase();
            const sectionQ = $("#filter-section").val();
            const statusQ = $("#filter-status").val();

            $(".prog-row").each(function() {
                // Safeguard against missing attributes preventing JS crash
                const name = $(this).data("name") || "";
                const lrn = ($(this).data("lrn") || "").toString();
                const sec = $(this).data("section") || "N/A";
                const stat = $(this).data("status") || "failing";

                let show = true;
                if (textQ && !name.includes(textQ) && !lrn.includes(textQ)) show = false;
                if (sectionQ !== "all" && sec !== sectionQ) show = false;
                if (statusQ !== "all" && stat !== statusQ) show = false;

                $(this).toggle(show);
            });
        };

        $("#filter-text, #filter-section, #filter-status").on("input change", applyFilters);
        
        $("#btn-reset-filters").click(function() {
            $("#filter-text").val("");
            $("#filter-section").val("all");
            $("#filter-status").val("all");
            applyFilters();
        });

        // Modal Fetch Logic
        $(document).on("click", ".view-details-btn", function(e) {
            e.preventDefault();
            const btn = $(this);
            $("#modalStudentName").text("Progress Breakdown: " + btn.data("name"));
            $("#modalProgressBody").html('<div class="text-center py-4"><div class="spinner-border text-danger"></div></div>');
            new bootstrap.Modal(document.getElementById('progressModal')).show();

            $.ajax({
                type: "GET",
                url: `../backend/api/web/get_student_progress_details.php?user_id=${btn.data("user-id")}&lrn=${btn.data("lrn")}`,
                success: function(res) {
                    if (res.error) {
                        $("#modalProgressBody").html(`<div class="alert alert-danger">${res.error}</div>`);
                        return;
                    }
                    let html = '';
                    for (const [markahan, lessons] of Object.entries(res.data)) {
                        html += `<h5 class="mt-3 border-bottom pb-2 text-danger">Markahan ${markahan}</h5>`;
                        html += `<table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Aralin</th><th>Video Status</th><th>Quiz Score</th></tr></thead><tbody>`;
                        lessons.forEach(lesson => {
                            const vStat = lesson.video_watched_date ? `<span class="text-success fw-bold"><i class="bi bi-check"></i> Watched</span>` : `<span class="text-secondary">Not Started</span>`;
                            let qStat = `<span class="text-secondary">Not Taken</span>`;
                            if (lesson.points !== null) {
                                const c = (lesson.points / lesson.total) * 100 >= 75 ? 'text-success' : 'text-danger';
                                qStat = `<span class="${c} fw-bold">${lesson.points}/${lesson.total}</span>`;
                            }
                            html += `<tr><td><strong>Aralin ${lesson.aralin_no}:</strong> ${lesson.aralin_title}</td><td>${vStat}</td><td>${qStat}</td></tr>`;
                        });
                        html += `</tbody></table>`;
                    }
                    $("#modalProgressBody").html(html || '<p class="text-muted text-center mt-4">No data available.</p>');
                }
            });
        });
    });
</script>
<?php include("components/footer.php"); ?>