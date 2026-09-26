@extends('layouts.admin')

@section('title', 'CaseHub Clients')

@section('content')
<div class="page-head">
  <h1>Clients</h1>
  <p>Manage all registered client accounts.</p>
</div>

<section class="card list-card">
  <div class="toolbar">
    <label class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="text" id="client-search" placeholder="Search clients..." />
    </label>

    <div class="filters">
      <label>
        Status:
        <select id="status-filter">
          <option value="">All Statuses</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
          <option value="Suspended">Suspended</option>
        </select>
      </label>
      <label>
        Subscription:
        <select id="plan-filter">
          <option value="">All Plans</option>
          <option value="Professional">Professional</option>
          <option value="Premium">Premium</option>
          <option value="Basic">Basic</option>
          <option value="Free">Free</option>
          <option value="No Subscription">No Subscription</option>
        </select>
      </label>
    </div>
  </div>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Client Name</th>
          <th>Email / Contact</th>
          <th>Total Cases</th>
          <th>Subscription</th>
          <th>Storage Used</th>
          <th>Account Status</th>
          <th>Joined Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="clients-body"></tbody>
    </table>
    <p class="empty-msg" id="empty-msg" hidden>No clients match your search.</p>
  </div>

  <div class="table-footer">
    <span id="showing-text"></span>
    <div class="pagination">
      <button class="page-btn page-text" disabled>Previous</button>
      <button class="page-btn is-active">1</button>
      <button class="page-btn">2</button>
      <button class="page-btn">3</button>
      <span class="page-dots">…</span>
      <button class="page-btn">179</button>
      <button class="page-btn page-text">Next</button>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
// Clients list (static sample data — replace with API data later)
const TOTAL_CLIENTS = "1,428";
const STORAGE_LIMIT_GB = 50;

const clients = [
  { name: "Rahul Sharma", role: "Corporate Counsel", email: "rahul.sharma@corpmail.com", phone: "+1 (555) 234-8901", cases: 12, plan: "Professional", storage: 18.4, status: "Active", joined: "14 Oct 2026" },
  { name: "Elena Rostova", role: "Business Owner", email: "e.rostova@techglobal.io", phone: "+1 (555) 876-2231", cases: 8, plan: "Premium", storage: 32.6, status: "Active", joined: "02 Oct 2026" },
  { name: "David Chen", role: "Individual", email: "david.chen@mindmail.com", phone: "+1 (555) 312-7788", cases: 3, plan: "Basic", storage: 4.2, status: "Inactive", joined: "25 Sep 2026" },
  { name: "Priya Verma", role: "Startup Founder", email: "priya.verma@novanest.com", phone: "+1 (555) 654-1209", cases: 1, plan: "Free", storage: 1.2, status: "Active", joined: "18 Sep 2026" },
  { name: "Michael Chang", role: "Real Estate", email: "m.chang@propertylaw.co", phone: "+1 (555) 981-4410", cases: 5, plan: "Professional", storage: 21.5, status: "Suspended", joined: "10 Sep 2026" },
  { name: "Sophia Martinez", role: "Healthcare Group", email: "smartinez@medcorp.org", phone: "+1 (555) 447-3920", cases: 0, plan: "No Subscription", storage: 0, status: "Inactive", joined: "04 Sep 2026" },
  { name: "Jonathan Cole", role: "Logistics Firm", email: "j.cole@colefreight.com", phone: "+1 (555) 702-6612", cases: 9, plan: "Premium", storage: 27.9, status: "Active", joined: "28 Aug 2026" },
  { name: "Ananya Patel", role: "Retail Chain", email: "ananya.patel@brightmart.in", phone: "+1 (555) 219-5534", cases: 6, plan: "Basic", storage: 9.8, status: "Active", joined: "21 Aug 2026" }
];

const planClass = {
  "Professional": "plan-pro",
  "Premium": "plan-premium",
  "Basic": "plan-basic",
  "Free": "plan-free",
  "No Subscription": "plan-none"
};

const tbody = document.getElementById("clients-body");
const searchInput = document.getElementById("client-search");
const statusFilter = document.getElementById("status-filter");
const planFilter = document.getElementById("plan-filter");
const emptyMsg = document.getElementById("empty-msg");
const showingText = document.getElementById("showing-text");

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function renderClients() {
  const query = searchInput.value.trim().toLowerCase();
  const status = statusFilter.value;
  const plan = planFilter.value;

  const list = clients.filter((c) =>
    (!query || [c.name, c.email, c.phone, c.role].some((v) => v.toLowerCase().includes(query))) &&
    (!status || c.status === status) &&
    (!plan || c.plan === plan)
  );

  tbody.innerHTML = list.map((c) => {
    const percent = Math.min(100, (c.storage / STORAGE_LIMIT_GB) * 100);
    return `
      <tr>
        <td><strong>${escapeHtml(c.name)}</strong><small>${escapeHtml(c.role)}</small></td>
        <td>${escapeHtml(c.email)}<small>${escapeHtml(c.phone)}</small></td>
        <td><span class="count-pill">${c.cases}</span></td>
        <td><span class="plan ${planClass[c.plan]}">${escapeHtml(c.plan)}</span></td>
        <td>
          <div class="storage-cell">
            <span>${c.storage} GB</span>
            <div class="progress"><span style="width:${percent}%"></span></div>
          </div>
        </td>
        <td><span class="status status-${c.status.toLowerCase()}">${c.status}</span></td>
        <td>${escapeHtml(c.joined)}</td>
        <td><a href="{{ route('admin.client-details') }}" class="btn-view">View</a></td>
      </tr>`;
  }).join("");

  emptyMsg.hidden = list.length > 0;
  const filtered = query || status || plan;
  showingText.textContent = filtered
    ? `Showing ${list.length} matching client${list.length === 1 ? "" : "s"}`
    : `Showing 1 to ${list.length} of ${TOTAL_CLIENTS} registered clients`;
}

[searchInput, statusFilter, planFilter].forEach((el) => el.addEventListener("input", renderClients));
renderClients();
</script>
@endpush
