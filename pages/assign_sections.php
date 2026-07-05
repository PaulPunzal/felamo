<?php
include("components/header.php");

// Fetch Teacher Data
if (isset($_GET['tId'])) {
    $teacherId = $_GET['tId'];
    $teacherResult = $AuthController->GetUser($teacherId);
    if ($teacherResult->num_rows > 0) {
        $teacher = $teacherResult->fetch_assoc();
    } else {
        header("Location: ../index.php");
        exit;
    }
} else {
    header("Location: ../index.php");
    exit;
}
$isSuperAdmin = $user['role'] === 'super_admin';
?>

<input type="hidden" id="hidden_user_id" value="<?= $auth_user_id ?>">
<input type="hidden" id="hidden_is_super_admin" value="<?= $isSuperAdmin ? 'true' : 'false' ?>">
<input type="hidden" id="hidden_teacher_id" value="<?= $teacherId ?>">

<style>
    /* --- FORCE RESET --- */
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
                <a href="teachers.php" class="btn btn-sm btn-light text-main me-3 rounded-circle" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-arrow-left"></i></a>
                <span><i class="bi bi-person-lines-fill me-2"></i> Assign Section: <?= htmlspecialchars($teacher['name'] ?? ($teacher['first_name'] . ' ' . $teacher['last_name']) ?? '') ?></span>
            </div>
            <button class="btn btn-sm btn-light text-main fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#assignSectionModal"><i class="bi bi-plus-circle-fill me-1"></i> Assign Section</button>
        </div>

        <div class="card shadow-sm rounded-3">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light"><tr><th><i class="bi bi-diagram-3-fill me-1"></i>Section Name</th><th class="text-end">Action</th></tr></thead>
                        <tbody id="section-table-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="assignSectionModal" tabindex="-1">
            <div class="modal-dialog">
                <form id="assign-section-form">
                    <div class="modal-content">
                        <div class="modal-header text-white" style="background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%);">
                            <h5 class="modal-title"><i class="bi bi-diagram-3-fill me-2"></i>Assign New Section</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body"><div class="mb-3"><label for="section_name" class="form-label">Section Name</label><input type="text" class="form-control" id="section_name" name="section_name" required placeholder="e.g. Grade 10 - Rizal"></div></div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-main text-light" style="background-color: #880f0b; border:none;"><i class="bi bi-save me-1"></i>Save</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php include("components/footer-scripts.php"); ?>
<script src="scripts/assignSections.js"></script>
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