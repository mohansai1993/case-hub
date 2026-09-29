@extends('layouts.admin')

@section('title', 'CaseHub Staff Details')

@section('content')
<div class="page-head page-head-row">
  <div>
    <h1 class="title-back">
      <a href="{{ route('admin.staff') }}" class="back-btn" aria-label="Back to staff">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
      </a>
      Staff Details
    </h1>
    <p>View and manage user account details and access credentials.</p>
  </div>
  <a href="{{ route('admin.create-staff') }}" class="btn-sm btn-sm-lg" id="edit-btn">
    <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    Edit User
  </a>
</div>

<div id="staff-content"></div>
@endsection

@push('modals')
<!-- MODAL: DEACTIVATE / ACTIVATE -->
  <div class="modal-backdrop" id="status-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="status-title">
      <div class="modal-head">
        <span class="modal-icon">
          <svg viewBox="0 0 24 24"><path d="M12 3l9 16H3L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
        </span>
        <div>
          <h3 id="status-title"></h3>
          <p id="status-text"></p>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-inline btn-light" data-close>Cancel</button>
        <button type="button" class="btn-inline btn-danger-solid" id="confirm-status"></button>
      </div>
    </div>
  </div>

@endpush

@push('scripts')
<script src="{{ asset('assets/admin/roles-data.js') }}"></script>
<script>
const statusModal = document.getElementById("status-modal");
statusModal.addEventListener("click", (e) => {
  if (e.target === statusModal || e.target.closest("[data-close]")) statusModal.hidden = true;
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") statusModal.hidden = true;
});

// Staff details: {{ route('admin.staff-details') }}?id=<staff id>
const roles = loadRoles();
const staff = loadStaff();
const staffId = new URLSearchParams(location.search).get("id");
const member = staff.find((s) => s.id === staffId) || staff.find((s) => s.name === "Rahul Sharma") || staff[0];
const content = document.getElementById("staff-content");

document.getElementById("edit-btn").href = `{{ route('admin.create-staff') }}?edit=${encodeURIComponent(member.id)}`;

function sinceText(created) {
  const parts = created.split(" ");
  const months = { Jan: "January", Feb: "February", Mar: "March", Apr: "April", May: "May", Jun: "June", Jul: "July", Aug: "August", Sep: "September", Oct: "October", Nov: "November", Dec: "December" };
  return parts.length === 3 ? `${months[parts[1]] || parts[1]} ${parts[2]}` : created;
}

function render() {
  document.title = `CaseHub ${member.name}`;
  const active = member.status === "Active";
  const role = roles.find((r) => r.id === member.roleId);

  content.innerHTML = `
    <section class="card role-card">
      <div class="client-main">
        <div class="client-avatar client-avatar-solid">${initials(member.name)}</div>
        <div>
          <h2 class="client-name">
            ${escapeHtml(member.name)}
            <span class="verify ${active ? "verify-verified" : "verify-pending"}">${active ? "Active User" : "Inactive User"}</span>
          </h2>
          <p class="client-sub">
            ${active ? `Account active since ${escapeHtml(sinceText(member.created))}` : "Account currently inactive"}
            &bull; Verified Legal Identity Node
          </p>
        </div>
      </div>

      <div class="info-grid info-grid-3 staff-info">
        <div class="info-item">
          <span>Full Name</span>
          <strong>${escapeHtml(member.name)}</strong>
        </div>
        <div class="info-item">
          <span>Email Address</span>
          <a href="mailto:${escapeHtml(member.email)}" class="info-link">
            ${escapeHtml(member.email)}
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
          </a>
        </div>
        <div class="info-item">
          <span>Mobile Number</span>
          <strong>${escapeHtml(member.mobile)}</strong>
        </div>
        <div class="info-item">
          <span>Account Status</span>
          <strong><span class="status ${active ? "status-active" : "status-inactive"}">${escapeHtml(member.status)}</span></strong>
        </div>
        <div class="info-item">
          <span>Created Date</span>
          <strong>${escapeHtml(member.created)}</strong>
        </div>
        <div class="info-item">
          <span>Last Login Session</span>
          <strong>${escapeHtml(member.lastLogin || "Never")}</strong>
          ${member.lastIp ? `<small class="info-sub">from IP ${escapeHtml(member.lastIp)}</small>` : ""}
        </div>
      </div>
    </section>

    <section class="card list-card">
      <h2>Access &amp; Account Settings</h2>
      <p class="card-sub">Configure status, assigned organizational role, and credential resets.</p>

      <div class="access-row">
        <div class="setting-text">
          <strong>Account Status <span class="section-label ${active ? "text-green" : ""}">${active ? "Active" : "Inactive"}</span></strong>
          <small>${active
            ? "Account has active access to the CaseHub platform and internal operational cases."
            : "Account is suspended. The user cannot sign in until it is activated."}</small>
        </div>
        <label class="switch">
          <input type="checkbox" id="status-toggle" ${active ? "checked" : ""} aria-label="Account status" />
          <span class="switch-track"></span>
        </label>
      </div>

      <div class="form-row access-role">
        <div class="form-label-row"><label for="role-select">Assigned Role</label></div>
        <select class="input" id="role-select">
          ${roles.map((r) => `
            <option value="${escapeHtml(r.id)}" ${r.id === member.roleId ? "selected" : ""} ${r.status !== "Active" && r.id !== member.roleId ? "disabled" : ""}>
              ${escapeHtml(r.name)}${r.status !== "Active" ? " (Disabled)" : ""}
            </option>`).join("")}
        </select>
        <small class="hint">
          Changing the role updates user access based on the configured permissions matrix for that role.
          ${role ? `<a href="{{ route('admin.role-details') }}?id=${encodeURIComponent(role.id)}" class="text-link">View ${escapeHtml(role.name)} permissions ›</a>` : ""}
        </small>
      </div>

      <div class="security-box">
        <div class="setting-text">
          <strong class="card-title">
            <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/></svg>
            Security &amp; Authentication
          </strong>
          <small>Sends a secure password reset link to <b>${escapeHtml(member.loginEmail || member.email)}</b>. The current session will remain valid until credentials are overwritten.</small>
        </div>
        <button type="button" class="btn-sm btn-sm-lg" id="reset-btn">
          <svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5"/></svg>
          Reset Password
        </button>
      </div>
    </section>

    <section class="card danger-card danger-card-end">
      <button type="button" class="btn-inline ${active ? "btn-danger-soft" : "btn-block-primary"}" id="deactivate-btn">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
        ${active ? "Deactivate User" : "Activate User"}
      </button>
    </section>`;
}

// Status change (toggle switch + deactivate button share the same confirmation)
const confirmStatus = document.getElementById("confirm-status");

function askStatusChange() {
  const active = member.status === "Active";
  document.getElementById("status-title").textContent = active ? `Deactivate ${member.name}?` : `Activate ${member.name}?`;
  document.getElementById("status-text").textContent = active
    ? "The user will be signed out and cannot access CaseHub until the account is activated again."
    : "The user will be able to sign in and use their assigned role permissions.";
  confirmStatus.textContent = active ? "Deactivate User" : "Activate User";
  confirmStatus.className = `btn-inline ${active ? "btn-danger-solid" : "btn-block-primary"}`;
  statusModal.hidden = false;
}

confirmStatus.addEventListener("click", () => {
  member.status = member.status === "Active" ? "Inactive" : "Active";
  saveStaff(staff);
  statusModal.hidden = true;
  render();
  showToast(`${member.name} is now ${member.status.toLowerCase()}.`);
});

content.addEventListener("click", (e) => {
  if (e.target.closest("#deactivate-btn")) askStatusChange();

  if (e.target.closest("#reset-btn")) {
    // TODO: trigger password reset email via backend API
    showToast(`Password reset link sent to ${member.loginEmail || member.email}.`);
  }
});

content.addEventListener("change", (e) => {
  if (e.target.id === "status-toggle") {
    e.target.checked = member.status === "Active"; // revert until confirmed
    askStatusChange();
  }

  if (e.target.id === "role-select") {
    member.roleId = e.target.value;
    saveStaff(staff);
    render();
    const role = roles.find((r) => r.id === member.roleId);
    showToast(`Role changed to ${role ? role.name : "—"}.`);
  }
});

render();
</script>
@endpush
