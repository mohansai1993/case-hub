@extends('layouts.admin')

@section('title', 'CaseHub Specializations')

@push('css')
<style>
  .area-edit-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .area-edit-form .input { width: 200px; }
  .area-edit-form [data-edit-error] { flex-basis: 100%; margin: 0; }
</style>
@endpush

@section('content')
<div class="page-head page-head-row">
  <div>
    <h1>Specializations</h1>
    <p>Manage the practice-area chips lawyers pick from at registration.</p>
  </div>
  <a href="{{ route('admin.lawyers') }}" class="btn-view">Back to Lawyers</a>
</div>

<!-- ADD SPECIALIZATION -->
<section class="card plans-card">
  <div class="card-head">
    <h2 class="card-title">
      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      Add Specialization
    </h2>
  </div>

  <form id="create-form" class="notif-form" novalidate>
    <div class="form-row">
      <div class="form-label-row"><label for="area-name">Name</label></div>
      <input class="input" id="area-name" type="text" maxlength="100" placeholder="e.g. Intellectual Property" />
    </div>
    <p class="error-msg" id="create-error"></p>
    <div class="form-actions">
      <button type="submit" class="btn-inline btn-block-primary">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Add Specialization
      </button>
    </div>
  </form>
</section>

<!-- ALL SPECIALIZATIONS -->
<section class="card list-card">
  <div class="card-head">
    <div>
      <h2>All Specializations</h2>
      <p>Shown as the "Practice areas / specialization" chips on lawyer registration.</p>
    </div>
    <span class="section-label">{{ $practiceAreas->count() }} total</span>
  </div>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Lawyers Using</th>
          <th>Status</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody id="areas-body">
        @forelse ($practiceAreas as $area)
          <tr data-row="{{ $area->id }}">
            <td>
              <span class="area-name" data-view>{{ $area->name }}</span>
              <form class="area-edit-form" data-edit-form hidden>
                <input class="input" type="text" value="{{ $area->name }}" maxlength="100" required />
                <button type="submit" class="btn-sm btn-sm-primary">Save</button>
                <button type="button" class="btn-sm" data-cancel-edit>Cancel</button>
                <p class="error-msg" data-edit-error></p>
              </form>
            </td>
            <td>{{ $area->lawyers_count }}</td>
            <td>
              <span class="status {{ $area->is_active ? 'status-active' : 'status-inactive' }}" data-status>
                {{ $area->is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="col-actions">
              <button type="button" class="btn-sm" data-edit-btn>Edit</button>
              <button type="button" class="btn-sm" data-toggle="{{ $area->id }}" data-active="{{ $area->is_active ? 1 : 0 }}">
                {{ $area->is_active ? 'Deactivate' : 'Activate' }}
              </button>
              <button type="button" class="btn-sm btn-sm-danger" data-delete="{{ $area->id }}"
                data-in-use="{{ $area->lawyers_count > 0 ? 1 : 0 }}">
                Delete
              </button>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="empty-msg">No specializations yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection

@include('admin.partials.moderation-scripts')

@push('scripts')
<script>
(function () {
  "use strict";

  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var COLORS = { primary: "#5547f5", danger: "#e0413a", cancel: "#8b8b94" };

  async function api(url, options) {
    options = options || {};
    const res = await fetch(url, Object.assign({}, options, {
      headers: Object.assign({
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF,
        "X-Requested-With": "XMLHttpRequest",
      }, options.body ? { "Content-Type": "application/json" } : {}, options.headers || {}),
    }));
    const body = await res.json().catch(function () { return {}; });
    if (!res.ok) {
      const error = new Error(body.message || "Something went wrong. Please try again.");
      error.errors = body.errors || null;
      throw error;
    }
    return body;
  }

  function firstError(err, fallback) {
    if (err.errors) { return Object.values(err.errors)[0][0]; }
    return err.message || fallback;
  }

  // Add specialization
  const createForm = document.getElementById("create-form");
  const nameInput = document.getElementById("area-name");
  const createError = document.getElementById("create-error");

  createForm.addEventListener("submit", async function (e) {
    e.preventDefault();
    const name = nameInput.value.trim();
    if (!name) {
      createError.textContent = "Please enter a name.";
      return;
    }
    createError.textContent = "";
    const btn = createForm.querySelector('button[type="submit"]');
    btn.disabled = true;

    try {
      await api("{{ route('admin.practice-areas.store') }}", { method: "POST", body: JSON.stringify({ name: name }) });
      window.location.reload();
    } catch (err) {
      createError.textContent = firstError(err, "Could not add this specialization.");
    } finally {
      btn.disabled = false;
    }
  });

  // Inline edit
  document.getElementById("areas-body").addEventListener("click", function (e) {
    const row = e.target.closest("tr[data-row]");
    if (!row) return;

    if (e.target.closest("[data-edit-btn]")) {
      row.querySelector("[data-view]").hidden = true;
      row.querySelector("[data-edit-form]").hidden = false;
      row.querySelector("[data-edit-form] input").focus();
    }

    if (e.target.closest("[data-cancel-edit]")) {
      row.querySelector("[data-view]").hidden = false;
      row.querySelector("[data-edit-form]").hidden = true;
    }

    const toggleBtn = e.target.closest("[data-toggle]");
    if (toggleBtn) {
      const isActive = toggleBtn.dataset.active === "1";
      Swal.fire({
        title: (isActive ? "Deactivate" : "Activate") + " this specialization?",
        text: isActive
          ? "Lawyers will no longer be able to pick it at registration."
          : "It will appear again on the registration screen.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, " + (isActive ? "deactivate" : "activate"),
        cancelButtonText: "Cancel",
        confirmButtonColor: isActive ? COLORS.danger : COLORS.primary,
        cancelButtonColor: COLORS.cancel,
        reverseButtons: true,
      }).then(function (result) {
        if (!result.isConfirmed) return;
        api("{{ url('admin/practice-areas') }}/" + toggleBtn.dataset.toggle + "/toggle", { method: "POST" })
          .then(function () { window.location.reload(); })
          .catch(function (err) { Swal.fire({ icon: "error", title: "Could not update", text: firstError(err, "Please try again.") }); });
      });
    }

    const deleteBtn = e.target.closest("[data-delete]");
    if (deleteBtn) {
      if (deleteBtn.dataset.inUse === "1") {
        Swal.fire({
          icon: "info",
          title: "Can't delete this specialization",
          text: "It's assigned to one or more lawyers. Deactivate it instead to hide it from new signups.",
        });
        return;
      }

      Swal.fire({
        title: "Delete this specialization?",
        text: "This cannot be undone.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete",
        cancelButtonText: "Cancel",
        confirmButtonColor: COLORS.danger,
        cancelButtonColor: COLORS.cancel,
        reverseButtons: true,
        focusCancel: true,
      }).then(function (result) {
        if (!result.isConfirmed) return;
        api("{{ url('admin/practice-areas') }}/" + deleteBtn.dataset.delete, { method: "DELETE" })
          .then(function () { window.location.reload(); })
          .catch(function (err) { Swal.fire({ icon: "error", title: "Could not delete", text: firstError(err, "Please try again.") }); });
      });
    }
  });

  document.getElementById("areas-body").addEventListener("submit", async function (e) {
    const form = e.target.closest("[data-edit-form]");
    if (!form) return;
    e.preventDefault();

    const row = form.closest("tr[data-row]");
    const errorBox = form.querySelector("[data-edit-error]");
    const name = form.querySelector("input").value.trim();
    if (!name) {
      errorBox.textContent = "Please enter a name.";
      return;
    }
    errorBox.textContent = "";

    try {
      await api("{{ url('admin/practice-areas') }}/" + row.dataset.row, {
        method: "PUT",
        body: JSON.stringify({ name: name }),
      });
      window.location.reload();
    } catch (err) {
      errorBox.textContent = firstError(err, "Could not save this change.");
    }
  });
})();
</script>
@endpush
