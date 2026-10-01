@extends('layouts.admin')

@section('title', 'CaseHub Settings')

@section('content')
@php
  $me = auth('admin')->user();
@endphp

<div class="page-head">
  <h1>Setting</h1>
  <p>Manage platform administrator profile, access levels, and security standards.</p>
</div>

<!-- ADMIN PROFILE -->
<section class="card plans-card">
  <div class="client-card profile-block">
    <div class="client-main">
      <div class="client-avatar client-avatar-solid">{{ $me->initials() }}</div>
      <div>
        <h2 class="client-name">{{ $me->name }} <span class="verify verify-verified">{{ $me->status->label() }} Admin</span></h2>
        <p class="client-sub">{{ $me->email }}</p>
        <p class="client-sub caps">{{ $me->isSuperAdmin() ? 'Super Administrator Credential' : ($me->role->name ?? 'No role') }}</p>
      </div>
    </div>
    <span class="verified-pill">
      <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/><path d="M9 12l2 2 4-4"/></svg>
      {{ $me->isSuperAdmin() ? 'Full Privileges' : ($me->role->name ?? 'No Role') }}
    </span>
  </div>

  <div class="setting-row">
    <span class="setting-icon">
      <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
    </span>
    <div class="setting-text">
      <strong>Password &amp; Security Compliance</strong>
      <small>Passwords require at least 8 characters with uppercase, lowercase and a number.</small>
    </div>
    <button type="button" class="btn-sm" data-open="password-modal">
      <svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5"/></svg>
      Update Password
    </button>
  </div>
</section>

@if ($me->hasPermission('subscriptions.manage'))
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
        <tbody id="plans-body">
          @foreach ($plans as $plan)
            <tr data-row="{{ $plan->id }}">
              <td class="col-check"><input type="checkbox" data-check="{{ $plan->id }}" aria-label="Select {{ $plan->name }}" @disabled(! $plan->is_active) /></td>
              <td><strong>{{ $plan->name }}</strong><small class="clip-2">{{ $plan->description }}</small></td>
              <td><span class="count-pill">{{ $plan->storageLabel() }}</span></td>
              <td><strong>{{ config('billing.currency_symbol') }}{{ number_format($plan->price) }}</strong></td>
              <td>Monthly<small>Auto-renewing</small></td>
              <td><span class="status {{ $plan->is_active ? 'status-active' : 'status-inactive' }}" data-status>{{ $plan->is_active ? 'Active' : 'Inactive' }}</span></td>
              <td class="col-actions">
                <div class="row-actions">
                  <a href="{{ route('admin.plans.edit', $plan) }}" class="btn-sm">Edit</a>
                  <button
                    type="button"
                    class="btn-sm {{ $plan->is_active ? 'btn-sm-danger' : 'btn-sm-primary' }}"
                    data-toggle="{{ route('admin.plans.toggle', $plan) }}"
                    data-name="{{ $plan->name }}"
                    data-active="{{ $plan->is_active ? 1 : 0 }}"
                  >
                    {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                  </button>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <p class="empty-msg" id="plans-empty" @unless ($plans->isEmpty()) hidden @endunless>No plans yet. Create your first plan.</p>
    </div>
  </section>
@endif

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
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="btn-sm">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Logout
      </button>
    </form>
  </div>

  @unless ($me->isSuperAdmin())
    <div class="setting-row setting-row-danger">
      <span class="setting-icon">
        <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
      </span>
      <div class="setting-text">
        <strong>Delete Admin Account</strong>
        <small>Permanently revoke your administration credentials and remove platform access.</small>
      </div>
      <button type="button" class="btn-inline btn-danger-solid" data-open="delete-modal">
        <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
        Delete Account
      </button>
    </div>
  @endunless
</section>
@endsection

@push('modals')
@if ($me->hasPermission('subscriptions.manage'))
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
@endif

<!-- MODAL: UPDATE PASSWORD -->
<div class="modal-backdrop" id="password-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="password-title">
    <div class="modal-head">
      <span class="modal-icon modal-icon-primary">
        <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
      </span>
      <div>
        <h3 id="password-title">Update Password</h3>
        <p>Use at least 8 characters with uppercase, lowercase and a number.</p>
      </div>
    </div>

    <form id="password-form" novalidate>
      <div class="form-row">
        <div class="form-label-row"><label for="current-password">Current Password</label></div>
        <input class="input" id="current-password" type="password" autocomplete="current-password" placeholder="••••••••" />
      </div>
      <div class="form-row">
        <div class="form-label-row"><label for="new-password">New Password</label></div>
        <input class="input" id="new-password" type="password" autocomplete="new-password" placeholder="••••••••" />
      </div>
      <div class="form-row">
        <div class="form-label-row"><label for="confirm-password">Confirm New Password</label></div>
        <input class="input" id="confirm-password" type="password" autocomplete="new-password" placeholder="••••••••" />
      </div>
      <p class="error-msg" id="password-error"></p>

      <div class="modal-actions">
        <button type="button" class="btn-inline btn-light" data-close>Cancel</button>
        <button type="submit" class="btn-inline btn-block-primary">Update Password</button>
      </div>
    </form>
  </div>
</div>

@unless ($me->isSuperAdmin())
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

      <p class="error-msg" id="delete-error"></p>

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
@endunless
@endpush

@push('scripts')
@include('admin.partials.moderation-scripts')
<script>
(function () {
  "use strict";

  var CSRF = document.querySelector('meta[name="csrf-token"]').content;

  function api(url, options) {
    options = options || {};
    return fetch(url, Object.assign({}, options, {
      headers: Object.assign({
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF,
        "X-Requested-With": "XMLHttpRequest",
      }, options.body ? { "Content-Type": "application/json" } : {}, options.headers || {}),
    })).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, status: res.status, body: body }; });
    });
  }

  function firstError(result, fallback) {
    var errors = result.body.errors;
    return errors ? Object.values(errors)[0][0] : (result.body.message || fallback);
  }

  // Modals
  function openModal(id) { document.getElementById(id).hidden = false; }
  function closeModal(modal) { modal.hidden = true; }

  document.querySelectorAll("[data-open]").forEach(function (btn) {
    btn.addEventListener("click", function () { openModal(btn.dataset.open); });
  });

  document.querySelectorAll(".modal-backdrop").forEach(function (modal) {
    modal.addEventListener("click", function (e) {
      if (e.target === modal || e.target.closest("[data-close]")) closeModal(modal);
    });
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") document.querySelectorAll(".modal-backdrop").forEach(closeModal);
  });

  // ---- Plans table (activate/deactivate + bulk) --------------------------

  var plansBody = document.getElementById("plans-body");

  if (plansBody) {
    var selected = new Set();
    var pendingDeactivate = [];
    var selectAll = document.getElementById("select-all");
    var selectionBar = document.getElementById("selection-bar");

    function refreshSelection() {
      selectionBar.hidden = selected.size === 0;
      document.getElementById("selected-count").textContent = selected.size;
      var rows = plansBody.querySelectorAll("[data-check]:not(:disabled)");
      selectAll.checked = rows.length > 0 && selected.size === rows.length;
      selectAll.indeterminate = selected.size > 0 && selected.size < rows.length;
      plansBody.querySelectorAll("tr[data-row]").forEach(function (row) {
        row.classList.toggle("row-selected", selected.has(row.dataset.row));
      });
    }

    plansBody.addEventListener("change", function (e) {
      var id = e.target.dataset.check;
      if (!id) return;
      e.target.checked ? selected.add(id) : selected.delete(id);
      refreshSelection();
    });

    selectAll.addEventListener("change", function () {
      selected.clear();
      if (selectAll.checked) {
        plansBody.querySelectorAll("[data-check]:not(:disabled)").forEach(function (cb) {
          selected.add(cb.dataset.check);
          cb.checked = true;
        });
      } else {
        plansBody.querySelectorAll("[data-check]").forEach(function (cb) { cb.checked = false; });
      }
      refreshSelection();
    });

    document.getElementById("unselect-all").addEventListener("click", function () {
      selected.clear();
      plansBody.querySelectorAll("[data-check]").forEach(function (cb) { cb.checked = false; });
      refreshSelection();
    });

    function askDeactivate(ids) {
      pendingDeactivate = ids;
      var names = ids.map(function (id) {
        return plansBody.querySelector('tr[data-row="' + id + '"] strong').textContent;
      });
      document.getElementById("affected-label").textContent = "Affected Plans (" + names.length + " Total)";
      document.getElementById("affected-chips").innerHTML = names
        .map(function (name) { return '<span class="chip chip-danger">' + name.replace(/</g, "&lt;") + "</span>"; })
        .join("");
      openModal("deactivate-modal");
    }

    document.getElementById("bulk-deactivate").addEventListener("click", function () {
      askDeactivate([...selected]);
    });

    document.getElementById("confirm-deactivate").addEventListener("click", function () {
      api("{{ route('admin.plans.bulk-deactivate') }}", { method: "POST", body: JSON.stringify({ ids: pendingDeactivate }) })
        .then(function () { window.location.reload(); });
    });

    plansBody.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-toggle]");
      if (!btn) return;

      var active = btn.dataset.active === "1";
      Swal.fire({
        title: (active ? "Deactivate" : "Activate") + ' "' + btn.dataset.name + '"?',
        icon: active ? "warning" : "question",
        showCancelButton: true,
        confirmButtonText: "Yes, " + (active ? "deactivate" : "activate"),
        cancelButtonText: "Cancel",
        confirmButtonColor: active ? "#e0413a" : "#5547f5",
        cancelButtonColor: "#8b8b94",
        reverseButtons: true,
      }).then(function (result) {
        if (!result.isConfirmed) return;
        api(btn.dataset.toggle, { method: "POST" }).then(function () { window.location.reload(); });
      });
    });
  }

  // ---- Update password -----------------------------------------------------

  var passwordForm = document.getElementById("password-form");
  var passwordError = document.getElementById("password-error");

  passwordForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var current = document.getElementById("current-password").value;
    var next = document.getElementById("new-password").value;
    var confirmValue = document.getElementById("confirm-password").value;

    if (!current) { passwordError.textContent = "Please enter your current password."; return; }
    if (next.length < 8 || !/[a-z]/.test(next) || !/[A-Z]/.test(next) || !/\d/.test(next)) {
      passwordError.textContent = "New password must be at least 8 characters with uppercase, lowercase and a number.";
      return;
    }
    if (next !== confirmValue) { passwordError.textContent = "Passwords do not match."; return; }
    passwordError.textContent = "";

    api("{{ route('admin.settings.password') }}", {
      method: "PUT",
      body: JSON.stringify({ current_password: current, password: next, password_confirmation: confirmValue }),
    }).then(function (result) {
      if (!result.ok) {
        passwordError.textContent = firstError(result, "Could not update the password.");
        return;
      }
      passwordForm.reset();
      closeModal(document.getElementById("password-modal"));
      showToast("Password updated successfully.");
    });
  });

  // ---- Delete account -----------------------------------------------------

  var deleteInput = document.getElementById("delete-confirm");
  var deleteBtn = document.getElementById("confirm-delete");

  if (deleteBtn) {
    var deleteError = document.getElementById("delete-error");

    deleteInput.addEventListener("input", function () {
      deleteBtn.disabled = deleteInput.value.trim() !== "DELETE";
    });

    deleteBtn.addEventListener("click", function () {
      deleteBtn.disabled = true;

      api("{{ route('admin.settings.destroy') }}", {
        method: "DELETE",
        body: JSON.stringify({ confirmation: deleteInput.value.trim() }),
      }).then(function (result) {
        if (!result.ok) {
          deleteError.textContent = result.body.message || "Could not delete your account.";
          deleteBtn.disabled = false;
          return;
        }
        window.location.href = "{{ route('login') }}";
      });
    });
  }
})();
</script>
@endpush
