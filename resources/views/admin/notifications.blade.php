@extends('layouts.admin')

@section('title', 'CaseHub Notifications')

@section('content')
<div class="page-head">
  <h1>Notifications</h1>
  <p>Draft, target, and broadcast administrative notices to legal operations network.</p>
</div>

<!-- CREATE NOTIFICATION -->
<section class="card plans-card">
  <div class="card-head">
    <h2 class="card-title">
      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      Create Notification
    </h2>
    <span class="tier-pill">Draft Composition</span>
  </div>

  <form id="create-form" class="notif-form" novalidate>
    <div class="form-row">
      <div class="form-label-row"><label for="notif-title">Notification Title</label></div>
      <input class="input" id="notif-title" type="text" maxlength="150" placeholder="Enter notification title" />
    </div>
    <div class="form-row">
      <div class="form-label-row"><label for="notif-message">Message</label></div>
      <textarea class="input" id="notif-message" rows="4" maxlength="1000" placeholder="Enter notification message"></textarea>
    </div>
    <p class="error-msg" id="create-error"></p>
    <div class="form-actions">
      <button type="submit" class="btn-inline btn-block-primary">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Create Notification
      </button>
    </div>
  </form>
</section>

<!-- CREATED NOTIFICATIONS -->
<section class="card list-card">
  <div class="card-head">
    <div>
      <h2>Created Notifications</h2>
      <p>Drafted announcements pending selection for dispatch.</p>
    </div>
    <span class="section-label" id="drafts-count"></span>
  </div>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Notification Title</th>
          <th>Message</th>
          <th>Created Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="drafts-body"></tbody>
    </table>
  </div>
</section>

<!-- SELECT RECIPIENT -->
<section class="card list-card">
  <div class="card-head">
    <h2 class="card-title">
      <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M19 8v6M16 11h6"/></svg>
      Select Recipient
    </h2>
    <span class="section-label">Targeting Configuration</span>
  </div>

  <div class="selected-draft" id="selected-draft"></div>

  <div class="form-row recipient-row">
    <div class="form-label-row"><label for="send-to">Send To</label></div>
    <select class="input" id="send-to">
      <option value="Clients">Clients</option>
      <option value="Lawyers">Lawyers</option>
    </select>
  </div>

  <label class="search-box recipient-search">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
    <input type="text" id="recipient-search" placeholder="Search clients..." />
  </label>

  <div class="chip-row" id="chip-row"></div>

  <ul class="recipient-list" id="recipient-list"></ul>

  <label class="bulk-box">
    <input type="checkbox" id="bulk-toggle" />
    <span>
      <strong>Bulk Targeting Policy</strong>
      <small id="bulk-text">When checked, the notification is sent to all registered clients instead of the individual selection above.</small>
    </span>
  </label>

  <p class="error-msg" id="send-error"></p>
  <div class="form-actions">
    <button type="button" class="btn-inline btn-block-primary" id="send-btn">
      <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
      Send Notification
    </button>
  </div>
</section>

<!-- SENT NOTIFICATIONS -->
<section class="card list-card">
  <div class="card-head">
    <div>
      <h2>Sent Notifications</h2>
      <p>Audit log of all messages and alerts transmitted and dispatched by admins.</p>
    </div>
    <span class="section-label" id="sent-count"></span>
  </div>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Notification Title</th>
          <th>Recipients</th>
          <th>Recipient Type</th>
          <th>Sent Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="sent-body"></tbody>
    </table>
  </div>
</section>
@endsection

@push('scripts')
<script>
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = String(text);
  return div.innerHTML;
}

function formatDate(date) {
  return date.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
}

function formatDateTime(date) {
  const time = date.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit" });
  return `${formatDate(date)}, ${time}`;
}

function initials(name) {
  const clean = String(name).replace(/^Adv\.\s*/, "");
  return clean.split(/\s+/).map((w) => w[0] || "").join("").slice(0, 2).toUpperCase();
}

function statusLabel(status) {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || "";
const ROUTES = {
  createDraft: @json(route('admin.notifications.drafts.store')),
  deleteDraftBase: @json(url('admin/notifications/drafts')),
  recipients: @json(route('admin.notifications.recipients')),
  send: @json(route('admin.notifications.send')),
};

async function api(url, options = {}) {
  const res = await fetch(url, {
    ...options,
    headers: {
      "Accept": "application/json",
      "X-CSRF-TOKEN": CSRF_TOKEN,
      "X-Requested-With": "XMLHttpRequest",
      ...(options.body ? { "Content-Type": "application/json" } : {}),
      ...(options.headers || {}),
    },
  });
  const body = await res.json().catch(() => ({}));
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

// Seeded from the server on page load.
let drafts = @json($draftsForJs);
let sent = @json($sentForJs);

let selectedDraftId = drafts.length ? drafts[0].id : null;

// id -> {id, reference, name, email, status}, kept even while the recipient
// list is re-filtered by search so chips never lose their label.
let selected = new Map();
let currentRecipients = [];
let recipientsLoading = false;
let recipientRequestToken = 0;

function audienceType(sendToLabel) {
  return sendToLabel === "Lawyers" ? "lawyer" : "client";
}

// Create notification
const createForm = document.getElementById("create-form");
const titleInput = document.getElementById("notif-title");
const messageInput = document.getElementById("notif-message");
const createError = document.getElementById("create-error");

createForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const title = titleInput.value.trim();
  const message = messageInput.value.trim();
  if (!title || !message) {
    createError.textContent = "Please enter both a title and a message.";
    return;
  }
  createError.textContent = "";

  const submitBtn = createForm.querySelector('button[type="submit"]');
  submitBtn.disabled = true;

  try {
    const res = await api(ROUTES.createDraft, { method: "POST", body: JSON.stringify({ title, message }) });
    const draft = { id: res.data.id, title: res.data.title, message: res.data.message, created: formatDate(new Date()) };
    drafts.unshift(draft);
    selectedDraftId = draft.id;
    createForm.reset();
    renderDrafts();
  } catch (err) {
    createError.textContent = firstError(err, "Could not create the notification. Please try again.");
  } finally {
    submitBtn.disabled = false;
  }
});

// Created notifications
const draftsBody = document.getElementById("drafts-body");
const selectedDraftBox = document.getElementById("selected-draft");

function renderDrafts() {
  document.getElementById("drafts-count").textContent = `${drafts.length} Saved`;
  draftsBody.innerHTML = drafts.length ? drafts.map((d) => {
    const isSelected = d.id === selectedDraftId;
    return `
      <tr class="${isSelected ? "row-selected" : ""}">
        <td><strong>${escapeHtml(d.title)}</strong>${isSelected ? '<small class="text-primary">Ready for targeting</small>' : ""}</td>
        <td class="cell-clip">${escapeHtml(d.message)}</td>
        <td>${escapeHtml(d.created)}</td>
        <td>
          ${isSelected
            ? '<span class="btn-view btn-view-selected">Selected</span>'
            : `<button class="btn-sm" data-select="${d.id}">Select</button>`}
          <button class="btn-sm btn-sm-danger" data-delete-draft="${d.id}">Delete</button>
        </td>
      </tr>`;
  }).join("") : '<tr><td colspan="4" class="empty-msg">No notification drafts yet.</td></tr>';

  const draft = drafts.find((d) => d.id === selectedDraftId);
  selectedDraftBox.innerHTML = draft
    ? `<div class="selected-draft-head">
         <span class="overline">Selected Notification Draft</span>
         <span class="pill-soft">Active Selection</span>
       </div>
       <strong>${escapeHtml(draft.title)}</strong>
       <p>${escapeHtml(draft.message)}</p>`
    : '<p class="text-muted">Select a notification draft above.</p>';
}

draftsBody.addEventListener("click", async (e) => {
  const selectBtn = e.target.closest("[data-select]");
  if (selectBtn) {
    selectedDraftId = Number(selectBtn.dataset.select);
    renderDrafts();
    return;
  }

  const deleteBtn = e.target.closest("[data-delete-draft]");
  if (deleteBtn) {
    const confirmed = await Swal.fire({
      title: "Delete this notification draft?",
      text: "This cannot be undone.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#e0413a",
      cancelButtonColor: "#8b8b94",
      reverseButtons: true,
      focusCancel: true,
    }).then((result) => result.isConfirmed);

    if (!confirmed) return;

    const id = Number(deleteBtn.dataset.deleteDraft);
    deleteBtn.disabled = true;

    try {
      await api(`${ROUTES.deleteDraftBase}/${id}`, { method: "DELETE" });
      drafts = drafts.filter((d) => d.id !== id);
      if (selectedDraftId === id) {
        selectedDraftId = drafts.length ? drafts[0].id : null;
      }
      renderDrafts();
    } catch (err) {
      Swal.fire({ icon: "error", title: "Could not delete", text: firstError(err, "Could not delete this draft. Please try again.") });
      deleteBtn.disabled = false;
    }
  }
});

// Recipients
const sendTo = document.getElementById("send-to");
const recipientSearch = document.getElementById("recipient-search");
const recipientList = document.getElementById("recipient-list");
const chipRow = document.getElementById("chip-row");
const bulkToggle = document.getElementById("bulk-toggle");
const bulkText = document.getElementById("bulk-text");

async function loadRecipients() {
  const token = ++recipientRequestToken;
  recipientsLoading = true;
  renderRecipients();

  const params = new URLSearchParams({ type: audienceType(sendTo.value) });
  const q = recipientSearch.value.trim();
  if (q) params.set("q", q);

  try {
    const res = await api(`${ROUTES.recipients}?${params.toString()}`);
    if (token !== recipientRequestToken) return; // a newer search is already in flight
    currentRecipients = res.data;
  } catch (err) {
    if (token !== recipientRequestToken) return;
    currentRecipients = [];
  } finally {
    if (token === recipientRequestToken) {
      recipientsLoading = false;
      renderRecipients();
    }
  }
}

function renderRecipients() {
  recipientList.classList.toggle("is-disabled", bulkToggle.checked);

  recipientList.innerHTML = currentRecipients.length
    ? currentRecipients.map((r) => `
        <li>
          <label class="recipient-item">
            <input type="checkbox" value="${escapeHtml(r.id)}" ${selected.has(r.id) ? "checked" : ""} ${bulkToggle.checked ? "disabled" : ""} />
            <span class="mini-avatar">${escapeHtml(initials(r.name))}</span>
            <span class="recipient-info">
              <strong>${escapeHtml(r.name)}</strong>
              <small>${escapeHtml(r.email)}</small>
            </span>
            <span class="id-pill">${escapeHtml(r.reference)}</span>
            <span class="status ${r.status === "active" ? "status-active" : "status-inactive"}">${escapeHtml(statusLabel(r.status))}</span>
          </label>
        </li>`).join("")
    : `<li class="empty-msg">${recipientsLoading ? "Loading…" : "No matches found."}</li>`;

  chipRow.innerHTML = bulkToggle.checked
    ? `<span class="chip-label">Sending to all ${sendTo.value.toLowerCase()}</span>`
    : selected.size
      ? `<span class="chip-label">${selected.size} selected:</span>` +
        Array.from(selected.values()).map((r) => `<span class="chip">${escapeHtml(r.name)}<button type="button" data-remove="${escapeHtml(r.id)}" aria-label="Remove">×</button></span>`).join("")
      : '<span class="chip-label">No recipients selected</span>';
}

recipientList.addEventListener("change", (e) => {
  if (e.target.type !== "checkbox") return;
  const id = e.target.value;
  if (e.target.checked) {
    const record = currentRecipients.find((r) => r.id === id);
    if (record) selected.set(id, record);
  } else {
    selected.delete(id);
  }
  renderRecipients();
});

chipRow.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-remove]");
  if (!btn) return;
  selected.delete(btn.dataset.remove);
  renderRecipients();
});

sendTo.addEventListener("change", () => {
  selected.clear();
  recipientSearch.value = "";
  recipientSearch.placeholder = `Search ${sendTo.value.toLowerCase()}...`;
  bulkText.textContent = `When checked, the notification is sent to all registered ${sendTo.value.toLowerCase()} instead of the individual selection above.`;
  loadRecipients();
});

let searchDebounce;
recipientSearch.addEventListener("input", () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(loadRecipients, 300);
});

bulkToggle.addEventListener("change", renderRecipients);

// Send notification
const sendError = document.getElementById("send-error");
const sendBtn = document.getElementById("send-btn");

sendBtn.addEventListener("click", async () => {
  const draft = drafts.find((d) => d.id === selectedDraftId);

  if (!draft) {
    sendError.textContent = "Please select a notification draft first.";
    return;
  }
  if (!bulkToggle.checked && selected.size === 0) {
    sendError.textContent = "Please select at least one recipient.";
    return;
  }
  sendError.textContent = "";
  sendBtn.disabled = true;

  try {
    const res = await api(ROUTES.send, {
      method: "POST",
      body: JSON.stringify({
        draft_id: draft.id,
        audience: audienceType(sendTo.value),
        bulk: bulkToggle.checked,
        user_ids: bulkToggle.checked ? [] : Array.from(selected.keys()),
      }),
    });

    sent.unshift({
      title: res.data.title,
      to: res.data.to,
      type: res.data.type,
      date: formatDateTime(new Date(res.data.date)),
    });

    selected.clear();
    bulkToggle.checked = false;
    renderRecipients();
    renderSent();
    document.getElementById("sent-body").closest("section").scrollIntoView({ behavior: "smooth" });
  } catch (err) {
    sendError.textContent = firstError(err, "Could not send the notification. Please try again.");
  } finally {
    sendBtn.disabled = false;
  }
});

// Sent notifications
function renderSent() {
  document.getElementById("sent-count").textContent = `${sent.length} Dispatched`;
  document.getElementById("sent-body").innerHTML = sent.length ? sent.map((s) => `
    <tr>
      <td><strong>${escapeHtml(s.title)}</strong></td>
      <td>${escapeHtml(s.to)}</td>
      <td><span class="plan ${s.type === "Clients" ? "plan-basic" : "plan-premium"}">${escapeHtml(s.type)}</span></td>
      <td>${escapeHtml(s.date)}</td>
      <td><span class="status status-active">Delivered</span></td>
    </tr>`).join("") : '<tr><td colspan="5" class="empty-msg">No notifications sent yet.</td></tr>';
}

renderDrafts();
loadRecipients();
renderSent();
</script>
@endpush
