@extends('layouts.admin')

@section('title', 'CaseHub Create Staff')

@section('content')
<div class="page-head">
  <h1 class="title-back">
    <a href="{{ route('admin.staff') }}" class="back-btn" id="back-btn" aria-label="Back">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <span id="form-title">Create Staff</span>
  </h1>
  <p>Create a user account and assign a role with specific permissions.</p>
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
        <input class="input" id="full-name" type="text" placeholder="Enter full name" />
      </div>
    </div>

    <div class="two-col">
      <div class="form-row">
        <div class="form-label-row"><label for="email">Email Address <span class="text-red">*</span></label></div>
        <div class="input-icon">
          <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
          <input class="input" id="email" type="email" placeholder="Enter email address" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-label-row"><label for="mobile">Mobile Number <span class="text-red">*</span></label></div>
        <div class="input-icon">
          <svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/></svg>
          <input class="input" id="mobile" type="tel" placeholder="Enter mobile number" />
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
        <select class="input" id="role"></select>
      </div>
      <small class="hint">The selected role will determine what this user can access.</small>
    </div>

    <div class="role-preview" id="role-preview"></div>
  </section>

  <!-- LOGIN CREDENTIALS -->
  <section class="card list-card">
    <div class="section-title">
      <span class="setting-icon"><svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/></svg></span>
      <div>
        <h2>Login Credentials</h2>
        <p>Define authentication credentials for platform sign-in.</p>
      </div>
    </div>

    <div class="form-row">
      <div class="form-label-row"><label for="login-email">Login Email <span class="text-red">*</span></label></div>
      <div class="input-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
        <input class="input" id="login-email" type="email" placeholder="Enter login email" />
      </div>
    </div>

    <div class="two-col">
      <div class="form-row">
        <div class="form-label-row"><label for="password">Password <span class="text-red" id="password-star">*</span></label></div>
        <div class="input-icon">
          <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          <input class="input" id="password" type="password" placeholder="Create password" autocomplete="new-password" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-label-row"><label for="confirm-password">Confirm Password <span class="text-red" id="confirm-star">*</span></label></div>
        <div class="input-icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
          <input class="input" id="confirm-password" type="password" placeholder="Confirm password" autocomplete="new-password" />
        </div>
      </div>
    </div>
    <small class="hint" id="password-hint">ⓘ Must be at least 8 characters with letters, numbers, and symbols.</small>
  </section>

  <!-- ACCOUNT STATUS -->
  <section class="card list-card">
    <div class="section-title">
      <span class="setting-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
      <div>
        <h2>Account Status</h2>
        <p>Set the initial operational clearance for this user profile.</p>
      </div>
    </div>

    <div class="form-label-row status-label"><label>Select Initial Status</label></div>
    <div class="radio-cards">
      <label class="radio-card">
        <input type="radio" name="status" value="Active" checked />
        <span>
          <strong>Active <span class="status status-active">Default</span></strong>
          <small>User can immediately sign in and exercise assigned role permissions.</small>
        </span>
      </label>
      <label class="radio-card">
        <input type="radio" name="status" value="Inactive" />
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
      <span id="footer-note">New user account will be activated under the specified governance policy.</span>
    </span>
    <a href="{{ route('admin.staff') }}" class="btn-inline btn-light" id="cancel-btn">Cancel</a>
    <button type="submit" class="btn-inline btn-block-primary">
      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      <span id="submit-text">Create User</span>
    </button>
  </section>
</form>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/roles-data.js') }}"></script>
<script>
const roles = loadRoles();
let staff = loadStaff();

const nameInput = document.getElementById("full-name");
const emailInput = document.getElementById("email");
const mobileInput = document.getElementById("mobile");
const roleSelect = document.getElementById("role");
const loginEmailInput = document.getElementById("login-email");
const passwordInput = document.getElementById("password");
const confirmInput = document.getElementById("confirm-password");
const rolePreview = document.getElementById("role-preview");
const formError = document.getElementById("form-error");

// Edit mode: {{ route('admin.create-staff') }}?edit=<staff id>
const editId = new URLSearchParams(location.search).get("edit");
const editing = staff.find((s) => s.id === editId);

// Roles dropdown (disabled roles can't be newly assigned)
roles.forEach((r) => {
  const option = new Option(r.status === "Active" ? r.name : `${r.name} (Disabled)`, r.id);
  option.disabled = r.status !== "Active" && (!editing || editing.roleId !== r.id);
  roleSelect.add(option);
});

const firstActive = roles.find((r) => r.status === "Active" && !r.system);
roleSelect.value = (firstActive || roles[0]).id;

function renderRolePreview() {
  const role = roles.find((r) => r.id === roleSelect.value);
  if (!role) return (rolePreview.innerHTML = "");
  rolePreview.innerHTML = `
    <span class="role-icon">${roleIcon(role, roles)}</span>
    <div>
      <strong>${escapeHtml(role.name)} <span class="tier-pill">${escapeHtml(role.tag)}</span></strong>
      <p>${escapeHtml(role.desc || "No description added.")} &middot; ${role.perms.length} permission${role.perms.length === 1 ? "" : "s"}</p>
    </div>
    <a href="{{ route('admin.role-details') }}?id=${encodeURIComponent(role.id)}" class="text-link" target="_blank">View permissions ›</a>`;
}

roleSelect.addEventListener("change", renderRolePreview);

// Login email follows the contact email until the user edits it
let loginEmailTouched = false;
loginEmailInput.addEventListener("input", () => (loginEmailTouched = true));
emailInput.addEventListener("input", () => {
  if (!loginEmailTouched) loginEmailInput.value = emailInput.value;
});

if (editing) {
  const detailsUrl = `{{ route('admin.staff-details') }}?id=${encodeURIComponent(editing.id)}`;
  document.title = "CaseHub Edit Staff";
  document.getElementById("form-title").textContent = "Edit Staff";
  document.querySelector(".page-head p").textContent = "Update the user account and role assignment.";
  document.getElementById("back-btn").href = detailsUrl;
  document.getElementById("cancel-btn").href = detailsUrl;
  document.getElementById("submit-text").textContent = "Save Changes";
  document.getElementById("footer-note").textContent = "Changes apply to this user's next sign-in session.";
  document.getElementById("password-star").hidden = true;
  document.getElementById("confirm-star").hidden = true;
  document.getElementById("password-hint").textContent = "ⓘ Leave password blank to keep the current one. A new password must be at least 8 characters with letters, numbers, and symbols.";
  document.querySelector(".status-label label").textContent = "Account Status";
  passwordInput.placeholder = "Leave blank to keep current";
  confirmInput.placeholder = "Leave blank to keep current";

  nameInput.value = editing.name;
  emailInput.value = editing.email;
  mobileInput.value = editing.mobile;
  loginEmailInput.value = editing.loginEmail || editing.email;
  loginEmailTouched = true;
  roleSelect.value = editing.roleId;
  document.querySelector(`input[name="status"][value="${editing.status}"]`).checked = true;
}

renderRolePreview();

// Validation + save
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validPassword(value) {
  return value.length >= 8 && /[a-z]/i.test(value) && /\d/.test(value) && /[^a-z0-9]/i.test(value);
}

function fail(message, input) {
  formError.textContent = message;
  if (input) input.focus();
}

document.getElementById("staff-form").addEventListener("submit", (e) => {
  e.preventDefault();
  const name = nameInput.value.trim();
  const email = emailInput.value.trim();
  const mobile = mobileInput.value.trim();
  const loginEmail = loginEmailInput.value.trim();
  const password = passwordInput.value;
  const mobileDigits = mobile.replace(/\D/g, "");

  if (!name) return fail("Please enter the full name.", nameInput);
  if (!EMAIL_RE.test(email)) return fail("Please enter a valid email address.", emailInput);
  if (mobileDigits.length < 10 || mobileDigits.length > 13) return fail("Please enter a valid mobile number.", mobileInput);
  if (!roleSelect.value) return fail("Please assign a role.", roleSelect);
  if (!EMAIL_RE.test(loginEmail)) return fail("Please enter a valid login email.", loginEmailInput);
  if (staff.some((s) => s !== editing && (s.loginEmail || s.email).toLowerCase() === loginEmail.toLowerCase())) {
    return fail("This login email is already used by another staff member.", loginEmailInput);
  }
  if (!editing || password) {
    if (!validPassword(password)) return fail("Password must be at least 8 characters with letters, numbers, and symbols.", passwordInput);
    if (password !== confirmInput.value) return fail("Passwords do not match.", confirmInput);
  }
  formError.textContent = "";

  const status = document.querySelector('input[name="status"]:checked').value;
  // Passwords are never stored in the browser — TODO: send to backend API
  const data = { name, email, mobile, loginEmail, roleId: roleSelect.value, status };

  let id;
  if (editing) {
    Object.assign(editing, data);
    id = editing.id;
  } else {
    id = `staff-${Date.now()}`;
    staff.push({ id, ...data, created: formatDate(new Date()), lastLogin: "Never", lastIp: "" });
  }

  saveStaff(staff);
  location.href = `{{ route('admin.staff-details') }}?id=${encodeURIComponent(id)}`;
});
</script>
@endpush
