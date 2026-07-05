<?php
include("components/header.php");
include_once("../backend/controller/SectionController.php");

$isSuperAdmin = isset($user['role']) && $user['role'] === 'super_admin';
$urlSectionId = isset($_GET['sectionId']) ? $_GET['sectionId'] : '';

$sections = null;
try {
    if (class_exists('SectionController')) {
        $sectionController = new SectionController();
        if ($isSuperAdmin) {
            $sections = $sectionController->GetSectionsResult(null);
        } else {
            $sections = $sectionController->GetSectionsResult($auth_user_id);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching sections: " . $e->getMessage());
}
?>

<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">
<input type="hidden" id="hidden_is_super_admin" value="<?= $isSuperAdmin ? 'true' : 'false' ?>">
<input type="hidden" id="url_section_id" value="<?= htmlspecialchars($urlSectionId) ?>">

<style>
    .navbar { display: none !important; }
    body { background-color: #f4f6f9; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; min-height: 100vh; width: 100%; overflow-x: hidden; }

    /* SIDEBAR STYLES (Kept from your original file) */
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
            <i class="bi bi-people-fill me-2"></i> Master Student Roster
        </div>

        <div class="filter-bar">
            <div class="filter-group">
                <label><i class="bi bi-person me-1"></i>Search Name / LRN</label>
                <input type="text" id="filter-text" class="form-control form-control-sm" placeholder="Enter name or LRN...">
            </div>
            <div class="filter-group">
                <label><i class="bi bi-diagram-3 me-1"></i>Section</label>
                <select id="filter-section" class="form-select form-select-sm">
                    <option value="all">ALL SECTIONS</option>
                    <?php 
                    if ($sections && $sections->num_rows > 0): 
                        while ($section = $sections->fetch_assoc()): 
                    ?>
                        <option value="<?= htmlspecialchars($section['section_name']) ?>" data-id="<?= htmlspecialchars($section['id']) ?>">
                            <?= htmlspecialchars($section['section_name']) ?>
                        </option>
                    <?php 
                        endwhile; 
                    endif; 
                    ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="bi bi-gender-ambiguous me-1"></i>Gender</label>
                <select id="filter-gender" class="form-select form-select-sm">
                    <option value="all">All</option>
                    <option value="lalaki">Lalaki</option>
                    <option value="babae">Babae</option>
                </select>
            </div>
            <button class="btn-filter-reset" id="btn-reset-filters"><i class="bi bi-x-circle me-1"></i> Reset</button>
        </div>

        <div class="mb-3 text-muted" style="font-size: 0.85rem;">
            Showing <strong id="visible-count">0</strong> students
        </div>

        <div class="card shadow-sm rounded-3">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <th>Section</th>
                                <th>LRN</th>
                                <th>Birth Date</th>
                                <th>Gender</th>
                                <th>Email</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody id="students-table-tbody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include("components/footer-scripts.php"); ?>

<script>
    $(document).ready(function() {
        // Sidebar Toggle
        $(document).off('click', '.sidebar-toggle').on('click', '.sidebar-toggle', function() {
            $(".dashboard-wrapper").toggleClass("toggled");
        });

        const auth_user_id = $("#hidden_user_id").val();
        const is_super_admin = $("#hidden_is_super_admin").val(); 
        const url_section_id = $("#url_section_id").val(); 
        let allStudents = [];

        // Check URL for Section ID and Pre-Select it
        if (url_section_id) {
            $("#filter-section option").each(function() {
                if ($(this).data('id') == url_section_id) {
                    $(this).prop('selected', true);
                }
            });
        }

        const renderTable = (data) => {
            $("#visible-count").text(data.length);
            let rowsHtml = "";

            if(data.length > 0) {
                data.forEach((student) => {
                    rowsHtml += `
                        <tr>
                            <td class="fw-bold">${student.first_name || ""}</td>
                            <td>${student.middle_name || ""}</td>
                            <td>${student.last_name || ""}</td>
                            <td><span class="badge bg-light text-dark border">${student.section_name || "N/A"}</span></td>
                            <td>${student.student_lrn || student.lrn || ""}</td>
                            <td>${student.birth_date || ""}</td>
                            <td>${student.gender || ""}</td>
                            <td>${student.email || ""}</td>
                            <td>${student.contact_no || ""}</td>
                        </tr>
                    `;
                });
            } else {
                rowsHtml = `<tr><td colspan="9" class="text-center py-4 text-muted">No students found.</td></tr>`;
            }
            $("#students-table-tbody").html(rowsHtml);
        };

        const applyFilters = () => {
            const textQ = $("#filter-text").val().toLowerCase();
            const sectionQ = $("#filter-section").val();
            const genderQ = $("#filter-gender").val();

            const filtered = allStudents.filter(s => {
                const fullName = ((s.first_name||"") + " " + (s.last_name||"")).toLowerCase();
                const lrn = (s.student_lrn || s.lrn || "").toLowerCase();
                const sec = s.section_name || "N/A";
                const gen = (s.gender || "").toLowerCase();

                if (textQ && !fullName.includes(textQ) && !lrn.includes(textQ)) return false;
                if (sectionQ !== "all" && sec !== sectionQ) return false;
                if (genderQ !== "all" && gen !== genderQ) return false;
                return true;
            });
            renderTable(filtered);
        };

        const loadStudents = () => {
             $.ajax({
                type: "POST", 
                url: "../backend/api/web/students.php",
                data: { requestType: "GetStudents", auth_user_id: auth_user_id, is_super_admin: is_super_admin, section_id: "" },
                success: function(response) {
                    try {
                        let res = typeof response === 'string' ? JSON.parse(response) : response;
                        if (res.status === "success") {
                            allStudents = res.data;
                            applyFilters(); // Apply immediately in case URL param was set
                        }
                    } catch (e) {}
                }
            });
        };

        // Event Listeners for Filters
        $("#filter-text, #filter-section, #filter-gender").on("input change", applyFilters);
        
        $("#btn-reset-filters").click(function() {
            $("#filter-text").val("");
            $("#filter-section").val("all");
            $("#filter-gender").val("all");
            applyFilters();
        });

        loadStudents();
    });
</script>
<?php include("components/footer.php"); ?>