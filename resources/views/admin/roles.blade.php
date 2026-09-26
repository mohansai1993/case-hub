@extends('layouts.admin')

@section('title', 'CaseHub Roles')

@section('content')
<div class="page-head page-head-row">
  <div>
    <h1>Roles &amp; Permissions</h1>
    <p>Create and manage roles and their access permissions.</p>
  </div>
  <a href="{{ route('admin.create-role') }}" class="btn-primary">+ Create Role</a>
</div>

<section class="card list-card">
  <div class="toolbar">
    <label class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="text" id="role-search" placeholder="Filter roles..." />
    </label>
    <span class="section-label" id="roles-count"></span>
  </div>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Role Name</th>
          <th>Description</th>
          <th>Users Assigned</th>
          <th>Status</th>
          <th>Created Date</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody id="roles-body"></tbody>
    </table>
    <p class="empty-msg" id="empty-msg" hidden>No roles match your search.</p>
  </div>

  <div class="table-footer">
    <span id="roles-footer"></span>
    <span class="access-active"><span class="dot dot-blue"></span> Access Control Active</span>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/roles-data.js') }}"></script>
<script>
// Roles list
const roles = loadRoles();
const staff = loadStaff();
const tbody = document.getElementById("roles-body");
const searchInput = document.getElementById("role-search");

function renderRoles() {
  const query = searchInput.value.trim().toLowerCase();
  const list = roles.filter((r) =>
    !query || [r.name, r.tag, r.desc].some((v) => (v || "").toLowerCase().includes(query))
  );

  tbody.innerHTML = list.map((r) => {
    const url = `{{ route('admin.role-details') }}?id=${encodeURIComponent(r.id)}`;
    return `
      <tr>
        <td>
          <div class="person">
            <span class="role-icon">${roleIcon(r, roles)}</span>
            <div><strong>${escapeHtml(r.name)}</strong><small class="role-tag-text">${escapeHtml(r.tag)}</small></div>
          </div>
        </td>
        <td class="cell-desc">${escapeHtml(r.desc || "—")}</td>
        <td><span class="users-pill"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-4-6.3"/></svg>${roleUsers(r.id, staff).length} User${roleUsers(r.id, staff).length === 1 ? "" : "s"}</span></td>
        <td><span class="status ${r.status === "Active" ? "status-active" : "status-suspended"}">${escapeHtml(r.status)}</span></td>
        <td>${escapeHtml(r.created)}</td>
        <td class="col-actions">
          <a href="${url}" class="text-link">View</a> / <a href="{{ route('admin.create-role') }}?edit=${encodeURIComponent(r.id)}" class="text-link">Edit</a>
        </td>
      </tr>`;
  }).join("");

  document.getElementById("empty-msg").hidden = list.length > 0;
  document.getElementById("roles-count").textContent = `Showing ${list.length} Defined Role${list.length === 1 ? "" : "s"}`;
}

document.getElementById("roles-footer").textContent =
  `${roles.length} total roles configured across organizational entities`;
searchInput.addEventListener("input", renderRoles);
renderRoles();
</script>
@endpush
