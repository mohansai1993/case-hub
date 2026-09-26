@extends('layouts.admin')

@section('title', 'CaseHub Client Details')

@section('content')
<div class="page-head">
  <h1 class="title-back">
    <a href="{{ route('admin.clients') }}" class="back-btn" aria-label="Back to clients">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    Client details
  </h1>
  <p>Manage all registered client accounts.</p>
</div>

<!-- CLIENT HEADER -->
<section class="card client-card">
  <div class="client-main">
    <div class="client-avatar">RS</div>
    <div>
      <h2 class="client-name">Rahul Sharma <span class="status status-active">Active</span></h2>
      <p class="client-sub">Client ID: #CL-10492 &bull; Corporate Counsel &bull; Vertex Innovations Ltd</p>
    </div>
  </div>
  <span class="verified-pill">
    <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4Z"/><path d="M9 12l2 2 4-4"/></svg>
    Identity Verified
  </span>
</section>

<div class="detail-grid">

  <!-- LEFT -->
  <div class="detail-col">
    <section class="card">
      <div class="card-head">
        <h2 class="card-title">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M6 16a3 3 0 0 1 6 0M14 9h4M14 13h4"/></svg>
          Client Information
        </h2>
        <span class="section-label">Account Metadata</span>
      </div>

      <div class="info-grid">
        <div class="info-item">
          <span>Full Name</span>
          <strong>Rahul Sharma</strong>
        </div>
        <div class="info-item">
          <span>Email</span>
          <a href="mailto:rahul.sharma@corpmail.com" class="info-link">rahul.sharma@corpmail.com</a>
        </div>
        <div class="info-item">
          <span>Phone Number</span>
          <strong>+1 (555) 234-8901</strong>
        </div>
        <div class="info-item">
          <span>Account Status</span>
          <strong class="text-green"><span class="dot dot-green"></span> Active</strong>
        </div>
        <div class="info-item">
          <span>Registration Date</span>
          <strong>14 Oct 2026</strong>
        </div>
        <div class="info-item">
          <span>Default Jurisdiction</span>
          <strong>New York (SDNY / 2nd Cir.)</strong>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <h2 class="card-title">
          <svg viewBox="0 0 24 24"><path d="M3 7h18v13H3zM8 7V4h8v3"/></svg>
          Case Information
        </h2>
        <span class="section-label">Docket's Summary</span>
      </div>

      <div class="case-stats">
        <div class="case-stat">
          <span>Total Cases</span>
          <strong>12</strong>
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h6"/></svg>
        </div>
        <div class="case-stat">
          <span>New Cases</span>
          <strong class="text-primary">2</strong>
          <svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M12 9v6M9 12h6"/></svg>
        </div>
        <div class="case-stat">
          <span>In Progress</span>
          <strong class="text-primary">5</strong>
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>
        </div>
        <div class="case-stat case-stat-wide">
          <span>Lawyer Assigned</span>
          <strong>4 <small>active retainers</small></strong>
          <svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M12 8v6M9 11l3 3 3-3"/></svg>
        </div>
        <div class="case-stat">
          <span>Closed Cases</span>
          <strong class="text-muted">1</strong>
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
        </div>
      </div>
    </section>
  </div>

  <!-- RIGHT -->
  <div class="detail-col">
    <section class="card">
      <div class="card-head">
        <h2 class="card-title">
          <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
          Subscription
        </h2>
        <span class="tier-pill">Tier Pro</span>
      </div>

      <ul class="kv-list">
        <li><span>Current Plan</span><strong class="text-primary">Professional</strong></li>
        <li><span>Subscription Status</span><strong class="text-green">Active (Paid)</strong></li>
        <li><span>Start Date</span><strong>14 Oct 2026</strong></li>
        <li><span>Renewal Date</span><strong>14 Oct 2027 <small>(Annual)</small></strong></li>
      </ul>
    </section>

    <section class="card">
      <div class="card-head">
        <h2 class="card-title">
          <svg viewBox="0 0 24 24"><path d="M17.5 19a4.5 4.5 0 1 0-1.4-8.8A6 6 0 0 0 4.5 12 3.5 3.5 0 0 0 6 19Z"/></svg>
          Storage Information
        </h2>
        <span class="section-label">36.8% Used</span>
      </div>

      <div class="progress progress-lg"><span style="width:36.8%"></span></div>
      <div class="progress-scale"><span>0 GB</span><span>50 GB Limit</span></div>

      <div class="storage-boxes">
        <div><span>Storage Used</span><strong>18.4 GB</strong></div>
        <div><span>Storage Limit</span><strong>50 GB</strong></div>
        <div><span>Remaining</span><strong class="text-primary">31.6 GB</strong></div>
      </div>
    </section>

    <section class="card">
      <h2 class="card-title">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        Account Actions
      </h2>

      <div class="action-btns">
        <button class="btn-block btn-block-outline" id="deactivate-btn">
          <svg viewBox="0 0 24 24"><path d="M12 2v10M18.4 6.6a9 9 0 1 1-12.8 0"/></svg>
          Deactivate Account
        </button>
        <button class="btn-block btn-block-primary" id="approve-btn">
          <svg viewBox="0 0 24 24"><path d="M12 3l9 16H3L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
          Approve
        </button>
      </div>
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
// Account actions — TODO: call backend API
document.getElementById("deactivate-btn").addEventListener("click", () => {
  if (confirm("Are you sure you want to deactivate this account?")) {
    alert("Account deactivated.");
  }
});

document.getElementById("approve-btn").addEventListener("click", () => {
  alert("Account approved.");
});
</script>
@endpush
