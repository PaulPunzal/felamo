<?php
include("components/header.php");
$isSuperAdmin = $user['role'] === 'super_admin';
?>

<input type="hidden" id="hidden_user_id" value="<?= $auth_user_id ?>">
<input type="hidden" id="hidden_is_super_admin" value="<?= $isSuperAdmin ? 'true' : 'false' ?>">

<style>
    /* --- UNIFIED CSS --- */
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

    /* CONTENT */
    .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; transition: all 0.3s ease; }
    .page-header { background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%); color: white; padding: 15px 30px; border-radius: 8px; font-weight: bold; font-size: 1.5rem; margin-bottom: 20px; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between; }
</style>

<div class="dashboard-wrapper">
    <?php include("components/sidebar.php"); ?>
    <main class="main-content">
        <div class="page-header">
            <div><i class="bi bi-person-badge-fill me-2"></i> TEACHERS</div>
            <div class="d-flex align-items-center gap-2">
                
                <button class="btn btn-sm btn-light text-main fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#insertTeacherModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Add Teacher
                </button>
                
                <button class="btn btn-sm btn-light text-main fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#importTeacherModal">
                    <i class="bi bi-upload me-1"></i> Import CSV
                </button>

            </div>
        </div>

        <div id="alert" style="display: none;"></div>

        <div class="card shadow-sm rounded-3">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Email</th><th>Grade</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody id="teacher-table-tbody">
                            <tr><td colspan="5" class="text-center text-muted">Loading teachers...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="insertTeacherModal" tabindex="-1">
            <div class="modal-dialog">
                <form id="insert-teacher-form">
                    <div class="modal-content">
                        <div class="modal-header text-white" style="background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%);">
                            <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New Teacher</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="teacher-first-name" name="first_name" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="teacher-middle-name" name="middle_name">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="teacher-last-name" name="last_name" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="teacher-email" name="email" required placeholder="Enter Email">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="teacher-password" name="password" required placeholder="Enter Password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-outline-secondary" type="button" id="generatePasswordBtn">Generate</button>
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="submit" id="btnSaveTeacher" class="btn btn-main text-light" style="background-color: #880f0b; border: none;">Save</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="importTeacherModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import Teachers via CSV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">
            CSV columns: <code>first_name, middle_name, last_name, email, password</code>
            (middle_name may be blank).
        </p>
        <div class="d-flex align-items-center gap-2 mb-3">
            <input type="file" id="teacherCsvFile" accept=".csv" class="form-control">
            <button type="button" class="btn btn-main text-light" id="btn-preview-teacher-csv">
                <i class="bi bi-eye me-1"></i> Preview
            </button>
        </div>

        <div id="teacher-csv-preview-panel" class="d-none">
            <div id="teacher-csv-summary" class="alert mb-3"></div>

            <div id="teacher-csv-errors-block" class="d-none mb-3">
                <h6 class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Errors</h6>
                <ul id="teacher-csv-errors-list" class="small text-danger mb-0"></ul>
            </div>

            <div id="teacher-csv-warnings-block" class="d-none mb-3">
                <h6 class="text-warning fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Warnings (rows skipped)</h6>
                <ul id="teacher-csv-warnings-list" class="small text-warning mb-0"></ul>
            </div>

            <div id="teacher-csv-valid-block" class="d-none">
                <h6 class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Valid Rows Preview</h6>
                <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr><th>#</th><th>First Name</th><th>Middle Name</th><th>Last Name</th><th>Email</th></tr>
                        </thead>
                        <tbody id="teacher-csv-preview-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success d-none" id="btn-confirm-teacher-import">
            Confirm Import (<span id="teacher-confirm-count">0</span> rows)
        </button>
      </div>
    </div>
  </div>
</div>

<?php include("components/footer-scripts.php"); ?>

<script src="scripts/teachers.js"></script>

<script>
    $(document).ready(function() {
        // Sidebar Toggle
        $(document).off('click', '.sidebar-toggle').on('click', '.sidebar-toggle', function() {
            $(".dashboard-wrapper").toggleClass("toggled");
        });
    });
</script>

<?php include("components/footer.php"); ?>