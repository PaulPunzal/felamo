<?php
include("components/header.php");

// FIX: Initialize variables safely
$sectionId = isset($_GET['sectionId']) ? $_GET['sectionId'] : null;
$section = [];

if ($sectionId) {
    try {
        // SAFETY CHECK: Ensure AuthController exists
        if (isset($AuthController) && method_exists($AuthController, 'GetUsingId')) {
            $sectionResult = $AuthController->GetUsingId('sections', $sectionId);
            
            // CHECK: Ensure we got a valid object back, not false/null
            if ($sectionResult && is_object($sectionResult) && $sectionResult->num_rows > 0) {
                $section = $sectionResult->fetch_assoc();
            } else {
                // Section not found - Redirect safely using JS
                echo "<script>window.location.href='../index.php';</script>";
                exit;
            }
        }
    } catch (Exception $e) {
        // If error occurs, prevent crash and redirect
        echo "<script>window.location.href='../index.php';</script>";
        exit;
    }
} else {
    // No ID provided
    echo "<script>window.location.href='../index.php';</script>";
    exit;
}

$isSuperAdmin = isset($user['role']) && $user['role'] === 'super_admin';
?>

<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">
<input type="hidden" id="hidden_is_super_admin" value="<?= $isSuperAdmin ? 'true' : 'false' ?>">
<input type="hidden" id="hidden_section_id" value="<?= htmlspecialchars($sectionId) ?>">

<style>
    /* --- FORCE RESET --- */
    /* CRITICAL: Hides the header.php navbar so the layout isn't 'ruined' */
    .navbar { display: none !important; }
    body { background-color: #f4f6f9; overflow-x: hidden; }
    
    /* Wrapper Layout */
    .dashboard-wrapper { 
        display: flex; 
        min-height: 100vh; 
        width: 100%; 
        overflow-x: hidden; 
    }

    /* --- SIDEBAR STYLES (SMOOTH SLIDE FIX) --- */
    .sidebar { 
        width: 280px !important;
        background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%); 
        color: white; 
        display: flex; 
        flex-direction: column; 
        padding: 20px; 
        position: fixed; 
        height: 100vh; 
        z-index: 9000 !important;
        left: 0 !important; 
        top: 0;
        
        /* USE TRANSFORM FOR SMOOTH SLIDING */
        transform: translateX(0);
        transition: transform 0.3s ease-in-out; 
        
        overflow: visible !important;
    }

    /* --- BUTTON STYLES --- */
    .sidebar-toggle { 
        position: absolute !important;
        right: -30px !important;
        top: 50% !important;
        width: 30px !important; 
        height: 60px !important; 
        background-color: #FFC107 !important;
        border: 2px solid #880f0b !important;
        border-left: none !important;
        border-radius: 0 8px 8px 0 !important; 
        display: flex !important; 
        align-items: center; 
        justify-content: center; 
        cursor: pointer; 
        color: #000 !important;
        z-index: 9999 !important;
        box-shadow: 4px 0 5px rgba(0,0,0,0.2) !important;
    }
    
    .sidebar-toggle i { transition: transform 0.3s ease-in-out; }

    /* --- CLOSED STATE (TRANSFORM LOGIC) --- */
    .dashboard-wrapper.toggled .sidebar { 
        transform: translateX(-280px); 
    }

    .dashboard-wrapper.toggled .main-content { 
        margin-left: 0 !important; 
    }

    .dashboard-wrapper.toggled .sidebar-toggle i { 
        transform: rotate(180deg); 
    }

    /* --- MAIN CONTENT --- */
    .main-content { 
        flex: 1; 
        margin-left: 280px; 
        padding: 30px 40px; 
        transition: margin-left 0.3s ease-in-out; 
    }

    /* --- OTHER STYLES --- */
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; max-width: 100%; display: block; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; }
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover { background-color: rgba(255, 255, 255, 0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107; color: #333; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; text-align: center; text-decoration: none; cursor: pointer; }
    .logout-btn:hover { background-color: #e0a800; color: black; }
    .page-header { background: linear-gradient(180deg, #880f0b 0%, #ce3c3c 100%); color: white; padding: 15px 30px; border-radius: 8px; font-weight: bold; font-size: 1.2rem; margin-bottom: 20px; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between; }
</style>

<div class="dashboard-wrapper">
    <?php include("components/sidebar.php"); ?>
    <main class="main-content">
        <div class="page-header shadow-sm">
            <div class="d-flex align-items-center">
                <?php 
    // Dynamically grab the previous URL, fallback depending on role if accessed directly
    $backUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ($isSuperAdmin ? 'teachers.php' : 'my_sections.php');
?>
<a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-light text-main me-3 rounded-circle" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-arrow-left"></i></a>
                <span><i class="bi bi-people-fill me-2"></i> Students: <?= isset($section['section_name']) ? htmlspecialchars($section['section_name']) : 'Unknown' ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-light text-main fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#insertStudentModal"><i class="bi bi-person-plus-fill me-1"></i> Insert Student</button>
                <button class="btn btn-sm btn-light text-main fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#importStudentModal">
                    <i class="bi bi-upload me-1"></i> Import CSV
                </button>
            </div>
        </div>

        <div class="card shadow-sm rounded-3">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr><th><i class="bi bi-card-list me-1"></i>LRN</th><th><i class="bi bi-person me-1"></i>Email</th><th><i class="bi bi-telephone me-1"></i>Contact</th><th><i class="bi bi-person me-1"></i>First Name</th><th><i class="bi bi-person me-1"></i>Middle Name</th><th><i class="bi bi-person me-1"></i>Last Name</th><th><i class="bi bi-calendar-event me-1"></i>Birth Date</th><th><i class="bi bi-gender-ambiguous me-1"></i>Gender</th></tr>
                        </thead>
                        <tbody id="student-table-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="insertStudentModal" tabindex="-1">
            <div class="modal-dialog">
                <form id="assign-student-form">
                    <div class="modal-content">
                        <div class="modal-header text-white" style="background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%);">
                            <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Assign Student</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="assign_lrn" class="form-label">LRN</label>
                                <input type="text" class="form-control" id="assign_lrn" name="lrn" required
                                    inputmode="numeric" pattern="\d{12}" maxlength="12"
                                    title="LRN must be exactly 12 digits"
                                    placeholder="12-digit LRN">
                                <div class="invalid-feedback" id="assign_lrn_error">LRN must be exactly 12 digits.</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label for="assign_first_name" class="form-label">First Name</label><input type="text" class="form-control" id="assign_first_name" name="first_name" required></div>
                                <div class="col-md-6 mb-3"><label for="assign_middle_name" class="form-label">Middle Name</label><input type="text" class="form-control" id="assign_middle_name" name="middle_name"></div>
                            </div>
                            <div class="mb-3"><label for="assign_last_name" class="form-label">Last Name</label><input type="text" class="form-control" id="assign_last_name" name="last_name" required></div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label for="assign_birth_date" class="form-label">Birth Date</label><input type="date" class="form-control" id="assign_birth_date" name="birth_date" required></div>
                                <div class="col-md-6 mb-3"><label for="assign_gender" class="form-label">Gender</label><select class="form-control" id="assign_gender" name="gender" required><option value="">Select</option><option value="Lalaki">Lalaki</option><option value="Babae">Babae</option></select></div>
                            </div>
                            <div class="mb-3"><label for="assign_contactno" class="form-label">Contact no</label><input type="number" class="form-control" id="assign_contactno" name="contact_no"></div>
                            <div class="mb-3"><label for="assign_email" class="form-label">Email</label><input type="email" class="form-control" id="assign_email" name="email"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-main text-light" style="background-color: #880f0b; border:none;"><i class="bi bi-save me-1"></i>Save</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="importStudentModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Students via CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        CSV columns: <code>lrn, first_name, middle_name, last_name, birth_date, gender, email, contact_no</code>
                        (middle_name may be blank, birth_date as M/D/YYYY).
                    </p>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <input type="file" id="studentCsvFile" accept=".csv" class="form-control">
                        <button type="button" class="btn btn-main text-light" id="btn-preview-student-csv">
                            <i class="bi bi-eye me-1"></i> Preview
                        </button>
                    </div>

                    <div id="student-csv-preview-panel" class="d-none">
                        <div id="student-csv-summary" class="alert mb-3"></div>

                        <div id="student-csv-errors-block" class="d-none mb-3">
                            <h6 class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Errors</h6>
                            <ul id="student-csv-errors-list" class="small text-danger mb-0"></ul>
                        </div>

                        <div id="student-csv-warnings-block" class="d-none mb-3">
                            <h6 class="text-warning fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Warnings (rows skipped)</h6>
                            <ul id="student-csv-warnings-list" class="small text-warning mb-0"></ul>
                        </div>

                        <div id="student-csv-valid-block" class="d-none">
                            <h6 class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Valid Rows Preview</h6>
                            <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>#</th><th>LRN</th><th>First Name</th><th>Middle Name</th><th>Last Name</th><th>Gender</th><th>Email</th><th>Contact</th></tr>
                                    </thead>
                                    <tbody id="student-csv-preview-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success d-none" id="btn-confirm-student-import">
                        Confirm Import (<span id="student-confirm-count">0</span> rows)
                    </button>
                </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include("components/footer-scripts.php"); ?>
<script src="scripts/assignStudentsv2.js?v=4"></script>
<script>
    // --- ADD SIDEBAR TOGGLE LOGIC HERE TO ENSURE IT WORKS ---
    $(document).ready(function() {
        $(document).off('click', '.sidebar-toggle');
        $(document).on('click', '.sidebar-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(".dashboard-wrapper").toggleClass("toggled");
        });
    });
</script>
<?php include("components/footer.php"); ?>