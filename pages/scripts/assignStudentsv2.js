$(document).on("input", "#assign_lrn", function () {
    if (/^\d{12}$/.test($(this).val().trim())) {
        $(this).removeClass("is-invalid");
    }
});

$(document).ready(function() {
    
    // --- HELPER: SHOW ALERT ---
    const showAlert = (type, message) => {
        if ($("#alert").length) {
            $("#alert").removeClass().addClass(`alert ${type}`).text(message).show();
            setTimeout(() => {
                $("#alert").fadeOut("slow");
            }, 3000);
        } else {
            const icon = type === 'alert-success' ? 'success' : 'error';
            if (typeof Swal !== 'undefined') {
                Swal.fire({ 
                    icon: icon, 
                    title: message, 
                    confirmButtonColor: '#880f0b',
                    timer: 2000 // Auto close after 2 seconds
                });
            } else {
                alert(message);
            }
        }
    };

    // --- LOAD STUDENTS FUNCTION ---
    const loadStudents = () => {
        const sectionId = $("#hidden_section_id").val();
        
        $.ajax({
            type: "POST",
            url: "../backend/api/web/students.php",
            data: { 
                requestType: "GetStudentsBySection",
                section_id: sectionId
            },
            dataType: "json", // Force response to be JSON
            success: function(response) {
                let tbody = $("#student-table-tbody");
                tbody.empty(); // Clear current list

                // Check if response has data
                // Some backends return {status: 'success', data: [...]}
                // Others might return just the array [...]
                const students = response.data || response;

                if (Array.isArray(students) && students.length > 0) {
                    students.forEach(student => {
                        // Handle null values gracefully
                        const email = student.email || 'N/A';
                        const contact = student.contact_no || 'N/A';
                        const middle = student.middle_name || '';
                        
                        tbody.append(`
                            <tr>
                                <td>${student.lrn}</td>
                                <td>${email}</td>
                                <td>${contact}</td>
                                <td>${student.first_name}</td>
                                <td>${middle}</td>
                                <td>${student.last_name}</td>
                                <td>${student.birth_date}</td>
                                <td>${student.gender}</td>
                            </tr>
                        `);
                    });
                } else {
                    tbody.append('<tr><td colspan="8" class="text-center text-muted">No students assigned to this section yet.</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Load Error:", error);
            }
        });
    };

    // --- SUBMIT FORM ---
    $("#assign-student-form").submit(function(e) {
    e.preventDefault(); // Stop page reload

    const lrn = $("#assign_lrn").val().trim();

    if (!/^\d{12}$/.test(lrn)) {
        showAlert("alert-danger", "LRN must be exactly 12 digits.");
        $("#assign_lrn").addClass("is-invalid").focus();
        return;
    }
    $("#assign_lrn").removeClass("is-invalid");

    const formData = new FormData(this);
        
        formData.append("requestType", "InsertStudent");
        formData.append("section_id", $("#hidden_section_id").val());
        formData.append("password", lrn); // Default password

        $.ajax({
            type: "POST",
            url: "../backend/api/web/students.php",
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json", // Expect JSON response
            success: function(response) {
                if (response.status === "success" || response.status === 200) {
                    
                    // 1. Show Success Message
                    showAlert("alert-success", "Student added successfully!");
                    
                    // 2. Close Modal
                    $("#insertStudentModal").modal('hide');
                    
                    // 3. Reset Form
                    $("#assign-student-form")[0].reset();
                    
                    // 4. RELOAD THE TABLE IMMEDIATELY
                    loadStudents(); 

                } else {
                    showAlert("alert-danger", response.message || "Failed to add student.");
                }
            },
            error: function(xhr, status, error) {
                console.error("Submit Error:", xhr.responseText);
                showAlert("alert-danger", "Error saving data. Check console.");
            }
        });
    });

    let studentCsvValidRows = [];

    $("#btn-preview-student-csv").on("click", function () {
        const fileInput = $("#studentCsvFile")[0];
        if (!fileInput.files.length) {
            showAlert("alert-warning", "Please choose a CSV file first.");
            return;
        }
        const formData = new FormData();
        formData.append("csv_file", fileInput.files[0]);
        formData.append("section_id", $("#hidden_section_id").val());
        formData.append("preview", "1");

        $.ajax({
            url: "../backend/api/web/upload-students.php",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: renderStudentPreview,
            error: function () {
                showAlert("alert-danger", "Server error while previewing CSV.");
            },
        });
    });

    function renderStudentPreview(res) {
        $("#student-csv-preview-panel").removeClass("d-none");
        const hasErrors = res.errors && res.errors.length > 0;
        const hasValid = res.valid && res.valid.length > 0;

        $("#student-csv-summary")
            .removeClass("alert-danger alert-success alert-warning")
            .addClass(hasErrors ? "alert-danger" : (hasValid ? "alert-success" : "alert-warning"))
            .html(`<strong>${res.message}</strong>`);

        if (hasErrors) {
            $("#student-csv-errors-block").removeClass("d-none");
            $("#student-csv-errors-list").html(res.errors.map(e => `<li>${e}</li>`).join(""));
        } else {
            $("#student-csv-errors-block").addClass("d-none");
        }

        if (res.warnings && res.warnings.length > 0) {
            $("#student-csv-warnings-block").removeClass("d-none");
            $("#student-csv-warnings-list").html(res.warnings.map(w => `<li>${w}</li>`).join(""));
        } else {
            $("#student-csv-warnings-block").addClass("d-none");
        }

        studentCsvValidRows = res.valid || [];

        if (hasValid && !hasErrors) {
            $("#student-csv-valid-block").removeClass("d-none");
            $("#student-confirm-count").text(studentCsvValidRows.length);
            $("#btn-confirm-student-import").removeClass("d-none");
            $("#student-csv-preview-tbody").html(studentCsvValidRows.map((r, i) => `
                <tr>
                    <td>${i + 1}</td><td>${r.lrn}</td><td>${r.first_name}</td><td>${r.middle_name || ""}</td>
                    <td>${r.last_name}</td><td>${r.gender}</td><td>${r.email}</td><td>${r.contact_no}</td>
                </tr>
            `).join(""));
        } else {
            $("#student-csv-valid-block").addClass("d-none");
            $("#btn-confirm-student-import").addClass("d-none");
        }
    }

    $("#btn-confirm-student-import").on("click", function () {
        const fileInput = $("#studentCsvFile")[0];
        if (!fileInput.files.length) {
            showAlert("alert-danger", "File was lost — please re-select it.");
            return;
        }
        const formData = new FormData();
        formData.append("csv_file", fileInput.files[0]);
        formData.append("section_id", $("#hidden_section_id").val());
        const btn = $(this);
        btn.prop("disabled", true).text("Importing...");

        $.ajax({
            url: "../backend/api/web/upload-students.php",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: function (res) {
                btn.prop("disabled", false).html('Confirm Import (<span id="student-confirm-count">0</span> rows)');
                if (res.status === 200) {
                    showAlert("alert-success", res.message);
                    loadStudents();
                    $("#importStudentModal").modal("hide");
                    $("#studentCsvFile").val("");
                    $("#student-csv-preview-panel").addClass("d-none");
                    btn.addClass("d-none");
                } else {
                    showAlert("alert-danger", res.message);
                }
            },
            error: function () {
                btn.prop("disabled", false);
                showAlert("alert-danger", "Server error during import.");
            },
        });
    });

    // Initial Load when page opens
    loadStudents();
});