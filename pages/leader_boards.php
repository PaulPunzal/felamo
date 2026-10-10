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

    /* --- PAGE HEADER BANNER --- */
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
    
    .header-left { display: flex; align-items: center; gap: 15px; }
    .page-header-banner h4 { margin: 0; font-weight: 700; text-transform: uppercase; font-size: 1.5rem; line-height: 1; color: #ffffff; }

    .header-right .input-group-text { background-color: rgba(255,255,255,0.9); color: #a71b1b; border: none; }
    .header-right .form-select { border: none; cursor: pointer; color: #333; }
    .header-right .form-select:focus { box-shadow: none; outline: none; }

    /* --- TABLE CONTAINER --- */
    .table-container {
        background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden; border: 1px solid #dee2e6;
    }
    .custom-table { width: 100%; margin-bottom: 0; border-collapse: collapse; }
    .custom-table thead {
        background-color: #e9ecef; color: #333; font-weight: 800; text-transform: uppercase; font-size: 0.85rem;
    }
    .custom-table th, .custom-table td {
        padding: 15px 25px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; font-size: 0.95rem;
    }
    .custom-table tbody tr:hover { background-color: #f8f9fa; }

    /* Rank highlighting */
    .rank-1 { color: #d4af37; font-weight: 900; font-size: 1.1rem; } /* Gold */
    .rank-2 { color: #c0c0c0; font-weight: 800; font-size: 1.05rem; } /* Silver */
    .rank-3 { color: #cd7f32; font-weight: 800; font-size: 1.05rem; } /* Bronze */
    .rank-other { font-weight: 600; color: #6c757d; }

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
                <i class="bi bi-trophy-fill fs-3 me-2 text-white"></i>
                <h4 title="Leader Boards">Leader Boards</h4>
            </div>

            <div class="header-right">
                <div class="input-group input-group-sm shadow-sm rounded">
                    <label for="sectionDropdown" class="input-group-text fw-bold">
                        <i class="bi bi-diagram-3 me-1"></i> Section
                    </label>
                    <select name="section_id" id="sectionDropdown" class="form-select fw-bold">
    <option value="">All Sections</option>
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
            </div>
        </div>

        <div id="alert" style="position: absolute; top:10px; right:10px; font-size: 12px; z-index: 1050; display:none;"></div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;"><i class="bi bi-sort-numeric-down me-1"></i>Rank</th>
                            <th style="width: 20%;"><i class="bi bi-person-vcard me-1"></i>LRN</th>
                            <th style="width: 25%;"><i class="bi bi-person me-1"></i>First Name</th>
                            <th style="width: 20%;"><i class="bi bi-person me-1"></i>Middle Name</th>
                            <th style="width: 25%;"><i class="bi bi-person me-1"></i>Last Name</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="leader-boards-table-tbody">
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="spinner-border text-secondary" role="status"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include("components/footer-scripts.php"); ?>

<script>
    $(document).ready(function() {
        // Sidebar Toggle
        $(document).off('click', '.sidebar-toggle');
        $(document).on('click', '.sidebar-toggle', function(e) {
            e.preventDefault(); e.stopPropagation(); 
            $(".dashboard-wrapper").toggleClass("toggled");
        });
        
        // Active Sidebar Link
        $('a.nav-link-custom[href="leader_boards.php"]').addClass('active');

        const teacher_id = $("#hidden_user_id").val();
        let current_section_id = ""; // Default empty string

        const showAlert = (type, message) => {
            $("#alert").removeClass().addClass(`alert ${type}`).text(message).fadeIn();
            setTimeout(() => {
                $("#alert").fadeOut("slow", function() { $(this).removeClass().text(""); });
            }, 2000);
        };

        const loadLeaderBoards = (section_id) => {
            $("#leader-boards-table-tbody").html('<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-secondary"></div></td></tr>');
            
            $.ajax({
                type: "POST",
                url: "../backend/api/web/leader_boards.php",
                data: {
                    requestType: "GetLeaderBoards",
                    teacher_id: teacher_id,
                    section_id: section_id
                },
                success: function(response) {
                    try {
                        let res = typeof response === 'string' ? JSON.parse(response) : response;

                        if (res.status === "success") {
                            let rows = "";
                            
                            if(!res.data || res.data.length === 0) {
                                rows = `<tr><td colspan="6" class="text-center py-4 text-muted">No students found.</td></tr>`;
                            } else {
                                res.data.forEach((student, index) => {
                                    let rank = index + 1;
                                    let rankClass = "rank-other";
                                    let rankIcon = rank;
                                    
                                    if(rank === 1) { rankClass = "rank-1"; rankIcon = `<i class="bi bi-trophy-fill me-1"></i>1st`; }
                                    else if(rank === 2) { rankClass = "rank-2"; rankIcon = `2nd`; }
                                    else if(rank === 3) { rankClass = "rank-3"; rankIcon = `3rd`; }

                                    rows += `
                                        <tr>
                                            <td class="${rankClass}">${rankIcon}</td>
                                            <td class="fw-bold">${student.lrn || ""}</td>
                                            <td>${student.first_name || ""}</td>
                                            <td>${student.middle_name || ""}</td>
                                            <td>${student.last_name || ""}</td>
                                            <td></td>
                                        </tr>
                                    `;
                                });
                            }
                            $("#leader-boards-table-tbody").html(rows);
                        } else {
                            $("#leader-boards-table-tbody").html(`
                              <tr><td colspan="6" class="text-center text-danger py-4">Failed to load leader boards</td></tr>
                            `);
                        }
                    } catch (e) {
                        console.error("Parse Error:", e);
                        $("#leader-boards-table-tbody").html(`
                              <tr><td colspan="6" class="text-center text-danger py-4">Error loading data.</td></tr>
                        `);
                    }
                },
                error: function() {
                    $("#leader-boards-table-tbody").html(`
                        <tr><td colspan="6" class="text-center text-danger py-4">Error connecting to server</td></tr>
                    `);
                },
            });
        };

        $("#sectionDropdown").change(function(e) {
            e.preventDefault();
            current_section_id = $(this).val();
            loadLeaderBoards(current_section_id);
        });

        // Initial Load
        loadLeaderBoards(current_section_id);
    });
</script>

<?php include("components/footer.php"); ?>