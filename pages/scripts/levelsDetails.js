// Cloudinary configuration
const CLOUD_NAME = 'dlqj1rhuq';
const UPLOAD_PRESET = 'felamo_videos'; 

// Global flag to prevent user from leaving the page during upload
let isUploadingToCloud = false;

window.addEventListener('beforeunload', function (e) {
    if (isUploadingToCloud) {
        e.preventDefault();
        e.returnValue = 'A video is currently uploading. If you leave now, the upload will be cancelled.';
    }
});

/**
 * Helper function to upload to Cloudinary with Chunking & Progress Tracking
 * Bypasses the 100MB limit by splitting the file into smaller pieces.
 */
const uploadToCloudinaryWithProgress = (file, modalElement) => {
    return new Promise((resolve, reject) => {
        // We will slice the video into 20MB chunks
        const chunkSize = 20 * 1024 * 1024; 
        const totalChunks = Math.ceil(file.size / chunkSize);
        
        // Generate a unique ID for this specific file upload session
        const uploadId = "upload_" + Date.now() + "_" + Math.random().toString(36).substring(2);
        let currentChunk = 0;

        const progressBar = modalElement.find('.upload-progress-bar');
        const progressText = modalElement.find('.upload-status-text');
        const progressContainer = modalElement.find('.upload-progress-container');

        // Show progress UI
        progressContainer.slideDown();
        progressBar.css('width', '0%').text('0%').attr('aria-valuenow', 0);
        progressText.text('Uploading Video to Cloudinary...');

        // Recursive function to upload chunks one by one
        const uploadNextChunk = () => {
            const start = currentChunk * chunkSize;
            const end = Math.min(start + chunkSize, file.size);
            const chunk = file.slice(start, end);

            const cloudFormData = new FormData();
            cloudFormData.append('file', chunk);
            cloudFormData.append('upload_preset', UPLOAD_PRESET);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', `https://api.cloudinary.com/v1_1/${CLOUD_NAME}/video/upload`);
            
            // Cloudinary headers required for chunked uploads
            xhr.setRequestHeader('X-Unique-Upload-Id', uploadId);
            xhr.setRequestHeader('Content-Range', `bytes ${start}-${end - 1}/${file.size}`);

            // Track Progress
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const chunkLoaded = e.loaded;
                    const totalLoaded = start + chunkLoaded;
                    const percent = Math.round((totalLoaded / file.size) * 100);
                    
                    progressBar.css('width', percent + '%').text(percent + '%').attr('aria-valuenow', percent);
                    
                    if (percent === 100) {
                        progressText.text('Processing video... please wait.');
                        progressBar.removeClass('progress-bar-animated');
                    }
                }
            };

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    currentChunk++;
                    
                    if (currentChunk < totalChunks) {
                        // If there are more chunks, upload the next one
                        uploadNextChunk();
                    } else {
                        // All chunks are done! Cloudinary returns the final URL.
                        progressText.text('Upload Complete! Saving to database...');
                        progressBar.removeClass('bg-primary').addClass('bg-success');
                        resolve(JSON.parse(xhr.responseText));
                    }
                } else {
                    reject(new Error(JSON.parse(xhr.responseText).error?.message || 'Upload failed'));
                }
            };

            xhr.onerror = () => reject(new Error('Network error during upload'));
            
            xhr.send(cloudFormData);
        };

        // Start the upload process
        uploadNextChunk(); 
    });
};


$(document).ready(function () {
    const level_id = $("#hidden_level_id").val();

    // --- LOAD ARALINS (TABLE VIEW) ---
    const loadAralins = () => {
        $.ajax({
            type: "POST",
            url: "../backend/api/web/aralin.php",
            data: {
                requestType: "GetAralin",
                level_id: level_id,
            },
            success: function (response) {
                let res = JSON.parse(response);
                if (res.status === "success") {
                    let aralins = res.data;
                    let html = "";

                    if (aralins.length === 0) {
                        html = `
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                    No lessons found. Click "New Aralin" to add one.
                                </td>
                            </tr>`;
                    } else {
                        aralins.forEach((aralin, index) => {
                            let lessonNum = index + 1;
                            let summary = aralin.summary || "";
                            if (summary.length > 100) summary = summary.substring(0, 100) + "...";

                            let videoFile = aralin.attachment_filename ? aralin.attachment_filename : "";
                            
                            let previewUrl = "#";
                            if (videoFile) {
                                previewUrl = videoFile.startsWith('http') ? videoFile : '../backend/storage/videos/' + videoFile;
                            }

                            html += `
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px; color: #a71b1b; font-weight: bold;">
                                                ${lessonNum}
                                            </div>
                                            <span class="text-secondary fw-bold">Aralin ${lessonNum}</span>
                                        </div>
                                    </td>
                                    <td>
                                       <div class="aralin-title">${aralin.aralin_title}</div>
                                    </td>
                                    <td>
                                        <div class="aralin-summary">${summary}</div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="dropdown">
                                            <button class="btn-action-red dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Action
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                                <li>
                                                    <a class="dropdown-item edit-aralin-btn" href="#" 
                                                    data-id="${aralin.id}" 
                                                    data-title="${aralin.aralin_title}" 
                                                    data-summary="${aralin.summary}" 
                                                    data-details="${aralin.details}" 
                                                    data-attachment="${videoFile}">
                                                    <i class="bi bi-pencil-square me-2"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-success fw-bold" href="create_assessment.php?aralin=${aralin.id}">
                                                        <i class="bi bi-card-checklist me-2"></i> Manage Assessment
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-info fw-bold item-analysis-btn"
                                                        href="#" data-aralin-id="${aralin.id}">
                                                         <i class="bi bi-bar-chart-line-fill me-2"></i> Item Analysis
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-primary" href="${previewUrl}" target="_blank" ${!videoFile ? 'style="pointer-events: none; opacity: 0.5;"' : ''}>
                                                        <i class="bi bi-play-circle me-2"></i> Preview Video
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger delete-aralin-btn" href="#" data-id="${aralin.id}">
                                                        <i class="bi bi-trash me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                    }

                    $("#antas-table-body").html(html);
                } else {
                    $("#antas-table-body").html('<tr><td colspan="4" class="text-center text-danger">Failed to load data.</td></tr>');
                }
            },
            error: function () {
                $("#antas-table-body").html('<tr><td colspan="4" class="text-center text-danger">Server error.</td></tr>');
            },
        });
    };

    loadAralins();

    // --- RESET UI HELPER ---
    const resetProgressUI = (modalElement) => {
        modalElement.find('.upload-progress-container').hide();
        modalElement.find('.upload-progress-bar')
            .removeClass('bg-success').addClass('bg-primary progress-bar-animated')
            .css('width', '0%').text('0%');
    };

    // --- CREATE ARALIN (WITH CLOUDINARY) ---
    $("#insert-aralin-form").submit(async function (e) {
        e.preventDefault();
        
        let form = $(this);
        let modalElement = $("#insertAralinModal");
        let submitBtn = form.find('button[type="submit"]');
        let originalText = submitBtn.text();
        
        let fileInput = form.find('input[name="attachment"]')[0];
        let file = fileInput.files[0];
        
        if (!file) {
            alert("Please select a video file first.");
            return;
        }

        submitBtn.prop("disabled", true);
        isUploadingToCloud = true; // Lock the page

        try {
            // 1. Upload to Cloudinary with Progress
            const cloudData = await uploadToCloudinaryWithProgress(file, modalElement);
            const secureVideoUrl = cloudData.secure_url;

            // 2. Send Data + Video URL to PHP Backend
            submitBtn.text("Saving Lesson Data...");
            let backendFormData = new FormData(this);
            
            backendFormData.delete('attachment'); 
            backendFormData.append('video_url', secureVideoUrl); 

            $.ajax({
                type: "POST",
                url: "../backend/api/web/aralin.php",
                data: backendFormData,
                contentType: false,
                processData: false,
                success: function (response) {
                    isUploadingToCloud = false; // Unlock page
                    submitBtn.text(originalText).prop("disabled", false);
                    try {
                        let res = JSON.parse(response);
                        if (res.status === "success") {
                            alert("Lesson successfully saved!");
                            modalElement.modal("hide");
                            form[0].reset();
                            resetProgressUI(modalElement);
                            loadAralins();
                        } else {
                            alert("Failed: " + res.message);
                        }
                    } catch (err) {
                        alert("Server returned invalid data.");
                    }
                },
                error: function () {
                    isUploadingToCloud = false;
                    submitBtn.text(originalText).prop("disabled", false);
                    alert("Server Error. Please try again.");
                },
            });

        } catch (error) {
            isUploadingToCloud = false;
            console.error("Error:", error);
            alert('Upload failed: ' + error.message);
            submitBtn.text(originalText).prop("disabled", false);
            resetProgressUI(modalElement);
        }
    });

    // --- OPEN EDIT MODAL ---
    $(document).on("click", ".edit-aralin-btn", function (e) {
        e.preventDefault();
        
        let id = $(this).data("id");
        let title = $(this).data("title");
        let summary = $(this).data("summary");
        let details = $(this).data("details");
        let attachment = $(this).data("attachment");

        $("#edit-aralin-id").val(id);
        $("#edit-aralin-title").val(title);
        $("#edit-aralin-summary").val(summary);
        $("#edit-aralin-details").val(details);

        if (attachment && attachment !== "undefined" && attachment !== "null" && attachment.trim() !== "") {
            let previewUrl = attachment.startsWith('http') ? attachment : "../backend/storage/videos/" + attachment;
            $("#current-video-link").attr("href", previewUrl);
            $("#current-video-link").show();
            $("#current-video-text").text("Video is currently attached");
        } else {
            $("#current-video-link").hide();
            $("#current-video-text").text("No video attached.");
        }

        resetProgressUI($("#editAralinModal"));
        $("#editAralinModal").modal("show");
    });

    // --- SUBMIT EDIT FORM (WITH CLOUDINARY) ---
    $("#edit-aralin-form").submit(async function (e) {
        e.preventDefault();
        
        let form = $(this);
        let modalElement = $("#editAralinModal");
        let submitBtn = form.find('button[type="submit"]');
        let originalText = submitBtn.text();
        submitBtn.prop("disabled", true);

        let backendFormData = new FormData(this);
        let fileInput = form.find('input[name="attachment"]')[0];
        let file = fileInput.files[0];

        try {
            // Only upload to Cloudinary if the admin selected a NEW video
            if (file) {
                isUploadingToCloud = true; // Lock page
                const cloudData = await uploadToCloudinaryWithProgress(file, modalElement);
                backendFormData.delete('attachment'); 
                backendFormData.append('video_url', cloudData.secure_url);
            } else {
                backendFormData.delete('attachment'); 
            }

            submitBtn.text("Updating Data...");
            $.ajax({
                type: "POST",
                url: "../backend/api/web/aralin.php",
                data: backendFormData,
                contentType: false,
                processData: false,
                success: function (response) {
                    isUploadingToCloud = false; // Unlock
                    submitBtn.text(originalText).prop("disabled", false);
                    let res = JSON.parse(response);
                    if (res.status === "success") {
                        alert(res.message);
                        modalElement.modal("hide");
                        form[0].reset();
                        resetProgressUI(modalElement);
                        loadAralins();
                    } else {
                        alert(res.message);
                    }
                },
                error: function () {
                    isUploadingToCloud = false;
                    submitBtn.text(originalText).prop("disabled", false);
                    alert("An error occurred updating the database.");
                },
            });
        } catch (error) {
            isUploadingToCloud = false;
            console.error("Error:", error);
            alert('An error occurred: ' + error.message);
            submitBtn.text(originalText).prop("disabled", false);
            resetProgressUI(modalElement);
        }
    });

    // --- DELETE HANDLER ---
    $(document).on("click", ".delete-aralin-btn", function(e) {
        e.preventDefault();
        if(confirm("Are you sure you want to delete this lesson?")) {
            alert("Delete functionality coming soon.");
        }
    }); 
});

// Item Analysis button
$(document).on('click', '.item-analysis-btn', function (e) {
    e.preventDefault();
    const aralinId = $(this).data('aralin-id');
 
    $.ajax({
        type    : 'POST',
        url     : '../backend/api/web/asssessments.php',
        data    : { requestType: 'GetAssessment', aralin_id: aralinId },
        dataType: 'json',
        success : function (res) {
            if (res.status === 'success' && res.data && res.data.length > 0) {
                const assessmentId = res.data[0].id;
                window.location.href = 'item_analysis.php?assessment_id=' + assessmentId;
            } else {
                alert('Walang assessment na nahanap para sa araling ito.\\nMangyaring gumawa muna ng assessment sa "Manage Assessment".');
            }
        },
        error : function () {
            alert('Hindi ma-load ang assessment. Subukan muli.');
        },
    });
});