// R2 (Cloudflare) video upload configuration
// Videos are uploaded directly from the browser to R2 using presigned
// multipart upload URLs brokered by backend/api/web/r2_video_upload.php.
// The video bytes never pass through our PHP server.
const R2_UPLOAD_ENDPOINT = "../backend/api/web/r2_video_upload.php";
const R2_PART_SIZE = 20 * 1024 * 1024; // 20MB per part (S3/R2 minimum is 5MB, except the final part)

// Global flag to prevent user from leaving the page during upload
let isUploadingToCloud = false;

window.addEventListener('beforeunload', function (e) {
    if (isUploadingToCloud) {
        e.preventDefault();
        e.returnValue = 'A video is currently uploading. If you leave now, the upload will be cancelled.';
    }
});

/**
 * Uploads a video file directly to Cloudflare R2 using the S3 multipart
 * upload API, with progress tracking, and resolves with the same shape
 * the old Cloudinary helper used ({ secure_url }) so the rest of the
 * form-submit logic doesn't need to change.
 */
const uploadToR2WithProgress = (file, modalElement) => {
    return new Promise((resolve, reject) => {
        const progressBar = modalElement.find('.upload-progress-bar');
        const progressText = modalElement.find('.upload-status-text');
        const progressContainer = modalElement.find('.upload-progress-container');

        // Show progress UI
        progressContainer.slideDown();
        progressBar.css('width', '0%').text('0%').attr('aria-valuenow', 0);
        progressText.text('Preparing upload...');

        // --- Step 1: Ask our backend to open a multipart upload session on R2 ---
        $.ajax({
            type: "POST",
            url: R2_UPLOAD_ENDPOINT,
            data: {
                requestType: "InitiateMultipartUpload",
                filename: file.name,
                content_type: file.type || "video/mp4",
            },
            dataType: "json",
        }).done(function (initRes) {
            if (!initRes || initRes.status !== "success") {
                reject(new Error((initRes && initRes.message) || "Failed to initiate upload."));
                return;
            }

            const key = initRes.key;
            const uploadId = initRes.upload_id;
            const totalChunks = Math.max(1, Math.ceil(file.size / R2_PART_SIZE));
            const uploadedParts = [];
            let currentPart = 1;
            let bytesUploaded = 0;

            progressText.text('Uploading Video to Cloudflare R2...');

            const abortUpload = (err) => {
                // Best-effort cleanup of the half-finished session; ignore failures
                $.post(R2_UPLOAD_ENDPOINT, {
                    requestType: "AbortMultipartUpload",
                    key: key,
                    upload_id: uploadId,
                });
                reject(err);
            };

            // Recursive function to upload parts one by one
            const uploadNextPart = () => {
                const start = (currentPart - 1) * R2_PART_SIZE;
                const end = Math.min(start + R2_PART_SIZE, file.size);
                const chunk = file.slice(start, end);

                // --- Step 2: Get a presigned PUT URL for this specific part ---
                $.ajax({
                    type: "POST",
                    url: R2_UPLOAD_ENDPOINT,
                    data: {
                        requestType: "GetUploadPartUrl",
                        key: key,
                        upload_id: uploadId,
                        part_number: currentPart,
                    },
                    dataType: "json",
                }).done(function (partRes) {
                    if (!partRes || partRes.status !== "success") {
                        abortUpload(new Error((partRes && partRes.message) || "Failed to get upload URL for part " + currentPart));
                        return;
                    }

                    // --- Step 3: PUT the chunk directly to R2 (bypasses our server) ---
                    const xhr = new XMLHttpRequest();
                    xhr.open('PUT', partRes.url);

                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            const totalLoaded = bytesUploaded + e.loaded;
                            const percent = Math.round((totalLoaded / file.size) * 100);

                            progressBar.css('width', percent + '%').text(percent + '%').attr('aria-valuenow', percent);

                            if (percent >= 100) {
                                progressText.text('Processing video... please wait.');
                            }
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            const etag = xhr.getResponseHeader('ETag');                        
                            if (!etag) {
                                abortUpload(new Error(
                                    "Upload succeeded but no ETag was returned. " +
                                    "Make sure the R2 bucket CORS policy includes 'ETag' in ExposeHeaders."
                                ));
                            }

                            uploadedParts.push({ PartNumber: currentPart, ETag: etag });
                            bytesUploaded += chunk.size;

                            if (currentPart < totalChunks) {
                                currentPart++;
                                uploadNextPart();
                            } else {
                                // --- Step 4: All parts uploaded — finalize on R2 ---
                                progressText.text('Finalizing upload...');

                                $.ajax({
                                    type: "POST",
                                    url: R2_UPLOAD_ENDPOINT,
                                    data: {
                                        requestType: "CompleteMultipartUpload",
                                        key: key,
                                        upload_id: uploadId,
                                        parts: JSON.stringify(uploadedParts),
                                    },
                                    dataType: "json",
                                }).done(function (completeRes) {
                                    if (completeRes && completeRes.status === "success") {
                                        progressText.text('Upload Complete! Saving to database...');
                                        progressBar.removeClass('bg-primary').addClass('bg-success');
                                        resolve({ secure_url: completeRes.url });
                                    } else {
                                        reject(new Error((completeRes && completeRes.message) || "Failed to finalize upload."));
                                    }
                                }).fail(() => {
                                    reject(new Error("Network error while finalizing the upload."));
                                });
                            }
                        } else {
                            abortUpload(new Error("Upload failed for part " + currentPart + " (HTTP " + xhr.status + ")"));
                        }
                    };

                    xhr.onerror = () => abortUpload(new Error("Network error while uploading part " + currentPart));

                    xhr.send(chunk);
                }).fail(() => {
                    abortUpload(new Error("Network error while requesting an upload URL for part " + currentPart));
                });
            };

            // Start the upload process
            uploadNextPart();

        }).fail((xhr) => {
            let msg = "Network error while initiating the upload.";
            try {
                const res = JSON.parse(xhr.responseText);
                if (res && res.message) msg = res.message;
            } catch (e) {}
            reject(new Error(msg));
        });
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

    // --- CREATE ARALIN (WITH CLOUDFLARE R2) ---
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
            // 1. Upload to Cloudflare R2 with Progress
            const uploadData = await uploadToR2WithProgress(file, modalElement);
            const secureVideoUrl = uploadData.secure_url;

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

    // --- SUBMIT EDIT FORM (WITH CLOUDFLARE R2) ---
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
            // Only upload to R2 if the admin selected a NEW video
            if (file) {
                isUploadingToCloud = true; // Lock page
                const uploadData = await uploadToR2WithProgress(file, modalElement);
                backendFormData.delete('attachment'); 
                backendFormData.append('video_url', uploadData.secure_url);
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
