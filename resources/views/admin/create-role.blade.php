@extends('layouts.admin')

@section('title', 'CaseHub Create Role')

@section('content')
<div class="page-head">
  <h1 class="title-back">
    <a href="{{ route('admin.roles') }}" class="back-btn" aria-label="Back to roles">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <span id="form-title">Create Role</span>
  </h1>
  <p>Create a role and assign specific permissions.</p>
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
        <input class="input" id="role-name" type="text" placeholder="Enter role name (e.g. Associate Case Manager)" />
        <small class="hint">Distinct internal name identifying the functional scope.</small>
      </div>
      <div class="form-row">
        <div class="form-label-row"><label for="role-desc">Description <span class="text-muted">(Optional)</span></label></div>
        <textarea class="input" id="role-desc" rows="2" placeholder="Enter a short description (e.g. Detailed access permissions for litigation associate staff)"></textarea>
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

    <div class="modules" id="modules"></div>
  </section>

  <p class="error-msg form-error-center" id="form-error"></p>

  <section class="card form-footer">
    <a href="{{ route('admin.roles') }}" class="btn-inline btn-light">Cancel</a>
    <button type="submit" class="btn-inline btn-block-primary" id="submit-btn">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
      <span>Create Role</span>
    </button>
  </section>
</form>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/roles-data.js') }}"></script>
<script>
// Permission modules
const modulesEl = document.getElementById("modules");

modulesEl.innerHTML = PERMISSION_MODULES.map((m, i) => `
  <div class="module" data-module="${m.id}">
    <div class="module-head">
      <label class="check">
        <input type="checkbox" class="module-check" />
        <strong>${escapeHtml(m.name)}</strong>
      </label>
      <span class="module-tag">Module ${String(i + 1).padStart(2, "0")}</span>
      <span class="module-count"></span>
    </div>
    <div class="module-perms">
      ${m.perms.map((p) => `
        <label class="check">
          <input type="checkbox" class="perm-check" value="${escapeHtml(p)}" />
          <span>${escapeHtml(p)}</span>
        </label>`).join("")}
    </div>
  </div>`).join("");

function refreshModules() {
  modulesEl.querySelectorAll(".module").forEach((mod) => {
    const perms = [...mod.querySelectorAll(".perm-check")];
    const checked = perms.filter((p) => p.checked).length;
    const head = mod.querySelector(".module-check");
    head.checked = checked === perms.length;
    head.indeterminate = checked > 0 && checked < perms.length;
    mod.querySelector(".module-count").textContent = `${checked} of ${perms.length} selected`;
    mod.classList.toggle("has-selection", checked > 0);
  });
}

modulesEl.addEventListener("change", (e) => {
  if (e.target.classList.contains("module-check")) {
    e.target.closest(".module").querySelectorAll(".perm-check").forEach((p) => (p.checked = e.target.checked));
  }
  refreshModules();
});

function setAll(value) {
  modulesEl.querySelectorAll(".perm-check").forEach((p) => (p.checked = value));
  refreshModules();
}

document.getElementById("select-all").addEventListener("click", () => setAll(true));
document.getElementById("deselect-all").addEventListener("click", () => setAll(false));

// Edit mode: {{ route('admin.create-role') }}?edit=<role id>
let roles = loadRoles();
const editId = new URLSearchParams(location.search).get("edit");
const editing = roles.find((r) => r.id === editId);
const nameInput = document.getElementById("role-name");
const descInput = document.getElementById("role-desc");

if (editing) {
  document.getElementById("form-title").textContent = "Edit Role";
  document.querySelector("#submit-btn span").textContent = "Save Changes";
  document.querySelector(".page-head p").textContent = "Update role details and permissions.";
  nameInput.value = editing.name;
  descInput.value = editing.desc || "";
  modulesEl.querySelectorAll(".perm-check").forEach((p) => (p.checked = editing.perms.includes(p.value)));
}

refreshModules();

// Save
const formError = document.getElementById("form-error");

document.getElementById("role-form").addEventListener("submit", (e) => {
  e.preventDefault();
  const name = nameInput.value.trim();
  const perms = [...modulesEl.querySelectorAll(".perm-check:checked")].map((p) => p.value);

  if (!name) {
    formError.textContent = "Please enter a role name.";
    nameInput.focus();
    return;
  }
  if (roles.some((r) => r.name.toLowerCase() === name.toLowerCase() && r !== editing)) {
    formError.textContent = "A role with this name already exists.";
    return;
  }
  if (!perms.length) {
    formError.textContent = "Please select at least one permission.";
    return;
  }
  formError.textContent = "";

  let id;
  if (editing) {
    Object.assign(editing, { name, desc: descInput.value.trim(), perms });
    id = editing.id;
  } else {
    id = `role-${Date.now()}`;
    roles.push({
      id,
      name,
      tag: "Custom Role",
      desc: descInput.value.trim(),
      status: "Active",
      created: formatDate(new Date()),
      perms
    });
  }

  saveRoles(roles);
  location.href = `{{ route('admin.role-details') }}?id=${encodeURIComponent(id)}`;
});
</script>
@endpush
