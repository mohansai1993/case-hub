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
    <span class="section-label" id="roles-count">Showing {{ $roles->count() }} Defined Role{{ $roles->count() === 1 ? '' : 's' }}</span>
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
      <tbody id="roles-body">
        @foreach ($roles as $role)
          <tr data-filter="{{ mb_strtolower($role->name . ' ' . $role->tag . ' ' . $role->description) }}">
            <td>
              <div class="person">
                <span class="role-icon"><svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/><path d="M9 12l2 2 4-4"/></svg></span>
                <div><strong>{{ $role->name }}</strong><small class="role-tag-text">{{ $role->tag }}</small></div>
              </div>
            </td>
            <td class="cell-desc">{{ $role->description ?: '—' }}</td>
            <td>
              <span class="users-pill">
                <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-4-6.3"/></svg>
                {{ $role->admins_count }} User{{ $role->admins_count === 1 ? '' : 's' }}
              </span>
            </td>
            <td><span class="status {{ $role->is_active ? 'status-active' : 'status-suspended' }}">{{ $role->is_active ? 'Active' : 'Disabled' }}</span></td>
            <td>{{ $role->created_at->format('d M Y') }}</td>
            <td class="col-actions">
              <a href="{{ route('admin.role-details', $role) }}" class="text-link">View</a>
              / <a href="{{ route('admin.roles.edit', $role) }}" class="text-link">Edit</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <p class="empty-msg" id="empty-msg" hidden>No roles match your search.</p>
  </div>

  <div class="table-footer">
    <span id="roles-footer">{{ $roles->count() }} total roles configured across organizational entities</span>
    <span class="access-active"><span class="dot dot-blue"></span> Access Control Active</span>
  </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
  "use strict";

  var searchInput = document.getElementById("role-search");
  var rows = [...document.querySelectorAll("#roles-body tr[data-filter]")];
  var emptyMsg = document.getElementById("empty-msg");
  var countLabel = document.getElementById("roles-count");

  function renderRoles() {
    var query = searchInput.value.trim().toLowerCase();
    var visible = 0;

    rows.forEach(function (row) {
      var match = !query || row.dataset.filter.includes(query);
      row.hidden = !match;
      if (match) visible++;
    });

    emptyMsg.hidden = visible > 0;
    countLabel.textContent = "Showing " + visible + " Defined Role" + (visible === 1 ? "" : "s");
  }

  searchInput.addEventListener("input", renderRoles);
})();
</script>
@endpush
