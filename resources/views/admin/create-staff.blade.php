@extends('layouts.admin')

@section('title', $admin ? 'CaseHub Edit Staff' : 'CaseHub Create Staff')

@section('content')
<div class="page-head">
  <h1 class="title-back">
    <a href="{{ $admin ? route('admin.staff-details', $admin) : route('admin.staff') }}" class="back-btn" aria-label="Back">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <span id="form-title">{{ $admin ? 'Edit Staff' : 'Create Staff' }}</span>
  </h1>
  <p>{{ $admin ? 'Update the user account and role assignment.' : 'Create a user account and assign a role with specific permissions.' }}</p>
</div>

<form id="staff-form" novalidate>
  <!-- USER INFORMATION -->
  <section class="card plans-card">
    <div class="section-title">
      <span class="setting-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
      <div>
        <h2>User Information</h2>
        <p>Basic personal and contact details for the user identity.</p>
      </div>
    </div>

    <div class="form-row">
      <div class="form-label-row"><label for="full-name">Full Name <span class="text-red">*</span></label></div>
      <div class="input-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        <input class="input" id="full-name" type="text" maxlength="255" value="{{ $admin->name ?? '' }}" placeholder="Enter full name" />
      </div>
    </div>

    <div class="two-col">
      <div class="form-row">
        <div class="form-label-row"><label for="email">Email Address <span class="text-red">*</span></label></div>
        <div class="input-icon">
          <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
          <input class="input" id="email" type="email" maxlength="255" value="{{ $admin->email ?? '' }}" placeholder="Enter email address" />
        </div>
        <small class="hint">Also used to sign in and to receive password reset codes.</small>
      </div>
      <div class="form-row">
        <div class="form-label-row"><label for="mobile">Mobile Number <span class="text-red">*</span></label></div>
        <div class="input-icon">
          <svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/></svg>
          <input class="input" id="mobile" type="tel" value="{{ $admin->mobile ?? '' }}" placeholder="Enter 10 digit mobile number" />
        </div>
      </div>
    </div>
  </section>

  <!-- ROLE ASSIGNMENT -->
  <section class="card list-card">
    <div class="section-title">
      <span class="setting-icon"><svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/><path d="M9 12l2 2 4-4"/></svg></span>
      <div>
        <h2>Role Assignment</h2>
        <p>Bind this user to an administrative role with pre-configured access rights.</p>
      </div>
    </div>

    <div class="form-row">
      <div class="form-label-row"><label for="role">Assign Role <span class="text-red">*</span></label></div>
      <div class="input-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/></svg>
        <select class="input" id="role">
          @foreach ($roles as $role)
            <option value="{{ $role->id }}" @selected(($admin->role_id ?? null) === $role->id)>
              {{ $role->name }}@unless ($role->is_active) (Disabled) @endunless
            </option>
          @endforeach
        </select>
      </div>
      <small class="hint">The selected role will determine what this user can access.</small>
    </div>

    @if ($admin)
      <div class="role-preview">
        <a href="{{ route('admin.role-details', $admin->role_id) }}" class="text-link" target="_blank">View current role permissions &rsaquo;</a>
      </div>
    @endif
  </section>

  <!-- LOGIN CREDENTIALS -->
  <section class="card list-card">
    <div class="section-title">
      <span class="setting-icon"><svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/></svg></span>
      <div>
        <h2>Login Credentials</h2>
        <p>Define the authentication password for platform sign-in.</p>
      </div>
    </div>

    <div class="two-col">
      <div class="form-row">
        <div class="form-label-row"><label for="password">Password <span class="text-red" id="password-star">*</span></label></div>
        <div class="input-icon">
          <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          <input class="input" id="password" type="password" placeholder="{{ $admin ? 'Leave blank to keep current' : 'Create password' }}" autocomplete="new-password" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-label-row"><label for="confirm-password">Confirm Password <span class="text-red" id="confirm-star">*</span></label></div>
        <div class="input-icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
          <input class="input" id="confirm-password" type="password" placeholder="{{ $admin ? 'Leave blank to keep current' : 'Confirm password' }}" autocomplete="new-password" />
        </div>
      </div>
    </div>
    <small class="hint" id="password-hint">
      @if ($admin)
        &#9432; Leave password blank to keep the current one. A new password must be at least 8 characters with uppercase, lowercase and a number.
      @else
        &#9432; Must be at least 8 characters with uppercase, lowercase and a number.
      @endif
    </small>
  </section>

  <!-- ACCOUNT STATUS -->
  <section class="card list-card">
    <div class="section-title">
      <span class="setting-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
      <div>
        <h2>Account Status</h2>
        <p>Set the operational clearance for this user profile.</p>
      </div>
    </div>

    <div class="form-label-row status-label"><label>{{ $admin ? 'Account Status' : 'Select Initial Status' }}</label></div>
    <div class="radio-cards">
      <label class="radio-card">
        <input type="radio" name="status" value="active" @checked(($admin->status->value ?? 'active') === 'active') />
        <span>
          <strong>Active @unless ($admin) <span class="status status-active">Default</span> @endunless</strong>
          <small>User can sign in and exercise assigned role permissions.</small>
        </span>
      </label>
      <label class="radio-card">
        <input type="radio" name="status" value="inactive" @checked(($admin->status->value ?? null) === 'inactive') />
        <span>
          <strong>Inactive</strong>
          <small>Account is provisioned but credentials remain suspended until activated.</small>
        </span>
      </label>
    </div>
  </section>

  <p class="error-msg form-error-center" id="form-error"></p>

  <section class="card form-footer form-footer-note">
    <span class="footer-note">
      <svg class="inline-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
      <span>{{ $admin ? "Changes apply to this user's next sign-in session." : 'New user account will be activated under the specified governance policy.' }}</span>
    </span>
    <a href="{{ $admin ? route('admin.staff-details', $admin) : route('admin.staff') }}" class="btn-inline btn-light">Cancel</a>
    <button type="submit" class="btn-inline btn-block-primary">
      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      <span>{{ $admin ? 'Save Changes' : 'Create User' }}</span>
    </button>
  </section>
</form>
@endsection

@push('scripts')
<script>
(function () {
  "use strict";

  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var formError = document.getElementById("form-error");
  var isEditing = {{ $admin ? 'true' : 'false' }};

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function fail(message, input) {
    formError.textContent = message;
    if (input) input.focus();
  }

  document.getElementById("staff-form").addEventListener("submit", function (e) {
    e.preventDefault();

    var name = document.getElementById("full-name").value.trim();
    var email = document.getElementById("email").value.trim();
    var mobile = document.getElementById("mobile").value.trim();
    var roleId = document.getElementById("role").value;
    var password = document.getElementById("password").value;
    var confirmPassword = document.getElementById("confirm-password").value;
    var status = document.querySelector('input[name="status"]:checked').value;

    if (!name) return fail("Please enter the full name.", document.getElementById("full-name"));
    if (!EMAIL_RE.test(email)) return fail("Please enter a valid email address.", document.getElementById("email"));
    if (!mobile) return fail("Please enter a mobile number.", document.getElementById("mobile"));
    if (!roleId) return fail("Please assign a role.", document.getElementById("role"));
    if (!isEditing || password) {
      if (password.length < 8) return fail("Password must be at least 8 characters.", document.getElementById("password"));
      if (password !== confirmPassword) return fail("Passwords do not match.", document.getElementById("confirm-password"));
    }
    formError.textContent = "";

    var payload = { name: name, email: email, mobile: mobile, role_id: roleId, status: status };
    if (password) {
      payload.password = password;
      payload.password_confirmation = confirmPassword;
    }

    var submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    fetch("{{ $admin ? route('admin.staff.update', $admin) : route('admin.staff.store') }}", {
      method: "{{ $admin ? 'PUT' : 'POST' }}",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF,
        "X-Requested-With": "XMLHttpRequest"
      },
      body: JSON.stringify(payload)
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body }; });
    }).then(function (result) {
      if (!result.ok) {
        var errors = result.body.errors;
        formError.textContent = errors ? Object.values(errors)[0][0] : (result.body.message || "Could not save this user.");
        submitBtn.disabled = false;
        return;
      }
      window.location.href = "{{ url('admin/staff') }}/" + result.body.data.id;
    }).catch(function () {
      formError.textContent = "Could not reach the server. Please try again.";
      submitBtn.disabled = false;
    });
  });
})();
</script>
@endpush
