<?php 
// 1. Include the global header
include("components/header.php"); 

// 2. Define role and user variables safely
$isSuperAdmin = isset($user['role']) && $user['role'] === 'super_admin'; 
$currentUserId = isset($user['id']) ? $user['id'] : (isset($auth_user_id) ? $auth_user_id : 0);
?>

<input type="hidden" id="hidden_user_id" value="<?= $currentUserId ?>">

<style>
    /* --- KEEPING YOUR EXACT DESIGN --- */
    
    /* 1. Page & Layout Reset */
    .navbar, header, .main-header { display: none !important; }
    body { background-color: #f4f6f9; overflow-x: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .dashboard-wrapper { display: flex; min-height: 100vh; width: 100%; overflow-x: hidden; position: relative; }

    /* 2. SIDEBAR STYLING */
    .sidebar { 
        width: 280px; 
        background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%); 
        color: white; 
        display: flex; 
        flex-direction: column; 
        padding: 20px; 
        position: fixed; 
        height: 100vh; 
        z-index: 1000; 
        left: 0; 
        transition: all 0.3s ease; 
        overflow: visible !important; 
    }

    /* Sidebar Profile */
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; max-width: 100%; display: block; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; color: white; }
    .sidebar-profile small { color: rgba(255,255,255,0.8); }

    /* Sidebar Links */
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover { background-color: rgba(255, 255, 255, 0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107; color: #440101; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }

    /* Sidebar Buttons */
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; text-align: center; text-decoration: none; cursor: pointer; }
    .logout-btn:hover { background-color: #e0a800; color: black; }

    .sidebar-toggle { position: absolute; right: -30px; top: 50%; width: 30px; height: 60px; background-color: #FFC107; border-radius: 0 4px 4px 0; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #333; transition: right 0.3s ease; z-index: 1001; }
    .sidebar-toggle i { transition: transform 0.3s ease; }

    /* Toggled State */
    .dashboard-wrapper.toggled .sidebar { left: -280px; }
    .dashboard-wrapper.toggled .sidebar-toggle { right: -30px; }
    .dashboard-wrapper.toggled .sidebar-toggle i { transform: rotate(180deg); }
    .dashboard-wrapper.toggled .main-content { margin-left: 0; }

    /* 3. MAIN CONTENT STYLING */
    .main-content { 
        flex: 1; 
        margin-left: 280px; 
        padding: 30px 40px; 
        transition: all 0.3s ease; 
        width: calc(100% - 280px);
    }

    /* 4. PROFILE SPECIFIC STYLES */
    .profile-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #d32f2f 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(167, 27, 27, 0.3);
        font-weight: 800;
        font-size: 1.8rem;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        display: flex;
        align-items: center;
    }
    .profile-header-banner i { margin-right: 15px; font-size: 2rem; }

    .profile-card {
        background: white;
        border-radius: 12px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        padding: 35px;
        margin-bottom: 30px;
    }

    .section-title {
        color: #a71b1b;
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 15px;
    }

    /* Form Styles */
    .input-group-text { background-color: #f8f9fa; border: 1px solid #e0e0e0; border-right: none; color: #a71b1b; font-size: 1.1rem; border-radius: 8px 0 0 8px; }
    .form-control-custom { background-color: #f8f9fa; border: 1px solid #e0e0e0; border-left: none; padding: 12px 15px; font-weight: 500; color: #495057; border-radius: 0 8px 8px 0; transition: all 0.3s; }
    .form-control-custom:focus { background-color: #fff; box-shadow: 0 0 0 3px rgba(167, 27, 27, 0.1); border-color: #a71b1b; }
    .input-group { margin-bottom: 25px; }
    .form-label { font-size: 0.85rem; font-weight: 600; color: #6c757d; margin-bottom: 8px; text-transform: uppercase; }

    .btn-update {
        background: linear-gradient(90deg, #a71b1b 0%, #c62828 100%);
        color: white;
        width: 100%;
        padding: 14px;
        border-radius: 8px;
        border: none;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        margin-top: 15px;
        box-shadow: 0 4px 6px rgba(167, 27, 27, 0.2);
    }
    .btn-update:hover {
        background: linear-gradient(90deg, #8e1616 0%, #b71c1c 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(167, 27, 27, 0.3);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) { 
        .main-content { margin-left: 0; width: 100%; padding: 20px; }
        .dashboard-wrapper.toggled .sidebar { left: 0; } 
        .sidebar { left: -280px; } 
        .sidebar-toggle { right: -30px; }
    }
</style>

<div class="dashboard-wrapper">
    
    <?php include("components/sidebar.php"); ?>

    <main class="main-content">
        <div class="profile-header-banner">
            <i class="bi bi-person-badge"></i> User Profile
        </div>

        <div class="profile-card">
            <div class="section-title">
                <i class="bi bi-camera-fill"></i> Profile Picture
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12 text-center">
                    <div class="position-relative d-inline-block">
                        <img id="profilePreview" 
                             src="../<?= isset($user['profile_picture']) && !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'assets/img/lei.png' ?>?v=<?= time() ?>" 
                             class="rounded-circle img-thumbnail" 
                             style="width: 150px; height: 150px; object-fit: cover;">
                        
                        <label for="profileUpload" class="btn btn-sm btn-warning position-absolute bottom-0 end-0 rounded-circle" style="padding: 5px 8px; cursor: pointer;">
                            <i class="bi bi-camera"></i>
                        </label>
                        <input type="file" id="profileUpload" style="display:none;" accept="image/*">
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Click the camera icon to change your photo</small>
                    </div>
                    <button type="button" id="btnSavePhoto" class="btn btn-sm btn-primary mt-2" style="display:none;">
                        <i class="bi bi-cloud-upload"></i> Upload Photo
                    </button>
                </div>
            </div>
            <hr class="my-4" style="opacity: 0.1;">

            <div class="section-title">
                <i class="bi bi-pencil-square"></i> Edit Account Details
            </div>

            <form id="editUserForm">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" id="first_name" name="first_name" class="form-control-custom"
                                value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                            <input type="text" id="last_name" name="last_name" class="form-control-custom"
                                value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                            <input type="email" id="email" name="email" class="form-control form-control-custom" required placeholder="Enter your email" value="<?= isset($user['email']) ? htmlspecialchars($user['email']) : '' ?>">
                        </div>
                    </div>

                    <div class="col-12"><hr class="my-4" style="opacity: 0.1;"></div>

                    <div class="col-md-6">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" id="newPassword" name="newPassword" class="form-control form-control-custom" placeholder="Leave blank to keep current">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-check-circle-fill"></i></span>
                            <input type="password" id="newPassword2" name="newPassword2" class="form-control form-control-custom" placeholder="Repeat new password">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-update" id="btnUpdate">
                    <i class="bi bi-save me-2"></i> Save Changes
                </button>
            </form>
        </div>
    </main>
</div>

<?php include("components/footer-scripts.php"); ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        
        // --- 1. SIDEBAR TOGGLE ---
        $(document).on('click', '.sidebar-toggle', function() {
            $(".dashboard-wrapper").toggleClass("toggled");
        });

        // --- 2. PROFILE PICTURE UPLOAD ---
        const profileUpload = document.getElementById('profileUpload');
        const profilePreview = document.getElementById('profilePreview');
        const btnSavePhoto = document.getElementById('btnSavePhoto');
        let selectedFile = null;

        if(profileUpload) {
            profileUpload.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const MAX_MB = 2; // keep this at or below your PHP upload_max_filesize
                    if (this.files[0].size > MAX_MB * 1024 * 1024) {
                        Swal.fire('File too large', 'Please choose an image smaller than ' + MAX_MB + 'MB.', 'error');
                        this.value = "";
                        selectedFile = null;
                        $(btnSavePhoto).hide();
                        return;
                    }

                    selectedFile = this.files[0];
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePreview.src = e.target.result;
                        $(btnSavePhoto).fadeIn();
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        $("#btnSavePhoto").on('click', function() {
            if (!selectedFile) return;
            const btn = $(this);

            const formData = new FormData();
            formData.append('requestType', 'UploadProfilePicture');
            formData.append('auth_user_id', $("#hidden_user_id").val());
            formData.append('profile_picture', selectedFile);

            // MODAL LOADING (Just like you asked)
            Swal.fire({
                title: 'Uploading...',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: '../backend/api/web/auth.php',
                type: 'POST',
                data: formData,
                contentType: false, 
                processData: false, 
                dataType: 'text', 
                success: function(responseText) {
                    try {
                        const response = JSON.parse(responseText.trim());
                        if (response.status === 'success') {
                            Swal.fire({ title: 'Success!', text: 'Photo updated.', icon: 'success', confirmButtonColor: '#a71b1b' }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message || 'Upload failed.', 'error');
                        }
                    } catch (e) {
                        Swal.fire('Server Error', 'Invalid response from server.', 'error');
                    }
                },
                error: function(xhr) {
                    Swal.fire('Network Error', 'Could not connect to server.', 'error');
                }
            });
        });

        // --- 3. PROFILE DETAILS UPDATE ---
        $("#editUserForm").on('submit', function(e) {
            e.preventDefault();
            
            // WE DO NOT TOUCH THE BUTTON TEXT ANYMORE.
            // NO LOADING SCREEN ON BUTTON.
            
            const userId = $("#hidden_user_id").val();
            const first_name = $("#first_name").val().trim();
            const last_name  = $("#last_name").val().trim();
            const email = $("#email").val().trim();
            const p1 = $("#newPassword").val();
            const p2 = $("#newPassword2").val();

            if (first_name === "" || last_name === "" || email === "") {
                Swal.fire('Error', 'Name and Email are required.', 'error');
                return;
            }

            if(p1 !== "" && p1 !== p2) {
                Swal.fire('Error', 'Passwords do not match!', 'error');
                return;
            }

            let dataToSend = {
                requestType: 'EditUser',
                auth_user_id: userId,
                first_name: first_name,
                last_name:  last_name,
                email: email,
                newPassword: p1
            };

            // MODAL LOADING (This replaces the button loading)
            Swal.fire({
                title: 'Updating...',
                text: 'Saving your details...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: '../backend/api/web/auth.php', 
                type: 'POST',
                data: dataToSend,
                dataType: 'text', // SAFE MODE to prevent crash
                
                success: function(responseText) {
                    try {
                        const response = JSON.parse(responseText.trim());

                        if (response.status === 'success') {
                            // Success Modal replaces Loading Modal
                            Swal.fire({
                                title: 'Success!',
                                text: response.message || 'Profile updated successfully.',
                                icon: 'success',
                                confirmButtonColor: '#a71b1b'
                            }).then(() => {
                                location.reload(); 
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to update profile.', 'error');
                        }
                    } catch (e) {
                        if(responseText.includes("success")) {
                             Swal.fire({ title: 'Success!', text: 'Profile updated.', icon: 'success', confirmButtonColor: '#a71b1b' }).then(() => location.reload());
                        } else {
                             console.error("Server Response:", responseText);
                             Swal.fire('Server Error', 'Response could not be read.', 'error');
                        }
                    }
                },
                error: function(xhr) {
                    Swal.fire('Network Error', 'Could not connect to server.', 'error');
                }
            });
        });
    });
</script>

<?php include("components/footer.php"); ?>