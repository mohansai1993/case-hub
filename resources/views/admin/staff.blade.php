@extends('layouts.admin')

@section('title', 'CaseHub Staff')

@section('content')
<div class="page-head page-head-row">
  <div>
    <h1>Staff</h1>
    <p>Manage users and their assigned roles.</p>
  </div>
  <a href="{{ route('admin.create-staff') }}" class="btn-primary">+ Create Staff</a>
</div>

<section class="card list-card">
  <div class="toolbar">
    <label class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="text" id="staff-search" placeholder="Search user by name or email" />
    </label>

    <div class="filters">
      <select id="role-filter" aria-label="Role">
        <option value="">All Roles</option>
      </select>
      <select id="status-filter" aria-label="Status">
        <option value="">All Statuses</option>
        <option value="Active">Active</option>
        <option value="Inactive">Inactive</option>
      </select>
    </div>
  </div>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Staff Name</th>
          <th>Email</th>
          <th>Assigned Role</th>
          <th>Status</th>
          <th>Created Date</th>
          <th>Last Login</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody id="staff-body"></tbody>
    </table>
    <p class="empty-msg" id="empty-msg" hidden>No staff members match your search.</p>
  </div>

  <div class="table-footer">
    <span class="access-active">
      <svg class="inline-icon" viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/></svg>
      <span id="staff-footer"></span>
    </span>
    <span id="showing-text"></span>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/roles-data.js') }}"></script>
<script>
// Staff list
const roles = loadRoles();
const staff = loadStaff();
const tbody = document.getElementById("staff-body");
const searchInput = document.getElementById("staff-search");
const roleFilter = document.getElementById("role-filter");
const statusFilter = document.getElementById("status-filter");

roles.forEach((r) => roleFilter.add(new Option(r.name, r.id)));

function rolePill(roleId) {
  const index = roles.findIndex((r) => r.id === roleId);
  const role = roles[index];
  return role
    ? `<span class="role-pill rp-${index % 5}">${escapeHtml(role.name)}</span>`
    : '<span class="role-pill">No role</span>';
}

function renderStaff() {
  const query = searchInput.value.trim().toLowerCase();
  const roleId = roleFilter.value;
  const status = statusFilter.value;

  const list = staff.filter((s) =>
    (!query || [s.name, s.email].some((v) => v.toLowerCase().includes(query))) &&
    (!roleId || s.roleId === roleId) &&
    (!status || s.status === status)
  );

  tbody.innerHTML = list.map((s) => {
    const id = encodeURIComponent(s.id);
    return `
      <tr>
        <td>
          <div class="person">
            <span class="mini-avatar">${initials(s.name)}</span>
            <strong>${escapeHtml(s.name)}</strong>
          </div>
        </td>
        <td>${escapeHtml(s.email)}</td>
        <td>${rolePill(s.roleId)}</td>
        <td><span class="status ${s.status === "Active" ? "status-active" : "status-inactive"}">${escapeHtml(s.status)}</span></td>
        <td>${escapeHtml(s.created)}</td>
        <td>${escapeHtml(s.lastLogin || "Never")}</td>
        <td class="col-actions">
          <div class="row-actions">
            <a href="{{ route('admin.staff-details') }}?id=${id}" class="btn-view">
              <svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
              View
            </a>
            <a href="{{ route('admin.create-staff') }}?edit=${id}" class="btn-sm">
              <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              Edit
            </a>
          </div>
        </td>
      </tr>`;
  }).join("");

  document.getElementById("empty-msg").hidden = list.length > 0;
  document.getElementById("showing-text").innerHTML =
    `Showing <b>${list.length}</b> Staff Member${list.length === 1 ? "" : "s"}`;
}

document.getElementById("staff-footer").textContent =
  `${staff.length} total staff members provisioned under enterprise governance • Access Control Active`;

[searchInput, roleFilter, statusFilter].forEach((el) => el.addEventListener("input", renderStaff));
renderStaff();
</script>
@endpush
