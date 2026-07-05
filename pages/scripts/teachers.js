$(document).ready(function () {
  
  // --- HELPER FUNCTIONS ---
  const showAlert = (type, message) => {
    // Check if #alert element exists, if not create it dynamically (optional safety)
    if ($("#alert").length === 0) {
       // You might want to ensure an element with id="alert" exists in your HTML
       // or use SweetAlert like in previous versions. 
       // For now, I'll stick to your provided code structure.
    }
    
    $("#alert").removeClass().addClass(`alert ${type}`).text(message).show();

    setTimeout(() => {
      $("#alert").fadeOut("slow", function () {
        $(this).removeClass().text("").hide();
      });
    }, 2000);
  };

  const loadTeacher = () => {
    $.ajax({
      type: "POST",
      url: "../backend/api/web/admin.php",
      data: { requestType: "GetTeachers" },
      success: function (response) {
        try {
          // Handle response if it's already an object or a string
          let res = typeof response === 'string' ? JSON.parse(response) : response;
          
          if (res.status === "success") {
            let tbody = $("#teacher-table-tbody");
            tbody.empty();

            if (res.data && res.data.length > 0) {
              res.data.forEach((teacher) => {
                const status = teacher.is_active == 1 ? "Active" : "Inactive";
                tbody.append(`
                  <tr>
                    <td>${teacher.last_name}, ${teacher.first_name}</td> 
                    <td>${teacher.email}</td>
                    <td>${teacher.grade_level ?? "N/A"}</td>
                    <td>${status}</td>
                    <td>
                        <a href="assign_sections.php?tId=${teacher.id}" class="btn btn-sm btn-primary" style="font-size: 12px">Assign Section</a>
                    </td>
                  </tr>
                `);
              });
            } else {
               tbody.append('<tr><td colspan="5" class="text-center">No teachers found.</td></tr>');
            }
          } else {
            showAlert("alert-danger", "Failed to load teachers.");
          }
        } catch (err) {
          console.error("JSON parse error:", err);
          showAlert("alert-danger", "Invalid server response.");
        }
      },
      error: function () {
        showAlert("alert-danger", "Error fetching teacher data.");
      },
    });
  };

  // --- FIX: PASSWORD TOGGLE LOGIC ---
  $("#togglePassword").click(function () {
    const passwordInput = $("#teacher-password");
    const icon = $(this).find("i");

    if (passwordInput.attr("type") === "password") {
      passwordInput.attr("type", "text"); // Show Password
      icon.removeClass("bi-eye").addClass("bi-eye-slash");
    } else {
      passwordInput.attr("type", "password"); // Hide Password
      icon.removeClass("bi-eye-slash").addClass("bi-eye");
    }
  });

  // --- FIX: GENERATE PASSWORD LOGIC ---
  $("#generatePasswordBtn").click(function () {
    const length = 12;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
    let password = "";
    for (let i = 0, n = charset.length; i < length; ++i) {
      password += charset.charAt(Math.floor(Math.random() * n));
    }
    
    // Set value and show it immediately so user can see what was generated
    $("#teacher-password").val(password);
    $("#teacher-password").attr("type", "text");
    $("#togglePassword i").removeClass("bi-eye").addClass("bi-eye-slash");
  });

  // --- FORM SUBMISSION ---
  $("#insert-teacher-form").submit(function (e) {
    e.preventDefault();

    const first_name = $("#teacher-first-name").val().trim();
    const middle_name = $("#teacher-middle-name").val().trim();
    const last_name  = $("#teacher-last-name").val().trim();
    const email = $("#teacher-email").val().trim();
    const password = $("#teacher-password").val();

    if (!first_name || !last_name || !email || !password) {
      showAlert("alert-warning", "All fields are required.");
      return;
    }

    $.ajax({
      type: "POST",
      url: "../backend/api/web/admin.php",
      data: {
        requestType: "InsertTeacher",
        first_name,
        middle_name,
        last_name,
        email,
        password,
      },
      success: function (response) {
        try {
          let res = typeof response === 'string' ? JSON.parse(response) : response;
          
          if (res.status === "success") {
            showAlert("alert-success", res.message);
            loadTeacher();
            $("#insert-teacher-form")[0].reset();
            
            // Reset password field visibility to hidden after save
            $("#teacher-password").attr("type", "password");
            $("#togglePassword i").removeClass("bi-eye-slash").addClass("bi-eye");

            // Close Modal correctly using Bootstrap 5 API
            const modalEl = document.getElementById("insertTeacherModal");
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            } else {
                // Fallback if getInstance fails (sometimes happens if modal wasn't initialized via JS)
                $(modalEl).modal('hide'); 
            }
            
          } else {
            showAlert("alert-danger", res.message || "Insert failed.");
          }
        } catch (err) {
          console.error("JSON parse error:", err);
          showAlert("alert-danger", "Unexpected response from server.");
        }
      },
      error: function () {
        showAlert("alert-danger", "Failed to submit data.");
      },
    });
  });

  let teacherCsvValidRows = [];

  $("#btn-preview-teacher-csv").on("click", function () {
      const fileInput = $("#teacherCsvFile")[0];
      if (!fileInput.files.length) {
          showAlert("alert-warning", "Please choose a CSV file first.");
          return;
      }
      const formData = new FormData();
      formData.append("csv_file", fileInput.files[0]);
      formData.append("preview", "1");

      $.ajax({
          url: "../backend/api/web/upload-teachers.php",
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          dataType: "json",
          success: renderTeacherPreview,
          error: function () {
              showAlert("alert-danger", "Server error while previewing CSV.");
          },
      });
  });

  function renderTeacherPreview(res) {
      $("#teacher-csv-preview-panel").removeClass("d-none");
      const hasErrors = res.errors && res.errors.length > 0;
      const hasValid = res.valid && res.valid.length > 0;

      $("#teacher-csv-summary")
          .removeClass("alert-danger alert-success alert-warning")
          .addClass(hasErrors ? "alert-danger" : (hasValid ? "alert-success" : "alert-warning"))
          .html(`<strong>${res.message}</strong>`);

      if (hasErrors) {
          $("#teacher-csv-errors-block").removeClass("d-none");
          $("#teacher-csv-errors-list").html(res.errors.map(e => `<li>${e}</li>`).join(""));
      } else {
          $("#teacher-csv-errors-block").addClass("d-none");
      }

      if (res.warnings && res.warnings.length > 0) {
          $("#teacher-csv-warnings-block").removeClass("d-none");
          $("#teacher-csv-warnings-list").html(res.warnings.map(w => `<li>${w}</li>`).join(""));
      } else {
          $("#teacher-csv-warnings-block").addClass("d-none");
      }

      teacherCsvValidRows = res.valid || [];

      if (hasValid && !hasErrors) {
          $("#teacher-csv-valid-block").removeClass("d-none");
          $("#teacher-confirm-count").text(teacherCsvValidRows.length);
          $("#btn-confirm-teacher-import").removeClass("d-none");
          $("#teacher-csv-preview-tbody").html(teacherCsvValidRows.map((r, i) => `
              <tr>
                  <td>${i + 1}</td><td>${r.first_name}</td><td>${r.middle_name || ""}</td>
                  <td>${r.last_name}</td><td>${r.email}</td>
              </tr>
          `).join(""));
      } else {
          $("#teacher-csv-valid-block").addClass("d-none");
          $("#btn-confirm-teacher-import").addClass("d-none");
      }
  }

  $("#btn-confirm-teacher-import").on("click", function () {
      const fileInput = $("#teacherCsvFile")[0];
      if (!fileInput.files.length) {
          showAlert("alert-danger", "File was lost — please re-select it.");
          return;
      }
      const formData = new FormData();
      formData.append("csv_file", fileInput.files[0]);
      const btn = $(this);
      btn.prop("disabled", true).text("Importing...");

      $.ajax({
          url: "../backend/api/web/upload-teachers.php",
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          dataType: "json",
          success: function (res) {
              btn.prop("disabled", false).html('Confirm Import (<span id="teacher-confirm-count">0</span> rows)');
              if (res.status === 200) {
                  showAlert("alert-success", res.message);
                  loadTeacher();
                  $("#importTeacherModal").modal("hide");
                  $("#teacherCsvFile").val("");
                  $("#teacher-csv-preview-panel").addClass("d-none");
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

  // Initial Load
  loadTeacher();
});