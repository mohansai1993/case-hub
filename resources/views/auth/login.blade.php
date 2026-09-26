@extends('layouts.auth')

@section('title', 'CaseHub Login')

@section('content')
<main class="page">
  <section class="login-card">

    <!-- LEFT IMAGE PANEL -->
    <div class="visual-panel">
      <img
        class="hero-image"
        src="{{ asset('assets/admin/images/loginscreen.png') }}"
        alt="Law and justice"
      />

      <div class="image-overlay"></div>

      <div class="brand">
        <img class="brand-logo" src="{{ asset('assets/admin/images/logo-light.svg') }}" alt="CaseHub" />
      </div>

      <div class="visual-copy">
        <h1>Smarter case management<br />for a strong legal tomorrow</h1>
        <p>
          Streamline your legal operations, manage cases, and keep everything organized- all in one place.
        </p>
      </div>
    </div>

    <!-- RIGHT LOGIN PANEL -->
    <div class="form-panel">
      <div class="form-content">
        <div class="mobile-brand">
          <img class="brand-logo" src="{{ asset('assets/admin/images/logo-light.svg') }}" alt="CaseHub" />
        </div>

        <!-- LOGIN -->
        <div class="view is-active" id="view-login">
          <h2>Welcome Back</h2>
          <p class="subtitle">Login to access your CaseHub Admin Dashboard</p>
          <p class="success-msg" id="login-success" hidden>Password updated. Please login with your new password.</p>

          <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
              <label for="email">Email or Mobile Number</label>
              <input
                id="email"
                name="identifier"
                type="text"
                value="{{ old('identifier') }}"
                placeholder="name@company.com"
                autocomplete="username"
                maxlength="191"
                required
                autofocus
              />
            </div>

            <div class="field password-field">
              <div class="label-row">
                <label for="password">PASSWORD</label>
              </div>
              <div class="password-wrap">
                <input
                  id="password"
                  name="password"
                  type="password"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  maxlength="255"
                  required
                />
                <button type="button" class="eye-btn" aria-label="Show password"></button>
              </div>
            </div>

            @if ($errors->has('identifier') || $errors->has('password'))
              <p class="error-msg is-visible" role="alert">{{ $errors->first('identifier') ?: $errors->first('password') }}</p>
            @endif

            <div class="options">
              <label class="remember">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) />
                <span>Remember me</span>
              </label>
              <a href="#" class="forgot" data-go="view-forgot">Forgot password?</a>
            </div>

            <button type="submit" class="login-btn">Login</button>
          </form>
        </div>

        <!-- FORGOT PASSWORD -->
        <div class="view" id="view-forgot">
          <h2>Forgot Password</h2>
          <p class="subtitle">Enter your registered email or mobile number to receive an OTP.</p>

          <form id="forgot-form" novalidate>
            <div class="field">
              <label for="fp-contact">Email or Mobile Number</label>
              <input
                id="fp-contact"
                type="text"
                placeholder="Enter your email or mobile"
              />
            </div>
            <p class="error-msg" id="fp-contact-error"></p>

            <button type="button" class="login-btn" id="send-otp-btn">Send OTP</button>

            <div class="otp-section" id="otp-section" hidden>
              <div class="field">
                <label>Enter 6 digit OTP</label>
                <div class="otp-inputs">
                  <input type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 1" />
                  <input type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 2" />
                  <input type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 3" />
                  <input type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 4" />
                  <input type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 5" />
                  <input type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 6" />
                </div>
              </div>

              <div class="otp-meta">
                <span class="otp-timer" id="otp-timer">Resend OTP in 01:00</span>
                <button type="button" class="link-btn" id="resend-btn" disabled>Resend OTP</button>
              </div>
              <p class="error-msg" id="otp-error"></p>

              <button type="submit" class="login-btn">Submit</button>
            </div>
          </form>

          <a href="#" class="back-link" data-go="view-login">← Back to login</a>
        </div>

        <!-- CREATE NEW PASSWORD -->
        <div class="view" id="view-reset">
          <h2>Create New Password</h2>
          <p class="subtitle">Use at least 8 characters, with upper and lower case letters and a number.</p>

          <form id="reset-form" novalidate>
            <div class="field">
              <label for="new-password">NEW PASSWORD</label>
              <div class="password-wrap">
                <input id="new-password" type="password" placeholder="••••••••" autocomplete="new-password" maxlength="255" />
                <button type="button" class="eye-btn" aria-label="Show password"></button>
              </div>
            </div>

            <div class="field password-field">
              <label for="confirm-password">CONFIRM PASSWORD</label>
              <div class="password-wrap">
                <input id="confirm-password" type="password" placeholder="••••••••" autocomplete="new-password" maxlength="255" />
                <button type="button" class="eye-btn" aria-label="Show password"></button>
              </div>
            </div>
            <p class="error-msg" id="reset-error"></p>

            <button type="submit" class="login-btn">Create</button>
          </form>

          <a href="#" class="back-link" data-go="view-login">← Back to login</a>
        </div>
      </div>

      <div class="watermark" aria-hidden="true">
        <img src="{{ asset('assets/admin/images/bottom.png') }}" alt="" />
      </div>
    </div>

  </section>
</main>
@endsection

@push('scripts')
<script>
  // Eye icon: show / hide password (works for every password box)
  const eyeIcons = `
    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>
    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.9 3.9M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7c1.9 0 3.6-.6 5-1.5"/>
      <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
      <path d="M2 2l20 20"/>
    </svg>`;

  document.querySelectorAll(".eye-btn").forEach((btn) => {
    btn.innerHTML = eyeIcons;
    const input = btn.parentElement.querySelector("input");
    btn.addEventListener("click", () => {
      const isPassword = input.type === "password";
      input.type = isPassword ? "text" : "password";
      btn.classList.toggle("is-visible", isPassword);
      btn.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
    });
  });

  // Screen switching
  function showView(id) {
    document.querySelectorAll(".view").forEach((v) => {
      v.classList.toggle("is-active", v.id === id);
    });
    if (id === "view-forgot") resetForgot();
    if (id !== "view-login") loginSuccess.hidden = true;
  }

  document.querySelectorAll("[data-go]").forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      showView(link.dataset.go);
    });
  });

  // ---- API (forgot password) ------------------------------------------------
  const API = {
    sendOtp: "{{ route('password.otp.send') }}",
    verifyOtp: "{{ route('password.otp.verify') }}",
    reset: "{{ route('password.reset') }}",
  };
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const GENERIC_ERROR = "Something went wrong. Please try again.";

  async function post(url, data) {
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": CSRF,
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify(data),
      });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch (e) {
      return { ok: false, status: 0, body: {} };
    }
  }

  function errorText(result) {
    if (result.status === 429) return "Too many requests. Please wait a moment and try again.";
    if (result.status === 419) return "Your session expired. Please refresh the page and try again.";
    if (result.body && result.body.errors) return Object.values(result.body.errors)[0][0];
    return (result.body && result.body.message) || GENERIC_ERROR;
  }

  // ---- Forgot password + OTP ------------------------------------------------
  const loginSuccess = document.getElementById("login-success");
  const contactInput = document.getElementById("fp-contact");
  const contactError = document.getElementById("fp-contact-error");
  const sendOtpBtn = document.getElementById("send-otp-btn");
  const otpSection = document.getElementById("otp-section");
  const otpInputs = [...document.querySelectorAll(".otp-inputs input")];
  const otpTimer = document.getElementById("otp-timer");
  const resendBtn = document.getElementById("resend-btn");
  const otpError = document.getElementById("otp-error");
  let timerId = null;
  let resetToken = null;

  function startTimer(seconds) {
    let left = seconds;
    clearInterval(timerId);
    resendBtn.disabled = true;
    otpTimer.hidden = false;

    const tick = () => {
      const mm = String(Math.floor(left / 60)).padStart(2, "0");
      const ss = String(left % 60).padStart(2, "0");
      otpTimer.textContent = `Resend OTP in ${mm}:${ss}`;
      if (left <= 0) {
        clearInterval(timerId);
        otpTimer.hidden = true;
        resendBtn.disabled = false;
      }
      left--;
    };
    tick();
    timerId = setInterval(tick, 1000);
  }

  async function sendOtp() {
    const value = contactInput.value.trim();
    if (!value) {
      contactError.textContent = "Please enter a valid email or 10 digit mobile number.";
      return;
    }

    contactError.textContent = "";
    sendOtpBtn.disabled = true;
    resendBtn.disabled = true;

    const result = await post(API.sendOtp, { identifier: value });
    sendOtpBtn.disabled = false;

    if (!result.ok) {
      contactError.textContent = errorText(result);
      if (!otpSection.hidden) resendBtn.disabled = false;
      return;
    }

    contactInput.readOnly = true;
    sendOtpBtn.hidden = true;
    otpSection.hidden = false;
    otpInputs.forEach((i) => (i.value = ""));
    otpError.textContent = "";
    startTimer(result.body.resend_in || 60);
    otpInputs[0].focus();
  }

  function resetForgot() {
    clearInterval(timerId);
    resetToken = null;
    contactInput.value = "";
    contactInput.readOnly = false;
    contactError.textContent = "";
    otpError.textContent = "";
    otpInputs.forEach((i) => (i.value = ""));
    otpSection.hidden = true;
    sendOtpBtn.hidden = false;
  }

  sendOtpBtn.addEventListener("click", sendOtp);
  resendBtn.addEventListener("click", sendOtp);

  otpInputs.forEach((input, index) => {
    input.addEventListener("input", () => {
      input.value = input.value.replace(/[^0-9]/g, "").slice(-1);
      if (input.value && index < otpInputs.length - 1) otpInputs[index + 1].focus();
    });

    input.addEventListener("keydown", (e) => {
      if (e.key === "Backspace" && !input.value && index > 0) otpInputs[index - 1].focus();
    });

    input.addEventListener("paste", (e) => {
      e.preventDefault();
      const digits = e.clipboardData.getData("text").replace(/[^0-9]/g, "").slice(0, otpInputs.length);
      digits.split("").forEach((d, i) => (otpInputs[i].value = d));
      otpInputs[Math.min(digits.length, otpInputs.length - 1)].focus();
    });
  });

  document.getElementById("forgot-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const otp = otpInputs.map((i) => i.value).join("");
    if (otp.length !== otpInputs.length) {
      otpError.textContent = `Please enter the ${otpInputs.length} digit OTP.`;
      return;
    }

    const submitBtn = e.submitter;
    if (submitBtn) submitBtn.disabled = true;
    const result = await post(API.verifyOtp, { identifier: contactInput.value.trim(), otp });
    if (submitBtn) submitBtn.disabled = false;

    if (!result.ok) {
      otpError.textContent = errorText(result);
      return;
    }

    clearInterval(timerId);
    resetToken = result.body.reset_token;
    otpError.textContent = "";
    showView("view-reset");
  });

  // ---- Create new password --------------------------------------------------
  const resetForm = document.getElementById("reset-form");
  const newPassword = document.getElementById("new-password");
  const confirmPassword = document.getElementById("confirm-password");
  const resetError = document.getElementById("reset-error");

  resetForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (newPassword.value.length < 8) {
      resetError.textContent = "Password must be at least 8 characters.";
      return;
    }
    if (newPassword.value !== confirmPassword.value) {
      resetError.textContent = "Passwords do not match.";
      return;
    }

    const submitBtn = e.submitter;
    if (submitBtn) submitBtn.disabled = true;
    const result = await post(API.reset, {
      identifier: contactInput.value.trim(),
      reset_token: resetToken,
      password: newPassword.value,
      password_confirmation: confirmPassword.value,
    });
    if (submitBtn) submitBtn.disabled = false;

    if (!result.ok) {
      resetError.textContent = errorText(result);
      return;
    }

    resetError.textContent = "";
    resetForm.reset();
    resetToken = null;
    showView("view-login");
    loginSuccess.hidden = false;
  });
</script>
@endpush
