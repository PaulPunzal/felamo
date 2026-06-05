$(document).ready(function () {
  const auth_user_id = $("#hidden_user_id").val();
  var notifSectionId = null;

  const showAlert = (type, message) => {
    $("#alert").removeClass().addClass(`alert ${type}`).text(message).show();

    setTimeout(() => {
      $("#alert").fadeOut("slow", function () {
        $(this).removeClass().text("").hide();
      });
    }, 2000);
  };

  const loadNotifs = () => {
    $.ajax({
      type: "POST",
      url: "../backend/api/web/notifications.php",
      data: { requestType: "GetCreatedNotification", auth_user_id },
      success: function (response) {
        let res = JSON.parse(response);

        if (res.status === "success") {
          const notifs = res.data;
          let rowsHtml = "";

          notifs.forEach((notif) => {
            rowsHtml += `
              <tr>
                <td>${notif.section_name}</td>
                <td>${notif.title}</td>
                <td>${notif.description}</td>
                <td>
                  <button class="btn btn-sm btn-info text-white btn-view-status" 
                          data-id="${notif.id}" data-section="${notif.section_id}">
                    <i class="bi bi-eye"></i> View Status
                  </button>
                </td>
              </tr>
            `;
          });

          $("#notif-table-tbody").html(rowsHtml);
        } else {
          $("#notif-table-tbody").html(
            `<tr><td colspan="5">Failed to load aralin: ${res.message}</td></tr>`
          );
        }
      },
      error: function () {
        $("#notif-table-tbody").html(
          `<tr><td colspan="5">Server error while loading aralin.</td></tr>`
        );
      },
    });
  };

  $("#notifSection").change(function (e) {
    e.preventDefault();
    notifSectionId = $(this).val();

    console.log(notifSectionId);
  });

    $("#notificationForm").on("submit", function (e) {
      e.preventDefault();

      let section_id  = notifSectionId;
      let title       = $("#notifTitle").val();
      let description = $("#notifDescription").val();

      // --- ADD: basic client-side guard ---
      if (!section_id) {
          showAlert("alert-warning", "Please select a section first.");
          return;
      }
      if (!title || !description) {
          showAlert("alert-warning", "Title and description are required.");
          return;
      }
      // --- END guard ---

      $.ajax({
          type: "POST",
          url: "../backend/api/web/notifications.php",
          data: {
              requestType: "CreateNotification",
              title,
              description,
              auth_user_id,
              section_id,
          },
          success: function (response) {
              try {
                  let res = typeof response === "string"
                      ? JSON.parse(response)
                      : response;

                  if (res.status === "success") {
                      showAlert("alert-success", res.message);
                      loadNotifs();
                      $("#notificationModal").modal("hide");
                      $("#notificationForm")[0].reset();
                      notifSectionId = null; // reset selected section

                  // --- ADD: handle duplicate ---
                  } else if (res.status === "duplicate") {
                      showAlert("alert-warning", res.message);
                      // keep modal open so teacher can edit if they want
                  // --- END ---

                  } else {
                      showAlert("alert-danger", "Insert failed: " + res.message);
                  }
              } catch (err) {
                  console.error("Invalid response:", response);
                  showAlert("alert-danger", "Unexpected error occurred.");
              }
          },
          error: function (xhr) {
              console.error(xhr.responseText);
              showAlert("alert-danger", "Request failed.");
          },
      });
  });
  loadNotifs();

  $(document).on("click", ".btn-view-status", function () {
    let notif_id = $(this).data("id");
    let section_id = $(this).data("section");

    $("#statusList").html('<li class="list-group-item text-center py-4"><div class="spinner-border text-secondary" role="status"></div></li>');
    $("#viewStatusModal").modal("show");

    $.ajax({
        type: "POST",
        url: "../backend/api/web/notifications.php",
        data: { requestType: "GetNotificationReadStatus", notification_id: notif_id, section_id: section_id },
        success: function (response) {
            let res = JSON.parse(response);
            if (res.status === "success") {
                let html = "";
                if(res.data.length === 0) {
                    html = '<li class="list-group-item text-center">No students in this section.</li>';
                } else {
                    res.data.forEach(student => {
                        let badge = student.is_read == 1 
                            ? `<span class="badge bg-success rounded-pill">Viewed</span>` 
                            : `<span class="badge bg-danger rounded-pill">Not Viewed</span>`;
                        
                        html += `
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            ${student.last_name}, ${student.first_name}
                            ${badge}
                        </li>`;
                    });
                }
                $("#statusList").html(html);
            }
        }
    });
  });
});
