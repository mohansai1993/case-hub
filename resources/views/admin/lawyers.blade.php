@extends('layouts.admin')

@section('title', 'CaseHub Lawyers')

@section('content')
<div class="page-head">
  <h1>Lawyers</h1>
  <p>Manage all registered lawyer accounts.</p>
</div>

<section class="card list-card">
  <div class="toolbar">
    <label class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="text" id="lawyer-search" placeholder="Search lawyers..." />
    </label>

    <div class="filters">
      <select id="status-filter" aria-label="Account status">
        <option value="">All Statuses</option>
        <option value="Active">Active</option>
        <option value="Inactive">Inactive</option>
        <option value="Suspended">Suspended</option>
      </select>
      <select id="verify-filter" aria-label="Verification status">
        <option value="">All Verifications</option>
        <option value="Verified">Verified</option>
        <option value="Pending">Pending</option>
        <option value="Rejected">Rejected</option>
      </select>
      <select id="spec-filter" aria-label="Specialization">
        <option value="">All Specializations</option>
      </select>
    </div>
  </div>

  <div class="table-wrap">
    <table class="clients-table">
      <thead>
        <tr>
          <th>Lawyer Name</th>
          <th>Email / Contact</th>
          <th>Specialization</th>
          <th>Total Cases</th>
          <th>Account Status</th>
          <th>Verification Status</th>
          <th>Joined Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="lawyers-body"></tbody>
    </table>
    <p class="empty-msg" id="empty-msg" hidden>No lawyers match your search.</p>
  </div>

  <div class="table-footer">
    <span id="showing-text"></span>
    <div class="pagination">
      <button class="page-btn page-text" disabled>Previous</button>
      <button class="page-btn is-active">1</button>
      <button class="page-btn">2</button>
      <button class="page-btn">3</button>
      <span class="page-dots">…</span>
      <button class="page-btn">36</button>
      <button class="page-btn page-text">Next</button>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
// Lawyers list (static sample data — replace with API data later)
const TOTAL_LAWYERS = "284";

const lawyers = [
  { name: "Adv. Sarah Williams", bar: "Bar #NY-88210", email: "s.williams@jurislex.com", phone: "+1 (555) 349-8821", spec: "Corporate & IP Law", cases: 24, status: "Active", verify: "Verified", joined: "12 Oct 2026" },
  { name: "Adv. John Smith", bar: "Bar #NY-73419", email: "j.smith@smithlegal.com", phone: "+1 (555) 762-9012", spec: "Real Estate Litigation", cases: 18, status: "Active", verify: "Verified", joined: "18 Sep 2026" },
  { name: "Adv. Elena Rostova", bar: "Bar #CA-90412", email: "e.rostova@rostovalaw.io", phone: "+1 (555) 891-2345", spec: "Commercial Arbitration", cases: 12, status: "Active", verify: "Verified", joined: "04 Oct 2026" },
  { name: "Adv. Marcus Vance", bar: "Bar #TX-44120", email: "m.vance@vancelegal.com", phone: "+1 (555) 431-7789", spec: "Corporate Governance", cases: 6, status: "Inactive", verify: "Pending", joined: "16 Oct 2026" },
  { name: "Adv. David Chen", bar: "Bar #IL-61108", email: "d.chen@chenpartners.com", phone: "+1 (555) 622-3341", spec: "Employment & Labor", cases: 15, status: "Active", verify: "Verified", joined: "22 Jul 2026" },
  { name: "Adv. Priya Verma", bar: "Bar #MA-52904", email: "p.verma@vermalegal.com", phone: "+1 (555) 224-9988", spec: "Family & Divorce", cases: 9, status: "Active", verify: "Verified", joined: "06 Aug 2026" },
  { name: "Adv. Michael Chang", bar: "Bar #NJ-31998", email: "m.chang@apexdefense.com", phone: "+1 (555) 912-1144", spec: "Criminal Defense", cases: 4, status: "Suspended", verify: "Rejected", joined: "14 Jan 2026" },
  { name: "Adv. Sophia Martinez", bar: "Bar #WA-71245", email: "s.martinez@martinezfirm.com", phone: "+1 (555) 342-8871", spec: "Civil Litigation", cases: 2, status: "Inactive", verify: "Pending", joined: "24 Oct 2026" }
];

const tbody = document.getElementById("lawyers-body");
const searchInput = document.getElementById("lawyer-search");
const statusFilter = document.getElementById("status-filter");
const verifyFilter = document.getElementById("verify-filter");
const specFilter = document.getElementById("spec-filter");
const emptyMsg = document.getElementById("empty-msg");
const showingText = document.getElementById("showing-text");

[...new Set(lawyers.map((l) => l.spec))].sort().forEach((spec) => {
  specFilter.add(new Option(spec, spec));
});

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function initials(name) {
  return name.replace(/^Adv\.\s*/, "").split(" ").map((w) => w[0]).slice(0, 2).join("");
}

function renderLawyers() {
  const query = searchInput.value.trim().toLowerCase();
  const status = statusFilter.value;
  const verify = verifyFilter.value;
  const spec = specFilter.value;

  const list = lawyers.filter((l) =>
    (!query || [l.name, l.email, l.phone, l.bar, l.spec].some((v) => v.toLowerCase().includes(query))) &&
    (!status || l.status === status) &&
    (!verify || l.verify === verify) &&
    (!spec || l.spec === spec)
  );

  tbody.innerHTML = list.map((l) => `
    <tr>
      <td>
        <div class="person">
          <span class="mini-avatar">${initials(l.name)}</span>
          <div><strong>${escapeHtml(l.name)}</strong><small>${escapeHtml(l.bar)}</small></div>
        </div>
      </td>
      <td>${escapeHtml(l.email)}<small>${escapeHtml(l.phone)}</small></td>
      <td>${escapeHtml(l.spec)}</td>
      <td><span class="count-pill">${l.cases}</span></td>
      <td><span class="status status-${l.status.toLowerCase()}">${l.status}</span></td>
      <td><span class="verify verify-${l.verify.toLowerCase()}">${l.verify}</span></td>
      <td>${escapeHtml(l.joined)}</td>
      <td><a href="{{ route('admin.lawyer-details') }}" class="btn-view">View</a></td>
    </tr>`).join("");

  emptyMsg.hidden = list.length > 0;
  const filtered = query || status || verify || spec;
  showingText.textContent = filtered
    ? `Showing ${list.length} matching lawyer${list.length === 1 ? "" : "s"}`
    : `Showing 1 to ${list.length} of ${TOTAL_LAWYERS} registered lawyers`;
}

[searchInput, statusFilter, verifyFilter, specFilter].forEach((el) => el.addEventListener("input", renderLawyers));
renderLawyers();
</script>
@endpush
