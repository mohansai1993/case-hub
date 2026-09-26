@extends('layouts.admin')

@section('title', 'CaseHub Dashboard')

@section('content')
<div class="page-head">
  <h1>Dashboard</h1>
  <p>Manage clients, lawyers, cases and subscriptions from one place.</p>
</div>

<!-- STAT CARDS -->
<section class="stats">
  <div class="stat-card">
    <div class="stat-top">
      <span>Total Clients</span>
      <span class="stat-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-4-6.3"/></svg></span>
    </div>
    <div class="stat-value">1,428</div>
    <div class="stat-note"><span class="dot dot-blue"></span>Active clients</div>
  </div>

  <div class="stat-card">
    <div class="stat-top">
      <span>Total Lawyers</span>
      <span class="stat-icon"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V10M19 21V10M9 21V10M15 21V10M2 10l10-7 10 7Z"/></svg></span>
    </div>
    <div class="stat-value">284</div>
    <div class="stat-note"><span class="dot dot-blue"></span>Verified lawyers</div>
  </div>

  <div class="stat-card">
    <div class="stat-top">
      <span>Pending Requests</span>
      <span class="stat-icon icon-red"><svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg></span>
    </div>
    <div class="stat-value text-red">18</div>
    <div class="stat-note text-red"><span class="dot dot-red"></span>Requires Attention</div>
  </div>

  <div class="stat-card">
    <div class="stat-top">
      <span>Subscriptions</span>
      <span class="stat-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
    </div>
    <div class="stat-value">1,180</div>
    <div class="stat-note"><span class="dot dot-blue"></span>Currently subscribed</div>
  </div>
</section>

<div class="grid">

  <!-- PENDING ACTIONS -->
  <section class="card pending">
    <div class="card-head">
      <div>
        <h2>Pending Actions <span class="badge-critical">4 Critical</span></h2>
        <p>High priority verification, payment failures, and matter escalations</p>
      </div>
      <a href="#" class="btn-outline">View All Queue</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Action Item</th>
            <th>Entity / Reference</th>
            <th>Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Lawyer Verification Request</strong><small>Bar Credential Validation</small></td>
            <td>Adv. Sarah Jenkins<br />Bar ENY-99210</td>
            <td>12 Oct<br />2026</td>
            <td><span class="status status-review">Under Review</span></td>
            <td><a href="#" class="btn-view">View</a></td>
          </tr>
          <tr>
            <td><strong>Subscription Payment Issue</strong><small>Invoice #INV-8902 Overdue</small></td>
            <td>TechGlobal Corp<br />Pro 300B Tier</td>
            <td>14 Oct<br />2026</td>
            <td><span class="status status-danger">Requires Attention</span></td>
            <td><a href="#" class="btn-view">View</a></td>
          </tr>
          <tr>
            <td><strong>Account Request (Lawyer Onboarding)</strong><small>Practice License Verification</small></td>
            <td>Adv. Marcus Vance<br />Bar RCA-41912</td>
            <td>19 Oct<br />2026</td>
            <td><span class="status status-pending">Pending</span></td>
            <td><a href="#" class="btn-view">View</a></td>
          </tr>
          <tr>
            <td><strong>Cases Requiring Admin Attention</strong><small>Jurisdiction Dispute Filing</small></td>
            <td>Commercial Breach Arbitration<br />#CM-05108</td>
            <td>18 Oct<br />2026</td>
            <td><span class="status status-danger">Requires Attention</span></td>
            <td><a href="#" class="btn-view">View</a></td>
          </tr>
          <tr>
            <td><strong>Lawyer Verification Request</strong><small>State Bar Clearance</small></td>
            <td>Adv. Elena Rostova<br />Bar RTX-10824</td>
            <td>18 Oct<br />2026</td>
            <td><span class="status status-review">Under Review</span></td>
            <td><a href="#" class="btn-view">View</a></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- RIGHT COLUMN -->
  <div class="side-col">

    <section class="card">
      <h2>Account Management</h2>

      <div class="acc-row">
        <div class="acc-top">
          <span class="acc-title">
            <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/></svg>
            Client Accounts
          </span>
          <span class="acc-total">1,428</span>
        </div>
        <div class="acc-meta">
          <span>Active: <b class="text-green">1,380</b></span>
          <span>Inactive: <b>36</b></span>
          <span>Suspended: <b class="text-red">12</b></span>
        </div>
      </div>

      <div class="acc-row">
        <div class="acc-top">
          <span class="acc-title">
            <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V10M19 21V10M9 21V10M15 21V10M2 10l10-7 10 7Z"/></svg>
            Lawyer Accounts
          </span>
          <span class="acc-total">284</span>
        </div>
        <div class="acc-meta">
          <span>Verified: <b class="text-green">262</b></span>
          <span>Pending: <b class="text-orange">18</b></span>
          <span>Suspended: <b class="text-red">4</b></span>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <div>
          <h2>Case Overview</h2>
          <p>Platform-wide matter distribution</p>
        </div>
        <svg class="head-icon" viewBox="0 0 24 24"><path d="M3 7h18v13H3zM8 7V4h8v3"/></svg>
      </div>

      <div class="case-grid">
        <div class="case-box">
          <span>New Cases</span>
          <strong>94</strong>
          <a href="#">View Cases →</a>
        </div>
        <div class="case-box">
          <span>Lawyer Assigned</span>
          <strong>418</strong>
          <a href="#">View Cases →</a>
        </div>
        <div class="case-box">
          <span>In Progress</span>
          <strong>330</strong>
          <a href="#">View Cases →</a>
        </div>
        <div class="case-box">
          <span>Closed</span>
          <strong>650</strong>
          <a href="#">View Cases →</a>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Recent Platform Activity</h2>
        <svg class="head-icon" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg>
      </div>

      <ul class="activity">
        <li class="c-blue">
          <strong>New client registered</strong>
          <p>Innes Kuzone (TechGlobal) created enterprise profile</p>
          <time>5m ago</time>
        </li>
        <li class="c-green">
          <strong>Lawyer verification completed</strong>
          <p>Adv. Sarah Williams (Bar ANY-962) approved for dockets</p>
          <time>22m ago</time>
        </li>
        <li class="c-purple">
          <strong>New case created</strong>
          <p>David Chen submitted Employment Contract #CH-7717-4</p>
          <time>45m ago</time>
        </li>
        <li class="c-orange">
          <strong>Lawyer assigned to case</strong>
          <p>Adv. John Smith assigned to matter #CH-1024</p>
          <time>1h ago</time>
        </li>
        <li class="c-blue">
          <strong>Subscription purchased</strong>
          <p>Apex Law Group upgraded to Pro 500B</p>
          <time>2h ago</time>
        </li>
        <li class="c-dark">
          <strong>Subscription upgraded</strong>
          <p>Marcus &amp; Partners upgraded to Enterprise plan</p>
          <time>3h ago</time>
        </li>
        <li class="c-green">
          <strong>Payment completed</strong>
          <p>Retainer &amp; Platform invoice #INV-4402 settled securely</p>
          <time>5h ago</time>
        </li>
      </ul>
    </section>

  </div>
</div>
@endsection
