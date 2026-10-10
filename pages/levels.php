<?php 
include("components/header.php"); 
?>

<input type="hidden" id="hidden_user_id" value="<?= $auth_user_id ?>">

<style>
    /* --- 1. RESET & LAYOUT (Required for Sidebar) --- */
    nav.navbar { display: none !important; } 
    body { background-color: #f4f6f9; overflow-x: hidden; }

    /* This wrapper class is CRITICAL for the sidebar toggle to work */
    .dashboard-wrapper {
        display: flex;
        width: 100%;
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* Main Content Area */
    .main-content {
        flex: 1;
        margin-left: 280px; /* Matches sidebar width */
        padding: 30px 40px;
        background-color: #f8f9fa;
        transition: margin-left 0.3s ease-in-out;
    }

    /* When Sidebar is Closed (Content Expands) */
    .dashboard-wrapper.toggled .main-content {
        margin-left: 0 !important;
    }

    /* --- 2. SIDEBAR INTERNAL STYLES (Missing from your sidebar.php) --- */
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
    .nav-link-custom.active { 
        background-color: #FFC107; color: #440101; 
    }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    
    .logout-btn { 
        margin-top: auto; background-color: #FFC107; color: black; 
        font-weight: bold; border: none; width: 100%; padding: 12px; 
        border-radius: 25px; text-align: center; cursor: pointer; 
    }
    .logout-btn:hover { background-color: #e0a800; }

    /* --- 3. PAGE SPECIFIC STYLES (Red Header & List) --- */
        .page-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
        color: #ffffff;
        padding: 15px 25px;
        border-radius: 8px;
        margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        font-size: 1.5rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .page-header-banner h4,
    .page-header-banner i {
        color: #ffffff;
    }
    .page-header-banner i { margin-right: 15px; font-size: 1.8rem; }

    .markahan-wrapper {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
        border: 1px solid #dee2e6;
    }
    .markahan-list-header {
        background-color: #e9ecef;
        padding: 15px 25px;
        font-weight: 800;
        color: #333;
        text-transform: uppercase;
        font-size: 0.95rem;
        border-bottom: 1px solid #dee2e6;
    }
    
    .markahan-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 25px;
        border-bottom: 1px solid #f0f0f0;
        background-color: #fff;
        transition: background-color 0.2s ease;
    }
    .markahan-item:last-child { border-bottom: none; }
    .markahan-item:hover { background-color: #d9d9d9; }
    .markahan-title { font-weight: 600; font-size: 1.1rem; color: #212529; }

    .btn-action-red {
        background-color: #c92a2a;
        color: white;
        border: none;
        padding: 6px 16px;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.2s;
        text-decoration: none;
    }
    .btn-action-red:hover { background-color: #a71b1b; color: white; }

    @media (max-width: 991.98px) {
        .main-content { margin-left: 0; padding: 1rem; }
    }
</style>

<div class="dashboard-wrapper">
    
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        
        <div class="page-header-banner">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <i class="bi bi-grid-fill fs-3"></i> 
        
        <h4 class="m-0 fw-bold text-uppercase">
            Markahan
        </h4>
    </div>
</div>

            <div id="levels-container">
                <div class="text-center py-5">
                    <div class="spinner-border text-main" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include("components/footer-scripts.php"); ?>

<script>
    $(document).ready(function () {
        
        // --- SIDEBAR TOGGLE LOGIC ---
        // This toggles the class on the wrapper, which triggers the CSS in sidebar.php
        $(document).off('click', '#sidebarToggleBtn'); 
        $(document).on('click', '#sidebarToggleBtn', function(e) {
            e.preventDefault();
            e.stopPropagation(); 
            $(".dashboard-wrapper").toggleClass("toggled");
        });

        // --- FETCH DATA LOGIC ---
        const auth_user_id = $("#hidden_user_id").val();

        const loadAntas = () => {
            $.ajax({
                type: "POST",
                url: "../backend/api/web/levels.php",
                data: { requestType: "GetLevels", auth_user_id },
                success: function (response) {
                    try {
                        let res = JSON.parse(response);

                        if (res.status === "success") {
                            let levels = res.data;
                            let html = "";

                            if (levels.length === 0) {
                                html = `<div class="p-4 text-center text-muted">Walang antas na nahanap.</div>`;
                            } else {
                                levels.forEach((level) => {
                                    let markahanName = "";
                                    switch (level.level) {
                                        case 1: markahanName = "Unang Markahan"; break;
                                        case 2: markahanName = "Pangalawang Markahan"; break;
                                        case 3: markahanName = "Pangatlong Markahan"; break;
                                        case 4: markahanName = "Ika-apat na Markahan"; break;
                                        default: markahanName = "Markahan " + level.level;
                                    }

                                    html += `
                                        <div class="markahan-item">
                                            <span class="markahan-title">${markahanName}</span>
                                            
                                            <div class="dropdown">
                                                <button class="btn-action-red dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                                    <li><a class="dropdown-item" href="level_details.php?level=${level.id}"><i class="bi bi-eye me-2"></i> View Details</a></li>
                                                    <li><a class="dropdown-item" href="taken_assessments.php?level=${level.id}"><i class="bi bi-journal-check me-2"></i> View Taken Assessment</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    `;
                                });
                            }
                            $("#levels-container").html(html);
                        } else {
                            $("#levels-container").html(`<div class="p-4 text-center text-danger">Failed to load levels.</div>`);
                        }
                    } catch (e) {
                        $("#levels-container").html(`<div class="p-4 text-center text-danger">Data error.</div>`);
                    }
                },
                error: function () {
                    $("#levels-container").html(`<div class="p-4 text-center text-danger">Server error.</div>`);
                },
            });
        };

        loadAntas();
    });
</script>

<?php include("components/footer.php"); ?>