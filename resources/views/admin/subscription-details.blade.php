@extends('layouts.admin')

@section('title', 'CaseHub Subscription Details')

@section('content')
<div class="page-head page-head-row">
  <div>
    <h1 class="title-back">
      <a href="{{ route('admin.subscriptions') }}" class="back-btn" aria-label="Back to subscriptions">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
      </a>
      Subscription Details
    </h1>
    <p>Manage all registered client accounts.</p>
  </div>

  <a href="{{ route('admin.client-details') }}" class="user-chip">
    <span class="mini-avatar">RS</span>
    <strong>Rahul Sharma</strong>
    <small>rahul.sharma@corpmail.com</small>
  </a>
</div>

<!-- CURRENT REQUEST -->
<section class="card request-card">
  <div class="card-head">
    <div class="plans-title">
      <span class="stat-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
      <div>
        <h2>Current Subscription Request</h2>
        <p>Review and take action on the client's immediate storage subscription request.</p>
      </div>
    </div>
    <div class="badge-row">
      <span class="tier-pill">Tier Pro</span>
      <span class="status status-pending-approval" data-request-status>Pending Approval</span>
    </div>
  </div>

  <div class="request-grid">
    <ul class="kv-list">
      <li><span>Subscription Plan</span><strong><span class="dot dot-blue"></span> Professional Tier</strong></li>
      <li><span>Storage Limit</span><strong>50 GB</strong></li>
      <li><span>Subscription Status</span><strong><span class="status status-pending-approval" data-request-status>Pending Approval</span></strong></li>
      <li><span>Request Date</span><strong>14 Oct 2026</strong></li>
      <li><span>Subscription Price</span><strong class="price-strong">₹499 <span class="pill-soft">One-time payment</span></strong></li>
    </ul>

    <div class="workflow-box">
      <span class="overline">
        <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/></svg>
        Admin Approval Workflow
      </span>
      <p>Rahul Sharma has requested to upgrade to the <strong>Professional (50 GB)</strong> subscription plan. Review the requested quota and confirm administrative action.</p>
      <div class="note">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
        Upon approval, the storage quota is allocated and status switches to Active.
      </div>
      <div class="inline-btns" id="request-actions">
        <button class="btn-inline btn-danger-soft" id="reject-btn">Reject</button>
        <button class="btn-inline btn-block-primary" id="approve-btn">Approve Request</button>
      </div>
    </div>
  </div>
</section>

<!-- HISTORY -->
<section class="card list-card">
  <div class="card-head">
    <div>
      <h2>Subscription History</h2>
      <p>All historical requests and purchases submitted by this client.</p>
    </div>
    <span class="section-label">3 Requests Recorded</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Subscription Plan</th>
          <th>Storage Limit</th>
          <th>Subscription Price</th>
          <th>Request Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong class="inline-strong"><span class="dot dot-orange"></span> Professional <span class="tag-upgrade">Upgrade</span></strong></td>
          <td>50 GB</td>
          <td><strong class="inline-strong">₹499</strong> <small class="inline-muted">One-time</small></td>
          <td>14 Oct 2026</td>
          <td><span class="status status-pending-approval" data-request-status>Pending Approval</span></td>
        </tr>
        <tr>
          <td><strong class="inline-strong">Plus Plan</strong></td>
          <td>25 GB</td>
          <td><strong class="inline-strong">₹199</strong> <small class="inline-muted">One-time</small></td>
          <td>12 Jan 2026</td>
          <td><span class="status status-active">Active</span></td>
        </tr>
        <tr>
          <td><strong class="inline-strong">Starter Plan</strong></td>
          <td>10 GB</td>
          <td><strong class="inline-strong">₹99</strong> <small class="inline-muted">One-time</small></td>
          <td>25 Aug 2025</td>
          <td><span class="status status-rejected">Rejected</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</section>
@endsection

@push('scripts')
<script>
// Request actions — TODO: call backend API
function setRequestStatus(label, className) {
  document.querySelectorAll("[data-request-status]").forEach((badge) => {
    badge.className = `status ${className}`;
    badge.textContent = label;
  });
  document.getElementById("request-actions").hidden = true;
}

document.getElementById("approve-btn").addEventListener("click", () => {
  setRequestStatus("Active", "status-active");
});

document.getElementById("reject-btn").addEventListener("click", () => {
  if (confirm("Reject this subscription request?")) {
    setRequestStatus("Rejected", "status-rejected");
  }
});
</script>
@endpush
