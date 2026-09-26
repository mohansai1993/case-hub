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
      <input class="input" id="notif-title" type="text" placeholder="Enter notification title" />
    </div>
    <div class="form-row">
      <div class="form-label-row"><label for="notif-message">Message</label></div>
      <textarea class="input" id="notif-message" rows="4" placeholder="Enter notification message"></textarea>
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

// Sample data — replace with API data later
let drafts = [
  { id: 1, title: "Docket Schedule Notice & Filing Deadline", message: "All court filings for Q4 trial dockets must be uploaded to the portal by Friday 5:00 PM EST.", created: "15 Oct 2026" },
  { id: 2, title: "Court Hearing Reschedule Advisory", message: "Upcoming hearings under the commercial division have been rescheduled; please review the updated calendar.", created: "14 Oct 2026" },
  { id: 3, title: "Quarterly Retainer & Invoicing Notice", message: "Trust account disbursements and retainer invoices for the quarter are now available for review.", created: "12 Oct 2026" }
];

const recipients = {
  Clients: [
    { id: "#CL-10492", name: "Rahul Sharma", email: "rahul.sharma@corpmail.com", status: "Engaged" },
    { id: "#CL-10511", name: "Priya Verma", email: "priya.verma@novanest.com", status: "Engaged" },
    { id: "#CL-10388", name: "David Chen", email: "david.chen@mindmail.com", status: "Inactive" },
    { id: "#CL-10604", name: "Elena Rostova", email: "e.rostova@techglobal.io", status: "Engaged" },
    { id: "#CL-10277", name: "Ananya Patel", email: "ananya.patel@brightmart.in", status: "Engaged" }
  ],
  Lawyers: [
    { id: "#NY-88210", name: "Adv. Sarah Williams", email: "s.williams@jurislex.com", status: "Engaged" },
    { id: "#NY-73419", name: "Adv. John Smith", email: "j.smith@smithlegal.com", status: "Engaged" },
    { id: "#TX-44120", name: "Adv. Marcus Vance", email: "m.vance@vancelegal.com", status: "Inactive" },
    { id: "#IL-61108", name: "Adv. David Chen", email: "d.chen@chenpartners.com", status: "Engaged" }
  ]
};

let sent = [
  { title: "Docket Schedule Notice & Filing Deadline", to: "Rahul Sharma, Priya Verma", type: "Clients", date: "15 Oct 2026, 14:22" },
  { title: "Urgent Discovery Hearing Reschedule", to: "Adv. Sarah Williams", type: "Lawyers", date: "14 Oct 2026, 11:05" },
  { title: "Annual Retainer Invoicing Ready", to: "All Clients", type: "Clients", date: "12 Oct 2026, 09:30" },
  { title: "Bar Association Policy Guidelines 2027", to: "All Lawyers", type: "Lawyers", date: "10 Oct 2026, 16:45" },
  { title: "System Upgrade & Portal Downtime Notice", to: "All Clients", type: "Clients", date: "08 Oct 2026, 18:00" },
  { title: "Emergency Court Filing Server Notice", to: "Adv. Marcus Vance", type: "Lawyers", date: "05 Oct 2026, 08:15" }
];

let selectedDraftId = drafts[0].id;
let selected = new Set(["#CL-10492", "#CL-10511"]);

// Create notification
const createForm = document.getElementById("create-form");
const titleInput = document.getElementById("notif-title");
const messageInput = document.getElementById("notif-message");
const createError = document.getElementById("create-error");

createForm.addEventListener("submit", (e) => {
  e.preventDefault();
  const title = titleInput.value.trim();
  const message = messageInput.value.trim();
  if (!title || !message) {
    createError.textContent = "Please enter both a title and a message.";
    return;
  }
  createError.textContent = "";
  // TODO: save the draft via backend API
  const draft = { id: Date.now(), title, message, created: formatDate(new Date()) };
  drafts.unshift(draft);
  selectedDraftId = draft.id;
  createForm.reset();
  renderDrafts();
});

// Created notifications
const draftsBody = document.getElementById("drafts-body");
const selectedDraftBox = document.getElementById("selected-draft");

function renderDrafts() {
  document.getElementById("drafts-count").textContent = `${drafts.length} Saved`;
  draftsBody.innerHTML = drafts.map((d) => {
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
        </td>
      </tr>`;
  }).join("");

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

draftsBody.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-select]");
  if (!btn) return;
  selectedDraftId = Number(btn.dataset.select);
  renderDrafts();
});

// Recipients
const sendTo = document.getElementById("send-to");
const recipientSearch = document.getElementById("recipient-search");
const recipientList = document.getElementById("recipient-list");
const chipRow = document.getElementById("chip-row");
const bulkToggle = document.getElementById("bulk-toggle");
const bulkText = document.getElementById("bulk-text");

function currentList() {
  return recipients[sendTo.value];
}

function renderRecipients() {
  const query = recipientSearch.value.trim().toLowerCase();
  const list = currentList().filter((r) =>
    !query || [r.name, r.email, r.id].some((v) => v.toLowerCase().includes(query))
  );

  recipientList.classList.toggle("is-disabled", bulkToggle.checked);
  recipientList.innerHTML = list.length
    ? list.map((r) => `
        <li>
          <label class="recipient-item">
            <input type="checkbox" value="${escapeHtml(r.id)}" ${selected.has(r.id) ? "checked" : ""} ${bulkToggle.checked ? "disabled" : ""} />
            <span class="mini-avatar">${escapeHtml(r.name.replace(/^Adv\.\s*/, "").split(" ").map((w) => w[0]).join("").slice(0, 2))}</span>
            <span class="recipient-info">
              <strong>${escapeHtml(r.name)}</strong>
              <small>${escapeHtml(r.email)}</small>
            </span>
            <span class="id-pill">${escapeHtml(r.id)}</span>
            <span class="status ${r.status === "Engaged" ? "status-active" : "status-inactive"}">${r.status}</span>
          </label>
        </li>`).join("")
    : '<li class="empty-msg">No matches found.</li>';

  const chosen = currentList().filter((r) => selected.has(r.id));
  chipRow.innerHTML = bulkToggle.checked
    ? `<span class="chip-label">Sending to all ${sendTo.value.toLowerCase()}</span>`
    : chosen.length
      ? `<span class="chip-label">${chosen.length} selected:</span>` +
        chosen.map((r) => `<span class="chip">${escapeHtml(r.name)}<button type="button" data-remove="${escapeHtml(r.id)}" aria-label="Remove">×</button></span>`).join("")
      : '<span class="chip-label">No recipients selected</span>';
}

recipientList.addEventListener("change", (e) => {
  if (e.target.type !== "checkbox") return;
  e.target.checked ? selected.add(e.target.value) : selected.delete(e.target.value);
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
  renderRecipients();
});

recipientSearch.addEventListener("input", renderRecipients);
bulkToggle.addEventListener("change", renderRecipients);

// Send notification
const sendError = document.getElementById("send-error");

document.getElementById("send-btn").addEventListener("click", () => {
  const draft = drafts.find((d) => d.id === selectedDraftId);
  const chosen = currentList().filter((r) => selected.has(r.id));

  if (!draft) {
    sendError.textContent = "Please select a notification draft first.";
    return;
  }
  if (!bulkToggle.checked && !chosen.length) {
    sendError.textContent = "Please select at least one recipient.";
    return;
  }
  sendError.textContent = "";

  // TODO: send the notification via backend API
  sent.unshift({
    title: draft.title,
    to: bulkToggle.checked ? `All ${sendTo.value}` : chosen.map((r) => r.name).join(", "),
    type: sendTo.value,
    date: formatDateTime(new Date())
  });

  selected.clear();
  bulkToggle.checked = false;
  renderRecipients();
  renderSent();
  document.getElementById("sent-body").closest("section").scrollIntoView({ behavior: "smooth" });
});

// Sent notifications
function renderSent() {
  document.getElementById("sent-count").textContent = `${sent.length} Dispatched`;
  document.getElementById("sent-body").innerHTML = sent.map((s) => `
    <tr>
      <td><strong>${escapeHtml(s.title)}</strong></td>
      <td>${escapeHtml(s.to)}</td>
      <td><span class="plan ${s.type === "Clients" ? "plan-basic" : "plan-premium"}">${escapeHtml(s.type)}</span></td>
      <td>${escapeHtml(s.date)}</td>
      <td><span class="status status-active">Delivered</span></td>
    </tr>`).join("");
}

renderDrafts();
renderRecipients();
renderSent();
</script>
@endpush
