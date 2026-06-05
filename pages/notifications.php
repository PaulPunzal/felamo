<?php
include("components/header.php");
include_once("../backend/controller/SectionController.php");

$sectionController = new SectionController();
// Fetch sections for this specific teacher
$sections = $sectionController->GetSectionsResult($auth_user_id);
?>

<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">

<style>
    /* --- RESET & LAYOUT --- */
    /* CRITICAL: Hides the first navbar to avoid ruined layout */
    nav.navbar { display: none !important; } 
    
    body { background-color: #f4f6f9; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; width: 100%; min-height: 100vh; overflow-x: hidden; }
    .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; background-color: #f8f9fa; transition: margin-left 0.3s ease-in-out; }
    .dashboard-wrapper.toggled .main-content { margin-left: 0 !important; }

    /* --- SIDEBAR --- */
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; color: white; }
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover { background-color: rgba(255, 255, 255, 0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107 !important; color: #440101 !important; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; text-align: center; cursor: pointer; }

    /* --- STRICT FIXED HEADER BANNER --- */
    .page-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
        color: white; 
        padding: 0 25px; 
        border-radius: 8px; 
        margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        
        height: 80px !important;
        min-height: 80px !important;
        max-height: 80px !important;
        
        display: flex; 
        align-items: center; 
        justify-content: space-between;
        overflow: hidden; 
    }

    .header-left {
        display: flex; align-items: center; gap: 15px; 
        flex: 1; min-width: 0; height: 100%;
    }
    
    .page-header-banner h4 {
        margin: 0; font-weight: 700; text-transform: uppercase;
        font-size: 1.5rem; line-height: 1;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    /* Header Action Button (Send Notification) */
    .btn-header-action {
        background-color: white; color: #a71b1b; border: none;
        font-size: 0.85rem; font-weight: 700; padding: 10px 20px;
        border-radius: 50px; text-transform: uppercase;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2); text-decoration: none;
        transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
        white-space: nowrap;
    }
    .btn-header-action:hover { background-color: #f0f0f0; transform: translateY(-2px); color: #880f0b; }

    /* --- TABLE CONTAINER --- */
    .table-container {
        background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden; border: 1px solid #dee2e6;
    }
    .custom-table { width: 100%; margin-bottom: 0; border-collapse: collapse; }
    .custom-table thead {
        background-color: #e9ecef; color: #333; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; white-space: nowrap;
    }
    .custom-table th, .custom-table td {
        padding: 15px 25px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem;
    }
    .custom-table tbody tr:hover { background-color: #f8f9fa; }

    /* --- MODAL STYLES --- */
    .modal-header-custom { background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%); color: white; padding: 15px 20px; }
    .modal-title { font-weight: 700; letter-spacing: 0.5px; }
    .btn-main { background-color: #a71b1b; color: white; border: none; }
    .btn-main:hover { background-color: #880f0b; color: white; }

    /* Mobile Adjustments */
    @media (max-width: 991.98px) { 
        .main-content { margin-left: 0; padding: 1rem; } 
        .page-header-banner { 
            height: auto !important; max-height: none !important;
            padding: 20px; flex-direction: column; gap: 15px; text-align: center; 
        } 
        .header-left { flex-direction: column; }
        .page-header-banner h4 { white-space: normal; } 
    }
</style>

<div class="dashboard-wrapper">
    
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        
        <div class="page-header-banner">
            <div class="header-left">
                <i class="bi bi-bell-fill fs-3 me-2"></i>
                <h4 title="Notifications">Notifications</h4>
            </div>

            <div class="header-right">
                <button class="btn-header-action" data-bs-toggle="modal" data-bs-target="#notificationModal">
                    <i class="bi bi-plus-circle-fill"></i> Send Notification
                </button>
            </div>
        </div>

        <div id="alert" style="position: absolute; top:10px; right:10px; font-size: 12px; z-index: 1050;"></div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;"><i class="bi bi-diagram-3 me-1"></i> Section</th>
                            <th style="width: 25%;"><i class="bi bi-chat-left-text me-1"></i> Title</th>
                            <th style="width: 50%;"><i class="bi bi-file-text me-1"></i> Description</th>
                        </tr>
                    </thead>
                    <tbody id="notif-table-tbody">
                        <tr>
                            <td colspan="3" class="text-center py-5">
                                <div class="spinner-border text-secondary" role="status"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="notificationForm" style="display: contents;">
            <div class="modal-content border-0 shadow">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title" id="notificationModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Send Notification
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="notifSection" class="form-label fw-bold text-secondary text-uppercase fs-7">
                            <i class="bi bi-diagram-3 me-1"></i>Section
                        </label>
                        <select name="section" id="notifSection" class="form-select py-2" required>
                            <option value="" selected disabled>Select a section...</option>
                            <?php 
                            if ($sections && $sections->num_rows > 0) {
                                while ($section = $sections->fetch_assoc()): 
                            ?>
                                <option value="<?= htmlspecialchars($section['id']) ?>">
                                    <?= htmlspecialchars($section['section_name']) ?>
                                </option>
                            <?php 
                                endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notifTitle" class="form-label fw-bold text-secondary text-uppercase fs-7">
                            <i class="bi bi-chat-left-text me-1"></i>Title
                        </label>
                        <input type="text" class="form-control py-2" id="notifTitle" name="title" placeholder="Enter title" required>
                    </div>
                    <div class="mb-3">
                        <label for="notifDescription" class="form-label fw-bold text-secondary text-uppercase fs-7">
                            <i class="bi bi-file-text me-1"></i>Description
                        </label>
                        <textarea class="form-control" id="notifDescription" name="description" rows="4" placeholder="Enter message..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-main px-4 shadow-sm">
                        <i class="bi bi-send-fill me-2"></i>Send
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="viewStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title">
                    <i class="bi bi-eye-fill me-2"></i>Recipient Status
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="list-group list-group-flush" id="statusList">
                    </ul>
            </div>
        </div>
    </div>
</div>

<?php include("components/footer-scripts.php"); ?>
<script src="scripts/notifications.js?v=<?= time() ?>"></script>

<script>
    $(document).ready(function() {
        // Sidebar Toggle
        $(document).off('click', '.sidebar-toggle');
        $(document).on('click', '.sidebar-toggle', function(e) {
            e.preventDefault(); e.stopPropagation(); 
            $(".dashboard-wrapper").toggleClass("toggled");
        });
        
        // Set Active Link
        $('a.nav-link-custom[href="notifications.php"]').addClass('active');
    });
</script>

<?php include("components/footer.php"); ?>