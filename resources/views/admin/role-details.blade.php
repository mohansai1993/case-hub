@extends('layouts.admin')

@section('title', 'CaseHub Role Details')

@section('content')
<div class="page-head page-head-row">
  <div>
    <h1 class="title-back">
      <a href="{{ route('admin.roles') }}" class="back-btn" aria-label="Back to roles">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
      </a>
      Role Details
    </h1>
    <p>View and manage role information and permissions.</p>
  </div>
  <a href="{{ route('admin.create-role') }}" class="btn-primary" id="edit-role-btn">
    <svg class="btn-icon" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    Edit Role
  </a>
</div>

<div id="role-content"></div>
@endsection

@push('modals')
<!-- MODAL: DISABLE / ENABLE ROLE -->
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
// Modals
document.querySelectorAll(".modal-backdrop").forEach((modal) => {
  modal.addEventListener("click", (e) => {
    if (e.target === modal || e.target.closest("[data-close]")) modal.hidden = true;
  });
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") document.querySelectorAll(".modal-backdrop").forEach((m) => (m.hidden = true));
});

// Role details: {{ route('admin.role-details') }}?id=<role id>
const roles = loadRoles();
const roleId = new URLSearchParams(location.search).get("id");
const role = roles.find((r) => r.id === roleId) || roles.find((r) => r.id === "case-manager") || roles[0];
const users = roleUsers(role.id);
const content = document.getElementById("role-content");

const checkIcon = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>';
const moduleIcons = {
  dashboard: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  clients: '<path d="M3 7h18v13H3zM8 7V4h8v3"/>',
  lawyers: '<path d="M3 21h18M5 21V10M19 21V10M9 21V10M15 21V10M2 10l10-7 10 7Z"/>',
  subscriptions: '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
  notifications: '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>'
};

function render() {
  document.title = `CaseHub ${role.name}`;
  document.getElementById("edit-role-btn").href = `{{ route('admin.create-role') }}?edit=${encodeURIComponent(role.id)}`;
  const active = role.status === "Active";

  const groups = PERMISSION_MODULES
    .map((m) => ({ ...m, granted: m.perms.filter((p) => role.perms.includes(p)) }))
    .filter((m) => m.granted.length);

  content.innerHTML = `
    <section class="card role-card">
      <div class="role-top">
        <div class="client-main">
          <span class="role-icon role-icon-lg">${roleIcon(role, roles)}</span>
          <div>
            <h2 class="client-name">${escapeHtml(role.name)} <span class="tier-pill">${escapeHtml(role.tag)}</span></h2>
            <p class="client-sub">${escapeHtml(role.desc || "No description added.")}</p>
          </div>
        </div>
        <span class="status ${active ? "status-active" : "status-suspended"}">${escapeHtml(role.status)}</span>
      </div>

      <div class="role-meta">
        <div class="info-item">
          <span>Status</span>
          <strong class="${active ? "text-green" : "text-red"}"><span class="dot ${active ? "dot-green" : "dot-red"}"></span> ${escapeHtml(role.status)}</strong>
        </div>
        <div class="info-item">
          <span>Users Assigned</span>
          <strong><span class="users-pill"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-4-6.3"/></svg>${users.length} User${users.length === 1 ? "" : "s"}</span></strong>
        </div>
        <div class="info-item">
          <span>Created Date</span>
          <strong><svg class="inline-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg> ${escapeHtml(role.created)}</strong>
        </div>
      </div>
    </section>

    <section class="card list-card">
      <h2 class="card-title">
        <svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/></svg>
        Assigned Permissions
      </h2>
      <p class="card-sub">Configured operational access granted to this role.</p>

      ${groups.length
        ? `<div class="perm-groups">
            ${groups.map((g) => `
              <div class="perm-group">
                <div class="perm-group-head">
                  <strong><svg viewBox="0 0 24 24">${moduleIcons[g.id]}</svg>${escapeHtml(g.name)}</strong>
                  <span>${g.granted.length} permission${g.granted.length === 1 ? "" : "s"}</span>
                </div>
                <ul>
                  ${g.granted.map((p) => `<li>${checkIcon}${escapeHtml(p)}</li>`).join("")}
                </ul>
              </div>`).join("")}
          </div>`
        : '<p class="empty-msg">No permissions assigned to this role.</p>'}
    </section>

    <section class="card list-card">
      <div class="card-head">
        <h2 class="card-title">
          <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-4-6.3"/></svg>
          Assigned Users <span class="count-pill">${String(users.length).padStart(2, "0")}</span>
        </h2>
        <span class="card-note">Active staff members holding this role</span>
      </div>

      ${users.length
        ? `<div class="table-wrap">
            <table class="clients-table users-table">
              <thead>
                <tr>
                  <th>User Name</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th class="col-actions">Action</th>
                </tr>
              </thead>
              <tbody>
                ${users.map((u) => `
                  <tr>
                    <td><div class="person"><span class="mini-avatar">${initials(u.name)}</span><strong>${escapeHtml(u.name)}</strong></div></td>
                    <td>${escapeHtml(u.email)}</td>
                    <td><span class="status ${u.status === "Active" ? "status-active" : "status-inactive"}">${escapeHtml(u.status)}</span></td>
                    <td class="col-actions"><a href="{{ route('admin.staff-details') }}?id=${encodeURIComponent(u.id)}" class="text-link">View ›</a></td>
                  </tr>`).join("")}
              </tbody>
            </table>
          </div>`
        : '<p class="empty-msg">No users are assigned to this role yet.</p>'}
    </section>

    ${role.system
      ? ""
      : `<section class="card danger-card">
          <div>
            <strong class="danger-title">
              <svg viewBox="0 0 24 24"><path d="M12 3l9 16H3L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
              ${active ? "Disable Role" : "Enable Role"}
            </strong>
            <p>${active
              ? `Temporarily revokes all active permissions for the ${users.length} assigned user${users.length === 1 ? "" : "s"} without deleting the role definition.`
              : "Restores all permissions of this role for its assigned users."}</p>
          </div>
          <button type="button" class="btn-inline ${active ? "btn-outline-danger" : "btn-block-primary"}" id="status-btn">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
            ${active ? "Disable Role" : "Enable Role"}
          </button>
        </section>`}`;
}

// Disable / enable
const statusModal = document.getElementById("status-modal");
const confirmStatus = document.getElementById("confirm-status");

content.addEventListener("click", (e) => {
  if (e.target.closest("#status-btn")) {
    const active = role.status === "Active";
    document.getElementById("status-title").textContent = active ? `Disable ${role.name}?` : `Enable ${role.name}?`;
    document.getElementById("status-text").textContent = active
      ? `All ${users.length} assigned users will lose these permissions until the role is enabled again.`
      : "Assigned users will regain all permissions of this role.";
    confirmStatus.textContent = active ? "Disable Role" : "Enable Role";
    confirmStatus.className = `btn-inline ${active ? "btn-danger-solid" : "btn-block-primary"}`;
    statusModal.hidden = false;
  }
});

confirmStatus.addEventListener("click", () => {
  role.status = role.status === "Active" ? "Disabled" : "Active";
  saveRoles(roles);
  statusModal.hidden = true;
  render();
  showToast(`${role.name} is now ${role.status.toLowerCase()}.`);
});

render();
</script>
@endpush
