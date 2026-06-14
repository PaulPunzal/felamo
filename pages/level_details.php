<?php 
include("components/header.php"); 

// Authorization and Validation
$level_id = isset($_GET['level']) ? $_GET['level'] : null;
$levelText = "Unknown"; // Default

if ($level_id) {
    try {
        if (isset($AuthController) && method_exists($AuthController, 'GetUsingId')) {
            $levelResult = $AuthController->GetUsingId("levels", $level_id);

            if ($levelResult && is_object($levelResult) && $levelResult->num_rows > 0) {
                $level = $levelResult->fetch_assoc();

                // --- Number to Word Mapping ---
                $levelNum = $level['level'];
                
                // --- NEW LOGIC FOR CORRECT GRAMMAR ---
                $ordinalMap = [
                    1 => "sa unang",
                    2 => "sa ikalawang",
                    3 => "sa ikatlong",
                    4 => "sa Ika-apat na" 
                ];
                $levelText = isset($ordinalMap[$levelNum]) ? $ordinalMap[$levelNum] : "sa markahan " . $levelNum;

                // Authorization check
                if ($level['teacher_id'] != $auth_user_id) {
                    echo "<script>window.location.href='../index.php';</script>";
                    exit;
                }
            } else {
                 echo "<script>window.location.href='../index.php';</script>";
                 exit;
            }
        }
    } catch (Exception $e) {
        echo "<script>window.location.href='../index.php';</script>";
        exit;
    }
} else {
    echo "<script>window.location.href='../index.php';</script>";
    exit;
}
?>

<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">
<input type="hidden" id="hidden_level_id" value="<?= htmlspecialchars($level_id) ?>">

<style>
    /* --- 1. RESET & LAYOUT --- */
    nav.navbar { display: none !important; } 
    body { background-color: #f4f6f9; overflow-x: hidden; }

    /* Dashboard Wrapper */
    .dashboard-wrapper {
        display: flex;
        width: 100%;
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* Main Content Area */
    .main-content {
        flex: 1;
        margin-left: 280px; 
        padding: 30px 40px;
        background-color: #f8f9fa;
        transition: margin-left 0.3s ease-in-out;
    }

    /* Toggled State */
    .dashboard-wrapper.toggled .main-content { margin-left: 0 !important; }

    /* --- 2. SIDEBAR INTERNAL STYLES --- */
    .sidebar-profile { 
        display: flex; align-items: center; gap: 15px; margin-bottom: 30px; 
        padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); 
    }
    .sidebar-profile img { 
        width: 80px !important; height: 80px !important; border-radius: 50%; 
        object-fit: cover; border: 2px solid white; 
    }
    .sidebar-profile h5 { 
        font-weight: bold; margin: 0; font-size: 1.2rem; 
        text-transform: uppercase; color: white; 
    }
    .nav-link-custom { 
        display: flex; align-items: center; padding: 12px 15px; color: white; 
        text-decoration: none; font-weight: 600; margin-bottom: 10px; 
        transition: 0.3s; border-radius: 5px; 
    }
    .nav-link-custom:hover { 
        background-color: rgba(255, 255, 255, 0.2); color: white; 
    }
    /* The Active State (Yellow) */
    .nav-link-custom.active { 
        background-color: #FFC107 !important; color: #440101 !important; 
    }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    
    .logout-btn { 
        margin-top: auto; background-color: #FFC107; color: black; 
        font-weight: bold; border: none; width: 100%; padding: 12px; 
        border-radius: 25px; text-align: center; cursor: pointer; 
    }
    .logout-btn:hover { background-color: #e0a800; }

    /* --- HEADER BANNER --- */
    .page-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        display: flex;
        align-items: center; 
        justify-content: space-between; 
    }

    /* Left Side Grouping */
    .header-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    /* --- ROUND BACK BUTTON (Matches create_assessment.php) --- */
    .btn-back-text {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        font-size: 0.85rem;
        font-weight: 700;
        padding: 8px 18px;
        border-radius: 50px;
        text-decoration: none;
        text-transform: uppercase;
        border: 1px solid rgba(255, 255, 255, 0.4);
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-back-text:hover {
        background-color: white;
        color: #a71b1b;
        transform: scale(1.02);
    }

    /* --- TITLE TEXT --- */
    .header-text {
        font-size: 1.5rem;
        font-weight: 700;
        text-transform: uppercase;
        margin: 0;
        line-height: 1; 
    }

    .btn-header-action {
        background-color: white;
        color: #a71b1b;
        border: none;
        font-size: 0.9rem;
        font-weight: 700;
        padding: 8px 20px;
        border-radius: 50px;
        text-transform: uppercase;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        text-decoration: none;
        transition: all 0.2s;
    }
    .btn-header-action:hover { background-color: #f0f0f0; transform: translateY(-2px); }

    /* --- 4. TABLE STYLES --- */
    .table-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid #dee2e6;
        overflow: visible; 
    }
    /* Stops Bootstrap from clipping the bottom of the dropdown */
    .table-responsive {
        overflow: visible !important;
    }

    /* Since we removed overflow: hidden, we need to manually round the top corners of the header */
    .custom-table thead th:first-child {
        border-top-left-radius: 8px;
    }
    .custom-table thead th:last-child {
        border-top-right-radius: 8px;
    }
    .custom-table { width: 100%; margin-bottom: 0; border-collapse: collapse; }
    .custom-table thead {
        background-color: #e9ecef; color: #333; font-weight: 800;
        text-transform: uppercase; font-size: 0.85rem;
    }
    .custom-table th, .custom-table td {
        padding: 15px 25px; vertical-align: middle; border-bottom: 1px solid #f0f0f0;
    }
    .custom-table tbody tr:hover { background-color: #f8f9fa; }
    .aralin-title { font-weight: 600; color: #212529; font-size: 1rem; }
    .aralin-summary { color: #6c757d; font-size: 0.9rem; max-width: 400px; }

    /* Action Button (Red) */
    .btn-action-red {
        background-color: #c92a2a; color: white; border: none;
        padding: 6px 12px; border-radius: 4px; font-size: 0.85rem;
        font-weight: 600; display: inline-flex; align-items: center; gap: 5px;
    }
    .btn-action-red:hover { background-color: #a71b1b; color: white; }

    /* Mobile */
    @media (max-width: 991.98px) {
        .main-content { margin-left: 0; padding: 1rem; }
        .page-header-banner { flex-direction: column; gap: 15px; text-align: center; }
    }
</style>

<div class="dashboard-wrapper">
    
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        
        <div class="page-header-banner">
    
            <div class="header-left">
                <a href="levels.php" class="btn-back-text">
            BACK
        </a>
                
                <h4 class="m-0 fw-bold text-uppercase">
    Aralin <?= htmlspecialchars($levelText) ?> Markahan
</h4>
            </div>

            <div class="header-right">
                <button type="button" 
                        class="btn-header-action" 
                        data-bs-toggle="modal" 
                        data-bs-target="#insertAralinModal">
                    <i class="bi bi-plus-circle-fill me-2"></i> INSERT ARALIN
                </button>
            </div>

        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Aralin</th>
                            <th style="width: 25%;">Title</th>
                            <th style="width: 45%;">Summary</th>
                            <th style="width: 15%; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="antas-table-body">
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <div class="spinner-border text-main" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="insertAralinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="insert-aralin-form" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="requestType" value="InsertAralin">
            <input type="hidden" name="level_id" value="<?= $level_id ?>">

            <div class="modal-content">
                <div class="modal-header bg-main text-white" style="background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Aralin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Lesson Title</label>
                        <input type="text" class="form-control" name="title" placeholder="e.g., Introduction to Programming" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Summary</label>
                            <textarea class="form-control" name="summary" rows="4" required></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Full Details</label>
                            <textarea class="form-control" name="details" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Upload Video</label>
                        <input type="file" class="form-control" name="attachment" accept="video/*" required>
                    </div>
                    <div class="upload-progress-container mt-3" style="display: none;">
                        <label class="form-label fw-bold text-primary mb-1 upload-status-text">Uploading Video...</label>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary upload-progress-bar" 
                                role="progressbar" 
                                style="width: 0%;" 
                                aria-valuenow="0" 
                                aria-valuemin="0" 
                                aria-valuemax="100">0%</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-main text-light" style="background-color: #a71b1b; color: white;">Save Lesson</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editAralinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="edit-aralin-form" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="requestType" value="EditAralin">
            <input type="hidden" name="aralin_id" id="edit-aralin-id">
            <input type="hidden" name="level_id" value="<?= $level_id ?>">

            <div class="modal-content">
                <div class="modal-header bg-main text-white" style="background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Aralin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title</label>
                        <input type="text" class="form-control" id="edit-aralin-title" name="title" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Summary</label>
                            <textarea class="form-control" id="edit-aralin-summary" name="summary" rows="4" required></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Details</label>
                            <textarea class="form-control" id="edit-aralin-details" name="details" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Video</label>
                        <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                            <span class="text-muted" id="current-video-text"><i class="bi bi-film me-2"></i>Current file attached</span>
                            <a href="#" target="_blank" id="current-video-link" class="btn btn-sm btn-outline-primary">Preview</a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Replace Video (Optional)</label>
                        <input type="file" class="form-control" name="attachment" accept="video/*">
                    </div>
                                        <div class="upload-progress-container mt-3" style="display: none;">
                        <label class="form-label fw-bold text-primary mb-1 upload-status-text">Uploading Video...</label>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary upload-progress-bar" 
                                role="progressbar" 
                                style="width: 0%;" 
                                aria-valuenow="0" 
                                aria-valuemin="0" 
                                aria-valuemax="100">0%</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-main text-light" style="background-color: #a71b1b; color: white;">Update Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include("components/footer-scripts.php"); ?>
<script src="scripts/levelsDetails.js?v=6"></script>

<script>
    $(document).ready(function () {
        // Toggle Sidebar
        $(document).off('click', '.sidebar-toggle');
        $(document).on('click', '.sidebar-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation(); 
            $(".dashboard-wrapper").toggleClass("toggled");
        });

        // Maintain 'active' class for the sidebar link
        $('a.nav-link-custom[href="levels.php"]').addClass('active');
    });
</script>

<?php include("components/footer.php"); ?>