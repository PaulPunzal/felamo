$(document).ready(function () {
  // ── Login ─────────────────────────────────────────────────────
  $("#LoginForm").submit(function (e) {
    e.preventDefault();
    e.stopPropagation();

    $("#login-error").addClass("d-none");
    const $btn = $(this).find('button[type="submit"]');
    $btn.prop("disabled", true);

    let email = $("#email").val();
    let password = $("#password").val();

    $.ajax({
      type: "POST",
      url: "backend/api/web/auth.php",
      data: { requestType: "Login", email: email, password: password },
      dataType: "json",
      success: function (response) {
        let res = typeof response === "string" ? JSON.parse(response) : response;

        if (res.status === "success") {
          window.location.href = "pages/home.php";
        } else {
          $("#login-error").text(res.message || "Invalid credentials").removeClass("d-none");
          $btn.prop("disabled", false);
        }
      },
      error: function (xhr) {
        console.error("AJAX Error:", xhr.responseText);

        // Sometimes the login worked but stray output broke JSON parsing
        try {
          let cleanResponse = JSON.parse(xhr.responseText);
          if (cleanResponse.status === "success") {
            window.location.href = "pages/home.php";
            return;
          }
        } catch (err) {}

        $("#login-error").text("System Error. Check console for details.").removeClass("d-none");
        $btn.prop("disabled", false);
      },
    });

    return false;
  });

  // ── Forgot password: send OTP ─────────────────────────────────
  $("#ForgotPasswordForm").submit(function (e) {
    e.preventDefault();

    const email = $("#email").val().trim();
    const $btn = $(this).find('button[type="submit"]');
    const $err = $("#forgot-password-error");

    $err.addClass("d-none");
    $btn.prop("disabled", true).text("Sending...");

    // Safely read the first JSON object from the response
    const parseFirstJson = (text) => {
      try { return JSON.parse(text); } catch (e) {}
      const match = (text || "").match(/^\s*\{[^{}]*\}/);
      try { return match ? JSON.parse(match[0]) : null; } catch (e) { return null; }
    };

    $.ajax({
      type: "POST",
      url: "backend/api/web/auth.php",
      data: { requestType: "SendOTP", email: email },
      dataType: "text",               // we parse it ourselves
      complete: function (xhr) {
        const res = parseFirstJson(xhr.responseText);

        if (res && res.status === "success") {
          window.location.href = "login-using-otp.php?email=" + encodeURIComponent(email);
          return;
        }

        $err
          .text(res && res.message ? res.message : "Server error. Please try again.")
          .removeClass("d-none");
        $btn.prop("disabled", false).text("Send OTP");
      },
    });
  });

  // ── Login using OTP ───────────────────────────────────────────
  $("#LoginUsingOtpForm").submit(function (e) {
    e.preventDefault();

    const email = $("#email").val();
    const otp = $("#otp").val().trim();
    const $btn = $(this).find('button[type="submit"]');

    $("#login-using-otp-error").addClass("d-none");
    $btn.prop("disabled", true);

    $.ajax({
      type: "POST",
      url: "backend/api/web/auth.php",
      data: { requestType: "LoginUsingOtp", email: email, otp: otp },
      dataType: "json",
      success: function (res) {
        if (res.status === "success") {
          window.location.href = "pages/home.php";
        } else {
          $("#login-using-otp-error")
            .text(res.message || "Invalid OTP!")
            .removeClass("d-none");
          $btn.prop("disabled", false);
        }
      },
      error: function (xhr) {
        console.error("LoginUsingOtp error:", xhr.responseText);
        $("#login-using-otp-error")
          .text("Server error. Please try again.")
          .removeClass("d-none");
        $btn.prop("disabled", false);
      },
    });
  });
});