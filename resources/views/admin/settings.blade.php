@extends('layouts.admin')

@section('title', 'CaseHub Settings')

@section('content')
<div class="page-head">
  <h1>Setting</h1>
  <p>Manage platform administrator profile, access levels, and security standards.</p>
</div>

<!-- ADMIN PROFILE -->
<section class="card plans-card">
  <div class="client-card profile-block">
    <div class="client-main">
      <div class="client-avatar client-avatar-solid">AV</div>
      <div>
        <h2 class="client-name">Adv. Arthur Vance <span class="verify verify-verified">Verified Admin</span></h2>
        <p class="client-sub">arthur.vance@casehub.legal</p>
        <p class="client-sub caps">Super Administrator Credential &bull; ID: ADM-99983-LC</p>
      </div>
    </div>
    <span class="verified-pill">
      <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/><path d="M9 12l2 2 4-4"/></svg>
      Full Privileges
    </span>
  </div>

  <div class="setting-row">
    <span class="setting-icon">
      <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
    </span>
    <div class="setting-text">
      <strong>Password &amp; Security Compliance</strong>
      <small>SOC-2 standard requires strong alphanumeric passphrase with at least 14 characters.</small>
    </div>
    <button class="btn-sm" data-open="password-modal">
      <svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5"/></svg>
      Update Password
    </button>
  </div>
</section>

<!-- MANAGE PLANS -->
<div class="section-head">
  <div>
    <h2>Manage Subscription Plans</h2>
    <p>Configure client storage capacity quotas, pricing, and active status across the platform.</p>
  </div>
  <a href="{{ route('admin.create-plan') }}" class="btn-primary">+ Create New Plan</a>
</div>

<section class="card">
  <div class="selection-bar" id="selection-bar" hidden>
    <span><b id="selected-count">0</b> Plans Selected &bull; <button type="button" class="link-btn" id="unselect-all">Unselect All</button></span>
    <button type="button" class="btn-inline btn-danger-solid" id="bulk-deactivate">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
      Bulk Deactivate
    </button>
  </div>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th class="col-check"><input type="checkbox" id="select-all" aria-label="Select all plans" /></th>
          <th>Plan Name</th>
          <th>Storage Limit</th>
          <th>Price</th>
          <th>Duration</th>
          <th>Status</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody id="plans-body"></tbody>
    </table>
    <p class="empty-msg" id="plans-empty" hidden>No plans yet. Create your first plan.</p>
  </div>
</section>

<!-- ACCOUNT ACTIONS -->
<div class="section-head">
  <div>
    <h2>Account Actions</h2>
    <p>Session controls and high-impact administrative credential revocation.</p>
  </div>
</div>

<section class="card account-actions">
  <div class="setting-row">
    <span class="setting-icon">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
    </span>
    <div class="setting-text">
      <strong>Terminate Admin Session</strong>
      <small>Safely end your active supervisory administrative console session across this browser.</small>
    </div>
    <a href="{{ route('login') }}" class="btn-sm">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Logout
    </a>
  </div>

  <div class="setting-row setting-row-danger">
    <span class="setting-icon">
      <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
    </span>
    <div class="setting-text">
      <strong>Delete Admin Account</strong>
      <small>Permanently revoke super administration credentials and remove platform access.</small>
    </div>
    <button type="button" class="btn-inline btn-danger-solid" data-open="delete-modal">
      <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
      Delete Account
    </button>
  </div>
</section>
@endsection

@push('modals')
<!-- MODAL: DEACTIVATE PLANS -->
  <div class="modal-backdrop" id="deactivate-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="deactivate-title">
      <div class="modal-head">
        <span class="modal-icon">
          <svg viewBox="0 0 24 24"><path d="M12 3l9 16H3L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
        </span>
        <div>
          <h3 id="deactivate-title">Deactivate Selected Plans</h3>
          <p>Are you sure you want to deactivate the selected subscription plans?</p>
        </div>
      </div>

      <div class="affected-box">
        <span class="section-label" id="affected-label">Affected Plans</span>
        <div class="chip-row" id="affected-chips"></div>
        <p>Existing subscribed enterprises will remain intact, but these tiers will no longer appear on public purchase portals.</p>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-inline btn-light" data-close>Cancel</button>
        <button type="button" class="btn-inline btn-danger-solid" id="confirm-deactivate">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
          Deactivate
        </button>
      </div>
    </div>
  </div>

  <!-- MODAL: UPDATE PASSWORD -->
  <div class="modal-backdrop" id="password-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="password-title">
      <div class="modal-head">
        <span class="modal-icon modal-icon-primary">
          <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        </span>
        <div>
          <h3 id="password-title">Update Password</h3>
          <p>Use at least 14 characters with letters and numbers.</p>
        </div>
      </div>

      <form id="password-form" novalidate>
        <div class="form-row">
          <div class="form-label-row"><label for="current-password">Current Password</label></div>
          <input class="input" id="current-password" type="password" placeholder="••••••••" />
        </div>
        <div class="form-row">
          <div class="form-label-row"><label for="new-password">New Password</label></div>
          <input class="input" id="new-password" type="password" placeholder="••••••••" />
        </div>
        <div class="form-row">
          <div class="form-label-row"><label for="confirm-password">Confirm New Password</label></div>
          <input class="input" id="confirm-password" type="password" placeholder="••••••••" />
        </div>
        <p class="error-msg" id="password-error"></p>

        <div class="modal-actions">
          <button type="button" class="btn-inline btn-light" data-close>Cancel</button>
          <button type="submit" class="btn-inline btn-block-primary">Update Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: DELETE ACCOUNT -->
  <div class="modal-backdrop" id="delete-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="delete-title">
      <div class="modal-head">
        <span class="modal-icon">
          <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
        </span>
        <div>
          <h3 id="delete-title">Delete Admin Account</h3>
          <p>This permanently removes your administrator access. This cannot be undone.</p>
        </div>
      </div>

      <div class="form-row">
        <div class="form-label-row"><label for="delete-confirm">Type <b>DELETE</b> to confirm</label></div>
        <input class="input" id="delete-confirm" type="text" autocomplete="off" />
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-inline btn-light" data-close>Cancel</button>
        <button type="button" class="btn-inline btn-danger-solid" id="confirm-delete" disabled>Delete Account</button>
      </div>
    </div>
  </div>

@endpush

@push('scripts')
<script>
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = String(text);
  return div.innerHTML;
}

// Modals
function openModal(id) {
  document.getElementById(id).hidden = false;
}

function closeModal(modal) {
  modal.hidden = true;
}

document.querySelectorAll("[data-open]").forEach((btn) => {
  btn.addEventListener("click", () => openModal(btn.dataset.open));
});

document.querySelectorAll(".modal-backdrop").forEach((modal) => {
  modal.addEventListener("click", (e) => {
    if (e.target === modal || e.target.closest("[data-close]")) closeModal(modal);
  });
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") document.querySelectorAll(".modal-backdrop").forEach(closeModal);
});

// Plans are shared with {{ route('admin.subscriptions') }} and {{ route('admin.create-plan') }} through localStorage
const PLANS_KEY = "casehub-plans";
const DEFAULT_PLANS = [
  { id: "basic", name: "Basic", price: 99, storage: "500 MB", popular: false, desc: "Essential document vault for individual clients handling standard matter proceedings." },
  { id: "standard", name: "Standard", price: 199, storage: "1 GB", popular: true, desc: "Expanded capacity for corporate files, evidentiary exhibits, and continuous records." },
  { id: "premium", name: "Premium", price: 399, storage: "5 GB", popular: false, desc: "High-capacity tier for complex litigation dockets with multimedia and large forensic bundles." }
];

function loadPlans() {
  try {
    const saved = JSON.parse(localStorage.getItem(PLANS_KEY));
    if (Array.isArray(saved)) return saved;
  } catch (e) {}
  return DEFAULT_PLANS;
}

function savePlans() {
  // TODO: save plan status via backend API
  try { localStorage.setItem(PLANS_KEY, JSON.stringify(plans)); } catch (e) {}
}

let plans = loadPlans();
const selected = new Set();
let pendingDeactivate = [];

const plansBody = document.getElementById("plans-body");
const selectAll = document.getElementById("select-all");
const selectionBar = document.getElementById("selection-bar");

function isActive(plan) {
  return plan.active !== false;
}

function renderPlans() {
  plansBody.innerHTML = plans.map((p) => `
    <tr class="${selected.has(p.id) ? "row-selected" : ""}">
      <td class="col-check"><input type="checkbox" data-check="${escapeHtml(p.id)}" ${selected.has(p.id) ? "checked" : ""} aria-label="Select ${escapeHtml(p.name)}" /></td>
      <td><strong>${escapeHtml(p.name)}</strong><small class="clip-2">${escapeHtml(p.desc || "")}</small></td>
      <td><span class="count-pill">${escapeHtml(p.storage)}</span></td>
      <td><strong>₹${Number(p.price).toLocaleString("en-IN")}</strong></td>
      <td>One-time payment<small>Non-expiring</small></td>
      <td><span class="status ${isActive(p) ? "status-active" : "status-inactive"}">${isActive(p) ? "Active" : "Inactive"}</span></td>
      <td class="col-actions">
        <div class="row-actions">
          <a href="{{ route('admin.create-plan') }}?edit=${encodeURIComponent(p.id)}" class="btn-sm">Edit</a>
          ${isActive(p)
            ? `<button class="btn-sm btn-sm-danger" data-deactivate="${escapeHtml(p.id)}">Deactivate</button>`
            : `<button class="btn-sm btn-sm-primary" data-activate="${escapeHtml(p.id)}">Activate</button>`}
        </div>
      </td>
    </tr>`).join("");

  document.getElementById("plans-empty").hidden = plans.length > 0;
  selectionBar.hidden = selected.size === 0;
  document.getElementById("selected-count").textContent = selected.size;
  selectAll.checked = plans.length > 0 && selected.size === plans.length;
  selectAll.indeterminate = selected.size > 0 && selected.size < plans.length;
}

function askDeactivate(ids) {
  pendingDeactivate = ids;
  const names = plans.filter((p) => ids.includes(p.id));
  document.getElementById("affected-label").textContent = `Affected Plans (${names.length} Total)`;
  document.getElementById("affected-chips").innerHTML = names
    .map((p) => `<span class="chip chip-danger">${escapeHtml(p.name)} (${escapeHtml(p.storage)})</span>`)
    .join("");
  openModal("deactivate-modal");
}

plansBody.addEventListener("change", (e) => {
  const id = e.target.dataset.check;
  if (!id) return;
  e.target.checked ? selected.add(id) : selected.delete(id);
  renderPlans();
});

plansBody.addEventListener("click", (e) => {
  const deactivateBtn = e.target.closest("[data-deactivate]");
  const activateBtn = e.target.closest("[data-activate]");

  if (deactivateBtn) askDeactivate([deactivateBtn.dataset.deactivate]);

  if (activateBtn) {
    const plan = plans.find((p) => p.id === activateBtn.dataset.activate);
    plan.active = true;
    savePlans();
    renderPlans();
    showToast(`${plan.name} activated.`);
  }
});

selectAll.addEventListener("change", () => {
  selected.clear();
  if (selectAll.checked) plans.forEach((p) => selected.add(p.id));
  renderPlans();
});

document.getElementById("unselect-all").addEventListener("click", () => {
  selected.clear();
  renderPlans();
});

document.getElementById("bulk-deactivate").addEventListener("click", () => {
  askDeactivate([...selected]);
});

document.getElementById("confirm-deactivate").addEventListener("click", () => {
  plans.forEach((p) => {
    if (pendingDeactivate.includes(p.id)) p.active = false;
  });
  savePlans();
  selected.clear();
  renderPlans();
  closeModal(document.getElementById("deactivate-modal"));
  showToast(`${pendingDeactivate.length} plan${pendingDeactivate.length === 1 ? "" : "s"} deactivated.`);
});

renderPlans();

// Update password
const passwordForm = document.getElementById("password-form");
const passwordError = document.getElementById("password-error");

passwordForm.addEventListener("submit", (e) => {
  e.preventDefault();
  const current = document.getElementById("current-password").value;
  const next = document.getElementById("new-password").value;
  const confirmValue = document.getElementById("confirm-password").value;

  if (!current) return (passwordError.textContent = "Please enter your current password.");
  if (next.length < 14 || !/[a-z]/i.test(next) || !/\d/.test(next)) {
    return (passwordError.textContent = "New password must be at least 14 characters with letters and numbers.");
  }
  if (next !== confirmValue) return (passwordError.textContent = "Passwords do not match.");

  // TODO: update the password via backend API
  passwordError.textContent = "";
  passwordForm.reset();
  closeModal(document.getElementById("password-modal"));
  showToast("Password updated successfully.");
});

// Delete account
const deleteInput = document.getElementById("delete-confirm");
const deleteBtn = document.getElementById("confirm-delete");

deleteInput.addEventListener("input", () => {
  deleteBtn.disabled = deleteInput.value.trim() !== "DELETE";
});

deleteBtn.addEventListener("click", () => {
  // TODO: delete the admin account via backend API
  location.href = "{{ route('login') }}";
});
</script>
@endpush
