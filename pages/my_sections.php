<?php 
include("components/header.php"); 
?>

<input type="hidden" id="hidden_user_id" value="<?= $auth_user_id ?>">

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

    /* --- HEADER BANNER --- */
    .page-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
        color: white; padding: 15px 25px; border-radius: 8px; margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;
        font-size: 1.5rem; font-weight: 700; text-transform: uppercase;
    }
    
    .btn-back-text {
        background-color: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.5);
        font-size: 0.85rem; font-weight: 600; padding: 8px 18px; border-radius: 50px; text-decoration: none;
        transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center;
    }
    .btn-back-text:hover { background-color: white; color: #a71b1b; transform: scale(1.02); }

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
        padding: 15px 25px; vertical-align: middle; border-bottom: 1px solid #f0f0f0;
    }
    .custom-table tbody tr:hover { background-color: #f8f9fa; }

    /* Buttons */
    .btn-action-main {
        background-color: #a71b1b; color: white; border: none; padding: 6px 14px;
        border-radius: 50px; font-size: 0.85rem; font-weight: 600; text-decoration: none;
        transition: background 0.2s; display: inline-flex; align-items: center; gap: 5px;
    }
    .btn-action-main:hover { background-color: #880f0b; color: white; }

    @media (max-width: 991.98px) { .main-content { margin-left: 0; padding: 1rem; } .page-header-banner { flex-direction: column; gap: 15px; text-align: center; } }
</style>

<div class="dashboard-wrapper">
    
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        
        <div class="page-header-banner">
            <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
                <i class="bi bi-diagram-3 fs-3"></i>
                <h4 class="m-0 fw-bold text-uppercase">
                    My Sections
                </h4>
            </div>
            <div class="header-right"></div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">
                                <i class="bi bi-people me-2"></i> Section Name
                            </th>
                            <th style="width: 30%; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="section-table-tbody">
                        <tr>
                            <td colspan="2" class="text-center py-5">
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
        
        // Active State
        $('a.nav-link-custom[href="my_sections.php"]').addClass('active');

        // Logic
        const teacher_id = $("#hidden_user_id").val();

        const loadAssignedSections = () => {
            $.ajax({
                type: "POST",
                url: "../backend/api/web/section_assignment.php",
                data: {
                    requestType: "GetAssignedSections",
                    teacher_id
                },
                success: function(response) {
                    let res = typeof response === 'string' ? JSON.parse(response) : response;

                    if (res.status === "success") {
                        let rows = "";
                        if(res.data.length === 0) {
                             rows = `<tr><td colspan="2" class="text-center py-4 text-muted">No sections assigned.</td></tr>`;
                        } else {
                            res.data.forEach((section) => {
                                rows += `
                                <tr>
                                    <td class="fw-bold text-dark fs-6">${section.section_name}</td>
                                    <td class="text-end">
                                        <a href="students.php?sectionId=${section.id}" class="btn-action-main">
                                            <i class="bi bi-people-fill"></i> View Students
                                        </a>
                                    </td>
                                </tr>`;
                            });
                        }
                        $("#section-table-tbody").html(rows);
                    } else {
                        $("#section-table-tbody").html(`
                            <tr><td colspan="2" class="text-center text-danger py-4">Failed to load sections</td></tr>
                        `);
                    }
                },
                error: function() {
                    $("#section-table-tbody").html(`
                        <tr><td colspan="2" class="text-center text-danger py-4">Error connecting to server</td></tr>
                    `);
                },
            });
        };

        loadAssignedSections();
    });
</script>

<?php include("components/footer.php"); ?>