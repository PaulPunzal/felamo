<?php
// pages/components/sidebar.php
$currentUserRole = $user['role'] ?? 'guest';
$currentUserName = $user['name'] ?? 'User';
$isSuperAdmin = $currentUserRole === 'super_admin';
$current_page = basename($_SERVER['PHP_SELF']); 

// --- LOGIC TO HANDLE PROFILE PICTURE ---
$profilePicPath = "../backend/storage/assets/logo.png"; // Default

// Check if the user has an uploaded picture
if (!empty($user['profile_picture'])) {
    $profilePicPath = "../" . $user['profile_picture'];
}
?>

<aside class="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profilePicPath) ?>?v=<?= time() ?>" alt="Profile"> 
        
        <div>
            <a href="profile.php" style="color: inherit; text-decoration: none;">               
                <h5><?= htmlspecialchars($currentUserName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></h5>
            </a>
            <small style="opacity: 0.8; font-size: 0.7rem;">
                <?= $isSuperAdmin ? 'SUPER ADMIN' : strtoupper($currentUserRole) ?>
            </small>
        </div>
    </div>

    <nav class="nav flex-column">
        <a href="home.php" class="nav-link-custom <?= $current_page == 'home.php' ? 'active' : '' ?>">
            <i class="bi bi-house-door-fill"></i> HOME
        </a>
        
        <?php if ($currentUserRole == "teacher") { ?>
            <a href="leader_boards.php" class="nav-link-custom <?= $current_page == 'leader_boards.php' ? 'active' : '' ?>">
                <i class="bi bi-trophy-fill"></i> LEADERBOARD
            </a>
            <a href="levels.php" class="nav-link-custom <?= $current_page == 'levels.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-bookmark-fill"></i> MARKAHAN
            </a>
            <a href="my_sections.php" class="nav-link-custom <?= $current_page == 'my_sections.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> SECTIONS
            </a>
            <a href="students.php" class="nav-link-custom <?= $current_page == 'students.php' ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill"></i> STUDENTS
            </a>

            <a href="student_progress.php" class="nav-link-custom <?= $current_page == 'student_progress.php' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line-fill"></i> PROGRESO NG MAG-AARAL
            </a>

            <a href="notifications.php" class="nav-link-custom <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i> NOTIFICATIONS
            </a>
        <?php } ?>

        <?php if ($currentUserRole == "super_admin") { ?>
            <a href="teachers.php" class="nav-link-custom <?= ($current_page == 'teachers.php' || $current_page == 'assign_sections.php' || $current_page == 'assign_students-v2.php') ? 'active' : '' ?>">
                <i class="bi bi-person-video3"></i> TEACHER
            </a>
            <a href="students.php" class="nav-link-custom <?= ($current_page == 'students.php' || $current_page == 'section_students.php') ? 'active' : '' ?>">
                <i class="bi bi-person-fill"></i> STUDENT
            </a>
        <?php } ?>
    </nav>

    <button class="logout-btn" type="button" data-bs-toggle="modal" data-bs-target="#logoutModal">
        LOG OUT
    </button>
</aside>

<div class="sidebar-toggle" id="sidebarToggleBtn">
    <i class="bi bi-chevron-left"></i>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%);">
                <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="mb-0 fs-5">Are you sure you want to log out?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="../logout.php" class="btn text-white" style="background-color: #880f0b;">Yes, Log Out</a>
            </div>
        </div>
    </div>
</div>

<style>
    /* --- SIDEBAR CONTAINER --- */
    .sidebar { 
        width: 280px !important;            
        background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%) !important; 
        color: white !important; 
        display: flex !important; 
        flex-direction: column !important; 
        padding: 20px !important; 
        position: fixed !important; 
        top: 0 !important; bottom: 0 !important; left: 0 !important; 
        z-index: 1000 !important;           
        transition: transform 0.3s ease !important;
    }

    /* --- SIDEBAR PROFILE PICTURE FIX --- */
    .sidebar-profile {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.5);
    }
    .sidebar-profile img {
        width: 80px !important; 
        height: 80px !important; 
        min-width: 80px; /* Prevent shrinking */
        min-height: 80px; 
        border-radius: 50% !important; 
        object-fit: cover !important; /* Forces image to fill circle without stretching */
        border: 2px solid white; 
        display: block;
        background-color: white;
    }

    /* --- THE TOGGLE BUTTON --- */
    .sidebar-toggle { 
        position: fixed !important; 
        left: 280px !important;     
        top: 50% !important;
        width: 30px !important; 
        height: 60px !important; 
        background-color: #FFC107 !important; 
        border: 1px solid #b38f00 !important; 
        border-left: none !important;
        border-radius: 0 8px 8px 0 !important; 
        display: flex !important; 
        align-items: center; 
        justify-content: center; 
        cursor: pointer !important; 
        color: #000 !important;             
        z-index: 9999 !important;   
        transition: left 0.3s ease !important; 
        box-shadow: 4px 0 5px rgba(0,0,0,0.2) !important;
    }

    /* --- CLOSED STATE ANIMATIONS --- */
    .dashboard-wrapper.toggled .sidebar { 
        transform: translateX(-280px) !important; 
    }
    .dashboard-wrapper.toggled .sidebar-toggle { 
        left: 0px !important; 
    }
    .dashboard-wrapper.toggled .main-content { 
        margin-left: 0 !important; 
    }
    .dashboard-wrapper.toggled .sidebar-toggle i { 
        transform: rotate(180deg) !important; 
    }
</style>