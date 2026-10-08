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

    $("#forgot-password-error").addClass("d-none");
    $btn.prop("disabled", true).text("Sending...");

    $.ajax({
      type: "POST",
      url: "backend/api/web/auth.php",
      data: { requestType: "SendOTP", email: email },
      dataType: "json",
      success: function (res) {
        if (res.status === "success") {
          window.location.href = "login-using-otp.php?email=" + encodeURIComponent(email);
        } else {
          $("#forgot-password-error")
            .text(res.message || "Something went wrong.")
            .removeClass("d-none");
          $btn.prop("disabled", false).text("Send OTP");
        }
      },
      error: function (xhr) {
        console.error("SendOTP error:", xhr.responseText);
        $("#forgot-password-error")
          .text("Server error. Please try again.")
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