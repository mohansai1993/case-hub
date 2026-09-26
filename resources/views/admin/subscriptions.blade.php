@extends('layouts.admin')

@section('title', 'CaseHub Subscriptions')

@section('content')
<div class="page-head">
  <h1>Subscriptions</h1>
  <p>Manage client subscriptions and subscription plans.</p>
</div>

<!-- SUBSCRIPTION PLANS -->
<section class="card plans-card">
  <div class="card-head">
    <div class="plans-title">
      <span class="stat-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
      <div>
        <h2>Subscription Plans</h2>
        <p>Configure legal client storage tiers, pricing models, and docket quotas.</p>
      </div>
    </div>
    <a href="{{ route('admin.create-plan') }}" class="btn-primary">+ Create Plan</a>
  </div>

  <div class="plan-grid" id="plan-grid"></div>
  <p class="empty-msg" id="plans-empty" hidden>No plans yet. Create your first plan.</p>
</section>

<!-- SUBSCRIPTIONS LIST -->
<section class="card list-card">
  <div class="toolbar">
    <label class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="text" id="sub-search" placeholder="Search subscriptions..." />
    </label>

    <div class="filters">
      <label>
        Plan:
        <select id="plan-filter">
          <option value="">All Plans</option>
          <option value="Professional">Professional</option>
          <option value="Premium">Premium</option>
          <option value="Basic">Basic</option>
          <option value="Free">Free</option>
        </select>
      </label>
    </div>
  </div>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Client Name</th>
          <th>Subscription Plan</th>
          <th>Storage Limit</th>
          <th>Storage Used</th>
          <th>Start Date</th>
          <th>Renewal Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="subs-body"></tbody>
    </table>
    <p class="empty-msg" id="empty-msg" hidden>No subscriptions match your search.</p>
  </div>

  <div class="table-footer">
    <span id="showing-text"></span>
    <div class="pagination">
      <button class="page-btn page-text" disabled>Previous</button>
      <button class="page-btn is-active">1</button>
      <button class="page-btn">2</button>
      <button class="page-btn">3</button>
      <span class="page-dots">…</span>
      <button class="page-btn">148</button>
      <button class="page-btn page-text">Next</button>
    </div>
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

// Subscription plans — saved in this browser (localStorage) until a backend exists
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

function savePlans(plans) {
  try { localStorage.setItem(PLANS_KEY, JSON.stringify(plans)); } catch (e) {}
}

let plans = loadPlans();
const planGrid = document.getElementById("plan-grid");
const plansEmpty = document.getElementById("plans-empty");

function renderPlans() {
  planGrid.innerHTML = plans.map((p) => `
    <article class="plan-card${p.popular ? " is-popular" : ""}${p.active === false ? " is-inactive" : ""}">
      ${p.popular ? '<span class="popular-tag">Popular Tier</span>' : ""}
      <div class="plan-card-top">
        <span class="plan-name">
          <span class="dot dot-blue"></span>${escapeHtml(p.name)}
          ${p.active === false ? '<span class="status status-inactive">Inactive</span>' : ""}
        </span>
        <span class="pill-soft">One-time payment</span>
      </div>
      <div class="plan-price">₹${escapeHtml(p.price)} <small>/ flat fee</small></div>
      <span class="storage-chip">
        <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>
        ${escapeHtml(p.storage)} Storage
      </span>
      <p class="plan-desc">${escapeHtml(p.desc)}</p>
      <div class="plan-actions">
        <a href="{{ route('admin.create-plan') }}?edit=${encodeURIComponent(p.id)}" class="btn-sm">
          <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
          Edit
        </a>
        <button class="btn-sm btn-sm-danger" data-delete="${escapeHtml(p.id)}">
          <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
          Delete
        </button>
      </div>
    </article>`).join("");
  plansEmpty.hidden = plans.length > 0;
}

planGrid.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-delete]");
  if (!btn) return;
  const plan = plans.find((p) => p.id === btn.dataset.delete);
  if (plan && confirm(`Delete the "${plan.name}" plan?`)) {
    plans = plans.filter((p) => p.id !== plan.id);
    savePlans(plans);
    renderPlans();
  }
});

renderPlans();

// Subscriptions list (static sample data — replace with API data later)
const TOTAL_SUBS = "1,180";

const subs = [
  { name: "Rahul Sharma", role: "Corporate Counsel", plan: "Professional", limit: 50, used: 18.4, start: "14 Oct 2026", renew: "14 Oct 2027" },
  { name: "Elena Ramirez", role: "Technology Firm", plan: "Premium", limit: 20, used: 14.2, start: "15 Oct 2026", renew: "15 Oct 2027" },
  { name: "David Chen", role: "Individual Client", plan: "Basic", limit: 10, used: 4.1, start: "22 Sep 2026", renew: "22 Sep 2027" },
  { name: "Michael Chang", role: "Real Estate", plan: "Professional", limit: 50, used: 32, start: "18 Jun 2026", renew: "18 Jun 2027" },
  { name: "Jonathan Cole", role: "Logistics Firm", plan: "Premium", limit: 20, used: 17.2, start: "11 May 2026", renew: "11 May 2027" },
  { name: "Priya Verma", role: "Startup Founder", plan: "Free", limit: 5, used: 1.8, start: "04 Aug 2026", renew: "04 Aug 2027" },
  { name: "Marcus Vance", role: "Venture Partners", plan: "Professional", limit: 50, used: 22.4, start: "09 Jan 2026", renew: "09 Jan 2027" },
  { name: "Sophie Martinez", role: "Healthcare Group", plan: "Basic", limit: 10, used: 3.9, start: "20 Oct 2026", renew: "20 Oct 2027" }
];

const planClass = {
  "Professional": "plan-pro",
  "Premium": "plan-premium",
  "Basic": "plan-basic",
  "Free": "plan-free"
};

const tbody = document.getElementById("subs-body");
const searchInput = document.getElementById("sub-search");
const planFilter = document.getElementById("plan-filter");
const emptyMsg = document.getElementById("empty-msg");
const showingText = document.getElementById("showing-text");

function initials(name) {
  return name.split(" ").map((w) => w[0]).slice(0, 2).join("");
}

function renderSubs() {
  const query = searchInput.value.trim().toLowerCase();
  const plan = planFilter.value;

  const list = subs.filter((s) =>
    (!query || [s.name, s.role, s.plan].some((v) => v.toLowerCase().includes(query))) &&
    (!plan || s.plan === plan)
  );

  tbody.innerHTML = list.map((s) => {
    const percent = Math.min(100, (s.used / s.limit) * 100);
    return `
      <tr>
        <td>
          <div class="person">
            <span class="mini-avatar">${initials(s.name)}</span>
            <div><strong>${escapeHtml(s.name)}</strong><small>${escapeHtml(s.role)}</small></div>
          </div>
        </td>
        <td><span class="plan ${planClass[s.plan]}">${escapeHtml(s.plan)}</span></td>
        <td><span class="count-pill">${s.limit} GB</span></td>
        <td>
          <div class="storage-cell">
            <span>${s.used} GB <small class="inline-muted">/ ${s.limit} GB</small></span>
            <div class="progress"><span style="width:${percent}%"></span></div>
          </div>
        </td>
        <td>${escapeHtml(s.start)}</td>
        <td>${escapeHtml(s.renew)}</td>
        <td><a href="{{ route('admin.subscription-details') }}" class="btn-view">View</a></td>
      </tr>`;
  }).join("");

  emptyMsg.hidden = list.length > 0;
  showingText.textContent = query || plan
    ? `Showing ${list.length} matching subscription${list.length === 1 ? "" : "s"}`
    : `Showing 1 to ${list.length} of ${TOTAL_SUBS} subscriptions`;
}

[searchInput, planFilter].forEach((el) => el.addEventListener("input", renderSubs));
renderSubs();
</script>
@endpush
