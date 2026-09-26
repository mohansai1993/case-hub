@extends('layouts.admin')

@section('title', 'CaseHub Lawyer Details')

@section('content')
<div class="page-head">
  <h1 class="title-back">
    <a href="{{ route('admin.lawyers') }}" class="back-btn" aria-label="Back to lawyers">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    Lawyer details
  </h1>
  <p>Manage all registered lawyer accounts.</p>
</div>

<!-- LAWYER HEADER -->
<section class="card client-card">
  <div class="client-main">
    <div class="client-avatar avatar-online">SW</div>
    <div>
      <h2 class="client-name">
        Adv. Sarah Williams
        <span class="status status-active">Active</span>
        <span class="verify verify-verified">Verified</span>
      </h2>
      <p class="client-sub">Bar #NY-88210 &bull; Corporate &amp; IP Law &bull; Williams &amp; Associates</p>
    </div>
  </div>
</section>

<div class="detail-stack">

  <!-- LAWYER INFORMATION -->
  <section class="card">
    <div class="card-head">
      <div>
        <span class="overline">Bar Credentials &amp; Metadata</span>
        <h2>Lawyer Information</h2>
      </div>
      <svg class="head-icon" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/></svg>
    </div>

    <div class="info-grid info-grid-3">
      <div class="info-item">
        <span>Full Name</span>
        <strong>Adv. Sarah Williams</strong>
      </div>
      <div class="info-item">
        <span>Email</span>
        <a href="mailto:s.williams@jurislex.com" class="info-link">
          s.williams@jurislex.com
          <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
        </a>
      </div>
      <div class="info-item">
        <span>Phone Number</span>
        <strong>+1 (555) 349-8821</strong>
      </div>
      <div class="info-item">
        <span>Specialization</span>
        <strong>Corporate &amp; IP Law</strong>
      </div>
      <div class="info-item">
        <span>Registration Date</span>
        <strong>12 Oct 2026</strong>
      </div>
      <div class="info-item">
        <span>Account Status</span>
        <strong><span class="status status-active">Active</span></strong>
      </div>
      <div class="info-item">
        <span>Verification Status</span>
        <strong><span class="verify verify-verified">Verified</span></strong>
      </div>
    </div>
  </section>

  <!-- CASE INFORMATION -->
  <section class="card">
    <div class="card-head">
      <div>
        <span class="overline">Caseload Summary</span>
        <h2>Case Information</h2>
      </div>
      <svg class="head-icon" viewBox="0 0 24 24"><path d="M14 4l6 6M4 20l8-8M11 7l6 6M8 10l6 6"/></svg>
    </div>

    <div class="case-stats case-stats-5">
      <div class="case-stat">
        <span>Total Cases</span>
        <strong>24</strong>
        <svg class="ic-primary" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h6"/></svg>
      </div>
      <div class="case-stat">
        <span>New Cases</span>
        <strong>3</strong>
        <svg class="ic-primary" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M12 9v6M9 12h6"/></svg>
      </div>
      <div class="case-stat">
        <span>Pending</span>
        <strong>4</strong>
        <svg class="ic-orange" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
      </div>
      <div class="case-stat">
        <span>In Progress</span>
        <strong>14</strong>
        <svg class="ic-primary" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>
      </div>
      <div class="case-stat">
        <span>Closed</span>
        <strong>3</strong>
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
      </div>
    </div>
  </section>

  <!-- ACCOUNT MANAGEMENT -->
  <section class="card">
    <div class="card-head">
      <div>
        <span class="overline">Administrative Controls</span>
        <h2>Account Management</h2>
      </div>
      <svg class="head-icon" viewBox="0 0 24 24"><circle cx="11" cy="8" r="4"/><path d="M3 21a8 8 0 0 1 11-7.4"/><circle cx="18" cy="17" r="3"/></svg>
    </div>

    <div class="inline-btns">
      <button class="btn-inline btn-danger-soft" id="suspend-btn">
        <svg viewBox="0 0 24 24"><path d="M12 3l9 16H3L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
        Suspend Account
      </button>
      <button class="btn-inline btn-block-primary" id="approve-btn">
        <svg viewBox="0 0 24 24"><path d="M12 3l9 16H3L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
        Approve
      </button>
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script>
// Account actions — TODO: call backend API
document.getElementById("suspend-btn").addEventListener("click", () => {
  if (confirm("Are you sure you want to suspend this lawyer's account?")) {
    alert("Account suspended.");
  }
});

document.getElementById("approve-btn").addEventListener("click", () => {
  alert("Lawyer approved.");
});
</script>
@endpush
