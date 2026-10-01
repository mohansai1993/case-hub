@extends('layouts.admin')

@section('title', $role ? 'CaseHub Edit Role' : 'CaseHub Create Role')

@section('content')
<div class="page-head">
  <h1 class="title-back">
    <a href="{{ $role ? route('admin.role-details', $role) : route('admin.roles') }}" class="back-btn" aria-label="Back">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <span id="form-title">{{ $role ? 'Edit Role' : 'Create Role' }}</span>
  </h1>
  <p>{{ $role ? 'Update role details and permissions.' : 'Create a role and assign specific permissions.' }}</p>
</div>

<form id="role-form" novalidate>
  <!-- ROLE INFORMATION -->
  <section class="card plans-card">
    <h2 class="card-title">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M6 16a3 3 0 0 1 6 0M14 9h4M14 13h4"/></svg>
      Role Information
    </h2>
    <p class="card-sub">Basic details identifying this administrative role.</p>

    <div class="two-col">
      <div class="form-row">
        <div class="form-label-row"><label for="role-name">Role Name <span class="text-red">*</span></label></div>
        <input class="input" id="role-name" type="text" maxlength="255" value="{{ $role->name ?? '' }}" placeholder="Enter role name (e.g. Associate Case Manager)" />
        <small class="hint">Distinct internal name identifying the functional scope.</small>
      </div>
      <div class="form-row">
        <div class="form-label-row"><label for="role-desc">Description <span class="text-muted">(Optional)</span></label></div>
        <textarea class="input" id="role-desc" rows="2" maxlength="500" placeholder="Enter a short description (e.g. Detailed access permissions for litigation associate staff)">{{ $role->description ?? '' }}</textarea>
        <small class="hint">Summary of duties and operational clearance.</small>
      </div>
    </div>
  </section>

  <!-- ROLE PERMISSIONS -->
  <section class="card list-card">
    <div class="card-head">
      <div>
        <h2 class="card-title">
          <svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/></svg>
          Role Permissions
        </h2>
        <p>Configure granular system permissions for this role. Check the parent module to grant full module access.</p>
      </div>
      <div class="seg-btns">
        <button type="button" class="btn-sm" id="select-all">Select All</button>
        <button type="button" class="btn-sm" id="deselect-all">Deselect All</button>
      </div>
    </div>

    @php $granted = $role?->permissionKeys() ?? []; @endphp
    <div class="modules" id="modules">
      @foreach (config('permissions.modules') as $module)
        <div class="module">
          <div class="module-head">
            <label class="check">
              <input type="checkbox" class="module-check" />
              <strong>{{ $module['label'] }}</strong>
            </label>
            <span class="module-count"></span>
          </div>
          <div class="module-perms">
            @foreach ($module['permissions'] as $key => $label)
              <label class="check">
                <input type="checkbox" class="perm-check" value="{{ $key }}" @checked(in_array($key, $granted, true)) />
                <span>{{ $label }}</span>
              </label>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  </section>

  <p class="error-msg form-error-center" id="form-error"></p>

  <section class="card form-footer">
    <a href="{{ $role ? route('admin.role-details', $role) : route('admin.roles') }}" class="btn-inline btn-light">Cancel</a>
    <button type="submit" class="btn-inline btn-block-primary" id="submit-btn">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
      <span>{{ $role ? 'Save Changes' : 'Create Role' }}</span>
    </button>
  </section>
</form>
@endsection

@push('scripts')
<script>
(function () {
  "use strict";

  var modulesEl = document.getElementById("modules");

  function refreshModules() {
    modulesEl.querySelectorAll(".module").forEach(function (mod) {
      var perms = [...mod.querySelectorAll(".perm-check")];
      var checked = perms.filter(function (p) { return p.checked; }).length;
      var head = mod.querySelector(".module-check");
      head.checked = checked === perms.length;
      head.indeterminate = checked > 0 && checked < perms.length;
      mod.querySelector(".module-count").textContent = checked + " of " + perms.length + " selected";
      mod.classList.toggle("has-selection", checked > 0);
    });
  }

  modulesEl.addEventListener("change", function (e) {
    if (e.target.classList.contains("module-check")) {
      e.target.closest(".module").querySelectorAll(".perm-check").forEach(function (p) { p.checked = e.target.checked; });
    }
    refreshModules();
  });

  document.getElementById("select-all").addEventListener("click", function () {
    modulesEl.querySelectorAll(".perm-check").forEach(function (p) { p.checked = true; });
    refreshModules();
  });

  document.getElementById("deselect-all").addEventListener("click", function () {
    modulesEl.querySelectorAll(".perm-check").forEach(function (p) { p.checked = false; });
    refreshModules();
  });

  refreshModules();

  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var formError = document.getElementById("form-error");
  var submitBtn = document.getElementById("submit-btn");

  document.getElementById("role-form").addEventListener("submit", function (e) {
    e.preventDefault();

    var name = document.getElementById("role-name").value.trim();
    var permissions = [...modulesEl.querySelectorAll(".perm-check:checked")].map(function (p) { return p.value; });

    if (!name) {
      formError.textContent = "Please enter a role name.";
      return;
    }
    if (!permissions.length) {
      formError.textContent = "Please select at least one permission.";
      return;
    }
    formError.textContent = "";
    submitBtn.disabled = true;

    fetch("{{ $role ? route('admin.roles.update', $role) : route('admin.roles.store') }}", {
      method: "{{ $role ? 'PUT' : 'POST' }}",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF,
        "X-Requested-With": "XMLHttpRequest"
      },
      body: JSON.stringify({
        name: name,
        description: document.getElementById("role-desc").value.trim(),
        permissions: permissions
      })
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body }; });
    }).then(function (result) {
      if (!result.ok) {
        var errors = result.body.errors;
        formError.textContent = errors ? Object.values(errors)[0][0] : (result.body.message || "Could not save this role.");
        submitBtn.disabled = false;
        return;
      }
      window.location.href = "{{ url('admin/roles') }}/" + result.body.data.id;
    }).catch(function () {
      formError.textContent = "Could not reach the server. Please try again.";
      submitBtn.disabled = false;
    });
  });
})();
</script>
@endpush
